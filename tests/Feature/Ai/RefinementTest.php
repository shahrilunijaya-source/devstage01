<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Enums\ObjectType;
use App\Models\Acl\Role;
use App\Models\Acl\ScopeBinding;
use App\Models\AiSuggestion;
use App\Models\Graph\EngObject;
use App\Models\Graph\TraceRelationship;
use App\Models\Portfolio\Module;
use App\Models\Portfolio\Tenant;
use App\Models\Project;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Ai\Exceptions\RefinementException;
use App\Services\Ai\RefinementService;
use App\Services\Graph\ObjectGraphService;
use App\Services\Rag\RagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * AI Refinement Engine (spec §10): stored proposals, explicit human decisions,
 * honest refusal on invalid AI output, token usage logged. AI never overwrites.
 */
class RefinementTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        RagService::flush();
        parent::tearDown();
    }

    /** @return array{0:Project,1:User,2:EngObject} */
    private function makeRequirement(): array
    {
        $tenant = Tenant::default();
        $project = Project::create(['tenant_id' => $tenant->id, 'name' => 'P', 'code' => 'P', 'status' => 'active']);
        Module::create(['project_id' => $project->id, 'name' => 'M', 'code' => 'M']);

        $pm = User::factory()->create(['role' => 'regular']);
        ScopeBinding::create([
            'user_id' => $pm->id, 'role_id' => Role::where('key', 'project_pm')->whereNull('tenant_id')->value('id'),
            'tenant_id' => $tenant->id, 'scope_type' => 'project', 'scope_id' => $project->id,
        ]);

        $object = app(ObjectGraphService::class)->create(
            ObjectType::BUSINESS_REQUIREMENT, $tenant->id, $project->id,
            'System should be fast', ['body' => 'The payroll should process quickly.'],
        );

        return [$project, $pm, $object];
    }

    private function enableAi(): void
    {
        SystemSetting::set('rag_enabled', '1');
        SystemSetting::set('anthropic_api_key', 'sk-test');
        SystemSetting::set('voyage_api_key', 'pa-test');
        RagService::flush();
    }

    private function fakeAnthropicJson(array $payload): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => json_encode($payload)]],
            'usage' => ['input_tokens' => 200, 'output_tokens' => 90],
        ])]);
    }

    public function test_improve_produces_a_proposal_without_touching_the_object(): void
    {
        [, $pm, $object] = $this->makeRequirement();
        $this->enableAi();
        $this->fakeAnthropicJson([
            'proposed_title' => 'Payroll batch completes within 30 minutes',
            'proposed_body' => 'The monthly payroll batch for 5,000 employees completes within 30 minutes.',
            'rationale' => 'Quantifies "fast" into a testable bound.',
            'confidence' => 'high',
        ]);

        $this->actingAs($pm)->post(route('objects.refine', $object), ['action' => 'improve'])
            ->assertSessionHas('status');

        $suggestion = AiSuggestion::firstOrFail();
        $this->assertSame('proposed', $suggestion->status);
        $this->assertSame('2026-07-04.1', $suggestion->prompt_version);

        // Nothing applied yet — the object is untouched (version still 1).
        $this->assertSame('System should be fast', $object->fresh()->title);
        $this->assertSame(1, (int) $object->fresh()->current_version);

        // Token spend logged.
        $this->assertSame(1, DB::table('ai_usage_log')->where('feature', 'refine.improve')->count());
    }

    public function test_accepting_improve_applies_a_new_immutable_version(): void
    {
        [, $pm, $object] = $this->makeRequirement();
        $this->enableAi();
        $this->fakeAnthropicJson([
            'proposed_title' => 'Payroll batch completes within 30 minutes',
            'proposed_body' => 'Testable body.',
            'rationale' => 'r', 'confidence' => 'high',
        ]);
        $suggestion = app(RefinementService::class)->suggest($object, 'improve', $pm);

        $this->actingAs($pm)->post(route('suggestions.decide', $suggestion), ['decision' => 'accept'])
            ->assertSessionHas('status');

        $fresh = $object->fresh();
        $this->assertSame('Payroll batch completes within 30 minutes', $fresh->title);
        $this->assertSame(2, (int) $fresh->current_version);
        $this->assertSame('accepted', $suggestion->fresh()->status);
    }

    public function test_accept_with_edits_applies_the_users_text(): void
    {
        [, $pm, $object] = $this->makeRequirement();
        $this->enableAi();
        $this->fakeAnthropicJson(['proposed_title' => 'AI title', 'proposed_body' => 'AI body', 'rationale' => 'r']);
        $suggestion = app(RefinementService::class)->suggest($object, 'improve', $pm);

        $this->actingAs($pm)->post(route('suggestions.decide', $suggestion), [
            'decision' => 'accept_edit', 'title' => 'Human-tuned title', 'body' => 'Human body.',
        ]);

        $this->assertSame('Human-tuned title', $object->fresh()->title);
        $this->assertSame('accepted_edited', $suggestion->fresh()->status);
    }

    public function test_rejecting_records_the_reason_and_never_touches_content(): void
    {
        [, $pm, $object] = $this->makeRequirement();
        $this->enableAi();
        $this->fakeAnthropicJson(['proposed_title' => 'X', 'proposed_body' => 'Y', 'rationale' => 'r']);
        $suggestion = app(RefinementService::class)->suggest($object, 'improve', $pm);

        $this->actingAs($pm)->post(route('suggestions.decide', $suggestion), [
            'decision' => 'reject', 'note' => 'Changes the meaning.',
        ]);

        $this->assertSame('System should be fast', $object->fresh()->title);
        $this->assertSame(1, (int) $object->fresh()->current_version);
        $this->assertSame('Changes the meaning.', $suggestion->fresh()->decision_note);
    }

    public function test_accepting_generated_acceptance_criteria_mints_linked_ac_objects(): void
    {
        [, $pm, $object] = $this->makeRequirement();
        $this->enableAi();
        $this->fakeAnthropicJson([
            'criteria' => [
                ['title' => 'Batch completes in 30 min', 'body' => 'Given 5,000 employees...'],
                ['title' => 'Failures are reported', 'body' => 'Given a gateway error...'],
            ],
            'rationale' => 'r',
        ]);
        $suggestion = app(RefinementService::class)->suggest($object, 'generate_ac', $pm);

        $this->actingAs($pm)->post(route('suggestions.decide', $suggestion), ['decision' => 'accept']);

        $acs = EngObject::where('type', ObjectType::ACCEPTANCE_CRITERION->value)->get();
        $this->assertCount(2, $acs);
        $this->assertStringStartsWith('AC-', $acs->first()->ref);
        $this->assertTrue(
            TraceRelationship::where('from_object_id', $acs->first()->id)
                ->where('to_object_id', $object->id)
                ->where('relation_type', 'refines')->exists(),
        );
    }

    public function test_invalid_ai_json_retries_once_then_refuses_honestly(): void
    {
        [, $pm, $object] = $this->makeRequirement();
        $this->enableAi();
        Http::fake(['api.anthropic.com/*' => Http::sequence()
            ->push(['content' => [['type' => 'text', 'text' => 'not json at all']], 'usage' => ['input_tokens' => 10, 'output_tokens' => 5]])
            ->push(['content' => [['type' => 'text', 'text' => 'still {broken']], 'usage' => ['input_tokens' => 10, 'output_tokens' => 5]]),
        ]);

        $this->expectException(RefinementException::class);

        try {
            app(RefinementService::class)->suggest($object, 'improve', $pm);
        } finally {
            $this->assertSame(0, AiSuggestion::count());          // no fabricated suggestion
            $this->assertSame(2, DB::table('ai_usage_log')->count()); // both attempts logged
        }
    }

    public function test_check_objective_without_an_objective_degrades_deterministically(): void
    {
        [, $pm, $object] = $this->makeRequirement();
        // AI disabled, no HTTP fake — must not call out at all.

        $this->actingAs($pm)->post(route('objects.refine', $object), ['action' => 'check_objective'])
            ->assertSessionHas('status');

        $suggestion = AiSuggestion::firstOrFail();
        $this->assertSame('insufficient_information', $suggestion->payload['classification']);
        $this->assertNull($suggestion->model);
    }

    public function test_refinement_requires_edit_rights(): void
    {
        [$project, , $object] = $this->makeRequirement();
        $member = User::factory()->create(['role' => 'regular']);
        ScopeBinding::create([
            'user_id' => $member->id, 'role_id' => Role::where('key', 'project_member')->whereNull('tenant_id')->value('id'),
            'tenant_id' => $project->tenant_id, 'scope_type' => 'project', 'scope_id' => $project->id,
        ]);

        $this->actingAs($member)->post(route('objects.refine', $object), ['action' => 'improve'])
            ->assertForbidden();
    }
}

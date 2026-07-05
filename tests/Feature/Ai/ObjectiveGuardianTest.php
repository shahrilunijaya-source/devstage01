<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Enums\ObjectStatus;
use App\Enums\ObjectType;
use App\Enums\RelationType;
use App\Models\Acl\Role;
use App\Models\Acl\ScopeBinding;
use App\Models\Graph\EngObject;
use App\Models\Portfolio\Module;
use App\Models\Portfolio\Tenant;
use App\Models\Project;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Change\ChangeManagementService;
use App\Services\Change\Exceptions\ChangeManagementException;
use App\Services\Graph\ObjectGraphService;
use App\Services\Graph\TraceService;
use App\Services\Portfolio\ObjectiveService;
use App\Services\Rag\RagService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Objective Guardian (spec §7) + change-impact disposition (spec §13).
 */
class ObjectiveGuardianTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        RagService::flush();
        parent::tearDown();
    }

    /** @return array{0:Project,1:User,2:User,3:EngObject} project, raiser, approver, target */
    private function makeProjectWithTarget(bool $withObjective = true): array
    {
        $tenant = Tenant::default();
        $project = Project::create(['tenant_id' => $tenant->id, 'name' => 'P', 'code' => 'P', 'status' => 'active']);
        Module::create(['project_id' => $project->id, 'name' => 'M', 'code' => 'M']);

        $roleId = Role::where('key', 'project_pm')->whereNull('tenant_id')->value('id');
        $raiser = User::factory()->create(['role' => 'regular']);
        $approver = User::factory()->create(['role' => 'regular']);
        foreach ([$raiser, $approver] as $u) {
            ScopeBinding::create([
                'user_id' => $u->id, 'role_id' => $roleId,
                'tenant_id' => $tenant->id, 'scope_type' => 'project', 'scope_id' => $project->id,
            ]);
        }

        if ($withObjective) {
            $objectives = app(ObjectiveService::class);
            $objectives->capture($project, ['title' => 'Pay everyone on time', 'business_problem' => 'p'], $raiser);
            $objectives->approve($project, $raiser);
        }

        $target = app(ObjectGraphService::class)->create(
            ObjectType::BUSINESS_REQUIREMENT, $tenant->id, $project->id,
            'Payroll runs by the 25th', ['body' => 'Monthly.'],
        );

        return [$project, $raiser, $approver, $target];
    }

    private function enableAi(): void
    {
        SystemSetting::set('rag_enabled', '1');
        SystemSetting::set('anthropic_api_key', 'sk-test');
        SystemSetting::set('voyage_api_key', 'pa-test');
        RagService::flush();
    }

    private function fakeGuardian(string $classification): void
    {
        Http::fake(['api.anthropic.com/*' => Http::response([
            'content' => [['type' => 'text', 'text' => json_encode([
                'classification' => $classification,
                'rationale' => 'Assessment rationale.',
                'benefits' => ['b1'], 'risks' => ['r1'], 'questions' => [],
                'confidence' => 'medium',
            ])]],
            'usage' => ['input_tokens' => 150, 'output_tokens' => 60],
        ])]);
    }

    public function test_raising_a_cr_stores_a_guardian_assessment(): void
    {
        [, $raiser, , $target] = $this->makeProjectWithTarget();
        $this->enableAi();
        $this->fakeGuardian('aligned');

        $cr = app(ChangeManagementService::class)->open($target, $raiser, 'Tighten the deadline', 'Move to the 24th.');

        $this->assertSame('aligned', $cr->guardian_assessment['classification']);
        $this->assertStringStartsWith('OBJ-', $cr->guardian_assessment['objective_ref']);
    }

    public function test_guardian_degrades_honestly_without_objective_or_ai(): void
    {
        [, $raiser, , $target] = $this->makeProjectWithTarget(withObjective: false);
        // AI disabled, no fake — must not call out.

        $cr = app(ChangeManagementService::class)->open($target, $raiser, 'Change', null);

        $this->assertSame('insufficient_information', $cr->guardian_assessment['classification']);
    }

    public function test_direct_conflict_requires_a_recorded_override_reason_to_approve(): void
    {
        [, $raiser, $approver, $target] = $this->makeProjectWithTarget();
        $this->enableAi();
        $this->fakeGuardian('direct_conflict');
        $cr = app(ChangeManagementService::class)->open($target, $raiser, 'Drop payroll deadline entirely', null);

        // Without a reason: refused.
        try {
            app(ChangeManagementService::class)->approve($cr, $approver);
            $this->fail('Expected ChangeManagementException.');
        } catch (ChangeManagementException $e) {
            $this->assertStringContainsString('override reason', $e->getMessage());
        }
        $this->assertSame('draft', $cr->fresh()->status);

        // With a recorded reason: approved, reason stored.
        $this->actingAs($approver)->post(route('changes.approve', $cr), [
            'override_reason' => 'Client contract renegotiated — objective update follows.',
        ])->assertSessionHas('status');

        $fresh = $cr->fresh();
        $this->assertSame('approved', $fresh->status);
        $this->assertStringContainsString('renegotiated', $fresh->override_reason);
    }

    public function test_apply_marks_downstream_review_required_and_disposition_clears_it(): void
    {
        [, $raiser, $approver, $target] = $this->makeProjectWithTarget();
        $this->enableAi();
        $this->fakeGuardian('aligned');

        // A downstream design object satisfied by the target requirement.
        $design = app(ObjectGraphService::class)->create(
            ObjectType::DESIGN_COMPONENT, $target->tenant_id, $target->project_id, 'Payroll scheduler',
        );
        app(TraceService::class)->link($target, $design, RelationType::DERIVED_FROM);

        $engine = app(ChangeManagementService::class);
        $cr = $engine->open($target, $raiser, 'Tighten', null, ['title' => 'Payroll runs by the 24th']);
        $engine->approve($cr, $approver);
        $engine->apply($cr, $approver);

        $flagged = $design->fresh();
        $this->assertSame('review_required', $flagged->getAttribute('attributes')['impact']);
        $this->assertSame($cr->ref, $flagged->getAttribute('attributes')['impact_from']);
        $this->assertSame(ObjectStatus::NEEDS_CONFIRMATION, $flagged->status);

        // Disposition: no impact → confirmed, flag resolved.
        $this->actingAs($approver)->post(route('changes.disposition', [$cr, $design]), [
            'impact' => 'no_impact',
        ])->assertSessionHas('status');

        $done = $design->fresh();
        $this->assertSame('no_impact', $done->getAttribute('attributes')['impact']);
        $this->assertSame(ObjectStatus::CONFIRMED_BY_EVIDENCE, $done->status);
    }

    public function test_disposition_invalidated_retires_the_object(): void
    {
        [, $raiser, $approver, $target] = $this->makeProjectWithTarget();
        $this->enableAi();
        $this->fakeGuardian('aligned');

        $design = app(ObjectGraphService::class)->create(
            ObjectType::DESIGN_COMPONENT, $target->tenant_id, $target->project_id, 'Legacy exporter',
        );
        app(TraceService::class)->link($target, $design, RelationType::DERIVED_FROM);

        $engine = app(ChangeManagementService::class);
        $cr = $engine->open($target, $raiser, 'Remove exports', null, ['body' => 'No exports.']);
        $engine->approve($cr, $approver);
        $engine->apply($cr, $approver);

        app(ChangeManagementService::class)->disposition($cr, $design->fresh(), 'invalidated', $approver);

        $this->assertSame(ObjectStatus::NOT_APPLICABLE, $design->fresh()->status);
    }

    public function test_disposition_rejects_objects_not_flagged_by_this_cr(): void
    {
        [, $raiser, $approver, $target] = $this->makeProjectWithTarget();
        $this->enableAi();
        $this->fakeGuardian('aligned');

        $unrelated = app(ObjectGraphService::class)->create(
            ObjectType::DESIGN_COMPONENT, $target->tenant_id, $target->project_id, 'Unrelated',
        );

        $engine = app(ChangeManagementService::class);
        $cr = $engine->open($target, $raiser, 'T', null, ['title' => 'x']);
        $engine->approve($cr, $approver);
        $engine->apply($cr, $approver);

        $this->expectException(ChangeManagementException::class);
        $engine->disposition($cr, $unrelated, 'no_impact', $approver);
    }
}

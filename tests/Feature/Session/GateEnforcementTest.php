<?php

declare(strict_types=1);

namespace Tests\Feature\Session;

use App\Enums\ObjectType;
use App\Models\Acl\Role;
use App\Models\Acl\ScopeBinding;
use App\Models\Graph\EngObject;
use App\Models\Portfolio\Module;
use App\Models\Portfolio\Session;
use App\Models\Portfolio\Stage;
use App\Models\Portfolio\Tenant;
use App\Models\Project;
use App\Models\User;
use App\Services\Graph\ObjectGraphService;
use App\Services\Portfolio\ObjectiveService;
use App\Services\Session\Exceptions\SessionEngineException;
use App\Services\Session\SessionEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The baseline endpoint must enforce the SAME gate conditions the gate page
 * shows (audit F-05), and session engine entry points must respect the phase
 * machine (audit F-06). Overrides are allowed only for overridable checks,
 * with a recorded reason.
 */
class GateEnforcementTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:Session,1:Stage,2:User,3:Project} */
    private function makeSession(string $stageKey = 'BRS'): array
    {
        $tenant = Tenant::default();
        $project = Project::create(['tenant_id' => $tenant->id, 'name' => 'P', 'code' => 'P', 'status' => 'active']);
        $module = Module::create(['project_id' => $project->id, 'name' => 'M', 'code' => 'M']);
        $stage = $module->stages()->where('stage', $stageKey)->firstOrFail();
        $session = Session::create([
            'stage_id' => $stage->id, 'module_id' => $module->id, 'project_id' => $project->id,
            'title' => 'S', 'status' => 'draft', 'phase' => 'pre_analysis',
        ]);
        app(ObjectGraphService::class)->create(ObjectType::EVIDENCE, $tenant->id, $project->id, 'Ev', [
            'module_id' => $module->id, 'stage_id' => $stage->id, 'session_id' => $session->id, 'body' => 'b',
        ]);

        $pm = User::factory()->create(['role' => 'regular']);
        ScopeBinding::create([
            'user_id' => $pm->id, 'role_id' => Role::where('key', 'project_pm')->whereNull('tenant_id')->value('id'),
            'tenant_id' => $tenant->id, 'scope_type' => 'project', 'scope_id' => $project->id,
        ]);

        // BRS gate requires an approved objective (spec §6) — approved here so
        // each test isolates the single check it is exercising.
        $objectives = app(ObjectiveService::class);
        $objectives->capture($project, ['title' => 'Objective', 'business_problem' => 'p'], $pm);
        $objectives->approve($project, $pm);

        return [$session, $stage, $pm, $project];
    }

    private function approveSession(Session $session, User $user): void
    {
        $engine = app(SessionEngineService::class);
        $engine->preAnalyze($session);
        $engine->passFirewall($session->fresh(), $user);
        $engine->startSession($session->fresh());
        foreach (EngObject::where('session_id', $session->id)->get() as $obj) {
            $engine->capture($obj, 'decide', $user);
        }
        $engine->consolidate($session->fresh());
        $engine->approveSession($session->fresh(), $user);
    }

    public function test_direct_post_cannot_baseline_with_unresolved_items(): void
    {
        [$session, $stage, $pm] = $this->makeSession();
        app(SessionEngineService::class)->preAnalyze($session); // leaves NEEDS_CONFIRMATION drafts

        // Approve a second, clean session so the approved-session blocker passes
        // and ONLY the unresolved-items check is failing.
        $clean = Session::create([
            'stage_id' => $stage->id, 'module_id' => $stage->module_id, 'project_id' => $stage->project_id,
            'title' => 'clean', 'status' => 'approved', 'phase' => 'approved',
        ]);

        $this->actingAs($pm)->post(route('stages.baseline', $stage))
            ->assertRedirect(route('stages.gate', $stage));

        $this->assertSame(0, $stage->baselines()->count());
        $this->assertNotSame('baselined', $stage->fresh()->status);
    }

    public function test_override_with_reason_baselines_and_records_the_exception(): void
    {
        [$session, $stage, $pm] = $this->makeSession();
        app(SessionEngineService::class)->preAnalyze($session);
        Session::create([
            'stage_id' => $stage->id, 'module_id' => $stage->module_id, 'project_id' => $stage->project_id,
            'title' => 'clean', 'status' => 'approved', 'phase' => 'approved',
        ]);

        $this->actingAs($pm)->post(route('stages.baseline', $stage), [
            'override' => '1',
            'override_reason' => 'Client demo deadline — unresolved items tracked as actions.',
        ])->assertRedirect(route('portfolio.show', $stage->project));

        $baseline = $stage->baselines()->first();
        $this->assertNotNull($baseline);
        $exceptions = $baseline->snapshot_meta['exceptions'];
        $this->assertSame($pm->id, $exceptions['by']);
        $this->assertStringContainsString('Client demo deadline', $exceptions['reason']);
        $this->assertNotEmpty($exceptions['checks']);
    }

    public function test_missing_approved_session_is_a_hard_blocker_even_with_override(): void
    {
        [, $stage, $pm] = $this->makeSession();

        $this->actingAs($pm)->post(route('stages.baseline', $stage), [
            'override' => '1', 'override_reason' => 'try anyway',
        ])->assertRedirect(route('stages.gate', $stage));

        $this->assertSame(0, $stage->baselines()->count());
    }

    public function test_already_baselined_stage_cannot_be_rebaselined_directly(): void
    {
        [$session, $stage, $pm] = $this->makeSession();
        $this->approveSession($session, $pm);

        $this->actingAs($pm)->post(route('stages.baseline', $stage))->assertRedirect();
        $this->assertSame(1, $stage->baselines()->count());

        $this->actingAs($pm)->post(route('stages.baseline', $stage), [
            'override' => '1', 'override_reason' => 'again',
        ])->assertRedirect(route('stages.gate', $stage));

        $this->assertSame(1, $stage->fresh()->baselines()->count());
    }

    public function test_stage_order_gate_blocks_urs_before_brs_unless_overridden(): void
    {
        [$session, $stage, $pm] = $this->makeSession('URS');
        $this->approveSession($session, $pm);

        // BRS not baselined → overridable failure; plain POST refused.
        $this->actingAs($pm)->post(route('stages.baseline', $stage))
            ->assertRedirect(route('stages.gate', $stage));
        $this->assertSame(0, $stage->baselines()->count());

        // With recorded exception it proceeds.
        $this->actingAs($pm)->post(route('stages.baseline', $stage), [
            'override' => '1', 'override_reason' => 'BRS intentionally deferred',
        ])->assertRedirect(route('portfolio.show', $stage->project));
        $this->assertSame(1, $stage->fresh()->baselines()->count());
    }

    public function test_pre_analysis_cannot_replay_an_approved_session(): void
    {
        [$session, , $pm] = $this->makeSession();
        $this->approveSession($session, $pm);

        $this->expectException(SessionEngineException::class);
        app(SessionEngineService::class)->preAnalyze($session->fresh());
    }

    public function test_capture_is_refused_outside_a_live_session(): void
    {
        [$session, , $pm] = $this->makeSession();
        app(SessionEngineService::class)->preAnalyze($session); // phase now firewall_review

        $draft = EngObject::where('session_id', $session->id)
            ->where('type', '!=', ObjectType::EVIDENCE->value)->firstOrFail();

        $this->expectException(SessionEngineException::class);
        app(SessionEngineService::class)->capture($draft, 'confirm', $pm);
    }

    public function test_firewall_send_back_returns_session_to_pre_analysis_with_reason(): void
    {
        [$session, , $pm] = $this->makeSession();
        $engine = app(SessionEngineService::class);
        $engine->preAnalyze($session);

        $this->actingAs($pm)->post(route('sessions.reject-firewall', $session->fresh()), [
            'reason' => 'Drafts miss the payroll cut-off rules.',
        ])->assertRedirect(route('sessions.show', $session));

        $fresh = $session->fresh();
        $this->assertSame('pre_analysis', $fresh->phase);
        $this->assertSame('Drafts miss the payroll cut-off rules.', $fresh->firewall_rejected_reason);

        // Re-running pre-analysis then passing the firewall clears the send-back.
        $engine->preAnalyze($fresh);
        $engine->passFirewall($session->fresh(), $pm);
        $this->assertNull($session->fresh()->firewall_rejected_reason);
    }
}

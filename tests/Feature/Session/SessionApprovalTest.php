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
use App\Models\Portfolio\StageBaseline;
use App\Models\Portfolio\Tenant;
use App\Models\Project;
use App\Models\User;
use App\Services\Graph\ObjectGraphService;
use App\Services\Portfolio\ObjectiveService;
use App\Services\Session\Exceptions\SessionEngineException;
use App\Services\Session\SessionEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionApprovalTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:Session,1:Stage,2:Project,3:User} */
    private function readySession(): array
    {
        $tenant = Tenant::default();
        $project = Project::create(['tenant_id' => $tenant->id, 'name' => 'P', 'code' => 'P', 'status' => 'active']);
        $module = Module::create(['project_id' => $project->id, 'name' => 'M', 'code' => 'M']);
        $stage = $module->stages()->where('stage', 'BRS')->firstOrFail();
        $session = Session::create([
            'stage_id' => $stage->id, 'module_id' => $module->id, 'project_id' => $project->id,
            'title' => 'S', 'status' => 'draft', 'phase' => 'pre_analysis',
        ]);
        app(ObjectGraphService::class)->create(ObjectType::EVIDENCE, $tenant->id, $project->id, 'Evidence', [
            'module_id' => $module->id, 'stage_id' => $stage->id, 'session_id' => $session->id, 'body' => 'b',
        ]);

        $pm = User::factory()->create(['role' => 'regular']);
        ScopeBinding::create([
            'user_id' => $pm->id, 'role_id' => Role::where('key', 'project_pm')->whereNull('tenant_id')->value('id'),
            'tenant_id' => $tenant->id, 'scope_type' => 'project', 'scope_id' => $project->id,
        ]);

        // BRS gate requires an approved objective (spec §6).
        $objectives = app(ObjectiveService::class);
        $objectives->capture($project, ['title' => 'Objective', 'business_problem' => 'p'], $pm);
        $objectives->approve($project, $pm);

        return [$session, $stage, $project, $pm];
    }

    private function engine(): SessionEngineService
    {
        return app(SessionEngineService::class);
    }

    /** Drive a session all the way to consolidated, all items resolved. */
    private function driveToPostSession(Session $session, User $user): void
    {
        $engine = $this->engine();
        $engine->preAnalyze($session);
        $engine->passFirewall($session->fresh(), $user);
        $engine->startSession($session->fresh());

        foreach (EngObject::where('session_id', $session->id)->get() as $obj) {
            // Evidence has no impact set; resolve everything via 'decide' (always allowed).
            $engine->capture($obj, 'decide', $user);
        }

        $engine->consolidate($session->fresh());
    }

    public function test_consolidate_requires_in_session_phase(): void
    {
        [$session] = $this->readySession();

        $this->expectException(SessionEngineException::class);
        $this->engine()->consolidate($session); // still pre_analysis
    }

    public function test_approval_blocked_while_items_unresolved(): void
    {
        [$session, , , $pm] = $this->readySession();
        $engine = $this->engine();
        $engine->preAnalyze($session);
        $engine->passFirewall($session->fresh(), $pm);
        $engine->startSession($session->fresh());
        $engine->consolidate($session->fresh()); // items still NEEDS_CONFIRMATION

        $this->expectException(SessionEngineException::class);
        $engine->approveSession($session->fresh(), $pm);
    }

    public function test_full_loop_approves_and_baselines(): void
    {
        [$session, $stage, $project, $pm] = $this->readySession();
        $this->driveToPostSession($session, $pm);

        $this->engine()->approveSession($session->fresh(), $pm);
        $this->assertSame('approved', $session->fresh()->status);

        // Baseline the stage via the web route as the bound PM.
        $this->actingAs($pm)->post(route('stages.baseline', $stage))
            ->assertRedirect(route('portfolio.show', $project));

        $baseline = StageBaseline::where('stage_id', $stage->id)->firstOrFail();
        $this->assertSame('approved', $baseline->status);
        // Evidence + Finding + Requirement = 3 frozen objects.
        $this->assertSame(3, $baseline->baselineObjects()->count());
        $this->assertSame('baselined', $stage->fresh()->status);
    }

    public function test_baseline_document_renders_and_pdf_downloads(): void
    {
        [$session, $stage, $project, $pm] = $this->readySession();
        $this->driveToPostSession($session, $pm);
        $this->engine()->approveSession($session->fresh(), $pm);
        $this->actingAs($pm)->post(route('stages.baseline', $stage));
        $baseline = StageBaseline::where('stage_id', $stage->id)->firstOrFail();

        $this->actingAs($pm)->get(route('baselines.show', $baseline))
            ->assertOk()->assertSee($baseline->version_label);

        $pdf = $this->actingAs($pm)->get(route('baselines.pdf', $baseline));
        $pdf->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('content-type'));
    }

    public function test_baseline_requires_baseline_right(): void
    {
        [$session, $stage, $project, $pm] = $this->readySession();
        $this->driveToPostSession($session, $pm);
        $this->engine()->approveSession($session->fresh(), $pm);

        $member = User::factory()->create(['role' => 'regular']);
        ScopeBinding::create([
            'user_id' => $member->id, 'role_id' => Role::where('key', 'project_member')->whereNull('tenant_id')->value('id'),
            'tenant_id' => $project->tenant_id, 'scope_type' => 'project', 'scope_id' => $project->id,
        ]);

        $this->actingAs($member)->post(route('stages.baseline', $stage))->assertForbidden();
    }

    public function test_baseline_blocked_without_approved_session(): void
    {
        [, $stage, $project, $pm] = $this->readySession();

        // Blocked baselines land on the gate page, where the failing checks show.
        $this->actingAs($pm)->from(route('portfolio.show', $project))
            ->post(route('stages.baseline', $stage))
            ->assertRedirect(route('stages.gate', $stage))
            ->assertSessionHas('error');
    }
}

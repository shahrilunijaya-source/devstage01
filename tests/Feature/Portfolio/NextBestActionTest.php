<?php

declare(strict_types=1);

namespace Tests\Feature\Portfolio;

use App\Enums\ObjectType;
use App\Models\Acl\Role;
use App\Models\Acl\ScopeBinding;
use App\Models\Graph\EngObject;
use App\Models\Portfolio\Module;
use App\Models\Portfolio\Session;
use App\Models\Portfolio\Tenant;
use App\Models\Project;
use App\Models\User;
use App\Services\Graph\ObjectGraphService;
use App\Services\Portfolio\NextBestActionService;
use App\Services\Portfolio\ObjectiveService;
use App\Services\Session\SessionEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Next Best Action (spec §14.3): derived guidance, ordered by what blocks
 * progress hardest.
 */
class NextBestActionTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:Project,1:User} */
    private function makeProject(): array
    {
        $tenant = Tenant::default();
        $project = Project::create(['tenant_id' => $tenant->id, 'name' => 'P', 'code' => 'P', 'status' => 'active']);

        $pm = User::factory()->create(['role' => 'regular']);
        ScopeBinding::create([
            'user_id' => $pm->id, 'role_id' => Role::where('key', 'project_pm')->whereNull('tenant_id')->value('id'),
            'tenant_id' => $tenant->id, 'scope_type' => 'project', 'scope_id' => $project->id,
        ]);

        return [$project, $pm];
    }

    public function test_missing_objective_is_the_first_action(): void
    {
        [$project, $pm] = $this->makeProject();

        $actions = app(NextBestActionService::class)->forProject($project, $pm);

        $this->assertSame('Capture the project objective', $actions[0]['label']);
    }

    public function test_unapproved_objective_then_missing_module_are_surfaced(): void
    {
        [$project, $pm] = $this->makeProject();
        app(ObjectiveService::class)->capture($project, ['title' => 'T', 'business_problem' => 'p'], $pm);

        $labels = array_column(app(NextBestActionService::class)->forProject($project, $pm), 'label');

        $this->assertSame('Approve the project objective', $labels[0]);
        $this->assertContains('Add the first module', $labels);
    }

    public function test_firewall_review_session_surfaces_a_review_action(): void
    {
        [$project, $pm] = $this->makeProject();
        $objectives = app(ObjectiveService::class);
        $objectives->capture($project, ['title' => 'T', 'business_problem' => 'p'], $pm);
        $objectives->approve($project, $pm);

        $module = Module::create(['project_id' => $project->id, 'name' => 'M', 'code' => 'M']);
        $stage = $module->stages()->where('stage', 'BRS')->firstOrFail();
        $session = Session::create([
            'stage_id' => $stage->id, 'module_id' => $module->id, 'project_id' => $project->id,
            'title' => 'Workshop 1', 'status' => 'draft', 'phase' => 'pre_analysis',
        ]);
        app(ObjectGraphService::class)->create(ObjectType::EVIDENCE, $project->tenant_id, $project->id, 'Ev', [
            'module_id' => $module->id, 'stage_id' => $stage->id, 'session_id' => $session->id, 'body' => 'b',
        ]);
        app(SessionEngineService::class)->preAnalyze($session);

        $labels = array_column(app(NextBestActionService::class)->forProject($project, $pm), 'label');

        $this->assertContains('Review AI drafts at the quality firewall ("Workshop 1")', $labels);
    }

    public function test_ready_stage_surfaces_a_baseline_action_and_panel_renders(): void
    {
        [$project, $pm] = $this->makeProject();
        $objectives = app(ObjectiveService::class);
        $objectives->capture($project, ['title' => 'T', 'business_problem' => 'p'], $pm);
        $objectives->approve($project, $pm);

        $module = Module::create(['project_id' => $project->id, 'name' => 'Payroll', 'code' => 'M']);
        $stage = $module->stages()->where('stage', 'BRS')->firstOrFail();
        $session = Session::create([
            'stage_id' => $stage->id, 'module_id' => $module->id, 'project_id' => $project->id,
            'title' => 'S', 'status' => 'draft', 'phase' => 'pre_analysis',
        ]);
        app(ObjectGraphService::class)->create(ObjectType::EVIDENCE, $project->tenant_id, $project->id, 'Ev', [
            'module_id' => $module->id, 'stage_id' => $stage->id, 'session_id' => $session->id, 'body' => 'b',
        ]);
        $engine = app(SessionEngineService::class);
        $engine->preAnalyze($session);
        $engine->passFirewall($session->fresh(), $pm);
        $engine->startSession($session->fresh());
        foreach (EngObject::where('session_id', $session->id)->get() as $obj) {
            $engine->capture($obj, 'decide', $pm);
        }
        $engine->consolidate($session->fresh());
        $engine->approveSession($session->fresh(), $pm);

        $labels = array_column(app(NextBestActionService::class)->forProject($project, $pm), 'label');
        $this->assertContains('Baseline BRS (Payroll)', $labels);

        $this->actingAs($pm)->get(route('portfolio.show', $project))
            ->assertOk()
            ->assertSee('Next best actions')
            ->assertSee('Baseline BRS');
    }

    public function test_stage_guidance_renders_on_the_gate_page(): void
    {
        [$project, $pm] = $this->makeProject();
        $module = Module::create(['project_id' => $project->id, 'name' => 'M', 'code' => 'M']);
        $stage = $module->stages()->where('stage', 'BRS')->firstOrFail();

        $this->actingAs($pm)->get(route('stages.gate', $stage))
            ->assertOk()
            ->assertSee('What is the BRS stage?')
            ->assertSee('Business Requirements Specification');
    }
}

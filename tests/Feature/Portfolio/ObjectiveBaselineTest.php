<?php

declare(strict_types=1);

namespace Tests\Feature\Portfolio;

use App\Enums\ObjectStatus;
use App\Enums\ObjectType;
use App\Enums\RelationType;
use App\Models\Acl\Role;
use App\Models\Acl\ScopeBinding;
use App\Models\Graph\EngObject;
use App\Models\Graph\TraceRelationship;
use App\Models\Portfolio\Module;
use App\Models\Portfolio\Session;
use App\Models\Portfolio\Tenant;
use App\Models\Project;
use App\Models\User;
use App\Services\Graph\ObjectGraphService;
use App\Services\Portfolio\ObjectiveService;
use App\Services\Session\SessionEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Project Objective Baseline (spec §6): the versioned, approval-gated anchor of
 * the requirement chain.
 */
class ObjectiveBaselineTest extends TestCase
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

    public function test_wizard_routes_project_creation_into_objective_capture(): void
    {
        $admin = User::factory()->create(['role' => 'admin', 'system_role' => 'admin']);
        $tenant = Tenant::default();

        $response = $this->actingAs($admin)->post(route('portfolio.projects.store'), [
            'tenant_id' => $tenant->id, 'name' => 'Wizard P', 'code' => 'WIZ', 'status' => 'active',
        ]);

        $project = Project::where('code', 'WIZ')->firstOrFail();
        $response->assertRedirect(route('objective.edit', ['project' => $project, 'wizard' => 1]));
    }

    public function test_capture_creates_a_versioned_objective_with_structured_attributes(): void
    {
        [$project, $pm] = $this->makeProject();

        $this->actingAs($pm)->post(route('objective.store', $project), [
            'title' => 'Ship payroll on time',
            'business_problem' => 'Manual runs breach the deadline.',
            'sponsor' => 'CFO',
            'success_measures' => '12 on-time runs',
        ])->assertRedirect(route('objective.show', $project));

        $objective = app(ObjectiveService::class)->objectiveFor($project);
        $this->assertNotNull($objective);
        $this->assertStringStartsWith('OBJ-', $objective->ref);
        $this->assertSame(ObjectStatus::NEEDS_CONFIRMATION, $objective->status);
        $this->assertSame('CFO', $objective->getAttribute('attributes')['sponsor']);
        $this->assertSame(1, (int) $objective->current_version);
    }

    public function test_revision_bumps_version_and_requires_re_approval(): void
    {
        [$project, $pm] = $this->makeProject();
        $service = app(ObjectiveService::class);
        $service->capture($project, ['title' => 'v1', 'business_problem' => 'p'], $pm);
        $service->approve($project, $pm);

        $this->assertTrue($service->isApproved($project));

        $service->capture($project, ['title' => 'v2 — scope widened', 'business_problem' => 'p'], $pm);

        $objective = $service->objectiveFor($project);
        $this->assertFalse($service->isApproved($project));
        $this->assertSame(ObjectStatus::NEEDS_CONFIRMATION, $objective->status);
        $this->assertGreaterThanOrEqual(3, (int) $objective->current_version); // create + approve + revise
    }

    public function test_approval_mints_a_linked_sign_off_record(): void
    {
        [$project, $pm] = $this->makeProject();
        $service = app(ObjectiveService::class);
        $objective = $service->capture($project, ['title' => 'T', 'business_problem' => 'p'], $pm);

        $this->actingAs($pm)->post(route('objective.approve', $project))
            ->assertRedirect(route('objective.show', $project));

        $this->assertTrue($service->isApproved($project));

        $approval = EngObject::where('project_id', $project->id)
            ->where('type', ObjectType::APPROVAL->value)->firstOrFail();
        $this->assertTrue(
            TraceRelationship::where('from_object_id', $approval->id)
                ->where('to_object_id', $objective->id)
                ->where('relation_type', RelationType::APPROVES->value)
                ->exists(),
        );
    }

    public function test_brs_gate_flags_a_missing_objective(): void
    {
        [$project, $pm] = $this->makeProject();
        $module = Module::create(['project_id' => $project->id, 'name' => 'M', 'code' => 'M']);
        $stage = $module->stages()->where('stage', 'BRS')->firstOrFail();

        $this->actingAs($pm)->get(route('stages.gate', $stage))
            ->assertOk()
            ->assertSee('Project objective approved')
            ->assertSee('No objective captured yet');
    }

    public function test_drafted_requirements_trace_back_to_the_objective(): void
    {
        [$project, $pm] = $this->makeProject();
        $service = app(ObjectiveService::class);
        $objective = $service->capture($project, ['title' => 'T', 'business_problem' => 'p'], $pm);

        $module = Module::create(['project_id' => $project->id, 'name' => 'M', 'code' => 'M']);
        $stage = $module->stages()->where('stage', 'BRS')->firstOrFail();
        $session = Session::create([
            'stage_id' => $stage->id, 'module_id' => $module->id, 'project_id' => $project->id,
            'title' => 'S', 'status' => 'draft', 'phase' => 'pre_analysis',
        ]);
        app(ObjectGraphService::class)->create(ObjectType::EVIDENCE, $project->tenant_id, $project->id, 'Ev', [
            'module_id' => $module->id, 'stage_id' => $stage->id, 'session_id' => $session->id, 'body' => 'b',
        ]);

        app(SessionEngineService::class)->preAnalyze($session);

        $requirement = EngObject::where('session_id', $session->id)
            ->where('type', ObjectType::BUSINESS_REQUIREMENT->value)->firstOrFail();

        $this->assertTrue(
            TraceRelationship::where('from_object_id', $objective->id)
                ->where('to_object_id', $requirement->id)
                ->where('relation_type', RelationType::DERIVED_FROM->value)
                ->exists(),
        );
    }

    public function test_objective_pages_respect_acl(): void
    {
        [$project] = $this->makeProject();
        $outsider = User::factory()->create(['role' => 'regular']);

        $this->actingAs($outsider)->get(route('objective.show', $project))->assertForbidden();
        $this->actingAs($outsider)->post(route('objective.store', $project), [
            'title' => 'x', 'business_problem' => 'y',
        ])->assertForbidden();
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Design;

use App\Enums\ObjectType;
use App\Models\Acl\Role;
use App\Models\Acl\ScopeBinding;
use App\Models\Graph\EngObject;
use App\Models\Portfolio\Tenant;
use App\Models\Project;
use App\Models\User;
use App\Services\Design\DesignService;
use App\Services\Graph\ObjectGraphService;
use App\Services\Verification\RtmService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DesignTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:Project,1:User} */
    private function boundProject(string $roleKey = 'project_pm'): array
    {
        $project = Project::create(['tenant_id' => Tenant::default()->id, 'name' => 'P', 'code' => 'P', 'status' => 'active']);
        $user = User::factory()->create(['role' => 'regular']);
        ScopeBinding::create([
            'user_id' => $user->id, 'role_id' => Role::where('key', $roleKey)->whereNull('tenant_id')->value('id'),
            'tenant_id' => $project->tenant_id, 'scope_type' => 'project', 'scope_id' => $project->id,
        ]);

        return [$project, $user];
    }

    private function requirement(Project $project, string $title = 'A requirement'): EngObject
    {
        return app(ObjectGraphService::class)->create(
            ObjectType::FUNCTIONAL_REQUIREMENT, $project->tenant_id, $project->id, $title,
        );
    }

    public function test_requirement_without_design_is_undesigned(): void
    {
        [$project] = $this->boundProject();
        $this->requirement($project);

        $register = app(DesignService::class)->register($project);
        $this->assertSame(1, $register['summary']['undesigned']);
        $this->assertFalse($register['rows']->first()['designed']);
    }

    public function test_adding_design_marks_requirement_designed(): void
    {
        [$project, $pm] = $this->boundProject();
        $req = $this->requirement($project);

        $design = app(DesignService::class)->addDesign($req, 'design_component', 'Payroll service', 'A microservice', $pm);

        $this->assertSame(ObjectType::DESIGN_COMPONENT, $design->fresh()->type);
        $register = app(DesignService::class)->register($project);
        $this->assertTrue($register['rows']->first()['designed']);
        $this->assertSame(100.0, $register['summary']['designed_pct']);
    }

    public function test_design_feeds_the_traceability_matrix(): void
    {
        [$project, $pm] = $this->boundProject();
        $req = $this->requirement($project);
        $design = app(DesignService::class)->addDesign($req, 'interface', 'Payroll API', null, $pm);

        $row = app(RtmService::class)->matrix($project)['rows']->first();
        $this->assertContains($design->ref, $row['design']->all());
    }

    public function test_unknown_design_type_rejected(): void
    {
        [$project, $pm] = $this->boundProject();
        $req = $this->requirement($project);

        $this->expectException(\InvalidArgumentException::class);
        app(DesignService::class)->addDesign($req, 'nonsense', 'X', null, $pm);
    }

    public function test_pm_can_author_design_via_http(): void
    {
        [$project, $pm] = $this->boundProject();
        $req = $this->requirement($project);

        $this->actingAs($pm)
            ->post(route('design.store', $req), ['type' => 'database_object', 'title' => 'payroll table'])
            ->assertRedirect(route('design.index', $project));

        $this->assertDatabaseHas('objects', ['type' => 'database_object', 'title' => 'payroll table']);
    }

    public function test_member_cannot_author_design(): void
    {
        [$project, $member] = $this->boundProject('project_member');
        $req = $this->requirement($project);

        $this->actingAs($member)
            ->post(route('design.store', $req), ['type' => 'design_component', 'title' => 'Sneaky'])
            ->assertForbidden();
    }

    public function test_design_can_only_target_a_requirement(): void
    {
        [$project, $pm] = $this->boundProject();
        $evidence = app(ObjectGraphService::class)->create(
            ObjectType::EVIDENCE, $project->tenant_id, $project->id, 'Evidence',
        );

        $this->actingAs($pm)
            ->post(route('design.store', $evidence), ['type' => 'design_component', 'title' => 'Bad'])
            ->assertNotFound();
    }

    public function test_index_renders_for_pm(): void
    {
        [$project, $pm] = $this->boundProject();
        $req = $this->requirement($project, 'Renderable requirement');
        app(DesignService::class)->addDesign($req, 'design_component', 'Renderable design', null, $pm);

        $this->actingAs($pm)->get(route('design.index', $project))
            ->assertOk()
            ->assertSee('Design register')
            ->assertSee('Renderable requirement')
            ->assertSee('Renderable design');
    }

    public function test_index_denied_outside_scope(): void
    {
        [$project] = $this->boundProject();
        $outsider = User::factory()->create(['role' => 'regular']);

        $this->actingAs($outsider)->get(route('design.index', $project))->assertForbidden();
    }
}

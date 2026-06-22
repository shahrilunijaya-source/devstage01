<?php

declare(strict_types=1);

namespace Tests\Feature\Prototype;

use App\Enums\ObjectType;
use App\Models\Acl\Role;
use App\Models\Acl\ScopeBinding;
use App\Models\Graph\EngObject;
use App\Models\Portfolio\Tenant;
use App\Models\Project;
use App\Models\User;
use App\Services\Graph\ObjectGraphService;
use App\Services\Prototype\PrototypeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrototypeTest extends TestCase
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

    public function test_requirement_without_element_is_not_started(): void
    {
        [$project] = $this->boundProject();
        $this->requirement($project);

        $register = app(PrototypeService::class)->register($project);
        $this->assertSame('none', $register['rows']->first()['status']);
        $this->assertSame(1, $register['summary']['not_started']);
    }

    public function test_added_element_starts_planned(): void
    {
        [$project, $pm] = $this->boundProject();
        $req = $this->requirement($project);

        $element = app(PrototypeService::class)->addElement($req, 'Login screen', null, $pm);

        $this->assertSame('planned', $element->fresh()->getAttribute('attributes')['state']);
        $this->assertSame('planned', app(PrototypeService::class)->register($project)['rows']->first()['status']);
    }

    public function test_status_reflects_most_advanced_element(): void
    {
        [$project, $pm] = $this->boundProject();
        $req = $this->requirement($project);
        $service = app(PrototypeService::class);
        $a = $service->addElement($req, 'Element A', null, $pm);
        $service->addElement($req, 'Element B', null, $pm);

        $service->setState($a, 'demoed', $pm);

        $this->assertSame('demoed', $service->register($project)['rows']->first()['status']);
        $this->assertSame(100.0, $service->register($project)['summary']['demoed_pct']);
    }

    public function test_invalid_state_rejected(): void
    {
        [$project, $pm] = $this->boundProject();
        $req = $this->requirement($project);
        $element = app(PrototypeService::class)->addElement($req, 'X', null, $pm);

        $this->expectException(\InvalidArgumentException::class);
        app(PrototypeService::class)->setState($element, 'shipped', $pm);
    }

    public function test_set_state_via_http_bumps_version(): void
    {
        [$project, $pm] = $this->boundProject();
        $req = $this->requirement($project);
        $element = app(PrototypeService::class)->addElement($req, 'Login screen', null, $pm);

        $this->actingAs($pm)
            ->post(route('prototype.state', $element), ['state' => 'built'])
            ->assertRedirect(route('prototype.index', $project));

        $this->assertSame('built', $element->fresh()->getAttribute('attributes')['state']);
        $this->assertGreaterThan(1, $element->fresh()->current_version);
    }

    public function test_member_cannot_author_element(): void
    {
        [$project, $member] = $this->boundProject('project_member');
        $req = $this->requirement($project);

        $this->actingAs($member)
            ->post(route('prototype.store', $req), ['title' => 'Sneaky'])
            ->assertForbidden();
    }

    public function test_element_can_only_target_a_requirement(): void
    {
        [$project, $pm] = $this->boundProject();
        $evidence = app(ObjectGraphService::class)->create(
            ObjectType::EVIDENCE, $project->tenant_id, $project->id, 'Evidence',
        );

        $this->actingAs($pm)
            ->post(route('prototype.store', $evidence), ['title' => 'Bad'])
            ->assertNotFound();
    }

    public function test_index_renders_for_pm(): void
    {
        [$project, $pm] = $this->boundProject();
        $req = $this->requirement($project, 'Renderable requirement');
        app(PrototypeService::class)->addElement($req, 'Renderable element', null, $pm);

        $this->actingAs($pm)->get(route('prototype.index', $project))
            ->assertOk()
            ->assertSee('Prototype register')
            ->assertSee('Renderable requirement')
            ->assertSee('Renderable element');
    }

    public function test_index_denied_outside_scope(): void
    {
        [$project] = $this->boundProject();
        $outsider = User::factory()->create(['role' => 'regular']);

        $this->actingAs($outsider)->get(route('prototype.index', $project))->assertForbidden();
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Graph;

use App\Enums\ObjectType;
use App\Models\Acl\Role;
use App\Models\Acl\ScopeBinding;
use App\Models\Graph\EngObject;
use App\Models\Portfolio\Tenant;
use App\Models\Project;
use App\Models\User;
use App\Services\Graph\ObjectGraphService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchTest extends TestCase
{
    use RefreshDatabase;

    private function object(Project $project, ObjectType $type, string $title, string $classification = 'internal'): EngObject
    {
        return app(ObjectGraphService::class)->create($type, $project->tenant_id, $project->id, $title, ['classification' => $classification]);
    }

    public function test_search_finds_objects_in_accessible_projects_only(): void
    {
        $tenantA = Tenant::default();
        $tenantB = Tenant::create(['name' => 'Other', 'slug' => 'other', 'type' => 'client', 'status' => 'active']);
        $projectA = Project::create(['tenant_id' => $tenantA->id, 'name' => 'Acme', 'code' => 'A', 'status' => 'active']);
        $projectB = Project::create(['tenant_id' => $tenantB->id, 'name' => 'Other Co', 'code' => 'B', 'status' => 'active']);

        // Per-project ID sequences mean both mint FIND-0001 — assert on titles, not refs.
        $this->object($projectA, ObjectType::FINDING, 'Acme payroll cadence');
        $this->object($projectB, ObjectType::FINDING, 'Other payroll cadence');

        $pm = User::factory()->create(['role' => 'regular']);
        ScopeBinding::create([
            'user_id' => $pm->id, 'role_id' => Role::where('key', 'project_pm')->whereNull('tenant_id')->value('id'),
            'tenant_id' => $tenantA->id, 'scope_type' => 'project', 'scope_id' => $projectA->id,
        ]);

        $this->actingAs($pm)->get(route('search', ['q' => 'payroll']))
            ->assertOk()
            ->assertSee('Acme payroll cadence')
            ->assertDontSee('Other payroll cadence');
    }

    public function test_restricted_object_excluded_from_results(): void
    {
        $project = Project::create(['tenant_id' => Tenant::default()->id, 'name' => 'Acme', 'code' => 'A', 'status' => 'active']);
        $visible = $this->object($project, ObjectType::FINDING, 'Open finding');
        $restricted = $this->object($project, ObjectType::DECISION, 'Open finding secret', 'restricted');

        $pm = User::factory()->create(['role' => 'regular']);
        ScopeBinding::create([
            'user_id' => $pm->id, 'role_id' => Role::where('key', 'project_pm')->whereNull('tenant_id')->value('id'),
            'tenant_id' => $project->tenant_id, 'scope_type' => 'project', 'scope_id' => $project->id,
        ]);

        $this->actingAs($pm)->get(route('search', ['q' => 'finding']))
            ->assertOk()
            ->assertSee($visible->ref)
            ->assertDontSee($restricted->ref);
    }

    public function test_empty_query_shows_prompt(): void
    {
        $pm = User::factory()->create(['role' => 'regular']);
        $this->actingAs($pm)->get(route('search'))->assertOk()->assertSee('to begin');
    }
}

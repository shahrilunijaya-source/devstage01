<?php

declare(strict_types=1);

namespace Tests\Feature\Portfolio;

use App\Models\Acl\Role;
use App\Models\Acl\ScopeBinding;
use App\Models\Portfolio\Tenant;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectTeamTest extends TestCase
{
    use RefreshDatabase;

    public function test_team_lists_bound_members(): void
    {
        $project = Project::create(['tenant_id' => Tenant::default()->id, 'name' => 'Acme', 'code' => 'A', 'status' => 'active']);
        $pm = User::factory()->create(['role' => 'regular', 'name' => 'Pat Manager']);
        $member = User::factory()->create(['role' => 'regular', 'name' => 'Mel Member']);
        foreach ([[$pm, 'project_pm'], [$member, 'project_member']] as [$u, $key]) {
            ScopeBinding::create([
                'user_id' => $u->id, 'role_id' => Role::where('key', $key)->whereNull('tenant_id')->value('id'),
                'tenant_id' => $project->tenant_id, 'scope_type' => 'project', 'scope_id' => $project->id,
            ]);
        }

        $this->actingAs($pm)->get(route('portfolio.team', $project))
            ->assertOk()
            ->assertSee('Pat Manager')
            ->assertSee('Mel Member');
    }

    public function test_team_denied_outside_scope(): void
    {
        $project = Project::create(['tenant_id' => Tenant::default()->id, 'name' => 'Acme', 'code' => 'A', 'status' => 'active']);
        $outsider = User::factory()->create(['role' => 'regular']);

        $this->actingAs($outsider)->get(route('portfolio.team', $project))->assertForbidden();
    }
}

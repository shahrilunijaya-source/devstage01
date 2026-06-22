<?php

declare(strict_types=1);

namespace Tests\Feature\Portfolio;

use App\Models\Acl\Role;
use App\Models\Acl\ScopeBinding;
use App\Models\Portfolio\Module;
use App\Models\Portfolio\Tenant;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortfolioWebTest extends TestCase
{
    use RefreshDatabase;

    private function project(?Tenant $tenant = null, string $code = 'P1'): Project
    {
        $tenant ??= Tenant::default();

        return Project::create(['tenant_id' => $tenant->id, 'name' => "Proj {$code}", 'code' => $code, 'status' => 'active']);
    }

    private function boundPm(Project $project): User
    {
        $user = User::factory()->create(['role' => 'regular']);
        ScopeBinding::create([
            'user_id' => $user->id,
            'role_id' => Role::where('key', 'project_pm')->whereNull('tenant_id')->value('id'),
            'tenant_id' => $project->tenant_id,
            'scope_type' => 'project',
            'scope_id' => $project->id,
        ]);

        return $user;
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/portfolio')->assertRedirect('/login');
    }

    public function test_login_succeeds_with_valid_credentials(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'password' => bcrypt('secret123')]);

        $this->post('/login', ['email' => $user->email, 'password' => 'secret123'])
            ->assertRedirect(route('portfolio.index'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_fails_with_bad_credentials(): void
    {
        $user = User::factory()->create(['role' => 'admin', 'password' => bcrypt('secret123')]);

        $this->from('/login')->post('/login', ['email' => $user->email, 'password' => 'wrong'])
            ->assertRedirect('/login')->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_admin_sees_all_projects(): void
    {
        $project = $this->project();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get('/portfolio')->assertOk()->assertSee($project->name);
    }

    public function test_bound_pm_can_view_own_project_but_not_other_tenant(): void
    {
        $project = $this->project();
        $otherTenant = Tenant::create(['name' => 'Other', 'slug' => 'other', 'type' => 'client', 'status' => 'active']);
        $foreign = $this->project($otherTenant, 'P2');
        $pm = $this->boundPm($project);

        $this->actingAs($pm)->get(route('portfolio.show', $project))->assertOk()->assertSee($project->name);
        $this->actingAs($pm)->get(route('portfolio.show', $foreign))->assertForbidden();
    }

    public function test_only_admin_can_create_project(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $regular = User::factory()->create(['role' => 'regular']);
        $tenant = Tenant::default();

        $this->actingAs($regular)->post(route('portfolio.projects.store'), [
            'tenant_id' => $tenant->id, 'name' => 'X', 'code' => 'X', 'status' => 'active',
        ])->assertForbidden();

        $this->actingAs($admin)->post(route('portfolio.projects.store'), [
            'tenant_id' => $tenant->id, 'name' => 'New Sys', 'code' => 'NS', 'status' => 'active',
        ])->assertRedirect();
        $this->assertDatabaseHas('projects', ['code' => 'NS']);
    }

    public function test_bound_pm_can_add_module_which_seeds_nine_stages(): void
    {
        $project = $this->project();
        $pm = $this->boundPm($project);

        $this->actingAs($pm)->post(route('portfolio.modules.store', $project), ['name' => 'Billing', 'code' => 'BILL'])
            ->assertRedirect(route('portfolio.show', $project));

        $module = Module::where('project_id', $project->id)->where('code', 'BILL')->firstOrFail();
        $this->assertSame(9, $module->stages()->count());
    }

    public function test_unbound_user_cannot_add_module(): void
    {
        $project = $this->project();
        $stranger = User::factory()->create(['role' => 'regular']);

        $this->actingAs($stranger)->post(route('portfolio.modules.store', $project), ['name' => 'X', 'code' => 'X'])
            ->assertForbidden();
    }
}

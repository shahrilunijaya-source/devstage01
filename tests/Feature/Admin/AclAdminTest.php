<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Acl\Role;
use App\Models\Acl\ScopeBinding;
use App\Models\Portfolio\Tenant;
use App\Models\Project;
use App\Models\User;
use App\Services\AccessControl\PolicyDecisionPoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AclAdminTest extends TestCase
{
    use RefreshDatabase;

    private function project(): Project
    {
        return Project::create(['tenant_id' => Tenant::default()->id, 'name' => 'P', 'code' => 'P', 'status' => 'active']);
    }

    private function pdp(): PolicyDecisionPoint
    {
        return app(PolicyDecisionPoint::class);
    }

    public function test_non_admin_is_forbidden(): void
    {
        $regular = User::factory()->create(['role' => 'regular']);

        $this->actingAs($regular)->get(route('admin.acl.index'))->assertForbidden();
        $this->actingAs($regular)->get(route('admin.acl.audit'))->assertForbidden();
    }

    public function test_admin_can_load_console(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->get(route('admin.acl.index'))->assertOk()->assertSee('Access Control');
    }

    public function test_admin_grant_gives_scoped_access_and_revoke_removes_it(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'regular']);
        $project = $this->project();
        $roleId = Role::where('key', 'project_pm')->whereNull('tenant_id')->value('id');

        $this->assertFalse($this->pdp()->can($target, 'view', $project)->permitted);

        $this->actingAs($admin)->post(route('admin.acl.grant'), [
            'user_id' => $target->id, 'role_id' => $roleId,
            'scope_type' => 'project', 'project_id' => $project->id,
        ])->assertRedirect(route('admin.acl.index'));

        $this->assertTrue($this->pdp()->can($target->fresh(), 'view', $project)->permitted);

        $binding = ScopeBinding::where('user_id', $target->id)->firstOrFail();
        $this->actingAs($admin)->post(route('admin.acl.revoke', $binding))->assertRedirect();

        $this->assertNotNull($binding->fresh()->revoked_at);
        $this->assertFalse($this->pdp()->can($target->fresh(), 'view', $project)->permitted);
    }

    public function test_grant_validates_required_scope_target(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $target = User::factory()->create(['role' => 'regular']);
        $roleId = Role::where('key', 'project_pm')->whereNull('tenant_id')->value('id');

        // scope_type=project but no project_id → validation error.
        $this->actingAs($admin)->from(route('admin.acl.index'))->post(route('admin.acl.grant'), [
            'user_id' => $target->id, 'role_id' => $roleId, 'scope_type' => 'project',
        ])->assertSessionHasErrors('project_id');
    }

    public function test_audit_decision_filter_narrows_results(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $stranger = User::factory()->create(['role' => 'regular']);
        $pm = User::factory()->create(['role' => 'regular']);
        $project = $this->project();
        ScopeBinding::create([
            'user_id' => $pm->id, 'role_id' => Role::where('key', 'project_pm')->whereNull('tenant_id')->value('id'),
            'tenant_id' => $project->tenant_id, 'scope_type' => 'project', 'scope_id' => $project->id,
        ]);

        $this->pdp()->can($stranger, 'view', $project); // deny: 'no active binding for scope'
        $this->pdp()->can($pm, 'view', $project);       // permit: 'granted by role'

        $this->actingAs($admin)->get(route('admin.acl.audit', ['decision' => 'deny']))
            ->assertOk()
            ->assertSee('no active binding for scope')
            ->assertDontSee('granted by role');

        $this->actingAs($admin)->get(route('admin.acl.audit', ['decision' => 'permit']))
            ->assertOk()
            ->assertSee('granted by role')
            ->assertDontSee('no active binding for scope');
    }

    public function test_audit_csv_export_streams_filtered_rows(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $stranger = User::factory()->create(['role' => 'regular']);
        $project = $this->project();
        $this->pdp()->can($stranger, 'view', $project); // a deny audit row

        $response = $this->actingAs($admin)->get(route('admin.acl.audit.csv', ['decision' => 'deny']));
        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));

        $csv = $response->streamedContent();
        $this->assertStringContainsString('Decision', $csv);
        $this->assertStringContainsString('no active binding for scope', $csv);
    }

    public function test_audit_csv_denied_for_non_admin(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'regular']))
            ->get(route('admin.acl.audit.csv'))->assertForbidden();
    }
}

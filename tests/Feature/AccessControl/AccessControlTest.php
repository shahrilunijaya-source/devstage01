<?php

declare(strict_types=1);

namespace Tests\Feature\AccessControl;

use App\Models\Acl\AccessAudit;
use App\Models\Acl\Role;
use App\Models\Acl\ScopeBinding;
use App\Models\Portfolio\Tenant;
use App\Models\Project;
use App\Models\RagChunk;
use App\Models\User;
use App\Services\AccessControl\PolicyDecisionPoint;
use App\Services\Rag\RagRetriever;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    private function pdp(): PolicyDecisionPoint
    {
        return app(PolicyDecisionPoint::class);
    }

    private function bind(User $user, Project $project, string $roleKey = 'project_pm'): void
    {
        ScopeBinding::create([
            'user_id' => $user->id,
            'role_id' => Role::where('key', $roleKey)->whereNull('tenant_id')->value('id'),
            'tenant_id' => $project->tenant_id,
            'scope_type' => 'project',
            'scope_id' => $project->id,
        ]);
    }

    public function test_provisioning_seeds_six_global_roles(): void
    {
        $this->assertSame(6, Role::whereNull('tenant_id')->count());
    }

    public function test_deny_by_default_for_unbound_user(): void
    {
        $tenant = Tenant::default();
        $project = Project::create(['tenant_id' => $tenant->id, 'name' => 'P', 'code' => 'P', 'status' => 'active']);
        $user = User::factory()->create(['role' => 'regular']);

        $decision = $this->pdp()->can($user, 'view', $project);

        $this->assertFalse($decision->permitted);
        $this->assertFalse($decision->abstain);
        $this->assertSame(1, AccessAudit::where('user_id', $user->id)->where('decision', 'deny')->count());
    }

    public function test_bound_user_permitted_on_own_project_denied_across_tenant(): void
    {
        $tenant1 = Tenant::default();
        $tenant2 = Tenant::create(['name' => 'Other', 'slug' => 'other', 'type' => 'client', 'status' => 'active']);
        $project1 = Project::create(['tenant_id' => $tenant1->id, 'name' => 'P1', 'code' => 'P1', 'status' => 'active']);
        $project2 = Project::create(['tenant_id' => $tenant2->id, 'name' => 'P2', 'code' => 'P2', 'status' => 'active']);
        $user = User::factory()->create(['role' => 'regular']);
        $this->bind($user, $project1);

        $this->assertTrue($this->pdp()->can($user, 'view', $project1)->permitted);
        $this->assertFalse($this->pdp()->can($user, 'view', $project2)->permitted);
    }

    public function test_admin_fast_path_permits_any_project(): void
    {
        $project = Project::create(['tenant_id' => Tenant::default()->id, 'name' => 'P', 'code' => 'P', 'status' => 'active']);
        $admin = User::factory()->create(['role' => 'admin']);

        $this->assertTrue($this->pdp()->can($admin, 'baseline', $project)->permitted);
    }

    public function test_rag_retrieval_is_scoped_to_accessible_projects(): void
    {
        $tenant1 = Tenant::default();
        $tenant2 = Tenant::create(['name' => 'Other', 'slug' => 'other', 'type' => 'client', 'status' => 'active']);
        $project1 = Project::create(['tenant_id' => $tenant1->id, 'name' => 'P1', 'code' => 'P1', 'status' => 'active']);
        $project2 = Project::create(['tenant_id' => $tenant2->id, 'name' => 'P2', 'code' => 'P2', 'status' => 'active']);
        $user = User::factory()->create(['role' => 'regular']);
        $this->bind($user, $project1);

        foreach ([$project1, $project2] as $p) {
            RagChunk::create([
                'project_id' => $p->id, 'tenant_id' => $p->tenant_id, 'scope' => 'project',
                'source_type' => 'document', 'source_id' => 1, 'source_label' => $p->name,
                'chunk_text' => 'text', 'embedding' => [0.1, 0.2], 'token_count' => 2,
                'content_hash' => 'h'.$p->id,
            ]);
        }

        $allowed = $this->pdp()->accessibleProjectIds($user, 'retrieve');
        $chunks = app(RagRetriever::class)->scopedChunks($allowed, $tenant1->id, includeGlobal: false);

        $this->assertSame([$project1->id], $allowed);
        $this->assertSame(1, $chunks->count());
        $this->assertSame($project1->id, (int) $chunks->first()->project_id);
    }

    public function test_access_audit_is_immutable(): void
    {
        $row = AccessAudit::create([
            'decision' => 'deny', 'action' => 'view', 'reason' => 'test',
        ]);

        $this->expectException(RuntimeException::class);
        $row->update(['reason' => 'tampered']);
    }
}

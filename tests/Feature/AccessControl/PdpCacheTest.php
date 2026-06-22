<?php

declare(strict_types=1);

namespace Tests\Feature\AccessControl;

use App\Enums\ObjectType;
use App\Models\Acl\AccessAudit;
use App\Models\Acl\Role;
use App\Models\Acl\ScopeBinding;
use App\Models\Graph\EngObject;
use App\Models\Portfolio\Tenant;
use App\Models\Project;
use App\Models\User;
use App\Services\AccessControl\PolicyDecisionPoint;
use App\Services\Graph\ObjectGraphService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PdpCacheTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:Project,1:User} */
    private function boundProject(): array
    {
        $project = Project::create(['tenant_id' => Tenant::default()->id, 'name' => 'Acme', 'code' => 'A', 'status' => 'active']);
        $pm = User::factory()->create(['role' => 'regular']);
        ScopeBinding::create([
            'user_id' => $pm->id, 'role_id' => Role::where('key', 'project_pm')->whereNull('tenant_id')->value('id'),
            'tenant_id' => $project->tenant_id, 'scope_type' => 'project', 'scope_id' => $project->id,
        ]);

        return [$project, $pm];
    }

    public function test_repeated_checks_reuse_cached_binding_and_rule_lookups(): void
    {
        [$project, $pm] = $this->boundProject();
        $graph = app(ObjectGraphService::class);
        for ($i = 0; $i < 10; $i++) {
            $graph->create(ObjectType::FINDING, $project->tenant_id, $project->id, "F{$i}", ['classification' => 'internal']);
        }
        $objects = EngObject::where('project_id', $project->id)->get();

        $pdp = app(PolicyDecisionPoint::class);
        $pdp->flushScopeCache();

        $queries = 0;
        DB::listen(function ($q) use (&$queries): void {
            if (str_contains($q->sql, 'acl_scope_bindings') || str_contains($q->sql, 'acl_role_permission') || str_contains($q->sql, 'acl_object_rules')) {
                $queries++;
            }
        });

        foreach ($objects as $o) {
            $pdp->can($pm, 'view', $o);
        }

        // 10 objects: bindings (1) + role permissions (1) + object rules (1) = 3,
        // not 30. The exact bound is generous to stay robust.
        $this->assertLessThanOrEqual(5, $queries, "Expected cached ACL lookups, got {$queries} queries.");
    }

    public function test_cache_is_busted_when_a_binding_changes_mid_request(): void
    {
        [$project, $pm] = $this->boundProject();
        $graph = app(ObjectGraphService::class);
        $object = $graph->create(ObjectType::FINDING, $project->tenant_id, $project->id, 'F', ['classification' => 'internal']);

        $pdp = app(PolicyDecisionPoint::class);
        $stranger = User::factory()->create(['role' => 'regular']);

        // First check primes the cache with an empty binding set → denied.
        $this->assertFalse($pdp->can($stranger, 'view', $object)->permitted);

        // Granting a binding must invalidate the cache → now permitted.
        ScopeBinding::create([
            'user_id' => $stranger->id, 'role_id' => Role::where('key', 'project_pm')->whereNull('tenant_id')->value('id'),
            'tenant_id' => $project->tenant_id, 'scope_type' => 'project', 'scope_id' => $project->id,
        ]);

        $this->assertTrue($pdp->can($stranger, 'view', $object)->permitted);
    }

    public function test_allows_is_silent_while_can_audits(): void
    {
        [$project, $pm] = $this->boundProject();
        $object = app(ObjectGraphService::class)->create(
            ObjectType::FINDING, $project->tenant_id, $project->id, 'F', ['classification' => 'internal'],
        );

        $pdp = app(PolicyDecisionPoint::class);

        // Bulk-filter checks must not write audit rows…
        AccessAudit::query()->delete();
        for ($i = 0; $i < 5; $i++) {
            $pdp->allows($pm, 'view', $object);
        }
        $this->assertSame(0, AccessAudit::count());

        // …but a terminal can() decision still does.
        $pdp->can($pm, 'view', $object);
        $this->assertSame(1, AccessAudit::count());
    }
}

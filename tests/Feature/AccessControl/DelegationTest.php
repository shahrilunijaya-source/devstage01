<?php

declare(strict_types=1);

namespace Tests\Feature\AccessControl;

use App\Enums\ObjectType;
use App\Models\Acl\Delegation;
use App\Models\Acl\Role;
use App\Models\Acl\ScopeBinding;
use App\Models\Graph\EngObject;
use App\Models\Portfolio\Tenant;
use App\Models\Project;
use App\Models\User;
use App\Services\AccessControl\DelegationService;
use App\Services\AccessControl\Exceptions\DelegationException;
use App\Services\AccessControl\PolicyDecisionPoint;
use App\Services\Graph\ObjectGraphService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DelegationTest extends TestCase
{
    use RefreshDatabase;

    private function service(): DelegationService
    {
        return app(DelegationService::class);
    }

    private function pdp(): PolicyDecisionPoint
    {
        return app(PolicyDecisionPoint::class);
    }

    private function project(): Project
    {
        return Project::create(['tenant_id' => Tenant::default()->id, 'name' => 'P', 'code' => 'P', 'status' => 'active']);
    }

    private function bind(User $user, Project $project, string $roleKey): ScopeBinding
    {
        return ScopeBinding::create([
            'user_id' => $user->id,
            'role_id' => Role::where('key', $roleKey)->whereNull('tenant_id')->value('id'),
            'tenant_id' => $project->tenant_id,
            'scope_type' => 'project',
            'scope_id' => $project->id,
        ]);
    }

    private function object(Project $project): EngObject
    {
        return app(ObjectGraphService::class)->create(
            ObjectType::FINDING, $project->tenant_id, $project->id, 'F', ['classification' => 'internal'],
        );
    }

    public function test_delegation_grants_the_delegate_access_until_it_expires(): void
    {
        $project = $this->project();
        $delegator = User::factory()->create(['role' => 'regular']);
        $delegate = User::factory()->create(['role' => 'regular']);
        $this->bind($delegator, $project, 'project_pm');
        $object = $this->object($project);

        // Before delegation the delegate has no binding → denied.
        $this->assertFalse($this->pdp()->can($delegate, 'view', $object)->permitted);

        $this->service()->delegate(
            delegator: $delegator,
            delegate: $delegate,
            roleId: Role::where('key', 'project_member')->whereNull('tenant_id')->value('id'),
            scopeType: 'project',
            scopeId: $project->id,
            tenantId: $project->tenant_id,
            endsAt: now()->addDay(),
        );

        $this->assertTrue($this->pdp()->can($delegate, 'view', $object)->permitted);
    }

    public function test_delegator_cannot_delegate_access_it_does_not_hold(): void
    {
        $project = $this->project();
        $delegator = User::factory()->create(['role' => 'regular']); // no binding
        $delegate = User::factory()->create(['role' => 'regular']);

        $this->expectException(DelegationException::class);

        $this->service()->delegate(
            delegator: $delegator,
            delegate: $delegate,
            roleId: Role::where('key', 'project_member')->whereNull('tenant_id')->value('id'),
            scopeType: 'project',
            scopeId: $project->id,
            tenantId: $project->tenant_id,
            endsAt: now()->addDay(),
        );
    }

    public function test_revoking_a_delegation_withdraws_its_binding(): void
    {
        $project = $this->project();
        $delegator = User::factory()->create(['role' => 'regular']);
        $delegate = User::factory()->create(['role' => 'regular']);
        $this->bind($delegator, $project, 'project_pm');
        $object = $this->object($project);

        $delegation = $this->service()->delegate(
            delegator: $delegator,
            delegate: $delegate,
            roleId: Role::where('key', 'project_member')->whereNull('tenant_id')->value('id'),
            scopeType: 'project',
            scopeId: $project->id,
            tenantId: $project->tenant_id,
            endsAt: now()->addDay(),
        );

        $this->assertTrue($this->pdp()->can($delegate, 'view', $object)->permitted);

        $this->service()->revoke($delegation);

        $this->assertNotNull($delegation->fresh()->revoked_at);
        $this->assertNotNull($delegation->binding->fresh()->revoked_at);
        $this->assertFalse($this->pdp()->can($delegate, 'view', $object)->permitted);
    }

    public function test_expire_due_sweeps_past_delegations_and_their_bindings(): void
    {
        $project = $this->project();
        $delegate = User::factory()->create(['role' => 'regular']);

        // Past-dated binding + delegation (constructed directly to bypass the window guard).
        $binding = ScopeBinding::create([
            'user_id' => $delegate->id,
            'role_id' => Role::where('key', 'project_member')->whereNull('tenant_id')->value('id'),
            'tenant_id' => $project->tenant_id,
            'scope_type' => 'project',
            'scope_id' => $project->id,
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDay(),
        ]);
        $delegation = Delegation::create([
            'delegator_id' => $delegate->id, 'delegate_id' => $delegate->id,
            'role_id' => $binding->role_id, 'tenant_id' => $project->tenant_id,
            'scope_type' => 'project', 'scope_id' => $project->id,
            'scope_binding_id' => $binding->id,
            'starts_at' => now()->subDays(2), 'ends_at' => now()->subDay(),
        ]);

        $swept = $this->service()->expireDue();

        $this->assertSame(1, $swept);
        $this->assertNotNull($delegation->fresh()->revoked_at);
        $this->assertNotNull($binding->fresh()->revoked_at);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\AccessControl;

use App\Enums\ObjectType;
use App\Models\Acl\Role;
use App\Models\Acl\ScopeBinding;
use App\Models\Graph\EngObject;
use App\Models\Portfolio\Tenant;
use App\Models\Project;
use App\Models\User;
use App\Services\AccessControl\PolicyDecisionPoint;
use App\Services\Graph\ObjectGraphService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ObjectFieldRulesTest extends TestCase
{
    use RefreshDatabase;

    private function pdp(): PolicyDecisionPoint
    {
        return app(PolicyDecisionPoint::class);
    }

    /** @return array{0:Project,1:User} bound project_pm on the project */
    private function boundProject(): array
    {
        $project = Project::create(['tenant_id' => Tenant::default()->id, 'name' => 'P', 'code' => 'P', 'status' => 'active']);
        $pm = User::factory()->create(['role' => 'regular']);
        ScopeBinding::create([
            'user_id' => $pm->id, 'role_id' => Role::where('key', 'project_pm')->whereNull('tenant_id')->value('id'),
            'tenant_id' => $project->tenant_id, 'scope_type' => 'project', 'scope_id' => $project->id,
        ]);

        return [$project, $pm];
    }

    private function object(Project $project, string $classification): EngObject
    {
        return app(ObjectGraphService::class)->create(
            ObjectType::FINDING, $project->tenant_id, $project->id, 'F', ['classification' => $classification],
        );
    }

    public function test_object_rule_denies_restricted_to_scoped_user_but_admin_bypasses(): void
    {
        [$project, $pm] = $this->boundProject();
        $restricted = $this->object($project, 'restricted');
        $admin = User::factory()->create(['role' => 'admin']);

        $this->assertFalse($this->pdp()->can($pm, 'view', $restricted)->permitted);
        $this->assertTrue($this->pdp()->can($admin, 'view', $restricted)->permitted);
    }

    public function test_internal_object_remains_viewable(): void
    {
        [$project, $pm] = $this->boundProject();
        $internal = $this->object($project, 'internal');

        $this->assertTrue($this->pdp()->can($pm, 'view', $internal)->permitted);
    }

    public function test_confidential_body_is_redacted_for_non_admin(): void
    {
        [$project, $pm] = $this->boundProject();
        $confidential = $this->object($project, 'confidential');
        $admin = User::factory()->create(['role' => 'admin']);

        $this->assertSame(['body'], $this->pdp()->filterFields($pm, $confidential, ['title', 'body']));
        $this->assertSame([], $this->pdp()->filterFields($admin, $confidential, ['title', 'body']));
    }

    public function test_internal_body_is_not_redacted(): void
    {
        [$project, $pm] = $this->boundProject();
        $internal = $this->object($project, 'internal');

        $this->assertSame([], $this->pdp()->filterFields($pm, $internal, ['title', 'body']));
    }
}

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

class BlockedViewTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:Project,1:User} */
    private function boundProject(): array
    {
        $project = Project::create(['tenant_id' => Tenant::default()->id, 'name' => 'Acme ERP', 'code' => 'ERP', 'status' => 'active']);
        $pm = User::factory()->create(['role' => 'regular']);
        ScopeBinding::create([
            'user_id' => $pm->id, 'role_id' => Role::where('key', 'project_pm')->whereNull('tenant_id')->value('id'),
            'tenant_id' => $project->tenant_id, 'scope_type' => 'project', 'scope_id' => $project->id,
        ]);

        return [$project, $pm];
    }

    public function test_blocked_view_lists_the_blocked_stage(): void
    {
        [$project, $pm] = $this->boundProject();
        $module = Module::create(['project_id' => $project->id, 'name' => 'Payroll', 'code' => 'PAY', 'status' => 'active']);
        $module->stages()->where('stage', 'URS')->update(['status' => 'blocked']);

        $this->actingAs($pm)->get(route('portfolio.blocked'))
            ->assertOk()
            ->assertSee('Payroll')
            ->assertSee('1 blocked stage');
    }

    public function test_blocked_view_empty_when_nothing_blocked(): void
    {
        [$project, $pm] = $this->boundProject();
        Module::create(['project_id' => $project->id, 'name' => 'Payroll', 'code' => 'PAY', 'status' => 'active']);

        $this->actingAs($pm)->get(route('portfolio.blocked'))
            ->assertOk()
            ->assertSee('Nothing is blocked');
    }
}

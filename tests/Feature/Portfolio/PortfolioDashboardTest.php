<?php

declare(strict_types=1);

namespace Tests\Feature\Portfolio;

use App\Enums\ObjectType;
use App\Models\Acl\Role;
use App\Models\Acl\ScopeBinding;
use App\Models\Portfolio\Module;
use App\Models\Portfolio\Tenant;
use App\Models\Project;
use App\Models\User;
use App\Services\Graph\ObjectGraphService;
use App\Services\Portfolio\PortfolioDashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PortfolioDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function service(): PortfolioDashboardService
    {
        return app(PortfolioDashboardService::class);
    }

    /** @return array{0:Project,1:User} bound project_pm */
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

    public function test_dashboard_derives_progress_baselines_and_risks(): void
    {
        [$project, $pm] = $this->boundProject();
        $module = Module::create(['project_id' => $project->id, 'name' => 'Payroll', 'code' => 'PAY', 'status' => 'active']);

        // Module create auto-seeds 9 lifecycle stages; baseline the first one.
        $this->assertSame(9, $module->stages()->count());
        $module->stages()->orderBy('id')->first()->update(['status' => 'baselined']);

        // One open RISK object.
        app(ObjectGraphService::class)->create(ObjectType::RISK, $project->tenant_id, $project->id, 'Budget risk');

        $card = $this->service()->forUser($pm)->firstWhere('project.id', $project->id);

        $this->assertNotNull($card);
        $this->assertSame(11, $card['progress']); // 1 of 9 baselined → 11%
        $this->assertSame(1, $card['openRisks']);
        $this->assertSame('at_risk', $card['health']); // open risk, nothing blocked
    }

    public function test_blocked_stage_drives_blocked_health(): void
    {
        [$project, $pm] = $this->boundProject();
        $module = Module::create(['project_id' => $project->id, 'name' => 'Payroll', 'code' => 'PAY', 'status' => 'active']);
        $module->stages()->orderBy('id')->first()->update(['status' => 'blocked']);

        $card = $this->service()->forUser($pm)->firstWhere('project.id', $project->id);

        $this->assertSame('blocked', $card['health']);
        $this->assertSame(1, $card['blockedStages']);
    }

    public function test_summary_rolls_up_portfolio_totals(): void
    {
        [$project, $pm] = $this->boundProject();
        $module = Module::create(['project_id' => $project->id, 'name' => 'Payroll', 'code' => 'PAY', 'status' => 'active']);
        $module->stages()->orderBy('id')->first()->update(['status' => 'baselined']);
        app(ObjectGraphService::class)->create(ObjectType::RISK, $project->tenant_id, $project->id, 'Budget risk');

        $cards = $this->service()->forUser($pm);
        $summary = $this->service()->summarize($cards);

        $this->assertSame(1, $summary['projects']);
        $this->assertSame(1, $summary['openRisks']);
        $this->assertSame(1, $summary['byHealth']['at_risk']);
        $this->assertSame(0, $summary['byHealth']['blocked']);
        $this->assertSame(11, $summary['avgProgress']);
    }

    public function test_dashboard_page_shows_executive_summary(): void
    {
        [$project, $pm] = $this->boundProject();
        Module::create(['project_id' => $project->id, 'name' => 'Payroll', 'code' => 'PAY', 'status' => 'active']);

        $this->actingAs($pm)->get(route('portfolio.dashboard'))
            ->assertOk()
            ->assertSee('Avg progress')
            ->assertSee('Open risks');
    }

    public function test_dashboard_hides_projects_the_user_cannot_see(): void
    {
        [$project, $pm] = $this->boundProject();

        // A second project in another tenant the pm is not bound to.
        $other = Tenant::create(['name' => 'Other', 'slug' => 'other']);
        Project::create(['tenant_id' => $other->id, 'name' => 'Hidden', 'code' => 'HID', 'status' => 'active']);

        $cards = $this->service()->forUser($pm);

        $this->assertCount(1, $cards);
        $this->assertSame($project->id, $cards->first()['project']->id);
    }
}

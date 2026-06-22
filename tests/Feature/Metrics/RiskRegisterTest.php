<?php

declare(strict_types=1);

namespace Tests\Feature\Metrics;

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

class RiskRegisterTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:Project,1:User} */
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

    private function risk(Project $project, string $title, string $impact, string $classification = 'internal'): EngObject
    {
        return app(ObjectGraphService::class)->create(
            ObjectType::RISK, $project->tenant_id, $project->id, $title,
            ['impact' => $impact, 'classification' => $classification],
        );
    }

    public function test_register_lists_risks_worst_impact_first(): void
    {
        [$project, $pm] = $this->boundProject();
        $this->risk($project, 'Low risk', 'low');
        $this->risk($project, 'Critical risk', 'critical');
        $this->risk($project, 'Medium risk', 'medium');

        $response = $this->actingAs($pm)->get(route('metrics.risks', $project))->assertOk();

        // Critical appears before medium, medium before low.
        $body = $response->getContent();
        $this->assertLessThan(strpos($body, 'Medium risk'), strpos($body, 'Critical risk'));
        $this->assertLessThan(strpos($body, 'Low risk'), strpos($body, 'Medium risk'));
    }

    public function test_restricted_risk_excluded_from_register(): void
    {
        [$project, $pm] = $this->boundProject();
        $this->risk($project, 'Visible risk', 'high');
        $this->risk($project, 'Hidden risk', 'high', 'restricted');

        $this->actingAs($pm)->get(route('metrics.risks', $project))
            ->assertOk()
            ->assertSee('Visible risk')
            ->assertDontSee('Hidden risk');
    }

    public function test_register_denied_outside_scope(): void
    {
        [$project] = $this->boundProject();
        $outsider = User::factory()->create(['role' => 'regular']);

        $this->actingAs($outsider)->get(route('metrics.risks', $project))->assertForbidden();
    }

    public function test_csv_export_streams_risk_rows(): void
    {
        [$project, $pm] = $this->boundProject();
        $this->risk($project, 'Exportable risk', 'high');

        $response = $this->actingAs($pm)->get(route('metrics.risks.csv', $project));
        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));

        $csv = $response->streamedContent();
        $this->assertStringContainsString('Impact', $csv);          // header row
        $this->assertStringContainsString('Exportable risk', $csv); // data row
    }

    public function test_csv_export_denied_outside_scope(): void
    {
        [$project] = $this->boundProject();
        $outsider = User::factory()->create(['role' => 'regular']);

        $this->actingAs($outsider)->get(route('metrics.risks.csv', $project))->assertForbidden();
    }
}

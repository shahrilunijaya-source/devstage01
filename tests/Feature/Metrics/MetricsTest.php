<?php

declare(strict_types=1);

namespace Tests\Feature\Metrics;

use App\Enums\ConfidenceLevel;
use App\Enums\ObjectType;
use App\Enums\RelationType;
use App\Models\Acl\Role;
use App\Models\Acl\ScopeBinding;
use App\Models\Graph\EngObject;
use App\Models\Portfolio\Module;
use App\Models\Portfolio\Session;
use App\Models\Portfolio\Tenant;
use App\Models\Project;
use App\Models\User;
use App\Services\Graph\ObjectGraphService;
use App\Services\Graph\TraceService;
use App\Services\Metrics\MetricsService;
use App\Services\Session\SessionEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetricsTest extends TestCase
{
    use RefreshDatabase;

    private function metrics(): MetricsService
    {
        return app(MetricsService::class);
    }

    private function project(): Project
    {
        return Project::create(['tenant_id' => Tenant::default()->id, 'name' => 'P', 'code' => 'P', 'status' => 'active']);
    }

    private function sessionWith(Project $project): Session
    {
        $module = Module::create(['project_id' => $project->id, 'name' => 'M', 'code' => 'M']);
        $stage = $module->stages()->where('stage', 'BRS')->firstOrFail();

        return Session::create([
            'stage_id' => $stage->id, 'module_id' => $module->id, 'project_id' => $project->id,
            'title' => 'S', 'status' => 'in_progress', 'phase' => 'in_session',
        ]);
    }

    private function finding(Session $session, string $title): EngObject
    {
        return app(ObjectGraphService::class)->create(
            ObjectType::FINDING, $session->project->tenant_id, $session->project_id, $title,
            ['session_id' => $session->id, 'confidence' => ConfidenceLevel::MEDIUM, 'impact' => 'medium'],
        );
    }

    public function test_traceability_complete_when_requirement_traces_to_evidence(): void
    {
        $project = $this->project();
        $graph = app(ObjectGraphService::class);
        $evd = $graph->create(ObjectType::EVIDENCE, $project->tenant_id, $project->id, 'E');
        $req = $graph->create(ObjectType::BUSINESS_REQUIREMENT, $project->tenant_id, $project->id, 'R');
        app(TraceService::class)->link($evd, $req, RelationType::DERIVED_FROM);

        $m = $this->metrics()->projectMetrics($project);

        $this->assertSame(1, $m['requirements']);
        $this->assertSame(0, $m['requirements_without_evidence']);
        $this->assertSame(100.0, $m['traceability_completeness']);
    }

    public function test_requirement_without_evidence_raises_red_flag(): void
    {
        $project = $this->project();
        app(ObjectGraphService::class)->create(ObjectType::BUSINESS_REQUIREMENT, $project->tenant_id, $project->id, 'lonely');

        $m = $this->metrics()->projectMetrics($project);

        $this->assertSame(1, $m['requirements_without_evidence']);
        $this->assertSame(0.0, $m['traceability_completeness']);
        $this->assertContains('missing_evidence', array_column($m['red_flags'], 'code'));
    }

    public function test_rubber_stamping_detected_when_all_confirmed_no_corrections(): void
    {
        $project = $this->project();
        $session = $this->sessionWith($project);
        $engine = app(SessionEngineService::class);
        $user = User::factory()->create(['role' => 'regular']);

        foreach (['a', 'b', 'c'] as $t) {
            $engine->capture($this->finding($session, $t), 'confirm', $user);
        }

        $m = $this->metrics()->sessionMetrics($session);

        $this->assertSame(3, $m['confirmations']);
        $this->assertSame(0, $m['corrections']);
        $this->assertTrue($m['rubber_stamp']);
        $this->assertSame(100.0, $m['confirmation_rate']);
    }

    public function test_correction_clears_rubber_stamp_signal(): void
    {
        $project = $this->project();
        $session = $this->sessionWith($project);
        $engine = app(SessionEngineService::class);
        $user = User::factory()->create(['role' => 'regular']);

        $engine->capture($this->finding($session, 'a'), 'confirm', $user);
        $engine->capture($this->finding($session, 'b'), 'confirm', $user);
        $engine->capture($this->finding($session, 'c'), 'correct', $user);

        $this->assertFalse($this->metrics()->sessionMetrics($session)['rubber_stamp']);
    }

    public function test_metrics_page_is_acl_gated(): void
    {
        $project = $this->project();
        $stranger = User::factory()->create(['role' => 'regular']);
        $pm = User::factory()->create(['role' => 'regular']);
        ScopeBinding::create([
            'user_id' => $pm->id, 'role_id' => Role::where('key', 'project_pm')->whereNull('tenant_id')->value('id'),
            'tenant_id' => $project->tenant_id, 'scope_type' => 'project', 'scope_id' => $project->id,
        ]);

        $this->actingAs($stranger)->get(route('metrics.show', $project))->assertForbidden();
        $this->actingAs($pm)->get(route('metrics.show', $project))->assertOk()->assertSee('Quality metrics');
    }
}

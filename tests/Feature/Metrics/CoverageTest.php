<?php

declare(strict_types=1);

namespace Tests\Feature\Metrics;

use App\Enums\ObjectType;
use App\Enums\RelationType;
use App\Models\Acl\Role;
use App\Models\Acl\ScopeBinding;
use App\Models\Graph\EngObject;
use App\Models\Portfolio\Tenant;
use App\Models\Project;
use App\Models\User;
use App\Services\Graph\ObjectGraphService;
use App\Services\Graph\TraceService;
use App\Services\Metrics\CoverageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CoverageTest extends TestCase
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

    private function object(Project $project, ObjectType $type, string $title): EngObject
    {
        return app(ObjectGraphService::class)->create($type, $project->tenant_id, $project->id, $title);
    }

    public function test_matrix_distinguishes_covered_and_uncovered_requirements(): void
    {
        [$project] = $this->boundProject();
        $trace = app(TraceService::class);

        $evidence = $this->object($project, ObjectType::EVIDENCE, 'Interview');
        $coveredReq = $this->object($project, ObjectType::BUSINESS_REQUIREMENT, 'Pay monthly');
        $orphanReq = $this->object($project, ObjectType::BUSINESS_REQUIREMENT, 'Unsupported');

        // evidence → coveredReq (so coveredReq traces back to evidence)
        $trace->link($evidence, $coveredReq, RelationType::DERIVED_FROM);

        $matrix = app(CoverageService::class)->matrix($project);

        $this->assertSame(2, $matrix['summary']['requirements']);
        $this->assertSame(1, $matrix['summary']['covered']);
        $this->assertSame(1, $matrix['summary']['uncovered']);
        $this->assertSame(50.0, $matrix['summary']['coverage_pct']);

        $covered = $matrix['requirements']->firstWhere('object.id', $coveredReq->id);
        $this->assertTrue($covered['covered']);
        $this->assertContains($evidence->ref, $covered['sources']->all());

        $orphan = $matrix['requirements']->firstWhere('object.id', $orphanReq->id);
        $this->assertFalse($orphan['covered']);
    }

    public function test_matrix_flags_orphan_evidence(): void
    {
        [$project] = $this->boundProject();
        $trace = app(TraceService::class);

        $usedEvidence = $this->object($project, ObjectType::EVIDENCE, 'Used');
        $orphanEvidence = $this->object($project, ObjectType::EVIDENCE, 'Orphan');
        $finding = $this->object($project, ObjectType::FINDING, 'Insight');
        $trace->link($usedEvidence, $finding, RelationType::DERIVED_FROM);

        $matrix = app(CoverageService::class)->matrix($project);

        $this->assertSame(2, $matrix['summary']['evidence']);
        $this->assertSame(1, $matrix['summary']['used']);
        $this->assertSame(1, $matrix['summary']['orphan']);

        $orphan = $matrix['evidence']->firstWhere('object.id', $orphanEvidence->id);
        $this->assertFalse($orphan['used']);
    }

    public function test_coverage_page_renders_for_authorised_viewer(): void
    {
        [$project, $pm] = $this->boundProject();
        $this->object($project, ObjectType::BUSINESS_REQUIREMENT, 'Pay monthly');

        $this->actingAs($pm)->get(route('metrics.coverage', $project))
            ->assertOk()
            ->assertSee('Coverage')
            ->assertSee('no evidence'); // the uncovered requirement pill
    }

    public function test_coverage_denied_outside_scope(): void
    {
        [$project] = $this->boundProject();
        $outsider = User::factory()->create(['role' => 'regular']);

        $this->actingAs($outsider)->get(route('metrics.coverage', $project))->assertForbidden();
    }
}

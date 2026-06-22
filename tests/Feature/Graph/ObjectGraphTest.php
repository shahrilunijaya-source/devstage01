<?php

declare(strict_types=1);

namespace Tests\Feature\Graph;

use App\Enums\ObjectType;
use App\Enums\RelationType;
use App\Models\Graph\EngObject;
use App\Models\Graph\ObjectVersion;
use App\Models\Portfolio\Module;
use App\Models\Portfolio\Session;
use App\Models\Portfolio\Tenant;
use App\Models\Project;
use App\Services\Graph\BaselineService;
use App\Services\Graph\ObjectGraphService;
use App\Services\Graph\TraceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class ObjectGraphTest extends TestCase
{
    use RefreshDatabase;

    private function scaffold(): array
    {
        $tenant = Tenant::default();
        $project = Project::create(['tenant_id' => $tenant->id, 'name' => 'Acme ERP', 'code' => 'ERP', 'status' => 'active']);
        $module = Module::create(['project_id' => $project->id, 'name' => 'Payroll', 'code' => 'PAY']);
        $brs = $module->stages()->where('stage', 'BRS')->firstOrFail();

        return [$tenant, $project, $module, $brs];
    }

    public function test_module_creation_auto_seeds_nine_stages(): void
    {
        [, , $module] = $this->scaffold();

        $this->assertSame(9, $module->stages()->count());
        $this->assertTrue($module->stages()->where('stage', 'DEPLOY')->exists());
    }

    public function test_create_mints_permanent_id_and_writes_first_version(): void
    {
        [$tenant, $project, , $brs] = $this->scaffold();
        $graph = app(ObjectGraphService::class);

        $evd = $graph->create(ObjectType::EVIDENCE, $tenant->id, $project->id, 'Tender doc', ['stage_id' => $brs->id]);

        $this->assertSame('EVD-0001', $evd->ref);
        $this->assertSame(1, $evd->versions()->count());
        $this->assertSame(1, (int) $evd->current_version);
    }

    public function test_permanent_id_sequence_is_per_project(): void
    {
        [$tenant, $project] = $this->scaffold();
        $project2 = Project::create(['tenant_id' => $tenant->id, 'name' => 'Beta', 'code' => 'BETA', 'status' => 'active']);
        $graph = app(ObjectGraphService::class);

        $a = $graph->create(ObjectType::EVIDENCE, $tenant->id, $project->id, 'A');
        $b = $graph->create(ObjectType::EVIDENCE, $tenant->id, $project2->id, 'B');

        $this->assertSame('EVD-0001', $a->ref);
        $this->assertSame('EVD-0001', $b->ref); // same ref, different project — both valid
    }

    public function test_update_appends_immutable_version(): void
    {
        [$tenant, $project, , $brs] = $this->scaffold();
        $graph = app(ObjectGraphService::class);
        $evd = $graph->create(ObjectType::EVIDENCE, $tenant->id, $project->id, 'v1', ['stage_id' => $brs->id]);

        $graph->update($evd, ['title' => 'v2']);

        $this->assertSame(2, (int) $evd->fresh()->current_version);
        $this->assertSame(2, $evd->versions()->count());

        $this->expectException(RuntimeException::class);
        ObjectVersion::where('object_id', $evd->id)->first()->update(['version' => 99]);
    }

    public function test_forward_trace_walks_the_chain(): void
    {
        [$tenant, $project, $module, $brs] = $this->scaffold();
        $graph = app(ObjectGraphService::class);
        $trace = app(TraceService::class);
        $scope = ['module_id' => $module->id, 'stage_id' => $brs->id];

        $evd = $graph->create(ObjectType::EVIDENCE, $tenant->id, $project->id, 'E', $scope);
        $find = $graph->create(ObjectType::FINDING, $tenant->id, $project->id, 'F', $scope);
        $req = $graph->create(ObjectType::BUSINESS_REQUIREMENT, $tenant->id, $project->id, 'R', $scope);

        $trace->link($evd, $find, RelationType::DERIVED_FROM);
        $trace->link($find, $req, RelationType::DERIVED_FROM);

        $refs = collect($trace->forwardTrace($evd))->map(fn (EngObject $o) => $o->ref)->all();

        $this->assertSame(['FIND-0001', 'BRS-REQ-0001'], $refs);
    }

    public function test_baseline_freezes_approved_session_objects(): void
    {
        [$tenant, $project, $module, $brs] = $this->scaffold();
        $session = Session::create([
            'stage_id' => $brs->id, 'module_id' => $module->id, 'project_id' => $project->id,
            'title' => 'BRS S1', 'status' => 'approved',
        ]);
        $graph = app(ObjectGraphService::class);
        $scope = ['module_id' => $module->id, 'stage_id' => $brs->id, 'session_id' => $session->id];

        $graph->create(ObjectType::EVIDENCE, $tenant->id, $project->id, 'E', $scope);
        $graph->create(ObjectType::FINDING, $tenant->id, $project->id, 'F', $scope);
        $graph->create(ObjectType::BUSINESS_REQUIREMENT, $tenant->id, $project->id, 'R', $scope);

        $baseline = app(BaselineService::class)->baseline($brs->fresh());

        $this->assertSame('BRS v1.0', $baseline->version_label);
        $this->assertSame(3, $baseline->baselineObjects()->count());
        $this->assertSame('baselined', $brs->fresh()->status);
        $this->assertSame($baseline->id, (int) $brs->fresh()->current_baseline_id);
    }

    public function test_rebaseline_supersedes_prior(): void
    {
        [$tenant, $project, $module, $brs] = $this->scaffold();
        $session = Session::create([
            'stage_id' => $brs->id, 'module_id' => $module->id, 'project_id' => $project->id,
            'title' => 'S', 'status' => 'approved',
        ]);
        app(ObjectGraphService::class)->create(ObjectType::EVIDENCE, $tenant->id, $project->id, 'E', [
            'module_id' => $module->id, 'stage_id' => $brs->id, 'session_id' => $session->id,
        ]);
        $service = app(BaselineService::class);

        $first = $service->baseline($brs->fresh());
        $second = $service->baseline($brs->fresh());

        $this->assertSame('superseded', $first->fresh()->status);
        $this->assertSame('approved', $second->fresh()->status);
        $this->assertSame(2, (int) $second->sequence);
    }
}

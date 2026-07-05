<?php

declare(strict_types=1);

namespace Tests\Feature\Document;

use App\Enums\ObjectType;
use App\Models\Acl\Role;
use App\Models\Acl\ScopeBinding;
use App\Models\Graph\EngObject;
use App\Models\Portfolio\Module;
use App\Models\Portfolio\Session;
use App\Models\Portfolio\StageBaseline;
use App\Models\Portfolio\Tenant;
use App\Models\Project;
use App\Models\User;
use App\Services\Document\DeckBuilder;
use App\Services\Document\PptxExporter;
use App\Services\Graph\ObjectGraphService;
use App\Services\Portfolio\ObjectiveService;
use App\Services\Session\SessionEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PptxExportTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:StageBaseline,1:User} approved + baselined BRS */
    private function baseline(): array
    {
        $tenant = Tenant::default();
        $project = Project::create(['tenant_id' => $tenant->id, 'name' => 'Acme ERP', 'code' => 'ERP', 'status' => 'active']);
        $module = Module::create(['project_id' => $project->id, 'name' => 'Payroll', 'code' => 'PAY']);
        $stage = $module->stages()->where('stage', 'BRS')->firstOrFail();
        $session = Session::create([
            'stage_id' => $stage->id, 'module_id' => $module->id, 'project_id' => $project->id,
            'title' => 'S', 'status' => 'draft', 'phase' => 'pre_analysis',
        ]);
        app(ObjectGraphService::class)->create(ObjectType::EVIDENCE, $tenant->id, $project->id, 'Evidence', [
            'module_id' => $module->id, 'stage_id' => $stage->id, 'session_id' => $session->id, 'body' => 'b',
        ]);

        $pm = User::factory()->create(['role' => 'regular']);
        ScopeBinding::create([
            'user_id' => $pm->id, 'role_id' => Role::where('key', 'project_pm')->whereNull('tenant_id')->value('id'),
            'tenant_id' => $tenant->id, 'scope_type' => 'project', 'scope_id' => $project->id,
        ]);

        $objectives = app(ObjectiveService::class);
        $objectives->capture($project, ['title' => 'Objective', 'business_problem' => 'p'], $pm);
        $objectives->approve($project, $pm);

        $engine = app(SessionEngineService::class);
        $engine->preAnalyze($session);
        $engine->passFirewall($session->fresh(), $pm);
        $engine->startSession($session->fresh());
        foreach (EngObject::where('session_id', $session->id)->get() as $obj) {
            $engine->capture($obj, 'decide', $pm);
        }
        $engine->consolidate($session->fresh());
        $engine->approveSession($session->fresh(), $pm);
        $this->actingAs($pm)->post(route('stages.baseline', $stage));

        return [StageBaseline::where('stage_id', $stage->id)->firstOrFail(), $pm];
    }

    public function test_exporter_writes_a_valid_pptx_file(): void
    {
        [$baseline, $pm] = $this->baseline();

        $deck = app(DeckBuilder::class)->build($baseline, $pm);
        $path = app(PptxExporter::class)->export($deck);

        $this->assertFileExists($path);
        $this->assertGreaterThan(0, filesize($path));
        // .pptx is a ZIP container — verify the magic bytes.
        $this->assertSame('PK', file_get_contents($path, false, null, 0, 2));

        @unlink($path);
    }

    public function test_route_streams_pptx_download(): void
    {
        [$baseline, $pm] = $this->baseline();

        $response = $this->actingAs($pm)->get(route('baselines.deck.pptx', $baseline));

        $response->assertOk();
        $response->assertDownload("{$baseline->version_label}.pptx");
    }

    public function test_pptx_denied_outside_scope(): void
    {
        [$baseline] = $this->baseline();
        $outsider = User::factory()->create(['role' => 'regular']);

        $this->actingAs($outsider)->get(route('baselines.deck.pptx', $baseline))->assertForbidden();
    }
}

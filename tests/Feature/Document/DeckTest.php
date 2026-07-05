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
use App\Services\Graph\ObjectGraphService;
use App\Services\Portfolio\ObjectiveService;
use App\Services\Session\SessionEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeckTest extends TestCase
{
    use RefreshDatabase;

    /** Build an approved, baselined BRS stage and return [baseline, project, pm]. */
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
        $baseline = StageBaseline::where('stage_id', $stage->id)->firstOrFail();

        return [$baseline, $project, $pm];
    }

    public function test_deck_builder_assembles_summary_and_sections(): void
    {
        [$baseline, , $pm] = $this->baseline();

        $deck = app(DeckBuilder::class)->build($baseline, $pm);

        $this->assertSame($baseline->id, $deck['baseline']->id);
        $this->assertSame('BRS', $deck['stageLabel']);
        $this->assertGreaterThan(0, $deck['summary']['total']);
        // The pre-analysis drafts a Finding + Business Requirement → at least one section.
        $this->assertNotEmpty($deck['sections']);
        $this->assertContains(
            'business_requirement',
            $deck['sections']->pluck('type')->all(),
        );
    }

    public function test_deck_route_renders_for_authorised_viewer(): void
    {
        [$baseline, , $pm] = $this->baseline();

        $this->actingAs($pm)->get(route('baselines.deck', $baseline))
            ->assertOk()
            ->assertSee($baseline->version_label)
            ->assertSee('Stage Review Deck');
    }

    public function test_deck_denied_for_user_outside_scope(): void
    {
        [$baseline] = $this->baseline();
        $outsider = User::factory()->create(['role' => 'regular']);

        $this->actingAs($outsider)->get(route('baselines.deck', $baseline))->assertForbidden();
    }
}

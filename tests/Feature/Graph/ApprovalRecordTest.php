<?php

declare(strict_types=1);

namespace Tests\Feature\Graph;

use App\Enums\ObjectType;
use App\Models\Acl\Role;
use App\Models\Acl\ScopeBinding;
use App\Models\Graph\EngObject;
use App\Models\Graph\TraceRelationship;
use App\Models\Portfolio\Module;
use App\Models\Portfolio\Session;
use App\Models\Portfolio\StageBaseline;
use App\Models\Portfolio\Tenant;
use App\Models\Project;
use App\Models\User;
use App\Services\Graph\ObjectGraphService;
use App\Services\Session\SessionEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApprovalRecordTest extends TestCase
{
    use RefreshDatabase;

    /** Drive a BRS stage to an approved, baselined state; return [baseline, stage, pm]. */
    private function baseline(): array
    {
        $tenant = Tenant::default();
        $project = Project::create(['tenant_id' => $tenant->id, 'name' => 'Acme', 'code' => 'ERP', 'status' => 'active']);
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

        return [$baseline, $stage, $pm];
    }

    public function test_baseline_mints_an_approval_object_linked_to_members(): void
    {
        [$baseline, $stage, $pm] = $this->baseline();

        $approval = EngObject::where('project_id', $baseline->project_id)
            ->where('type', 'approval')->firstOrFail();

        $this->assertStringStartsWith('APR-', $approval->ref);
        $this->assertSame('confirmed_by_evidence', $approval->status->value);
        $this->assertSame((int) $pm->id, (int) $approval->owner_user_id);
        $this->assertSame($baseline->id, $approval->attributes['stage_baseline_id']);

        // Linked via APPROVES to each baselined member (3 objects).
        $links = TraceRelationship::where('from_object_id', $approval->id)
            ->where('relation_type', 'approves')->count();
        $this->assertSame(3, $links);
    }

    public function test_approval_is_not_itself_a_baseline_member(): void
    {
        [$baseline] = $this->baseline();

        $approvalId = EngObject::where('type', 'approval')->value('id');

        $this->assertSame(3, $baseline->baselineObjects()->count());
        $this->assertDatabaseMissing('baseline_objects', [
            'stage_baseline_id' => $baseline->id,
            'object_id' => $approvalId,
        ]);
    }

    public function test_rebaseline_does_not_swallow_prior_approval(): void
    {
        [$baseline, $stage, $pm] = $this->baseline();

        // Re-baseline the same stage; the prior approval must stay out of the new baseline.
        $this->actingAs($pm)->post(route('stages.baseline', $stage));
        $newBaseline = StageBaseline::where('stage_id', $stage->id)->where('status', 'approved')->firstOrFail();

        $approvalIds = EngObject::where('type', 'approval')->pluck('id');
        foreach ($approvalIds as $id) {
            $this->assertDatabaseMissing('baseline_objects', [
                'stage_baseline_id' => $newBaseline->id,
                'object_id' => $id,
            ]);
        }
    }
}

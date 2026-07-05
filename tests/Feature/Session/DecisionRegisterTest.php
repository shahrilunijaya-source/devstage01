<?php

declare(strict_types=1);

namespace Tests\Feature\Session;

use App\Enums\ObjectType;
use App\Models\Acl\Role;
use App\Models\Acl\ScopeBinding;
use App\Models\Graph\EngObject;
use App\Models\Graph\TraceRelationship;
use App\Models\Portfolio\Module;
use App\Models\Portfolio\Session;
use App\Models\Portfolio\Tenant;
use App\Models\Project;
use App\Models\User;
use App\Services\Graph\ObjectGraphService;
use App\Services\Session\SessionEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DecisionRegisterTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:Session,1:Project,2:User,3:EngObject} in-session + bound pm + a requirement */
    private function ready(): array
    {
        $tenant = Tenant::default();
        $project = Project::create(['tenant_id' => $tenant->id, 'name' => 'P', 'code' => 'P', 'status' => 'active']);
        $module = Module::create(['project_id' => $project->id, 'name' => 'M', 'code' => 'M']);
        $stage = $module->stages()->where('stage', 'BRS')->firstOrFail();
        $session = Session::create([
            'stage_id' => $stage->id, 'module_id' => $module->id, 'project_id' => $project->id,
            'title' => 'S', 'status' => 'in_progress', 'phase' => 'in_session',
        ]);
        $req = app(ObjectGraphService::class)->create(
            ObjectType::BUSINESS_REQUIREMENT, $tenant->id, $project->id, 'A requirement',
            ['module_id' => $module->id, 'stage_id' => $stage->id, 'session_id' => $session->id],
        );

        $pm = User::factory()->create(['role' => 'regular']);
        ScopeBinding::create([
            'user_id' => $pm->id, 'role_id' => Role::where('key', 'project_pm')->whereNull('tenant_id')->value('id'),
            'tenant_id' => $tenant->id, 'scope_type' => 'project', 'scope_id' => $project->id,
        ]);

        return [$session, $project, $pm, $req];
    }

    public function test_decide_records_a_decision_object_linked_to_the_item(): void
    {
        [, $project, $pm, $req] = $this->ready();

        app(SessionEngineService::class)->capture($req, 'decide', $pm);

        $decision = EngObject::where('project_id', $project->id)->where('type', 'decision')->firstOrFail();
        $this->assertStringStartsWith('DEC-', $decision->ref);
        $this->assertSame((int) $req->id, (int) $decision->source_object_id);
        $this->assertSame((int) $pm->id, (int) $decision->owner_user_id);

        $this->assertSame(1, TraceRelationship::where('from_object_id', $decision->id)
            ->where('to_object_id', $req->id)->where('relation_type', 'resolves')->count());
    }

    public function test_deciding_evidence_records_no_decision(): void
    {
        [$session, $project, $pm] = $this->ready();
        $evd = app(ObjectGraphService::class)->create(
            ObjectType::EVIDENCE, $project->tenant_id, $project->id, 'Evidence',
            ['module_id' => $session->module_id, 'stage_id' => $session->stage_id, 'session_id' => $session->id, 'body' => 'b'],
        );

        app(SessionEngineService::class)->capture($evd, 'decide', $pm);

        $this->assertSame(0, EngObject::where('project_id', $project->id)->where('type', 'decision')->count());
    }

    public function test_register_lists_recorded_decisions(): void
    {
        [, $project, $pm, $req] = $this->ready();
        app(SessionEngineService::class)->capture($req, 'decide', $pm);

        $decision = EngObject::where('type', 'decision')->firstOrFail();

        $this->actingAs($pm)->get(route('metrics.decisions', $project))
            ->assertOk()
            ->assertSee('Decision register')
            ->assertSee($decision->ref)
            ->assertSee($req->ref);
    }

    public function test_register_denied_outside_scope(): void
    {
        [, $project] = $this->ready();
        $outsider = User::factory()->create(['role' => 'regular']);

        $this->actingAs($outsider)->get(route('metrics.decisions', $project))->assertForbidden();
    }
}

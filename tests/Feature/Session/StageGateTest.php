<?php

declare(strict_types=1);

namespace Tests\Feature\Session;

use App\Enums\ObjectType;
use App\Models\Acl\Role;
use App\Models\Acl\ScopeBinding;
use App\Models\Graph\EngObject;
use App\Models\Notification;
use App\Models\Portfolio\Module;
use App\Models\Portfolio\Session;
use App\Models\Portfolio\Stage;
use App\Models\Portfolio\Tenant;
use App\Models\Project;
use App\Models\User;
use App\Services\Graph\ObjectGraphService;
use App\Services\Session\SessionEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StageGateTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:Session,1:Stage,2:User} */
    private function readySession(): array
    {
        $tenant = Tenant::default();
        $project = Project::create(['tenant_id' => $tenant->id, 'name' => 'P', 'code' => 'P', 'status' => 'active']);
        $module = Module::create(['project_id' => $project->id, 'name' => 'M', 'code' => 'M']);
        $stage = $module->stages()->where('stage', 'BRS')->firstOrFail();
        $session = Session::create([
            'stage_id' => $stage->id, 'module_id' => $module->id, 'project_id' => $project->id,
            'title' => 'S', 'status' => 'draft', 'phase' => 'pre_analysis',
        ]);
        app(ObjectGraphService::class)->create(ObjectType::EVIDENCE, $tenant->id, $project->id, 'Ev', [
            'module_id' => $module->id, 'stage_id' => $stage->id, 'session_id' => $session->id, 'body' => 'b',
        ]);

        $pm = User::factory()->create(['role' => 'regular']);
        ScopeBinding::create([
            'user_id' => $pm->id, 'role_id' => Role::where('key', 'project_pm')->whereNull('tenant_id')->value('id'),
            'tenant_id' => $tenant->id, 'scope_type' => 'project', 'scope_id' => $project->id,
        ]);

        return [$session, $stage, $pm];
    }

    public function test_gate_not_ready_with_unresolved_items(): void
    {
        [$session, $stage, $pm] = $this->readySession();
        app(SessionEngineService::class)->preAnalyze($session); // drafts NEEDS_CONFIRMATION items, no approval

        $this->actingAs($pm)->get(route('stages.gate', $stage))
            ->assertOk()
            ->assertSee('Not ready to baseline');
    }

    public function test_gate_ready_after_approval(): void
    {
        [$session, $stage, $pm] = $this->readySession();
        $engine = app(SessionEngineService::class);
        $engine->preAnalyze($session);
        $engine->passFirewall($session->fresh(), $pm);
        $engine->startSession($session->fresh());
        foreach (EngObject::where('session_id', $session->id)->get() as $obj) {
            $engine->capture($obj, 'decide', $pm);
        }
        $engine->consolidate($session->fresh());
        $engine->approveSession($session->fresh(), $pm);

        $this->actingAs($pm)->get(route('stages.gate', $stage))
            ->assertOk()
            ->assertSee('Ready to baseline');
    }

    public function test_gate_denied_outside_scope(): void
    {
        [, $stage] = $this->readySession();
        $outsider = User::factory()->create(['role' => 'regular']);

        $this->actingAs($outsider)->get(route('stages.gate', $stage))->assertForbidden();
    }

    public function test_blocking_a_stage_escalates_to_directors_and_team(): void
    {
        [, $stage, $pm] = $this->readySession();
        $director = User::factory()->create(['role' => 'regular', 'system_role' => 'director', 'active' => true]);

        $this->actingAs($pm)->post(route('stages.status', $stage), [
            'status' => 'blocked', 'reason' => 'Awaiting client sign-off',
        ])->assertRedirect(route('stages.gate', $stage));

        $this->assertSame('blocked', $stage->fresh()->status);
        $this->assertSame(1, Notification::where('user_id', $director->id)->where('type', 'stage_blocked')->count());
    }
}

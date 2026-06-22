<?php

declare(strict_types=1);

namespace Tests\Feature\Notifications;

use App\Enums\ObjectType;
use App\Models\Acl\Role;
use App\Models\Acl\ScopeBinding;
use App\Models\Graph\ChangeRequest;
use App\Models\Graph\EngObject;
use App\Models\Notification;
use App\Models\Portfolio\Module;
use App\Models\Portfolio\Session;
use App\Models\Portfolio\Tenant;
use App\Models\Project;
use App\Models\User;
use App\Services\Graph\ObjectGraphService;
use App\Services\NotificationService;
use App\Services\Session\SessionEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GovernanceNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private function bind(User $user, Project $project, string $roleKey = 'project_pm', string $scopeType = 'project'): void
    {
        ScopeBinding::create([
            'user_id' => $user->id,
            'role_id' => Role::where('key', $roleKey)->whereNull('tenant_id')->value('id'),
            'tenant_id' => $project->tenant_id,
            'scope_type' => $scopeType,
            'scope_id' => $scopeType === 'project' ? $project->id : null,
        ]);
    }

    public function test_notify_project_bindings_targets_bound_users_and_excludes_actor(): void
    {
        $project = Project::create(['tenant_id' => Tenant::default()->id, 'name' => 'P', 'code' => 'P', 'status' => 'active']);
        $actor = User::factory()->create(['role' => 'regular']);
        $teammate = User::factory()->create(['role' => 'regular']);
        $tenantUser = User::factory()->create(['role' => 'regular']);
        $outsider = User::factory()->create(['role' => 'regular']);

        $this->bind($actor, $project);
        $this->bind($teammate, $project);
        $this->bind($tenantUser, $project, scopeType: 'tenant');
        // outsider: no binding

        app(NotificationService::class)->notifyProjectBindings($project, 'demo', 'Hello team', $actor->id);

        $this->assertSame(0, Notification::where('user_id', $actor->id)->count(), 'actor excluded');
        $this->assertSame(1, Notification::where('user_id', $teammate->id)->count(), 'project-bound notified');
        $this->assertSame(1, Notification::where('user_id', $tenantUser->id)->count(), 'tenant-bound notified');
        $this->assertSame(0, Notification::where('user_id', $outsider->id)->count(), 'unbound not notified');
    }

    public function test_notify_reaches_module_scoped_bindings(): void
    {
        $project = Project::create(['tenant_id' => Tenant::default()->id, 'name' => 'P', 'code' => 'P', 'status' => 'active']);
        $module = Module::create(['project_id' => $project->id, 'name' => 'M', 'code' => 'M']);

        $moduleUser = User::factory()->create(['role' => 'regular']);
        ScopeBinding::create([
            'user_id' => $moduleUser->id, 'role_id' => Role::where('key', 'project_pm')->whereNull('tenant_id')->value('id'),
            'tenant_id' => $project->tenant_id, 'scope_type' => 'module', 'scope_id' => $module->id,
        ]);

        app(NotificationService::class)->notifyProjectBindings($project, 'demo', 'Module-scoped user should hear this');

        $this->assertSame(1, Notification::where('user_id', $moduleUser->id)->count());
    }

    public function test_session_approval_notifies_the_team(): void
    {
        $tenant = Tenant::default();
        $project = Project::create(['tenant_id' => $tenant->id, 'name' => 'P', 'code' => 'P', 'status' => 'active']);
        $module = Module::create(['project_id' => $project->id, 'name' => 'M', 'code' => 'M']);
        $stage = $module->stages()->where('stage', 'BRS')->firstOrFail();
        $session = Session::create([
            'stage_id' => $stage->id, 'module_id' => $module->id, 'project_id' => $project->id,
            'title' => 'Payroll BRS', 'status' => 'draft', 'phase' => 'pre_analysis',
        ]);
        app(ObjectGraphService::class)->create(ObjectType::EVIDENCE, $tenant->id, $project->id, 'Ev', [
            'module_id' => $module->id, 'stage_id' => $stage->id, 'session_id' => $session->id, 'body' => 'b',
        ]);

        $approver = User::factory()->create(['role' => 'regular']);
        $teammate = User::factory()->create(['role' => 'regular']);
        $this->bind($approver, $project);
        $this->bind($teammate, $project);

        // Drive to post_session as the approver.
        $engine = app(SessionEngineService::class);
        $engine->preAnalyze($session);
        $engine->passFirewall($session->fresh(), $approver);
        $engine->startSession($session->fresh());
        foreach (EngObject::where('session_id', $session->id)->get() as $obj) {
            $engine->capture($obj, 'decide', $approver);
        }
        $engine->consolidate($session->fresh());

        $this->actingAs($approver)->post(route('sessions.approve', $session))
            ->assertRedirect(route('sessions.show', $session));

        $this->assertSame(1, Notification::where('user_id', $teammate->id)->where('type', 'session_approved')->count());
        $this->assertSame(0, Notification::where('user_id', $approver->id)->where('type', 'session_approved')->count());
    }

    public function test_change_request_raise_notifies_team_and_decision_notifies_raiser(): void
    {
        $project = Project::create(['tenant_id' => Tenant::default()->id, 'name' => 'P', 'code' => 'P', 'status' => 'active']);
        $finding = app(ObjectGraphService::class)->create(ObjectType::FINDING, $project->tenant_id, $project->id, 'Finding');

        $raiser = User::factory()->create(['role' => 'regular']);
        $teammate = User::factory()->create(['role' => 'regular']);
        $approver = User::factory()->create(['role' => 'regular']);
        foreach ([$raiser, $teammate, $approver] as $u) {
            $this->bind($u, $project);
        }

        $this->actingAs($raiser)->post(route('changes.store', $finding), ['title' => 'Reword finding'])
            ->assertRedirect();

        $this->assertSame(1, Notification::where('user_id', $teammate->id)->where('type', 'change_raised')->count());
        $this->assertSame(0, Notification::where('user_id', $raiser->id)->where('type', 'change_raised')->count());

        // A different user approves (separation of duties) → the raiser is notified.
        $change = ChangeRequest::where('project_id', $project->id)->firstOrFail();
        $this->actingAs($approver)->post(route('changes.approve', $change))->assertRedirect();

        $this->assertSame(1, Notification::where('user_id', $raiser->id)->where('type', 'change_approved')->count());
    }
}

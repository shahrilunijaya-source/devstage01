<?php

declare(strict_types=1);

namespace Tests\Feature\Inbox;

use App\Enums\ObjectType;
use App\Models\Acl\Role;
use App\Models\Acl\ScopeBinding;
use App\Models\Portfolio\Module;
use App\Models\Portfolio\Session;
use App\Models\Portfolio\Tenant;
use App\Models\Project;
use App\Models\User;
use App\Services\Change\ChangeManagementService;
use App\Services\Graph\ObjectGraphService;
use App\Services\InboxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InboxTest extends TestCase
{
    use RefreshDatabase;

    private function bind(User $user, Project $project, string $roleKey): void
    {
        ScopeBinding::create([
            'user_id' => $user->id, 'role_id' => Role::where('key', $roleKey)->whereNull('tenant_id')->value('id'),
            'tenant_id' => $project->tenant_id, 'scope_type' => 'project', 'scope_id' => $project->id,
        ]);
    }

    private function makeSession(Project $project, Module $module, string $phase): Session
    {
        $stage = $module->stages()->where('stage', 'BRS')->firstOrFail();

        return Session::create([
            'stage_id' => $stage->id, 'module_id' => $module->id, 'project_id' => $project->id,
            'title' => "Session {$phase}", 'status' => 'draft', 'phase' => $phase,
        ]);
    }

    /** @return array{0:Project,1:Module,2:User,3:User,4:User} project, module, pm, member, raiser */
    private function scenario(): array
    {
        $project = Project::create(['tenant_id' => Tenant::default()->id, 'name' => 'P', 'code' => 'P', 'status' => 'active']);
        $module = Module::create(['project_id' => $project->id, 'name' => 'M', 'code' => 'M']);

        $pm = User::factory()->create(['role' => 'regular']);
        $member = User::factory()->create(['role' => 'regular']);
        $raiser = User::factory()->create(['role' => 'regular']);
        $this->bind($pm, $project, 'project_pm');
        $this->bind($member, $project, 'project_member');
        $this->bind($raiser, $project, 'project_pm');

        $this->makeSession($project, $module, 'firewall_review');
        $this->makeSession($project, $module, 'post_session');

        // A draft change request raised by someone other than the pm.
        $finding = app(ObjectGraphService::class)->create(ObjectType::FINDING, $project->tenant_id, $project->id, 'F');
        app(ChangeManagementService::class)->open($finding, $raiser, 'Reword');

        return [$project, $module, $pm, $member, $raiser];
    }

    public function test_inbox_lists_items_the_user_can_act_on(): void
    {
        [, , $pm] = $this->scenario();

        $inbox = app(InboxService::class)->forUser($pm);

        $this->assertCount(1, $inbox['firewall']);
        $this->assertCount(1, $inbox['approval']);
        $this->assertCount(1, $inbox['change_approval']);
        $this->assertSame(3, $inbox['total']);
    }

    public function test_stage_scoped_validator_sees_the_in_stage_session(): void
    {
        [$project, $module] = $this->scenario();
        $stage = $module->stages()->where('stage', 'BRS')->firstOrFail();

        // Bound only at stage scope (not project) — must still see that stage's work.
        $stageUser = User::factory()->create(['role' => 'regular']);
        ScopeBinding::create([
            'user_id' => $stageUser->id, 'role_id' => Role::where('key', 'project_pm')->whereNull('tenant_id')->value('id'),
            'tenant_id' => $project->tenant_id, 'scope_type' => 'stage', 'scope_id' => $stage->id,
        ]);

        $inbox = app(InboxService::class)->forUser($stageUser);

        $this->assertCount(1, $inbox['firewall']);
        $this->assertCount(1, $inbox['approval']);
    }

    public function test_member_without_validate_or_approve_sees_empty_inbox(): void
    {
        [, , , $member] = $this->scenario();

        $inbox = app(InboxService::class)->forUser($member);

        $this->assertSame(0, $inbox['total']);
    }

    public function test_user_does_not_see_their_own_change_request(): void
    {
        [$project, , , , $raiser] = $this->scenario();

        // The raiser has approve rights but must not be asked to approve their own CR.
        $inbox = app(InboxService::class)->forUser($raiser);

        $this->assertCount(0, $inbox['change_approval']);
    }

    public function test_inbox_page_renders(): void
    {
        [, , $pm] = $this->scenario();

        $this->actingAs($pm)->get(route('inbox'))
            ->assertOk()
            ->assertSee('Awaiting your approval');
    }
}

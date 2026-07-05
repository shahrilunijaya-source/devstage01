<?php

declare(strict_types=1);

namespace Tests\Feature\Discussion;

use App\Enums\ObjectType;
use App\Models\Acl\Role;
use App\Models\Acl\ScopeBinding;
use App\Models\Discussion;
use App\Models\Graph\EngObject;
use App\Models\Graph\TraceRelationship;
use App\Models\Portfolio\Module;
use App\Models\Portfolio\Tenant;
use App\Models\Project;
use App\Models\User;
use App\Services\Discussion\DiscussionService;
use App\Services\Graph\ObjectGraphService;
use App\Services\InboxService;
use App\Services\Portfolio\StageGateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Discussions (spec §12): contextual threads with internal/client visibility,
 * blocking-the-gate semantics, and comment→artefact conversion.
 */
class DiscussionTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:Project,1:User,2:EngObject} project + pm + one requirement object */
    private function makeProject(): array
    {
        $tenant = Tenant::default();
        $project = Project::create(['tenant_id' => $tenant->id, 'name' => 'P', 'code' => 'P', 'status' => 'active']);
        Module::create(['project_id' => $project->id, 'name' => 'M', 'code' => 'M']);

        $pm = User::factory()->create(['role' => 'regular']);
        ScopeBinding::create([
            'user_id' => $pm->id, 'role_id' => Role::where('key', 'project_pm')->whereNull('tenant_id')->value('id'),
            'tenant_id' => $tenant->id, 'scope_type' => 'project', 'scope_id' => $project->id,
        ]);

        $object = app(ObjectGraphService::class)->create(
            ObjectType::BUSINESS_REQUIREMENT, $tenant->id, $project->id, 'Payroll must run by the 25th',
        );

        return [$project, $pm, $object];
    }

    private function makeClient(Project $project): User
    {
        $client = User::factory()->create(['role' => 'client', 'system_role' => 'client']);
        ScopeBinding::create([
            'user_id' => $client->id, 'role_id' => Role::where('key', 'client')->whereNull('tenant_id')->value('id'),
            'tenant_id' => $project->tenant_id, 'scope_type' => 'project', 'scope_id' => $project->id,
        ]);

        return $client;
    }

    public function test_open_and_reply_on_an_object(): void
    {
        [$project, $pm, $object] = $this->makeProject();

        $this->actingAs($pm)->post(route('discussions.store'), [
            'discussable_kind' => 'object', 'discussable_id' => $object->id,
            'title' => 'Is the 25th a hard deadline?', 'body' => 'Contract clause 4.2 says so — confirm with client.',
        ])->assertSessionHas('status');

        $discussion = Discussion::firstOrFail();
        $this->assertSame($project->id, (int) $discussion->project_id);

        $this->actingAs($pm)->post(route('discussions.comment', $discussion), [
            'body' => 'Confirmed in the workshop.',
        ])->assertSessionHas('status');

        $this->assertSame(2, $discussion->comments()->count());
    }

    public function test_clients_only_see_client_visible_threads_and_cannot_write_internal(): void
    {
        [$project, $pm, $object] = $this->makeProject();
        $client = $this->makeClient($project);
        $service = app(DiscussionService::class);

        $internal = $service->open($object, $project, $pm, 'Internal pricing note', 'sensitive', ['visibility' => 'internal']);
        $service->open($object, $project, $pm, 'Client question', 'visible', ['visibility' => 'client']);

        $visible = $service->for($object, $client);
        $this->assertSame(['Client question'], $visible->pluck('title')->all());

        // Even a direct POST to the internal thread 404s for the client.
        $this->actingAs($client)->post(route('discussions.comment', $internal), ['body' => 'hi'])
            ->assertNotFound();

        // A client-authored thread is forced client-visible regardless of input.
        $this->actingAs($client)->post(route('discussions.store'), [
            'discussable_kind' => 'object', 'discussable_id' => $object->id,
            'title' => 'Client attempt', 'body' => 'b', 'visibility' => 'internal',
        ]);
        $this->assertSame('client', Discussion::where('title', 'Client attempt')->value('visibility'));
    }

    public function test_resolve_and_reopen(): void
    {
        [$project, $pm, $object] = $this->makeProject();
        $discussion = app(DiscussionService::class)->open($object, $project, $pm, 'T', 'b');

        $this->actingAs($pm)->post(route('discussions.resolve', $discussion))->assertSessionHas('status');
        $this->assertSame('resolved', $discussion->fresh()->status);

        $this->actingAs($pm)->post(route('discussions.reopen', $discussion))->assertSessionHas('status');
        $this->assertSame('open', $discussion->fresh()->status);
    }

    public function test_open_blocking_discussion_fails_the_stage_gate(): void
    {
        [$project, $pm] = $this->makeProject();
        $stage = $project->modules()->first()->stages()->where('stage', 'BRS')->firstOrFail();

        $discussion = app(DiscussionService::class)->open($stage, $project, $pm, 'Scope unclear', 'b', ['blocking' => true]);

        $gate = app(StageGateService::class)->readiness($stage);
        $check = collect($gate['checks'])->firstWhere('key', 'blocking_discussions');
        $this->assertFalse($check['pass']);
        $this->assertTrue($check['overridable']);

        app(DiscussionService::class)->resolve($discussion, $pm);
        $gate = app(StageGateService::class)->readiness($stage);
        $this->assertTrue(collect($gate['checks'])->firstWhere('key', 'blocking_discussions')['pass']);
    }

    public function test_convert_comment_to_risk_mints_a_traced_object(): void
    {
        [$project, $pm, $object] = $this->makeProject();
        $discussion = app(DiscussionService::class)->open($object, $project, $pm, 'Gateway vendor may slip', 'Lead time unknown.');
        $comment = $discussion->comments()->first();

        $this->actingAs($pm)->post(route('discussions.convert', [$discussion, $comment]), [
            'target' => 'risk',
        ])->assertSessionHas('status');

        $risk = EngObject::where('type', ObjectType::RISK->value)->firstOrFail();
        $this->assertSame('Gateway vendor may slip', $risk->title);
        $this->assertSame($risk->ref, $comment->fresh()->converted_ref);
        $this->assertTrue(
            TraceRelationship::where('from_object_id', $object->id)
                ->where('to_object_id', $risk->id)->exists(),
        );
    }

    public function test_convert_comment_to_change_request_against_the_discussed_object(): void
    {
        [$project, $pm, $object] = $this->makeProject();
        $discussion = app(DiscussionService::class)->open($object, $project, $pm, 'Deadline should be the 24th', 'Client asked.');
        $comment = $discussion->comments()->first();

        $this->actingAs($pm)->post(route('discussions.convert', [$discussion, $comment]), [
            'target' => 'change_request',
        ])->assertSessionHas('status');

        $this->assertStringStartsWith('CR-', (string) $comment->fresh()->converted_ref);
    }

    public function test_convert_to_change_request_needs_an_object_context(): void
    {
        [$project, $pm] = $this->makeProject();
        $discussion = app(DiscussionService::class)->open($project, $project, $pm, 'General note', 'b');
        $comment = $discussion->comments()->first();

        $this->actingAs($pm)->post(route('discussions.convert', [$discussion, $comment]), [
            'target' => 'change_request',
        ])->assertSessionHas('error');

        $this->assertNull($comment->fresh()->converted_ref);
    }

    public function test_assigned_discussions_reach_the_inbox(): void
    {
        [$project, $pm, $object] = $this->makeProject();
        $assignee = User::factory()->create(['role' => 'regular']);
        ScopeBinding::create([
            'user_id' => $assignee->id, 'role_id' => Role::where('key', 'project_member')->whereNull('tenant_id')->value('id'),
            'tenant_id' => $project->tenant_id, 'scope_type' => 'project', 'scope_id' => $project->id,
        ]);

        app(DiscussionService::class)->open($object, $project, $pm, 'Please clarify', 'b', ['assigned_to' => $assignee->id]);

        $inbox = app(InboxService::class)->forUser($assignee);
        $this->assertSame(1, $inbox['discussions']->count());
        $this->assertSame('Please clarify', $inbox['discussions']->first()->title);
    }

    public function test_outsiders_cannot_open_discussions(): void
    {
        [, , $object] = $this->makeProject();
        $outsider = User::factory()->create(['role' => 'regular']);

        $this->actingAs($outsider)->post(route('discussions.store'), [
            'discussable_kind' => 'object', 'discussable_id' => $object->id,
            'title' => 'x', 'body' => 'y',
        ])->assertForbidden();
    }
}

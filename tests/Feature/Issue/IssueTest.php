<?php

declare(strict_types=1);

namespace Tests\Feature\Issue;

use App\Enums\ObjectType;
use App\Models\Acl\Role;
use App\Models\Acl\ScopeBinding;
use App\Models\Portfolio\Tenant;
use App\Models\Project;
use App\Models\User;
use App\Services\Graph\ObjectGraphService;
use App\Services\Issue\IssueService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IssueTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:Project,1:User} */
    private function boundProject(string $roleKey = 'project_pm'): array
    {
        $project = Project::create(['tenant_id' => Tenant::default()->id, 'name' => 'P', 'code' => 'P', 'status' => 'active']);
        $user = User::factory()->create(['role' => 'regular']);
        ScopeBinding::create([
            'user_id' => $user->id, 'role_id' => Role::where('key', $roleKey)->whereNull('tenant_id')->value('id'),
            'tenant_id' => $project->tenant_id, 'scope_type' => 'project', 'scope_id' => $project->id,
        ]);

        return [$project, $user];
    }

    public function test_raised_issue_is_open(): void
    {
        [$project, $pm] = $this->boundProject();
        $issue = app(IssueService::class)->raise($project, 'POS API missing', 'blocks reconciliation', 'high', $pm);

        $this->assertSame(ObjectType::ISSUE, $issue->fresh()->type);
        $data = app(IssueService::class)->forProject($project);
        $this->assertCount(1, $data['open']);
        $this->assertSame(1, $data['summary']['high']);
    }

    public function test_invalid_severity_rejected(): void
    {
        [$project, $pm] = $this->boundProject();

        $this->expectException(\InvalidArgumentException::class);
        app(IssueService::class)->raise($project, 'X', null, 'critical', $pm);
    }

    public function test_open_issues_sorted_high_severity_first(): void
    {
        [$project, $pm] = $this->boundProject();
        $service = app(IssueService::class);
        $service->raise($project, 'Low one', null, 'low', $pm);
        $service->raise($project, 'High one', null, 'high', $pm);

        $open = $service->forProject($project)['open'];
        $this->assertSame('High one', $open->first()->title);
    }

    public function test_resolving_moves_issue_out_of_open(): void
    {
        [$project, $pm] = $this->boundProject();
        $service = app(IssueService::class);
        $issue = $service->raise($project, 'Temp issue', null, 'medium', $pm);

        $service->resolve($issue, 'fixed it', $pm);

        $data = $service->forProject($project);
        $this->assertCount(0, $data['open']);
        $this->assertCount(1, $data['resolved']);
        $this->assertSame('resolved', $issue->fresh()->getAttribute('attributes')['state']);
    }

    public function test_resolving_already_resolved_issue_is_noop(): void
    {
        [$project, $pm] = $this->boundProject();
        $service = app(IssueService::class);
        $issue = $service->raise($project, 'Temp issue', null, 'medium', $pm);

        $service->resolve($issue, 'genuine resolution', $pm);
        $service->resolve($issue, 'tampered note', $pm);

        $this->assertSame('genuine resolution', $issue->fresh()->getAttribute('attributes')['resolution']);
    }

    public function test_raise_via_http(): void
    {
        [$project, $pm] = $this->boundProject();

        $this->actingAs($pm)
            ->post(route('issues.store', $project), ['title' => 'Vendor silent', 'severity' => 'high'])
            ->assertRedirect(route('issues.index', $project));

        $this->assertDatabaseHas('objects', ['type' => 'issue', 'title' => 'Vendor silent']);
    }

    public function test_member_cannot_raise_issue(): void
    {
        [$project, $member] = $this->boundProject('project_member');

        $this->actingAs($member)
            ->post(route('issues.store', $project), ['title' => 'Sneaky', 'severity' => 'low'])
            ->assertForbidden();
    }

    public function test_resolve_requires_issue_type(): void
    {
        [$project, $pm] = $this->boundProject();
        $notIssue = app(ObjectGraphService::class)->create(
            ObjectType::EVIDENCE, $project->tenant_id, $project->id, 'Evidence',
        );

        $this->actingAs($pm)->post(route('issues.resolve', $notIssue), [])->assertNotFound();
    }

    public function test_index_renders_for_pm(): void
    {
        [$project, $pm] = $this->boundProject();
        app(IssueService::class)->raise($project, 'Renderable issue', null, 'high', $pm);

        $this->actingAs($pm)->get(route('issues.index', $project))
            ->assertOk()
            ->assertSee('Issue log')
            ->assertSee('Renderable issue');
    }

    public function test_index_denied_outside_scope(): void
    {
        [$project] = $this->boundProject();
        $outsider = User::factory()->create(['role' => 'regular']);

        $this->actingAs($outsider)->get(route('issues.index', $project))->assertForbidden();
    }
}

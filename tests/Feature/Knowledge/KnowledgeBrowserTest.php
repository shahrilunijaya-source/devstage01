<?php

declare(strict_types=1);

namespace Tests\Feature\Knowledge;

use App\Models\Acl\Role;
use App\Models\Acl\ScopeBinding;
use App\Models\Portfolio\Tenant;
use App\Models\Project;
use App\Models\User;
use App\Services\Knowledge\KnowledgeResolver;
use App\Services\Knowledge\KnowledgeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KnowledgeBrowserTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:Project,1:User} bound project_pm */
    private function boundProject(): array
    {
        $project = Project::create(['tenant_id' => Tenant::default()->id, 'name' => 'Acme', 'code' => 'A', 'status' => 'active']);
        $pm = User::factory()->create(['role' => 'regular']);
        ScopeBinding::create([
            'user_id' => $pm->id, 'role_id' => Role::where('key', 'project_pm')->whereNull('tenant_id')->value('id'),
            'tenant_id' => $project->tenant_id, 'scope_type' => 'project', 'scope_id' => $project->id,
        ]);

        return [$project, $pm];
    }

    public function test_browser_shows_pinned_knowledge_book_contents(): void
    {
        [$project, $pm] = $this->boundProject();
        app(KnowledgeSeeder::class)->seed();
        app(KnowledgeResolver::class)->pin($project, 'v2026.1');

        $this->actingAs($pm)->get(route('knowledge.show', $project))
            ->assertOk()
            ->assertSee('Knowledge Book')
            ->assertSee('v2026.1')
            ->assertSee('D01')          // a seeded deliverable code
            ->assertSee('Deliverables');
    }

    public function test_stage_filter_is_accepted(): void
    {
        [$project, $pm] = $this->boundProject();
        app(KnowledgeSeeder::class)->seed();
        app(KnowledgeResolver::class)->pin($project, 'v2026.1');

        $this->actingAs($pm)->get(route('knowledge.show', [$project, 'stage' => 'BRS']))
            ->assertOk()
            ->assertSee('Knowledge Book');
    }

    public function test_unpinned_project_shows_empty_state(): void
    {
        [$project, $pm] = $this->boundProject();

        $this->actingAs($pm)->get(route('knowledge.show', $project))
            ->assertOk()
            ->assertSee('not pinned to a Knowledge Book');
    }

    public function test_denied_outside_scope(): void
    {
        [$project] = $this->boundProject();
        $outsider = User::factory()->create(['role' => 'regular']);

        $this->actingAs($outsider)->get(route('knowledge.show', $project))->assertForbidden();
    }
}

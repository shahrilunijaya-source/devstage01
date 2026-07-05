<?php

declare(strict_types=1);

namespace Tests\Feature\Knowledge;

use App\Models\Acl\Role;
use App\Models\Acl\ScopeBinding;
use App\Models\Knowledge\ProjectKnowledgeItem;
use App\Models\Portfolio\Tenant;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectKnowledgeTest extends TestCase
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

    public function test_pm_records_an_assumption(): void
    {
        [$project, $pm] = $this->boundProject();

        $this->actingAs($pm)->post(route('project-knowledge.store', $project), [
            'item_type' => 'approved_assumption',
            'title' => 'Vendor will deliver the API by Q3',
        ])->assertRedirect(route('project-knowledge.index', $project));

        $item = ProjectKnowledgeItem::where('project_id', $project->id)->firstOrFail();
        $this->assertSame('approved_assumption', $item->item_type);
        $this->assertSame('approved', $item->status);
        $this->assertSame($pm->id, $item->approved_by);

        $this->actingAs($pm)->get(route('project-knowledge.index', $project))
            ->assertOk()
            ->assertSee('Vendor will deliver the API by Q3');
    }

    public function test_member_cannot_record_but_can_view(): void
    {
        [$project, $member] = $this->boundProject('project_member');

        $this->actingAs($member)->post(route('project-knowledge.store', $project), [
            'item_type' => 'lesson_learned', 'title' => 'x',
        ])->assertForbidden();

        $this->actingAs($member)->get(route('project-knowledge.index', $project))->assertOk();
    }

    public function test_denied_outside_scope(): void
    {
        [$project] = $this->boundProject();
        $outsider = User::factory()->create(['role' => 'regular']);

        $this->actingAs($outsider)->get(route('project-knowledge.index', $project))->assertForbidden();
    }
}

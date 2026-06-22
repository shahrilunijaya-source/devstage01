<?php

declare(strict_types=1);

namespace Tests\Feature\Graph;

use App\Enums\ObjectType;
use App\Models\Acl\Role;
use App\Models\Acl\ScopeBinding;
use App\Models\Graph\EngObject;
use App\Models\Portfolio\Tenant;
use App\Models\Project;
use App\Models\User;
use App\Services\Graph\ObjectGraphService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ObjectBrowserTest extends TestCase
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

    private function object(Project $project, ObjectType $type, string $title, string $classification = 'internal'): EngObject
    {
        return app(ObjectGraphService::class)->create($type, $project->tenant_id, $project->id, $title, ['classification' => $classification]);
    }

    public function test_browser_lists_project_objects(): void
    {
        [$project, $pm] = $this->boundProject();
        $evidence = $this->object($project, ObjectType::EVIDENCE, 'Interview notes');
        $finding = $this->object($project, ObjectType::FINDING, 'Payroll cadence');

        $this->actingAs($pm)->get(route('objects.index', $project))
            ->assertOk()
            ->assertSee($evidence->ref)
            ->assertSee($finding->ref);
    }

    public function test_type_filter_narrows_results(): void
    {
        [$project, $pm] = $this->boundProject();
        $evidence = $this->object($project, ObjectType::EVIDENCE, 'Interview notes');
        $finding = $this->object($project, ObjectType::FINDING, 'Payroll cadence');

        $this->actingAs($pm)->get(route('objects.index', [$project, 'type' => 'finding']))
            ->assertOk()
            ->assertSee($finding->ref)
            ->assertDontSee($evidence->ref);
    }

    public function test_text_search_matches_title(): void
    {
        [$project, $pm] = $this->boundProject();
        $hit = $this->object($project, ObjectType::FINDING, 'Payroll cadence');
        $miss = $this->object($project, ObjectType::FINDING, 'Leave policy');

        $this->actingAs($pm)->get(route('objects.index', [$project, 'q' => 'payroll']))
            ->assertOk()
            ->assertSee($hit->ref)
            ->assertDontSee($miss->ref);
    }

    public function test_restricted_objects_are_excluded_from_listing(): void
    {
        [$project, $pm] = $this->boundProject();
        $visible = $this->object($project, ObjectType::FINDING, 'Visible');
        $restricted = $this->object($project, ObjectType::DECISION, 'Hidden', 'restricted');

        $this->actingAs($pm)->get(route('objects.index', $project))
            ->assertOk()
            ->assertSee($visible->ref)
            ->assertDontSee($restricted->ref);
    }

    public function test_browser_denied_outside_scope(): void
    {
        [$project] = $this->boundProject();
        $outsider = User::factory()->create(['role' => 'regular']);

        $this->actingAs($outsider)->get(route('objects.index', $project))->assertForbidden();
    }

    public function test_wildcard_search_term_is_neutralised(): void
    {
        [$project, $pm] = $this->boundProject();
        $a = $this->object($project, ObjectType::FINDING, 'Payroll cadence');
        $b = $this->object($project, ObjectType::FINDING, 'Leave policy');

        // A bare '%' must match literally (nothing), not act as a match-all wildcard.
        $this->actingAs($pm)->get(route('objects.index', [$project, 'q' => '%']))
            ->assertOk()
            ->assertDontSee($a->ref)
            ->assertDontSee($b->ref);
    }
}

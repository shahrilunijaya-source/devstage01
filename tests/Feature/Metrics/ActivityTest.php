<?php

declare(strict_types=1);

namespace Tests\Feature\Metrics;

use App\Enums\ObjectType;
use App\Models\Acl\Role;
use App\Models\Acl\ScopeBinding;
use App\Models\Graph\EngObject;
use App\Models\Portfolio\Tenant;
use App\Models\Project;
use App\Models\User;
use App\Services\Graph\ObjectGraphService;
use App\Services\Metrics\ActivityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityTest extends TestCase
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

    public function test_feed_includes_object_creation_events(): void
    {
        [$project, $pm] = $this->boundProject();
        $obj = $this->object($project, ObjectType::FINDING, 'A finding');

        $feed = app(ActivityService::class)->feed($project, $pm);

        $this->assertTrue($feed->contains(fn (array $e) => $e['ref'] === $obj->ref && $e['kind'] === 'created'));
    }

    public function test_feed_excludes_restricted_objects(): void
    {
        [$project, $pm] = $this->boundProject();
        $restricted = $this->object($project, ObjectType::DECISION, 'Secret', 'restricted');

        $feed = app(ActivityService::class)->feed($project, $pm);

        $this->assertFalse($feed->contains(fn (array $e) => $e['ref'] === $restricted->ref));
    }

    public function test_activity_page_renders(): void
    {
        [$project, $pm] = $this->boundProject();
        $this->object($project, ObjectType::FINDING, 'A finding');

        $this->actingAs($pm)->get(route('metrics.activity', $project))
            ->assertOk()
            ->assertSee('Activity')
            ->assertSee('created');
    }

    public function test_activity_denied_outside_scope(): void
    {
        [$project] = $this->boundProject();
        $outsider = User::factory()->create(['role' => 'regular']);

        $this->actingAs($outsider)->get(route('metrics.activity', $project))->assertForbidden();
    }
}

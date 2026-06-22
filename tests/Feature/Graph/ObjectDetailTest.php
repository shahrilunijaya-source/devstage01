<?php

declare(strict_types=1);

namespace Tests\Feature\Graph;

use App\Enums\ObjectType;
use App\Enums\RelationType;
use App\Models\Acl\Role;
use App\Models\Acl\ScopeBinding;
use App\Models\Graph\EngObject;
use App\Models\Portfolio\Tenant;
use App\Models\Project;
use App\Models\User;
use App\Services\Graph\ObjectGraphService;
use App\Services\Graph\TraceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ObjectDetailTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:Project,1:User} bound project_pm */
    private function boundProject(): array
    {
        $project = Project::create(['tenant_id' => Tenant::default()->id, 'name' => 'P', 'code' => 'P', 'status' => 'active']);
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

    public function test_detail_shows_object_traces_and_versions(): void
    {
        [$project, $pm] = $this->boundProject();
        $evidence = $this->object($project, ObjectType::EVIDENCE, 'Interview');
        $finding = $this->object($project, ObjectType::FINDING, 'Monthly payroll');
        app(TraceService::class)->link($evidence, $finding, RelationType::DERIVED_FROM);

        // Finding's detail: inbound trace from evidence, version history present.
        $this->actingAs($pm)->get(route('objects.show', $finding))
            ->assertOk()
            ->assertSee($finding->ref)
            ->assertSee($evidence->ref)        // appears as an input + in reverse reach
            ->assertSee('derived from')
            ->assertSee('Version history');
    }

    public function test_confidential_body_is_redacted_on_detail(): void
    {
        [$project, $pm] = $this->boundProject();
        $secret = app(ObjectGraphService::class)->create(
            ObjectType::FINDING, $project->tenant_id, $project->id, 'Sensitive',
            ['classification' => 'confidential', 'body' => 'TOP SECRET BODY'],
        );

        $this->actingAs($pm)->get(route('objects.show', $secret))
            ->assertOk()
            ->assertDontSee('TOP SECRET BODY')
            ->assertSee('redacted');
    }

    public function test_detail_denied_for_user_outside_scope(): void
    {
        [$project] = $this->boundProject();
        $object = $this->object($project, ObjectType::FINDING, 'X');
        $outsider = User::factory()->create(['role' => 'regular']);

        $this->actingAs($outsider)->get(route('objects.show', $object))->assertForbidden();
    }

    public function test_restricted_neighbour_is_hidden_from_trace(): void
    {
        [$project, $pm] = $this->boundProject();
        $finding = $this->object($project, ObjectType::FINDING, 'Visible');
        $restricted = $this->object($project, ObjectType::DECISION, 'Hidden decision', 'restricted');
        app(TraceService::class)->link($finding, $restricted, RelationType::TRACES_TO);

        // PM is denied 'restricted' objects → the neighbour must not leak into the trace.
        $this->actingAs($pm)->get(route('objects.show', $finding))
            ->assertOk()
            ->assertDontSee($restricted->ref);
    }
}

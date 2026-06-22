<?php

declare(strict_types=1);

namespace Tests\Feature\Session;

use App\Enums\ObjectType;
use App\Models\Graph\EngObject;
use App\Models\Graph\TraceRelationship;
use App\Models\Portfolio\Module;
use App\Models\Portfolio\Session;
use App\Models\Portfolio\Tenant;
use App\Models\Project;
use App\Models\User;
use App\Services\Graph\ObjectGraphService;
use App\Services\Session\ConflictDetectionService;
use App\Services\Session\Exceptions\SessionEngineException;
use App\Services\Session\SessionEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConflictDetectionTest extends TestCase
{
    use RefreshDatabase;

    private function makeSession(string $phase = 'in_session'): array
    {
        $tenant = Tenant::default();
        $project = Project::create(['tenant_id' => $tenant->id, 'name' => 'P', 'code' => 'P', 'status' => 'active']);
        $module = Module::create(['project_id' => $project->id, 'name' => 'M', 'code' => 'M']);
        $stage = $module->stages()->where('stage', 'BRS')->firstOrFail();
        $session = Session::create([
            'stage_id' => $stage->id, 'module_id' => $module->id, 'project_id' => $project->id,
            'title' => 'S', 'status' => 'in_progress', 'phase' => $phase,
        ]);

        return [$session, $project];
    }

    private function requirement(Session $session, Project $project, string $title): EngObject
    {
        return app(ObjectGraphService::class)->create(
            ObjectType::BUSINESS_REQUIREMENT, $project->tenant_id, $project->id, $title,
            ['module_id' => $session->module_id, 'stage_id' => $session->stage_id, 'session_id' => $session->id],
        );
    }

    public function test_duplicate_requirements_are_flagged_as_conflict(): void
    {
        [$session, $project] = $this->makeSession();
        $a = $this->requirement($session, $project, 'Pay salary monthly');
        $b = $this->requirement($session, $project, 'Pay  Salary, monthly.'); // same normalized intent
        $this->requirement($session, $project, 'Support leave requests');     // unrelated

        $count = app(ConflictDetectionService::class)->scan($session);

        $this->assertSame(1, $count);
        $this->assertSame('conflict_detected', $a->fresh()->status->value);
        $this->assertSame('conflict_detected', $b->fresh()->status->value);

        $conflict = EngObject::where('session_id', $session->id)->where('type', 'conflict')->firstOrFail();
        $this->assertStringStartsWith('CONF-', $conflict->ref);
        $this->assertSame([$a->ref, $b->ref], $conflict->attributes['conflicting_refs']);
        $this->assertSame(2, TraceRelationship::where('from_object_id', $conflict->id)->where('relation_type', 'conflicts_with')->count());
    }

    public function test_no_duplicates_records_no_conflict(): void
    {
        [$session, $project] = $this->makeSession();
        $this->requirement($session, $project, 'Pay salary monthly');
        $this->requirement($session, $project, 'Support leave requests');

        $this->assertSame(0, app(ConflictDetectionService::class)->scan($session));
        $this->assertSame(0, EngObject::where('session_id', $session->id)->where('type', 'conflict')->count());
    }

    public function test_scan_is_idempotent(): void
    {
        [$session, $project] = $this->makeSession();
        $this->requirement($session, $project, 'Pay salary monthly');
        $this->requirement($session, $project, 'Pay salary monthly');

        $detector = app(ConflictDetectionService::class);
        $this->assertSame(1, $detector->scan($session));
        $this->assertSame(0, $detector->scan($session)); // already flagged → no new conflict
        $this->assertSame(1, EngObject::where('session_id', $session->id)->where('type', 'conflict')->count());
    }

    public function test_flagged_conflict_blocks_session_approval(): void
    {
        [$session, $project] = $this->makeSession('post_session');
        $this->requirement($session, $project, 'Pay salary monthly');
        $this->requirement($session, $project, 'Pay salary monthly');
        app(ConflictDetectionService::class)->scan($session);

        $pm = User::factory()->create(['role' => 'admin']); // admin bypasses ACL for the engine call

        $this->expectException(SessionEngineException::class);
        app(SessionEngineService::class)->approveSession($session->fresh(), $pm);
    }
}

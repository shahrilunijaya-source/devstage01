<?php

declare(strict_types=1);

namespace Tests\Feature\Verification;

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
use App\Services\Verification\RtmService;
use App\Services\Verification\VerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RtmTest extends TestCase
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

    private function object(Project $project, ObjectType $type, string $title): EngObject
    {
        return app(ObjectGraphService::class)->create($type, $project->tenant_id, $project->id, $title);
    }

    public function test_requirement_without_evidence_is_unsupported(): void
    {
        [$project] = $this->boundProject();
        $this->object($project, ObjectType::FUNCTIONAL_REQUIREMENT, 'Lonely requirement');

        $row = app(RtmService::class)->matrix($project)['rows']->first();
        $this->assertSame('unsupported', $row['status']);
    }

    public function test_supported_but_untested_requirement_is_unverified(): void
    {
        [$project] = $this->boundProject();
        $req = $this->object($project, ObjectType::FUNCTIONAL_REQUIREMENT, 'Supported requirement');
        $evidence = $this->object($project, ObjectType::EVIDENCE, 'Some evidence');
        app(TraceService::class)->link($evidence, $req, RelationType::DERIVED_FROM);

        $row = app(RtmService::class)->matrix($project)['rows']->first();
        $this->assertSame('unverified', $row['status']);
        $this->assertContains($evidence->ref, $row['support']->all());
    }

    public function test_fully_traced_requirement_when_evidence_and_passing_test(): void
    {
        [$project, $pm] = $this->boundProject();
        $req = $this->object($project, ObjectType::FUNCTIONAL_REQUIREMENT, 'Proven requirement');
        $evidence = $this->object($project, ObjectType::EVIDENCE, 'Backing evidence');
        app(TraceService::class)->link($evidence, $req, RelationType::DERIVED_FROM);

        $vv = app(VerificationService::class);
        $case = $vv->addTestCase($req, 'Prove it', null, $pm);
        $vv->recordResult($case, 'pass', null, $pm);

        $matrix = app(RtmService::class)->matrix($project);
        $this->assertSame('traced', $matrix['rows']->first()['status']);
        $this->assertSame(100.0, $matrix['summary']['traced_pct']);
    }

    public function test_open_defect_makes_requirement_broken(): void
    {
        [$project, $pm] = $this->boundProject();
        $req = $this->object($project, ObjectType::FUNCTIONAL_REQUIREMENT, 'Defective requirement');
        $evidence = $this->object($project, ObjectType::EVIDENCE, 'Backing evidence');
        app(TraceService::class)->link($evidence, $req, RelationType::DERIVED_FROM);

        $vv = app(VerificationService::class);
        $case = $vv->addTestCase($req, 'Prove it', null, $pm);
        $vv->recordResult($case, 'fail', null, $pm);
        $vv->raiseDefect($case, 'It broke', null, $pm);

        $matrix = app(RtmService::class)->matrix($project);
        $this->assertSame('broken', $matrix['rows']->first()['status']);
        $this->assertSame(1, $matrix['summary']['broken']);
    }

    public function test_trace_does_not_cross_project_boundary(): void
    {
        [$projectA] = $this->boundProject();
        $projectB = Project::create(['tenant_id' => Tenant::default()->id, 'name' => 'B', 'code' => 'B', 'status' => 'active']);

        $reqA = $this->object($projectA, ObjectType::FUNCTIONAL_REQUIREMENT, 'Requirement A');
        $evidenceB = $this->object($projectB, ObjectType::EVIDENCE, 'Foreign evidence');

        // A cross-project edge (its project_id resolves to B) must not be walked
        // when building project A's matrix.
        app(TraceService::class)->link($evidenceB, $reqA, RelationType::DERIVED_FROM);

        $row = app(RtmService::class)->matrix($projectA)['rows']->first();
        $this->assertNotContains($evidenceB->ref, $row['support']->all());
    }

    public function test_index_renders_for_pm(): void
    {
        [$project, $pm] = $this->boundProject();
        $this->object($project, ObjectType::FUNCTIONAL_REQUIREMENT, 'Renderable requirement');

        $this->actingAs($pm)->get(route('rtm.index', $project))
            ->assertOk()
            ->assertSee('Requirements traceability matrix')
            ->assertSee('Renderable requirement')
            ->assertSee('unsupported');
    }

    public function test_index_denied_outside_scope(): void
    {
        [$project] = $this->boundProject();
        $outsider = User::factory()->create(['role' => 'regular']);

        $this->actingAs($outsider)->get(route('rtm.index', $project))->assertForbidden();
    }

    public function test_csv_export_streams_matrix(): void
    {
        [$project, $pm] = $this->boundProject();
        $this->object($project, ObjectType::FUNCTIONAL_REQUIREMENT, 'Exportable requirement');

        $response = $this->actingAs($pm)->get(route('rtm.csv', $project));
        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));
        $this->assertStringContainsString('Exportable requirement', $response->streamedContent());
    }

    public function test_pdf_export_returns_pdf(): void
    {
        [$project, $pm] = $this->boundProject();
        $this->object($project, ObjectType::FUNCTIONAL_REQUIREMENT, 'Exportable requirement');

        $response = $this->actingAs($pm)->get(route('rtm.pdf', $project));
        $response->assertOk();
        $this->assertStringContainsString('application/pdf', (string) $response->headers->get('content-type'));
    }
}

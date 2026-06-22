<?php

declare(strict_types=1);

namespace Tests\Feature\Verification;

use App\Enums\ObjectType;
use App\Models\Acl\Role;
use App\Models\Acl\ScopeBinding;
use App\Models\Graph\EngObject;
use App\Models\Portfolio\Tenant;
use App\Models\Project;
use App\Models\User;
use App\Services\Graph\ObjectGraphService;
use App\Services\Verification\VerificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VerificationTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:Project,1:User} A project with a PM (view+validate). */
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

    private function requirement(Project $project, string $title = 'Salary paid by 25th'): EngObject
    {
        return app(ObjectGraphService::class)->create(
            ObjectType::FUNCTIONAL_REQUIREMENT, $project->tenant_id, $project->id, $title,
        );
    }

    public function test_requirement_with_no_test_case_is_unverified(): void
    {
        [$project] = $this->boundProject();
        $this->requirement($project);

        $register = app(VerificationService::class)->register($project);

        $this->assertSame(1, $register['summary']['requirements']);
        $this->assertSame(1, $register['summary']['unverified']);
        $this->assertSame('unverified', $register['rows']->first()['status']);
    }

    public function test_adding_test_case_makes_requirement_pending(): void
    {
        [$project, $pm] = $this->boundProject();
        $req = $this->requirement($project);

        $service = app(VerificationService::class);
        $service->addTestCase($req, 'Run a payroll cycle', null, $pm);

        $row = $service->register($project)['rows']->first();
        $this->assertSame('pending', $row['status']);
        $this->assertCount(1, $row['cases']);
    }

    public function test_passing_result_verifies_requirement(): void
    {
        [$project, $pm] = $this->boundProject();
        $req = $this->requirement($project);
        $service = app(VerificationService::class);
        $case = $service->addTestCase($req, 'Run a payroll cycle', null, $pm);

        $service->recordResult($case, 'pass', 'salary disbursed on the 25th', $pm);

        $register = $service->register($project);
        $this->assertSame('verified', $register['rows']->first()['status']);
        $this->assertSame(100.0, $register['summary']['pass_rate']);
    }

    public function test_failing_result_marks_requirement_failing(): void
    {
        [$project, $pm] = $this->boundProject();
        $req = $this->requirement($project);
        $service = app(VerificationService::class);
        $case = $service->addTestCase($req, 'Run a payroll cycle', null, $pm);

        $service->recordResult($case, 'fail', 'paid on the 27th', $pm);

        $this->assertSame('failing', $service->register($project)['rows']->first()['status']);
    }

    public function test_latest_result_wins_over_earlier_runs(): void
    {
        [$project, $pm] = $this->boundProject();
        $req = $this->requirement($project);
        $service = app(VerificationService::class);
        $case = $service->addTestCase($req, 'Run a payroll cycle', null, $pm);

        $service->recordResult($case, 'fail', 'first run failed', $pm);
        $service->recordResult($case, 'pass', 'fixed and re-run', $pm);

        $this->assertSame('verified', $service->register($project)['rows']->first()['status']);
    }

    public function test_invalid_outcome_is_rejected(): void
    {
        [$project, $pm] = $this->boundProject();
        $req = $this->requirement($project);
        $service = app(VerificationService::class);
        $case = $service->addTestCase($req, 'A case', null, $pm);

        $this->expectException(\InvalidArgumentException::class);
        $service->recordResult($case, 'maybe', null, $pm);
    }

    public function test_pm_can_add_test_case_via_http(): void
    {
        [$project, $pm] = $this->boundProject();
        $req = $this->requirement($project);

        $this->actingAs($pm)
            ->post(route('verification.test-cases.store', $req), ['title' => 'Verify disbursement date'])
            ->assertRedirect(route('verification.index', $project));

        $this->assertDatabaseHas('objects', ['type' => 'test_case', 'title' => 'Verify disbursement date']);
    }

    public function test_member_cannot_author_test_case(): void
    {
        [$project, $member] = $this->boundProject('project_member');
        $req = $this->requirement($project);

        $this->actingAs($member)
            ->post(route('verification.test-cases.store', $req), ['title' => 'Sneaky case'])
            ->assertForbidden();

        $this->assertDatabaseMissing('objects', ['type' => 'test_case']);
    }

    public function test_test_case_can_only_target_a_requirement(): void
    {
        [$project, $pm] = $this->boundProject();
        $evidence = app(ObjectGraphService::class)->create(
            ObjectType::EVIDENCE, $project->tenant_id, $project->id, 'Some evidence',
        );

        $this->actingAs($pm)
            ->post(route('verification.test-cases.store', $evidence), ['title' => 'Bad target'])
            ->assertNotFound();
    }

    public function test_index_denied_outside_scope(): void
    {
        [$project] = $this->boundProject();
        $outsider = User::factory()->create(['role' => 'regular']);

        $this->actingAs($outsider)->get(route('verification.index', $project))->assertForbidden();
    }

    public function test_index_renders_register_for_pm(): void
    {
        [$project, $pm] = $this->boundProject();
        $req = $this->requirement($project, 'Renderable requirement');
        app(VerificationService::class)->addTestCase($req, 'Renderable case', null, $pm);

        $this->actingAs($pm)->get(route('verification.index', $project))
            ->assertOk()
            ->assertSee('Verification')
            ->assertSee('Renderable requirement')
            ->assertSee('Renderable case')
            ->assertSee('pending');
    }

    public function test_csv_export_streams_matrix(): void
    {
        [$project, $pm] = $this->boundProject();
        $req = $this->requirement($project, 'Exportable requirement');
        $service = app(VerificationService::class);
        $case = $service->addTestCase($req, 'Exportable case', null, $pm);
        $service->recordResult($case, 'pass', null, $pm);

        $response = $this->actingAs($pm)->get(route('verification.csv', $project));
        $response->assertOk();
        $this->assertStringContainsString('text/csv', (string) $response->headers->get('content-type'));

        $csv = $response->streamedContent();
        $this->assertStringContainsString('Exportable requirement', $csv);
        $this->assertStringContainsString('Exportable case', $csv);
        $this->assertStringContainsString('pass', $csv);
    }
}

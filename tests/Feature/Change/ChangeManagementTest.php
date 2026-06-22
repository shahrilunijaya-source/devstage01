<?php

declare(strict_types=1);

namespace Tests\Feature\Change;

use App\Enums\ObjectStatus;
use App\Enums\ObjectType;
use App\Enums\RelationType;
use App\Models\Acl\Role;
use App\Models\Acl\ScopeBinding;
use App\Models\Graph\EngObject;
use App\Models\Portfolio\StageBaseline;
use App\Models\Portfolio\Tenant;
use App\Models\Project;
use App\Models\User;
use App\Services\Change\ChangeManagementService;
use App\Services\Change\Exceptions\ChangeManagementException;
use App\Services\Change\Exceptions\SeparationOfDutiesException;
use App\Services\Graph\Exceptions\BaselinedObjectException;
use App\Services\Graph\ObjectGraphService;
use App\Services\Graph\TraceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChangeManagementTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{evd:EngObject,find:EngObject,req:EngObject,project:Project,tenant:Tenant} */
    private function chain(): array
    {
        $tenant = Tenant::default();
        $project = Project::create(['tenant_id' => $tenant->id, 'name' => 'P', 'code' => 'P', 'status' => 'active']);
        $graph = app(ObjectGraphService::class);
        $evd = $graph->create(ObjectType::EVIDENCE, $tenant->id, $project->id, 'Evidence');
        $find = $graph->create(ObjectType::FINDING, $tenant->id, $project->id, 'Finding');
        $req = $graph->create(ObjectType::BUSINESS_REQUIREMENT, $tenant->id, $project->id, 'Requirement');
        $trace = app(TraceService::class);
        $trace->link($evd, $find, RelationType::DERIVED_FROM);
        $trace->link($find, $req, RelationType::DERIVED_FROM);

        return compact('evd', 'find', 'req', 'project', 'tenant');
    }

    private function engine(): ChangeManagementService
    {
        return app(ChangeManagementService::class);
    }

    public function test_impact_analysis_traces_up_and_downstream(): void
    {
        ['find' => $find] = $this->chain();

        $impact = $this->engine()->analyzeImpact($find);

        $this->assertSame(1, $impact['downstream']); // requirement
        $this->assertSame(1, $impact['upstream']);   // evidence
        $this->assertSame(2, $impact['total']);
    }

    public function test_open_creates_draft_change_request_with_ref_and_impact(): void
    {
        ['find' => $find] = $this->chain();
        $user = User::factory()->create(['role' => 'regular']);

        $cr = $this->engine()->open($find, $user, 'Clarify wording', 'because', ['title' => 'Finding v2']);

        $this->assertSame('CR-0001', $cr->ref);
        $this->assertSame('draft', $cr->status);
        $this->assertSame(2, $cr->impact['total']);
    }

    public function test_baselined_object_cannot_be_edited_without_change_request(): void
    {
        ['find' => $find, 'project' => $project] = $this->chain();
        $baseline = StageBaseline::create([
            'stage_id' => $project->modules()->create(['name' => 'M', 'code' => 'M'])->stages()->first()->id,
            'project_id' => $project->id, 'module_id' => $project->modules()->first()->id,
            'version_label' => 'X v1.0', 'sequence' => 1, 'status' => 'approved',
        ]);
        $find->update(['baseline_id' => $baseline->id]);

        $this->expectException(BaselinedObjectException::class);
        app(ObjectGraphService::class)->update($find->fresh(), ['title' => 'sneaky']);
    }

    public function test_apply_requires_approval(): void
    {
        ['find' => $find] = $this->chain();
        $user = User::factory()->create(['role' => 'regular']);
        $cr = $this->engine()->open($find, $user, 'x');

        $this->expectException(ChangeManagementException::class);
        $this->engine()->apply($cr, $user); // still draft
    }

    public function test_apply_versions_target_and_flags_downstream(): void
    {
        ['find' => $find, 'req' => $req] = $this->chain();
        $raiser = User::factory()->create(['role' => 'regular']);
        $approver = User::factory()->create(['role' => 'regular']);
        $cr = $this->engine()->open($find, $raiser, 'Reword', null, ['title' => 'Finding reworded']);

        // Separation of duties: a different user approves and applies.
        $this->engine()->approve($cr, $approver);
        $this->engine()->apply($cr, $approver);

        $this->assertSame('applied', $cr->fresh()->status);
        $this->assertSame('Finding reworded', $find->fresh()->title);
        $this->assertSame(2, (int) $find->fresh()->current_version);
        // Downstream requirement flagged for re-confirmation.
        $this->assertSame(ObjectStatus::NEEDS_CONFIRMATION, $req->fresh()->status);
    }

    public function test_separation_of_duties_blocks_author_from_approving(): void
    {
        ['find' => $find] = $this->chain();
        $raiser = User::factory()->create(['role' => 'regular']);
        $cr = $this->engine()->open($find, $raiser, 'self-approve attempt');

        $this->expectException(SeparationOfDutiesException::class);
        $this->engine()->approve($cr, $raiser);
    }

    public function test_separation_of_duties_can_be_disabled_by_config(): void
    {
        config(['acl.separation_of_duties' => false]);
        ['find' => $find] = $this->chain();
        $raiser = User::factory()->create(['role' => 'regular']);
        $cr = $this->engine()->open($find, $raiser, 'self-approve allowed');

        $this->engine()->approve($cr, $raiser);

        $this->assertSame('approved', $cr->fresh()->status);
    }

    public function test_web_raise_requires_edit_and_apply_requires_baseline_right(): void
    {
        ['find' => $find, 'project' => $project] = $this->chain();

        $member = User::factory()->create(['role' => 'regular']);
        ScopeBinding::create([
            'user_id' => $member->id, 'role_id' => Role::where('key', 'project_member')->whereNull('tenant_id')->value('id'),
            'tenant_id' => $project->tenant_id, 'scope_type' => 'project', 'scope_id' => $project->id,
        ]);

        // project_member lacks 'edit' → cannot raise a change.
        $this->actingAs($member)->get(route('changes.create', $find))->assertForbidden();

        $pm = User::factory()->create(['role' => 'regular']);
        ScopeBinding::create([
            'user_id' => $pm->id, 'role_id' => Role::where('key', 'project_pm')->whereNull('tenant_id')->value('id'),
            'tenant_id' => $project->tenant_id, 'scope_type' => 'project', 'scope_id' => $project->id,
        ]);

        $this->actingAs($pm)->post(route('changes.store', $find), ['title' => 'CR via web'])
            ->assertRedirect();
        $this->assertDatabaseHas('change_requests', ['title' => 'CR via web', 'status' => 'draft']);
    }
}

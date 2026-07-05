<?php

declare(strict_types=1);

namespace Tests\Feature\Session;

use App\Enums\ConfidenceLevel;
use App\Enums\ObjectStatus;
use App\Enums\ObjectType;
use App\Models\Acl\Role;
use App\Models\Acl\ScopeBinding;
use App\Models\Graph\EngObject;
use App\Models\Portfolio\Module;
use App\Models\Portfolio\Session;
use App\Models\Portfolio\Tenant;
use App\Models\Project;
use App\Models\User;
use App\Services\Graph\ObjectGraphService;
use App\Services\Session\Exceptions\SessionEngineException;
use App\Services\Session\SessionEngineService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SessionEngineTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:Session,1:EngObject,2:Project} */
    private function sessionWithEvidence(): array
    {
        $tenant = Tenant::default();
        $project = Project::create(['tenant_id' => $tenant->id, 'name' => 'P', 'code' => 'P', 'status' => 'active']);
        $module = Module::create(['project_id' => $project->id, 'name' => 'M', 'code' => 'M']);
        $stage = $module->stages()->where('stage', 'BRS')->firstOrFail();
        $session = Session::create([
            'stage_id' => $stage->id, 'module_id' => $module->id, 'project_id' => $project->id,
            'title' => 'S', 'status' => 'draft', 'phase' => 'pre_analysis',
        ]);
        $evd = app(ObjectGraphService::class)->create(ObjectType::EVIDENCE, $tenant->id, $project->id, 'Tender clause', [
            'module_id' => $module->id, 'stage_id' => $stage->id, 'session_id' => $session->id, 'body' => 'Pay by 25th.',
        ]);

        return [$session, $evd, $project];
    }

    private function engine(): SessionEngineService
    {
        return app(SessionEngineService::class);
    }

    public function test_pre_analysis_drafts_finding_and_requirement_and_advances_phase(): void
    {
        [$session, $evd] = $this->sessionWithEvidence();

        $drafted = $this->engine()->preAnalyze($session);

        $this->assertSame(2, $drafted);
        $this->assertSame('firewall_review', $session->fresh()->phase);
        $this->assertSame(1, EngObject::where('session_id', $session->id)->where('type', 'finding')->count());
        $this->assertSame(1, EngObject::where('session_id', $session->id)->where('type', 'business_requirement')->count());
        $this->assertTrue($evd->outgoingTraces()->exists());
    }

    public function test_pre_analysis_is_idempotent(): void
    {
        [$session] = $this->sessionWithEvidence();
        $this->engine()->preAnalyze($session);
        $second = $this->engine()->preAnalyze($session->fresh());

        $this->assertSame(0, $second);
    }

    public function test_firewall_only_passes_from_review_phase(): void
    {
        [$session] = $this->sessionWithEvidence();
        $reviewer = User::factory()->create(['role' => 'regular']);

        $this->expectException(SessionEngineException::class);
        $this->engine()->passFirewall($session, $reviewer); // still pre_analysis
    }

    public function test_firewall_pass_records_reviewer_and_sets_ready(): void
    {
        [$session] = $this->sessionWithEvidence();
        $this->engine()->preAnalyze($session);
        $reviewer = User::factory()->create(['role' => 'regular']);

        $this->engine()->passFirewall($session->fresh(), $reviewer);

        $fresh = $session->fresh();
        $this->assertSame('ready', $fresh->phase);
        $this->assertSame($reviewer->id, (int) $fresh->firewall_approved_by);
    }

    public function test_high_impact_low_confidence_cannot_be_quick_confirmed(): void
    {
        [$session] = $this->sessionWithEvidence();
        $user = User::factory()->create(['role' => 'regular']);
        $this->engine()->preAnalyze($session);
        $this->engine()->passFirewall($session->fresh(), $user);
        $this->engine()->startSession($session->fresh());

        // The drafted requirement is impact=high, confidence=low.
        $req = EngObject::where('session_id', $session->id)->where('type', 'business_requirement')->firstOrFail();

        $this->expectExceptionMessage('cannot be quick-confirmed');
        $this->engine()->capture($req, 'confirm', $user);
    }

    public function test_confirm_marks_finding_confirmed(): void
    {
        [$session] = $this->sessionWithEvidence();
        $user = User::factory()->create(['role' => 'regular']);
        $this->engine()->preAnalyze($session);
        $this->engine()->passFirewall($session->fresh(), $user);
        $this->engine()->startSession($session->fresh());

        $finding = EngObject::where('session_id', $session->id)->where('type', 'finding')->firstOrFail();
        $this->engine()->capture($finding, 'confirm', $user);

        $fresh = $finding->fresh();
        $this->assertSame(ObjectStatus::CONFIRMED_BY_EVIDENCE, $fresh->status);
        $this->assertSame(ConfidenceLevel::HIGH, $fresh->confidence);
        $this->assertSame(2, (int) $fresh->current_version); // create + confirm
    }

    public function test_decide_resolves_high_impact_low_confidence_item(): void
    {
        [$session] = $this->sessionWithEvidence();
        $user = User::factory()->create(['role' => 'regular']);
        $this->engine()->preAnalyze($session);
        $this->engine()->passFirewall($session->fresh(), $user);
        $this->engine()->startSession($session->fresh());
        $req = EngObject::where('session_id', $session->id)->where('type', 'business_requirement')->firstOrFail();

        $this->engine()->capture($req, 'decide', $user);

        $this->assertSame(ObjectStatus::CONFIRMED_BY_EVIDENCE, $req->fresh()->status);
    }

    public function test_session_page_requires_acl_access(): void
    {
        [$session, , $project] = $this->sessionWithEvidence();
        $stranger = User::factory()->create(['role' => 'regular']);
        $pm = User::factory()->create(['role' => 'regular']);
        ScopeBinding::create([
            'user_id' => $pm->id, 'role_id' => Role::where('key', 'project_pm')->whereNull('tenant_id')->value('id'),
            'tenant_id' => $project->tenant_id, 'scope_type' => 'project', 'scope_id' => $project->id,
        ]);

        $this->actingAs($stranger)->get(route('sessions.show', $session))->assertForbidden();
        $this->actingAs($pm)->get(route('sessions.show', $session))->assertOk()->assertSee($session->title);
    }

    public function test_firewall_route_requires_validate_right(): void
    {
        [$session, , $project] = $this->sessionWithEvidence();
        $this->engine()->preAnalyze($session);
        $member = User::factory()->create(['role' => 'regular']);
        ScopeBinding::create([
            'user_id' => $member->id, 'role_id' => Role::where('key', 'project_member')->whereNull('tenant_id')->value('id'),
            'tenant_id' => $project->tenant_id, 'scope_type' => 'project', 'scope_id' => $project->id,
        ]);

        // project_member lacks 'validate' → 403.
        $this->actingAs($member)->post(route('sessions.firewall', $session))->assertForbidden();
    }
}

<?php

namespace Database\Seeders;

use App\Enums\ObjectType;
use App\Models\Acl\Role;
use App\Models\Acl\ScopeBinding;
use App\Models\Graph\EngObject;
use App\Models\Knowledge\ProjectKnowledgeItem;
use App\Models\Portfolio\Module;
use App\Models\Portfolio\Session;
use App\Models\Portfolio\Tenant;
use App\Models\Project;
use App\Models\User;
use App\Services\Design\DesignService;
use App\Services\Graph\BaselineService;
use App\Services\Graph\ObjectGraphService;
use App\Services\Issue\IssueService;
use App\Services\Knowledge\KnowledgeResolver;
use App\Services\Knowledge\KnowledgeSeeder;
use App\Services\Portfolio\ObjectiveService;
use App\Services\Prototype\PrototypeService;
use App\Services\Session\SessionEngineService;
use App\Services\Verification\VerificationService;
use Illuminate\Database\Seeder;

/**
 * Self-contained demo slice for the /ursb verification dashboard. Idempotent:
 * only builds the Acme ERP project once.
 */
class UrsbDemoSeeder extends Seeder
{
    public function run(): void
    {
        app(KnowledgeSeeder::class)->seed();

        $tenant = Tenant::firstOrCreate(
            ['slug' => 'acme'],
            ['name' => 'Acme Agency', 'type' => 'agency', 'status' => 'active'],
        );

        $project = Project::where('tenant_id', $tenant->id)->where('code', 'ERP')->first();

        if ($project === null) {
            $project = $this->buildDemoProject($tenant);
        }

        $this->seedUsers($tenant, $project);
        $this->seedSecondProject();
    }

    /**
     * A second tenant + project in a different lifecycle state, so the portfolio
     * dashboard shows multiple projects with varied health and tenant isolation
     * is demonstrable (a PM bound to one tenant cannot see the other).
     */
    private function seedSecondProject(): void
    {
        $tenant = Tenant::firstOrCreate(
            ['slug' => 'petron-tl'],
            ['name' => 'Petron TL', 'type' => 'agency', 'status' => 'active'],
        );

        $project = Project::where('tenant_id', $tenant->id)->where('code', 'PTR')->first();

        if ($project === null) {
            $project = Project::create([
                'tenant_id' => $tenant->id, 'name' => 'Petron Retail Ops', 'code' => 'PTR', 'status' => 'active',
            ]);

            app(KnowledgeResolver::class)->pin($project, 'v2026.1');

            $module = Module::create(['project_id' => $project->id, 'name' => 'Station Ops', 'code' => 'STN']);
            if ($module->stages()->count() === 0) {
                $module->seedStages();
            }

            // Mid-flight: BRS underway, URS blocked → dashboard health "blocked".
            $module->stages()->where('stage', 'BRS')->update(['status' => 'in_progress', 'started_at' => now()]);
            $module->stages()->where('stage', 'URS')->update(['status' => 'blocked', 'started_at' => now()]);

            $brs = $module->stages()->where('stage', 'BRS')->firstOrFail();
            $session = Session::create([
                'stage_id' => $brs->id, 'module_id' => $module->id, 'project_id' => $project->id,
                'title' => 'Station BRS — Session 1', 'process' => 'Fuel reconciliation',
                'domain' => 'Retail', 'location' => 'Site 12', 'status' => 'draft', 'phase' => 'pre_analysis',
            ]);

            $graph = app(ObjectGraphService::class);
            $scope = ['module_id' => $module->id, 'stage_id' => $brs->id, 'session_id' => $session->id];
            $graph->create(ObjectType::EVIDENCE, $tenant->id, $project->id, 'Site survey notes', $scope + [
                'body' => 'Manual dip readings reconcile against pump totals nightly.',
            ]);
            $graph->create(ObjectType::RISK, $tenant->id, $project->id, 'Legacy POS lacks an export API', $scope + [
                'body' => 'Integration may require a manual nightly export until the POS is upgraded.',
                'impact' => 'high',
            ]);

            // Draft hypotheses but leave the session mid-review (not approved/baselined).
            app(SessionEngineService::class)->preAnalyze($session->fresh());

            // A failed verification with an open defect, so the V&V register on a
            // mid-flight project shows the defect loop in action.
            $requirement = EngObject::where('project_id', $project->id)
                ->where('type', ObjectType::BUSINESS_REQUIREMENT->value)
                ->orderBy('id')->first();

            if ($requirement !== null) {
                $approver = User::firstOrCreate(
                    ['email' => 'admin@ursb.test'],
                    ['name' => 'URSB Admin', 'role' => 'admin', 'password' => bcrypt('password'), 'email_verified_at' => now()],
                );
                $verification = app(VerificationService::class);
                $case = $verification->addTestCase($requirement, 'Reconcile a nightly fuel export', 'Export one night of POS totals; assert they match pump dips.', $approver);
                $verification->recordResult($case, 'fail', 'Legacy POS has no export API — reconciliation could not run.', $approver);
                $verification->raiseDefect($case, 'Legacy POS lacks an export API', 'Blocks automated nightly reconciliation; manual export needed until POS upgrade.', $approver);

                app(IssueService::class)->raise(
                    $project,
                    'POS vendor has not confirmed the upgrade timeline',
                    'Reconciliation automation is blocked until the POS export API ships.',
                    'high',
                    $approver,
                );
            }
        }

        $this->seedSecondUser($tenant, $project);
    }

    private function buildDemoProject(Tenant $tenant): Project
    {
        $project = Project::create([
            'tenant_id' => $tenant->id, 'name' => 'Acme ERP', 'code' => 'ERP', 'status' => 'active',
        ]);

        app(KnowledgeResolver::class)->pin($project, 'v2026.1');

        $module = Module::create(['project_id' => $project->id, 'name' => 'Payroll', 'code' => 'PAY']);
        // DatabaseSeeder runs WithoutModelEvents, so the Module::created hook that
        // auto-seeds stages may not fire — seed them explicitly (idempotent).
        if ($module->stages()->count() === 0) {
            $module->seedStages();
        }
        $brs = $module->stages()->where('stage', 'BRS')->firstOrFail();

        // Admin drives + signs off the session (idempotent; seedUsers reconciles the rest).
        $approver = User::firstOrCreate(
            ['email' => 'admin@ursb.test'],
            ['name' => 'URSB Admin', 'role' => 'admin', 'password' => bcrypt('password'), 'email_verified_at' => now()],
        );

        // Project Objective Baseline (spec §6): captured + approved so the demo
        // BRS gate and objective->requirement tracing are populated.
        $objectives = app(ObjectiveService::class);
        $objectives->capture($project, [
            'title' => 'Pay every Acme employee accurately and on time, every month',
            'business_problem' => 'Manual payroll runs breach the contractual 25th-of-month disbursement deadline roughly once a quarter, causing penalties and staff complaints.',
            'sponsor' => 'Acme CFO',
            'desired_outcome' => 'Automated payroll disbursed by the 25th with zero manual reconciliation.',
            'target_users' => 'HR payroll officers, finance approvers',
            'scope' => 'Salary processing, statutory deductions, disbursement gateway integration.',
            'out_of_scope' => 'Claims and expenses, recruitment.',
            'success_measures' => '12 consecutive on-time payroll runs; reconciliation effort under 1 person-day/month.',
        ], $approver);
        $objectives->approve($project, $approver);

        $session = Session::create([
            'stage_id' => $brs->id, 'module_id' => $module->id, 'project_id' => $project->id,
            'title' => 'Payroll BRS — Session 1', 'process' => 'Salary processing',
            'domain' => 'HR', 'location' => 'HQ', 'status' => 'draft', 'phase' => 'pre_analysis',
        ]);

        $graph = app(ObjectGraphService::class);
        $scope = ['module_id' => $module->id, 'stage_id' => $brs->id, 'session_id' => $session->id];

        // Evidence enters as immutable source; a risk is captured alongside it.
        $graph->create(ObjectType::EVIDENCE, $tenant->id, $project->id, 'Payroll tender document', $scope + [
            'body' => 'Tender clause 4.2 mandates monthly salary disbursement by the 25th.',
        ]);
        $graph->create(ObjectType::RISK, $tenant->id, $project->id, 'Vendor onboarding may slip the go-live', $scope + [
            'body' => 'Third-party payroll gateway integration depends on vendor onboarding lead time.',
            'impact' => 'medium',
        ]);

        // Run the five-phase lifecycle so the demo exercises every engine.
        $engine = app(SessionEngineService::class);
        $engine->preAnalyze($session->fresh());                  // AI drafts a finding + requirement
        $engine->passFirewall($session->fresh(), $approver);
        $engine->startSession($session->fresh());

        foreach (EngObject::where('session_id', $session->id)->get() as $item) {
            // High-impact requirements need a recorded decision; the rest are confirmed.
            $decision = $item->type === ObjectType::BUSINESS_REQUIREMENT ? 'decide' : 'confirm';
            $engine->capture($item, $decision, $approver);
        }

        $engine->consolidate($session->fresh());
        $engine->approveSession($session->fresh(), $approver);

        app(BaselineService::class)->baseline($brs->fresh(), [
            'knowledge_book_version' => 'v2026.1',
            'approved_by' => $approver->id,
        ]);

        // A couple of project-knowledge entries so the assumptions/lessons log
        // has demo content.
        ProjectKnowledgeItem::create([
            'tenant_id' => $tenant->id, 'project_id' => $project->id,
            'item_type' => 'approved_assumption', 'status' => 'approved', 'approved_by' => $approver->id,
            'title' => 'Payroll gateway vendor delivers the integration API by go-live.',
            'body' => 'Assumed during BRS; revisit if vendor onboarding slips.',
        ]);
        ProjectKnowledgeItem::create([
            'tenant_id' => $tenant->id, 'project_id' => $project->id,
            'item_type' => 'lesson_learned', 'status' => 'approved', 'approved_by' => $approver->id,
            'title' => 'Capture tender clauses as evidence before pre-analysis.',
            'body' => 'Earlier evidence capture produced cleaner AI-drafted requirements.',
        ]);

        // Verify a requirement end-to-end so the V&V register has live content:
        // one passing test case proves the requirement, the rest stay unverified.
        $requirement = EngObject::where('project_id', $project->id)
            ->where('type', ObjectType::BUSINESS_REQUIREMENT->value)
            ->orderBy('id')->first();

        if ($requirement !== null) {
            // Design that satisfies the requirement, so the design register and
            // the traceability matrix's design column have live content.
            app(DesignService::class)->addDesign(
                $requirement,
                'design_component',
                'Payroll disbursement scheduler',
                'A scheduled job that releases salary payments to land on or before the 25th.',
                $approver,
            );

            $verification = app(VerificationService::class);
            $case = $verification->addTestCase(
                $requirement,
                'Run a payroll cycle and confirm disbursement by the 25th',
                'Process a sample payroll batch; assert the disbursement date is on or before the 25th.',
                $approver,
            );
            $verification->recordResult($case, 'pass', 'Sample batch disbursed on the 24th.', $approver);

            // A demoed prototype element, so the prototype register shows the
            // full requirement → design → prototype → verification chain.
            $element = app(PrototypeService::class)->addElement(
                $requirement,
                'Disbursement scheduler prototype',
                'Clickable demo of the scheduled disbursement job and its calendar.',
                $approver,
            );
            app(PrototypeService::class)->setState($element, 'demoed', $approver);
        }

        return $project;
    }

    private function seedUsers(Tenant $tenant, Project $project): void
    {
        User::updateOrCreate(
            ['email' => 'admin@ursb.test'],
            ['name' => 'URSB Admin', 'role' => 'admin', 'system_role' => 'admin', 'password' => bcrypt('password'), 'email_verified_at' => now()],
        );

        User::updateOrCreate(
            ['email' => 'director@ursb.test'],
            ['name' => 'Acme Director', 'role' => 'regular', 'system_role' => 'director', 'password' => bcrypt('password'), 'email_verified_at' => now()],
        );

        $pm = User::updateOrCreate(
            ['email' => 'pm@ursb.test'],
            ['name' => 'Acme PM', 'role' => 'regular', 'system_role' => 'regular', 'password' => bcrypt('password'), 'email_verified_at' => now()],
        );

        // Bind the PM to the demo project so the ACL grants scoped access.
        ScopeBinding::firstOrCreate([
            'user_id' => $pm->id,
            'role_id' => Role::where('key', 'project_pm')->whereNull('tenant_id')->value('id'),
            'scope_type' => 'project',
            'scope_id' => $project->id,
        ], ['tenant_id' => $tenant->id]);
    }

    /** A PM scoped to the second tenant only — proves cross-tenant isolation. */
    private function seedSecondUser(Tenant $tenant, Project $project): void
    {
        $pm = User::updateOrCreate(
            ['email' => 'pm2@ursb.test'],
            ['name' => 'Petron PM', 'role' => 'regular', 'system_role' => 'regular', 'password' => bcrypt('password'), 'email_verified_at' => now()],
        );

        ScopeBinding::firstOrCreate([
            'user_id' => $pm->id,
            'role_id' => Role::where('key', 'project_pm')->whereNull('tenant_id')->value('id'),
            'scope_type' => 'project',
            'scope_id' => $project->id,
        ], ['tenant_id' => $tenant->id]);
    }
}

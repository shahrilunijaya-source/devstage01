<?php

namespace Database\Seeders;

use App\Enums\ObjectType;
use App\Models\Acl\Role;
use App\Models\Acl\ScopeBinding;
use App\Models\Graph\EngObject;
use App\Models\Portfolio\Module;
use App\Models\Portfolio\Session;
use App\Models\Portfolio\Tenant;
use App\Models\Project;
use App\Models\User;
use App\Services\Graph\BaselineService;
use App\Services\Graph\ObjectGraphService;
use App\Services\Knowledge\KnowledgeResolver;
use App\Services\Knowledge\KnowledgeSeeder;
use App\Services\Session\SessionEngineService;
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
}

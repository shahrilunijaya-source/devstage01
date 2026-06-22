<?php

namespace Database\Seeders;

use App\Enums\ObjectType;
use App\Enums\RelationType;
use App\Models\Acl\Role;
use App\Models\Acl\ScopeBinding;
use App\Models\Portfolio\Module;
use App\Models\Portfolio\Session;
use App\Models\Portfolio\Tenant;
use App\Models\Project;
use App\Models\User;
use App\Services\Graph\BaselineService;
use App\Services\Graph\ObjectGraphService;
use App\Services\Graph\TraceService;
use App\Services\Knowledge\KnowledgeResolver;
use App\Services\Knowledge\KnowledgeSeeder;
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

        $session = Session::create([
            'stage_id' => $brs->id, 'module_id' => $module->id, 'project_id' => $project->id,
            'title' => 'Payroll BRS — Session 1', 'process' => 'Salary processing',
            'domain' => 'HR', 'location' => 'HQ', 'status' => 'approved',
        ]);

        $graph = app(ObjectGraphService::class);
        $scope = ['module_id' => $module->id, 'stage_id' => $brs->id, 'session_id' => $session->id];

        $evd = $graph->create(ObjectType::EVIDENCE, $tenant->id, $project->id, 'Payroll tender document', $scope + [
            'body' => 'Tender clause 4.2 mandates monthly salary disbursement by the 25th.',
        ]);
        $find = $graph->create(ObjectType::FINDING, $tenant->id, $project->id, 'Salary must disburse by the 25th', $scope);
        $req = $graph->create(ObjectType::BUSINESS_REQUIREMENT, $tenant->id, $project->id, 'System shall disburse salaries by the 25th of each month', $scope);

        $trace = app(TraceService::class);
        $trace->link($evd, $find, RelationType::DERIVED_FROM);
        $trace->link($find, $req, RelationType::DERIVED_FROM);

        // Admin signs off the baseline (idempotent; seedUsers reconciles the rest).
        $approver = User::firstOrCreate(
            ['email' => 'admin@ursb.test'],
            ['name' => 'URSB Admin', 'role' => 'admin', 'password' => bcrypt('password'), 'email_verified_at' => now()],
        );

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

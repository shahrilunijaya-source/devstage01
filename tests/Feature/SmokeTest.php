<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Graph\EngObject;
use App\Models\Portfolio\Session;
use App\Models\Portfolio\StageBaseline;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\UrsbDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * End-to-end smoke test over the seeded demo: every authenticated page an admin
 * can reach must render (200). Guards against broken Blade / missing route /
 * controller regressions across the whole product surface.
 */
class SmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_project_page_renders_for_admin(): void
    {
        $this->seed(UrsbDemoSeeder::class);

        $admin = User::where('email', 'admin@ursb.test')->firstOrFail();
        $project = Project::where('code', 'ERP')->firstOrFail();
        $session = Session::where('project_id', $project->id)->firstOrFail();
        $baseline = StageBaseline::whereHas('stage', fn ($q) => $q->where('project_id', $project->id))->first();

        $projectRoutes = [
            'portfolio.index', 'portfolio.dashboard', 'portfolio.blocked',
        ];
        foreach ($projectRoutes as $name) {
            $this->actingAs($admin)->get(route($name))->assertOk();
        }

        $projectScoped = [
            'portfolio.show', 'portfolio.team', 'objects.index', 'knowledge.show',
            'project-knowledge.index', 'metrics.show', 'metrics.coverage', 'metrics.activity',
            'metrics.decisions', 'metrics.risks', 'design.index', 'prototype.index',
            'verification.index', 'rtm.index', 'issues.index', 'changes.index',
        ];
        foreach ($projectScoped as $name) {
            $this->actingAs($admin)->get(route($name, $project))->assertOk();
        }

        // Exports.
        foreach (['metrics.risks.csv', 'metrics.decisions.csv', 'verification.csv', 'rtm.csv', 'rtm.pdf'] as $name) {
            $this->actingAs($admin)->get(route($name, $project))->assertOk();
        }

        // Object-scoped + session + baseline pages.
        $object = EngObject::where('project_id', $project->id)->firstOrFail();
        $this->actingAs($admin)->get(route('objects.show', $object))->assertOk();
        $this->actingAs($admin)->get(route('sessions.show', $session))->assertOk();

        if ($baseline !== null) {
            $this->actingAs($admin)->get(route('baselines.show', $baseline))->assertOk();
            $this->actingAs($admin)->get(route('baselines.deck', $baseline))->assertOk();
        }
    }

    public function test_admin_console_pages_render(): void
    {
        $this->seed(UrsbDemoSeeder::class);
        $admin = User::where('email', 'admin@ursb.test')->firstOrFail();

        foreach (['admin.acl.index', 'admin.acl.audit', 'admin.settings.index', 'inbox', 'notifications.index'] as $name) {
            $this->actingAs($admin)->get(route($name))->assertOk();
        }
    }
}

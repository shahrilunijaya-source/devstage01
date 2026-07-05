<?php

declare(strict_types=1);

namespace Tests\Feature\Session;

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
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaptureTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:Session,1:User,2:EngObject} in-session + bound pm + one requirement */
    private function setup_(): array
    {
        $tenant = Tenant::default();
        $project = Project::create(['tenant_id' => $tenant->id, 'name' => 'P', 'code' => 'P', 'status' => 'active']);
        $module = Module::create(['project_id' => $project->id, 'name' => 'M', 'code' => 'M']);
        $stage = $module->stages()->where('stage', 'BRS')->firstOrFail();
        $session = Session::create([
            'stage_id' => $stage->id, 'module_id' => $module->id, 'project_id' => $project->id,
            'title' => 'S', 'status' => 'in_progress', 'phase' => 'in_session',
        ]);
        $req = app(ObjectGraphService::class)->create(
            ObjectType::BUSINESS_REQUIREMENT, $tenant->id, $project->id, 'Original title',
            ['module_id' => $module->id, 'stage_id' => $stage->id, 'session_id' => $session->id, 'body' => 'Original body'],
        );

        $pm = User::factory()->create(['role' => 'regular']);
        ScopeBinding::create([
            'user_id' => $pm->id, 'role_id' => Role::where('key', 'project_pm')->whereNull('tenant_id')->value('id'),
            'tenant_id' => $tenant->id, 'scope_type' => 'project', 'scope_id' => $project->id,
        ]);

        return [$session, $pm, $req];
    }

    public function test_correct_applies_revised_title_and_body(): void
    {
        [$session, $pm, $req] = $this->setup_();

        $this->actingAs($pm)->post(route('sessions.capture', [$session, $req]), [
            'decision' => 'correct', 'title' => 'Revised title', 'body' => 'Revised body',
        ])->assertRedirect(route('sessions.show', $session));

        $req->refresh();
        $this->assertSame('Revised title', $req->title);
        $this->assertSame('Revised body', $req->body);
        $this->assertSame('confirmed_by_evidence', $req->status->value);
        $this->assertSame(2, $req->current_version); // edit appended an immutable version
    }

    public function test_confirm_ignores_text_fields_and_keeps_original(): void
    {
        [$session, $pm, $req] = $this->setup_();

        $this->actingAs($pm)->post(route('sessions.capture', [$session, $req]), [
            'decision' => 'confirm', 'title' => 'Should be ignored',
        ])->assertRedirect();

        $req->refresh();
        $this->assertSame('Original title', $req->title);
        $this->assertSame('confirmed_by_evidence', $req->status->value);
    }

    public function test_capture_requires_edit_scope(): void
    {
        [$session, , $req] = $this->setup_();
        $outsider = User::factory()->create(['role' => 'regular']);

        $this->actingAs($outsider)->post(route('sessions.capture', [$session, $req]), [
            'decision' => 'confirm',
        ])->assertForbidden();
    }
}

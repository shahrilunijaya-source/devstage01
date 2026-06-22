<?php

declare(strict_types=1);

namespace Tests\Feature\Session;

use App\Models\Acl\Role;
use App\Models\Acl\ScopeBinding;
use App\Models\Graph\EngObject;
use App\Models\Graph\ObjectVersion;
use App\Models\Portfolio\Module;
use App\Models\Portfolio\Session;
use App\Models\Portfolio\Tenant;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class EvidenceIntakeTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:Session,1:Project,2:User} pre-analysis session + bound pm */
    private function makeSession(): array
    {
        $tenant = Tenant::default();
        $project = Project::create(['tenant_id' => $tenant->id, 'name' => 'Acme ERP', 'code' => 'ERP', 'status' => 'active']);
        $module = Module::create(['project_id' => $project->id, 'name' => 'Payroll', 'code' => 'PAY']);
        $stage = $module->stages()->where('stage', 'BRS')->firstOrFail();
        $session = Session::create([
            'stage_id' => $stage->id, 'module_id' => $module->id, 'project_id' => $project->id,
            'title' => 'S', 'status' => 'draft', 'phase' => 'pre_analysis',
        ]);

        $pm = User::factory()->create(['role' => 'regular']);
        ScopeBinding::create([
            'user_id' => $pm->id, 'role_id' => Role::where('key', 'project_pm')->whereNull('tenant_id')->value('id'),
            'tenant_id' => $tenant->id, 'scope_type' => 'project', 'scope_id' => $project->id,
        ]);

        return [$session, $project, $pm];
    }

    public function test_pasted_text_becomes_an_immutable_confirmed_evidence_object(): void
    {
        [$session, $project, $pm] = $this->makeSession();

        $this->actingAs($pm)->post(route('sessions.evidence.store', $session), [
            'label' => 'Interview notes', 'source_type' => 'paste', 'text' => 'Payroll runs monthly.',
        ])->assertRedirect(route('sessions.show', $session));

        $evidence = EngObject::where('session_id', $session->id)->where('type', 'evidence')->firstOrFail();
        $this->assertSame('Interview notes', $evidence->title);
        $this->assertSame('Payroll runs monthly.', $evidence->body);
        $this->assertSame('confirmed_by_evidence', $evidence->status->value);
        $this->assertSame('upload', $evidence->source);
        $this->assertStringStartsWith('EVD-', $evidence->ref);

        // Immutable v1 snapshot exists.
        $this->assertSame(1, ObjectVersion::where('object_id', $evidence->id)->count());
    }

    public function test_uploaded_file_is_stored_and_referenced_in_attributes(): void
    {
        Storage::fake('local');
        [$session, $project, $pm] = $this->makeSession();

        $this->actingAs($pm)->post(route('sessions.evidence.store', $session), [
            'label' => 'Spec PDF', 'source_type' => 'file',
            'file' => UploadedFile::fake()->create('spec.pdf', 12, 'application/pdf'),
        ])->assertRedirect();

        $evidence = EngObject::where('session_id', $session->id)->where('type', 'evidence')->firstOrFail();
        $this->assertSame('spec.pdf', $evidence->attributes['filename']);
        $this->assertSame('file', $evidence->attributes['kind']);
        Storage::disk('local')->assertExists($evidence->attributes['path']);
    }

    public function test_evidence_rejected_outside_pre_analysis_phase(): void
    {
        [$session, , $pm] = $this->makeSession();
        $session->update(['phase' => 'in_session']);

        $this->actingAs($pm)->post(route('sessions.evidence.store', $session), [
            'label' => 'Late', 'source_type' => 'paste', 'text' => 'too late',
        ])->assertSessionHas('error');

        $this->assertSame(0, EngObject::where('session_id', $session->id)->count());
    }

    public function test_evidence_denied_for_user_without_edit_scope(): void
    {
        [$session] = $this->makeSession();
        $outsider = User::factory()->create(['role' => 'regular']);

        $this->actingAs($outsider)->post(route('sessions.evidence.store', $session), [
            'label' => 'X', 'source_type' => 'paste', 'text' => 'x',
        ])->assertForbidden();
    }
}

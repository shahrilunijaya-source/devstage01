<?php

declare(strict_types=1);

namespace Tests\Feature\AccessControl;

use App\Models\Acl\Role;
use App\Models\Acl\ScopeBinding;
use App\Models\Portfolio\Tenant;
use App\Models\Project;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\AccessControl\PolicyDecisionPoint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Testing\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Regression cover for the security-review hardening pass: single role authority,
 * admin-only inspector, route-level admin guard, and secret-at-rest encryption.
 */
class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function pdp(): PolicyDecisionPoint
    {
        return app(PolicyDecisionPoint::class);
    }

    /** system_role is authoritative; it must win over the legacy `role` column. */
    public function test_system_role_overrides_legacy_role_for_admin_bypass(): void
    {
        $project = Project::create(['tenant_id' => Tenant::default()->id, 'name' => 'P', 'code' => 'P', 'status' => 'active']);

        // Legacy column says admin, but the authoritative system_role demotes them.
        $demoted = User::factory()->create(['role' => 'admin', 'system_role' => 'regular']);
        $this->assertFalse($demoted->isAdmin());
        $this->assertFalse($this->pdp()->can($demoted, 'view', $project)->permitted);

        // Legacy fallback still works when system_role is unset.
        $legacyAdmin = User::factory()->create(['role' => 'admin']);
        $this->assertTrue($legacyAdmin->isAdmin());
        $this->assertTrue($this->pdp()->can($legacyAdmin, 'view', $project)->permitted);
    }

    public function test_ursb_inspector_is_admin_only(): void
    {
        $project = Project::create(['tenant_id' => Tenant::default()->id, 'name' => 'P', 'code' => 'P', 'status' => 'active']);
        $pm = User::factory()->create(['system_role' => 'regular']);
        ScopeBinding::create([
            'user_id' => $pm->id, 'role_id' => Role::where('key', 'project_pm')->whereNull('tenant_id')->value('id'),
            'tenant_id' => $project->tenant_id, 'scope_type' => 'project', 'scope_id' => $project->id,
        ]);

        $this->actingAs($pm)->get(route('ursb.dashboard'))->assertForbidden();
        $this->actingAs(User::factory()->create(['system_role' => 'admin']))
            ->get(route('ursb.dashboard'))->assertOk();
    }

    public function test_admin_settings_route_is_guarded_for_non_admin(): void
    {
        $this->actingAs(User::factory()->create(['system_role' => 'regular']))
            ->get(route('admin.settings.index'))->assertForbidden();
    }

    public function test_secret_settings_are_encrypted_at_rest(): void
    {
        SystemSetting::set('anthropic_api_key', 'sk-ant-secret-value');

        // Stored ciphertext must not contain the plaintext...
        $raw = DB::table('system_settings')->where('key', 'anthropic_api_key')->value('value');
        $this->assertNotSame('sk-ant-secret-value', $raw);
        $this->assertStringNotContainsString('sk-ant-secret-value', (string) $raw);

        // ...but reading it back returns the plaintext transparently.
        $this->assertSame('sk-ant-secret-value', SystemSetting::get('anthropic_api_key'));
    }

    public function test_evidence_upload_rejects_executable_files(): void
    {
        $rules = ['file' => ['required_if:source_type,file', 'nullable', 'file', 'max:10240',
            'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,md,jpg,jpeg,png,gif,webp']];

        $php = File::create('payload.php', 1);
        $validator = Validator::make(['file' => $php, 'source_type' => 'file'], $rules);
        $this->assertTrue($validator->fails(), 'A .php evidence upload must be rejected by the mime allow-list.');
    }
}

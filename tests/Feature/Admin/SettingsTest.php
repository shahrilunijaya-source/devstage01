<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_system_setting_get_set_roundtrip(): void
    {
        $this->assertSame('fallback', SystemSetting::get('missing', 'fallback'));

        SystemSetting::set('foo', 'bar');
        $this->assertSame('bar', SystemSetting::get('foo'));

        SystemSetting::set('foo', 'baz'); // update, not duplicate
        $this->assertSame('baz', SystemSetting::get('foo'));
        $this->assertSame(1, SystemSetting::where('key', 'foo')->count());
    }

    public function test_admin_can_view_settings(): void
    {
        $this->actingAs($this->admin())->get(route('admin.settings.index'))
            ->assertOk()
            ->assertSee('Platform settings')
            ->assertSee('Anthropic API key');
    }

    public function test_non_admin_is_forbidden(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'regular']))
            ->get(route('admin.settings.index'))->assertForbidden();
    }

    public function test_saving_keys_and_flag_persists(): void
    {
        $this->actingAs($this->admin())->post(route('admin.settings.update'), [
            'anthropic_api_key' => 'sk-ant-test',
            'voyage_api_key' => 'pa-test',
            'rag_enabled' => '1',
        ])->assertRedirect(route('admin.settings.index'));

        $this->assertSame('sk-ant-test', SystemSetting::get('anthropic_api_key'));
        $this->assertSame('pa-test', SystemSetting::get('voyage_api_key'));
        $this->assertSame('1', SystemSetting::get('rag_enabled'));
    }

    public function test_blank_secret_keeps_existing_value(): void
    {
        SystemSetting::set('anthropic_api_key', 'existing-key');

        $this->actingAs($this->admin())->post(route('admin.settings.update'), [
            'anthropic_api_key' => '', // blank → keep
            'rag_enabled' => '1',
        ])->assertRedirect();

        $this->assertSame('existing-key', SystemSetting::get('anthropic_api_key'));
    }

    public function test_unchecking_flag_disables_it(): void
    {
        SystemSetting::set('rag_enabled', '1');

        $this->actingAs($this->admin())->post(route('admin.settings.update'), [
            // rag_enabled omitted → hidden field default '0'
            'rag_enabled' => '0',
        ])->assertRedirect();

        $this->assertSame('0', SystemSetting::get('rag_enabled'));
    }
}

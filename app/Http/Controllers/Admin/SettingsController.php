<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SystemSetting;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Platform settings console (admin-only). Stores API keys + feature flags in the
 * SystemSetting store. Secrets are never rendered back to the page — the form
 * shows only whether each key is set and updates a key only when a new value is
 * submitted.
 */
class SettingsController extends Controller
{
    /** Secret keys: shown as set/unset, write-only. */
    private const SECRETS = ['anthropic_api_key', 'voyage_api_key'];

    public function index(Request $request): View
    {
        $this->authorizeAdmin($request);

        return view('admin.settings', [
            'isSet' => collect(self::SECRETS)->mapWithKeys(
                fn (string $key): array => [$key => filled(SystemSetting::get($key))],
            ),
            'ragEnabled' => filter_var(SystemSetting::get('rag_enabled', false), FILTER_VALIDATE_BOOL),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $data = $request->validate([
            'anthropic_api_key' => ['nullable', 'string', 'max:255'],
            'voyage_api_key' => ['nullable', 'string', 'max:255'],
            'rag_enabled' => ['nullable', 'boolean'],
        ]);

        // Only overwrite a secret when a new non-empty value is supplied.
        foreach (self::SECRETS as $key) {
            if (filled($data[$key] ?? null)) {
                SystemSetting::set($key, trim($data[$key]));
            }
        }

        SystemSetting::set('rag_enabled', $request->boolean('rag_enabled') ? '1' : '0');

        return redirect()->route('admin.settings.index')->with('status', 'Settings saved.');
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()->isAdmin(), 403, 'Administrators only.');
    }
}

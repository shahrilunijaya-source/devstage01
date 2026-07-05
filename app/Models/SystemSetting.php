<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Key-value store for runtime platform settings (API keys, feature flags). Read
 * via SystemSetting::get(), written via SystemSetting::set(). Secrets are kept
 * here, never in code or committed config — and stored encrypted at rest so a
 * DB read or backup never exposes a live API key.
 */
class SystemSetting extends Model
{
    protected $fillable = ['key', 'value'];

    /** Keys whose values are encrypted at rest (app-key sealed). */
    private const ENCRYPTED = ['anthropic_api_key', 'voyage_api_key'];

    /** Read a setting, returning $default when it is unset. */
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = static::query()->where('key', $key)->value('value');

        if ($value === null) {
            return $default;
        }

        if (in_array($key, self::ENCRYPTED, true)) {
            try {
                return Crypt::decryptString($value);
            } catch (DecryptException) {
                return $value; // legacy plaintext written before encryption — return as-is
            }
        }

        return $value;
    }

    /** Create or update a setting (secrets are encrypted before write). */
    public static function set(string $key, mixed $value): void
    {
        if (in_array($key, self::ENCRYPTED, true) && $value !== null && $value !== '') {
            $value = Crypt::encryptString((string) $value);
        }

        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}

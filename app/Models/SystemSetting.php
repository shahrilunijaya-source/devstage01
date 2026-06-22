<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Key-value store for runtime platform settings (API keys, feature flags). Read
 * via SystemSetting::get(), written via SystemSetting::set(). Secrets are kept
 * here, never in code or committed config.
 */
class SystemSetting extends Model
{
    protected $fillable = ['key', 'value'];

    /** Read a setting, returning $default when it is unset. */
    public static function get(string $key, mixed $default = null): mixed
    {
        $value = static::query()->where('key', $key)->value('value');

        return $value ?? $default;
    }

    /** Create or update a setting. */
    public static function set(string $key, mixed $value): void
    {
        static::query()->updateOrCreate(['key' => $key], ['value' => $value]);
    }
}

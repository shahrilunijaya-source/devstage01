<?php

declare(strict_types=1);

namespace App\Models\Portfolio;

use App\Models\Project;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One Client / Agency = One Tenant (PRD §5). Top of the portfolio hierarchy
 * and the hard isolation boundary for knowledge retrieval and ACL.
 */
class Tenant extends Model
{
    protected $fillable = ['name', 'slug', 'type', 'status', 'settings'];

    protected $casts = ['settings' => 'array'];

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    /** The seeded fallback tenant used for single-tenant operation. */
    public static function default(): self
    {
        return static::firstOrCreate(
            ['slug' => 'default'],
            ['name' => 'Default Tenant', 'type' => 'agency', 'status' => 'active'],
        );
    }
}

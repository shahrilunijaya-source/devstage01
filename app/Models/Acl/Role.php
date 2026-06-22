<?php

declare(strict_types=1);

namespace App\Models\Acl;

use App\Models\Portfolio\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A named action-set (PRD §6.3). tenant_id null = global role template.
 */
class Role extends Model
{
    protected $table = 'acl_roles';

    protected $fillable = ['tenant_id', 'key', 'name', 'description', 'is_system'];

    protected $casts = ['is_system' => 'boolean'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'acl_role_permission')
            ->withPivot('effect')
            ->withTimestamps();
    }
}

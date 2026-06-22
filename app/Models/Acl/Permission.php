<?php

declare(strict_types=1);

namespace App\Models\Acl;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * An atomic action × object_type permission (PRD §6.1).
 */
class Permission extends Model
{
    protected $table = 'acl_permissions';

    protected $fillable = ['action', 'object_type', 'key', 'description'];

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'acl_role_permission')->withPivot('effect');
    }

    public static function makeKey(string $action, string $objectType): string
    {
        return $action.':'.$objectType;
    }
}

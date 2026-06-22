<?php

declare(strict_types=1);

namespace App\Models\Acl;

use App\Models\Portfolio\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Binds a role to a user at a scope node (PRD §6.3), optionally time-bound and
 * revocable.
 */
class ScopeBinding extends Model
{
    protected $table = 'acl_scope_bindings';

    protected $fillable = [
        'user_id', 'role_id', 'tenant_id', 'scope_type', 'scope_id',
        'granted_by', 'starts_at', 'ends_at', 'revoked_at',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /** Bindings currently in force: not revoked and within their time window. */
    public function scopeActive(Builder $query): Builder
    {
        $now = now();

        return $query->whereNull('revoked_at')
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', $now));
    }
}

<?php

declare(strict_types=1);

namespace App\Models\Acl;

use App\Models\Portfolio\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A time-bound grant of access from a delegator to a delegate (PRD §6.3).
 * Owns the scope binding it spawned so revocation withdraws both together.
 */
class Delegation extends Model
{
    protected $table = 'acl_delegations';

    protected $fillable = [
        'delegator_id', 'delegate_id', 'role_id', 'tenant_id', 'scope_type',
        'scope_id', 'scope_binding_id', 'reason', 'starts_at', 'ends_at',
        'revoked_at', 'created_by',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    public function delegator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegator_id');
    }

    public function delegate(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegate_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function binding(): BelongsTo
    {
        return $this->belongsTo(ScopeBinding::class, 'scope_binding_id');
    }

    /** Delegations in force: not revoked and within their window. */
    public function scopeActive(Builder $query): Builder
    {
        $now = now();

        return $query->whereNull('revoked_at')
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where('ends_at', '>', $now);
    }

    /** Past their end date but not yet revoked — candidates for sweep. */
    public function scopeDue(Builder $query): Builder
    {
        return $query->whereNull('revoked_at')->where('ends_at', '<=', now());
    }
}

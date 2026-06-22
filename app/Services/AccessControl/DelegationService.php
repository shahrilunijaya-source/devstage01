<?php

declare(strict_types=1);

namespace App\Services\AccessControl;

use App\Models\Acl\Delegation;
use App\Models\Acl\ScopeBinding;
use App\Models\User;
use App\Services\AccessControl\Exceptions\DelegationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Time-bound delegation of access (PRD §6.3). A delegator may grant a delegate
 * a role over a scope the delegator already holds, for a bounded window. The
 * delegation spawns and owns an auto-expiring scope binding.
 */
class DelegationService
{
    /**
     * Delegate a role at a scope from delegator to delegate until $endsAt.
     *
     * @throws DelegationException when the delegator lacks covering access,
     *                             or the window is invalid (PRD §6.3).
     */
    public function delegate(
        User $delegator,
        User $delegate,
        int $roleId,
        string $scopeType,
        ?int $scopeId,
        int $tenantId,
        Carbon $endsAt,
        ?Carbon $startsAt = null,
        ?string $reason = null,
    ): Delegation {
        $startsAt ??= now();

        if ($endsAt->lessThanOrEqualTo($startsAt)) {
            throw new DelegationException('Delegation end must be after its start.');
        }

        if ($delegator->id === $delegate->id) {
            throw new DelegationException('A user cannot delegate access to themselves.');
        }

        // Cannot delegate what you do not hold (admins always may).
        if ($delegator->role !== 'admin' && ! $this->holds($delegator, $scopeType, $scopeId, $tenantId)) {
            throw new DelegationException('Delegator does not hold covering access for this scope.');
        }

        return DB::transaction(function () use ($delegator, $delegate, $roleId, $scopeType, $scopeId, $tenantId, $startsAt, $endsAt, $reason): Delegation {
            $binding = ScopeBinding::create([
                'user_id' => $delegate->id,
                'role_id' => $roleId,
                'tenant_id' => $tenantId,
                'scope_type' => $scopeType,
                'scope_id' => $scopeId,
                'granted_by' => $delegator->id,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
            ]);

            return Delegation::create([
                'delegator_id' => $delegator->id,
                'delegate_id' => $delegate->id,
                'role_id' => $roleId,
                'tenant_id' => $tenantId,
                'scope_type' => $scopeType,
                'scope_id' => $scopeId,
                'scope_binding_id' => $binding->id,
                'reason' => $reason,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'created_by' => $delegator->id,
            ]);
        });
    }

    /** Withdraw a delegation and its spawned binding immediately. */
    public function revoke(Delegation $delegation): void
    {
        DB::transaction(function () use ($delegation): void {
            $now = now();

            if ($delegation->binding !== null && $delegation->binding->revoked_at === null) {
                $delegation->binding->update(['revoked_at' => $now]);
            }

            $delegation->update(['revoked_at' => $now]);
        });
    }

    /**
     * Sweep delegations past their end date: revoke each and its binding so the
     * audit shows an explicit terminal state (PRD §6.3). Returns the count.
     */
    public function expireDue(): int
    {
        $count = 0;

        Delegation::due()->with('binding')->get()->each(function (Delegation $delegation) use (&$count): void {
            $this->revoke($delegation);
            $count++;
        });

        return $count;
    }

    /** True when the user holds an active binding covering the target scope. */
    private function holds(User $user, string $scopeType, ?int $scopeId, int $tenantId): bool
    {
        return ScopeBinding::active()
            ->where('user_id', $user->id)
            ->where('tenant_id', $tenantId)
            ->get()
            ->contains(fn (ScopeBinding $b): bool => $this->covers($b, $scopeType, $scopeId));
    }

    /** A tenant binding covers anything in the tenant; else exact scope match. */
    private function covers(ScopeBinding $binding, string $scopeType, ?int $scopeId): bool
    {
        if ($binding->scope_type === 'tenant') {
            return true;
        }

        return $binding->scope_type === $scopeType && (int) $binding->scope_id === (int) $scopeId;
    }
}

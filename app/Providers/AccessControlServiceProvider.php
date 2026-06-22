<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use App\Services\AccessControl\PolicyDecisionPoint;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

/**
 * Wires the Policy Decision Point into Laravel's Gate (PRD §6.2). To guarantee
 * zero regression, Gate::before only governs the net-new ACL abilities; every
 * pre-existing ability falls through to the 12 existing policies (abstain).
 * Engine services and RAG call the PDP directly for the full action vocabulary.
 */
class AccessControlServiceProvider extends ServiceProvider
{
    /** Abilities the PDP owns at the Gate layer (no collision with existing policies). */
    private const GATE_OWNED = ['retrieve', 'validate', 'baseline'];

    public function register(): void
    {
        $this->app->singleton(PolicyDecisionPoint::class);
    }

    public function boot(): void
    {
        Gate::before(function (User $user, string $ability, array $arguments = []) {
            if (! in_array($ability, self::GATE_OWNED, true)) {
                return null; // abstain — existing policies decide
            }

            $decision = app(PolicyDecisionPoint::class)->can($user, $ability, $arguments[0] ?? null);

            if ($decision->abstain) {
                return null;
            }

            return $decision->permitted;
        });
    }
}

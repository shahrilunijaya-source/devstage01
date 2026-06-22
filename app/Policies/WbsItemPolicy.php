<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WbsItem;

class WbsItemPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isAdmin() || $user->isDirector()) {
            return true;
        }

        return null;
    }

    public function view(User $user, WbsItem $item): bool
    {
        return $item->project->activeAssignments()->where('user_id', $user->id)->exists();
    }

    // PM and PE can update actuals on leaf rows
    public function updateActuals(User $user, WbsItem $item): bool
    {
        if (! $item->is_leaf) {
            return false;
        }

        return $item->project->activeAssignments()
            ->where('user_id', $user->id)
            ->whereIn('project_role', ['pm', 'pe'])
            ->exists();
    }

    // Plan fields locked — nobody can update them directly after import (Admin re-uploads only)
    public function updatePlan(User $user, WbsItem $item): bool
    {
        return false;
    }
}

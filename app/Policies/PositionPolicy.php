<?php

namespace App\Policies;

use App\Models\Position;
use App\Models\User;

/**
 * Position master data (roles + departments + levels) is Admin-maintained per
 * PRD §5.2. Anyone authenticated may view the list (needed to render allocation
 * pickers in budgeting screens). Only Admin may create/update/delete.
 */
class PositionPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Position $position): bool
    {
        return true;
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Position $position): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Position $position): bool
    {
        return $user->isAdmin();
    }
}

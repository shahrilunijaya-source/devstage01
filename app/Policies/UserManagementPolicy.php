<?php

namespace App\Policies;

use App\Models\User;

class UserManagementPolicy
{
    // Only Admin can manage users
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, User $target): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, User $target): bool
    {
        return $user->isAdmin() && $user->id !== $target->id;
    }

    public function resetPassword(User $user, User $target): bool
    {
        return $user->isAdmin();
    }
}

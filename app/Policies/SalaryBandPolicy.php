<?php

namespace App\Policies;

use App\Models\SalaryBand;
use App\Models\User;

/**
 * Salary bands hold raw Max Salary. PRD §5.3 + §9 restrict visibility to Admin.
 * PM and PE see only the derived Daily Rate (resolved by DailyRateService),
 * never the underlying salary.
 */
class SalaryBandPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function view(User $user, SalaryBand $band): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, SalaryBand $band): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, SalaryBand $band): bool
    {
        return $user->isAdmin();
    }
}

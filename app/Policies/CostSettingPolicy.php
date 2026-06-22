<?php

namespace App\Policies;

use App\Models\CostSetting;
use App\Models\User;

/**
 * Global cost defaults (inflate, working days, scenarios, contingency, tax)
 * are Admin-maintained per PRD §5.4. Any authenticated user may read them
 * (needed to compute Daily Rate); only Admin may update.
 */
class CostSettingPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, CostSetting $setting): bool
    {
        return true;
    }

    public function update(User $user, CostSetting $setting): bool
    {
        return $user->isAdmin();
    }
}

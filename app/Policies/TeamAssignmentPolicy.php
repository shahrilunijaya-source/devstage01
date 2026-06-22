<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class TeamAssignmentPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        return null;
    }

    // PM can assign/remove PE and Member (not other PMs)
    public function assignPe(User $user, Project $project): bool
    {
        return $project->activeAssignments()
            ->where('user_id', $user->id)
            ->where('project_role', 'pm')
            ->exists();
    }

    public function removePe(User $user, Project $project): bool
    {
        return $this->assignPe($user, $project);
    }

    public function assignMember(User $user, Project $project): bool
    {
        return $project->activeAssignments()
            ->where('user_id', $user->id)
            ->whereIn('project_role', ['pm', 'pe'])
            ->exists();
    }
}

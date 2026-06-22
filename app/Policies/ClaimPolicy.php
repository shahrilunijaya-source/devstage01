<?php

namespace App\Policies;

use App\Models\ClaimMilestone;
use App\Models\Project;
use App\Models\User;

class ClaimPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isAdmin() || $user->isDirector()) {
            return true;
        }

        return null;
    }

    // Only PM can set up the claim schedule
    public function manageMilestones(User $user, Project $project): bool
    {
        return $project->activeAssignments()
            ->where('user_id', $user->id)
            ->where('project_role', 'pm')
            ->exists();
    }

    // Only PM can submit claims
    public function submit(User $user, ClaimMilestone $milestone): bool
    {
        return $milestone->project->activeAssignments()
            ->where('user_id', $user->id)
            ->where('project_role', 'pm')
            ->exists();
    }

    public function view(User $user, ClaimMilestone $milestone): bool
    {
        return $milestone->project->activeAssignments()->where('user_id', $user->id)->exists();
    }
}

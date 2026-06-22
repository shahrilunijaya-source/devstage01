<?php

namespace App\Policies;

use App\Models\MonthlyReport;
use App\Models\Project;
use App\Models\User;

class ReportPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isAdmin() || $user->isDirector()) {
            return true;
        }

        return null;
    }

    // PM and PE can generate/publish weekly updates
    public function manageWeekly(User $user, Project $project): bool
    {
        return $project->activeAssignments()
            ->where('user_id', $user->id)
            ->whereIn('project_role', ['pm', 'pe'])
            ->exists();
    }

    // PM and PE can generate monthly reports; only PM can send to client
    public function generateMonthly(User $user, Project $project): bool
    {
        return $project->activeAssignments()
            ->where('user_id', $user->id)
            ->whereIn('project_role', ['pm', 'pe'])
            ->exists();
    }

    public function sendToClient(User $user, Project $project): bool
    {
        return $project->activeAssignments()
            ->where('user_id', $user->id)
            ->where('project_role', 'pm')
            ->exists();
    }

    // PM only: approve a finalised report for sending to the client
    public function approveMonthly(User $user, Project $project): bool
    {
        return $project->activeAssignments()
            ->where('user_id', $user->id)
            ->where('project_role', 'pm')
            ->exists();
    }

    public function view(User $user, MonthlyReport $report): bool
    {
        return $report->project->activeAssignments()->where('user_id', $user->id)->exists();
    }
}

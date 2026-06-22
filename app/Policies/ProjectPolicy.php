<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\User;

class ProjectPolicy
{
    // Admins and Directors bypass all checks
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isAdmin() || $user->isDirector()) {
            return true;
        }

        return null;
    }

    // Any authenticated user can viewAny — scope handled in controller
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Project $project): bool
    {
        return $project->activeAssignments()->where('user_id', $user->id)->exists();
    }

    // Existing PMs (+ Admin/Director via before()) can create projects
    public function create(User $user): bool
    {
        return $user->isProjectPm();
    }

    // PM can update their own project; Admin/Director via before()
    public function update(User $user, Project $project): bool
    {
        return $project->activeAssignments()
            ->where('user_id', $user->id)
            ->where('project_role', 'pm')
            ->exists();
    }

    // Archive / unarchive / permanent delete are Admin (+ Director via before()) only.
    public function archive(User $user, Project $project): bool
    {
        return false;
    }

    public function delete(User $user, Project $project): bool
    {
        return false;
    }

    // The project's active PM (+ Admin/Director via before()) can upload a baseline
    public function uploadBaseline(User $user, Project $project): bool
    {
        return $project->activeAssignments()
            ->where('user_id', $user->id)
            ->where('project_role', 'pm')
            ->exists();
    }

    public function changePm(User $user, Project $project): bool
    {
        return false;
    }

    // PM only
    public function manageMilestones(User $user, Project $project): bool
    {
        return $project->activeAssignments()
            ->where('user_id', $user->id)
            ->where('project_role', 'pm')
            ->exists();
    }

    // PM only: submit claims
    public function submit(User $user, Project $project): bool
    {
        return $this->manageMilestones($user, $project);
    }

    // PM + PE: manage weekly updates
    public function manageWeekly(User $user, Project $project): bool
    {
        return $project->activeAssignments()
            ->where('user_id', $user->id)
            ->whereIn('project_role', ['pm', 'pe'])
            ->exists();
    }

    // PM + PE: generate monthly reports
    public function generateMonthly(User $user, Project $project): bool
    {
        return $project->activeAssignments()
            ->where('user_id', $user->id)
            ->whereIn('project_role', ['pm', 'pe'])
            ->exists();
    }

    // PM only: send report to client
    public function sendToClient(User $user, Project $project): bool
    {
        return $this->manageMilestones($user, $project);
    }

    // PM only: approve a finalised report for sending to the client
    public function approveMonthly(User $user, Project $project): bool
    {
        return $this->manageMilestones($user, $project);
    }

    // PM + PE: generate the AI insight (spends tokens). Admin/Director via before().
    public function generateInsight(User $user, Project $project): bool
    {
        return $project->activeAssignments()
            ->where('user_id', $user->id)
            ->whereIn('project_role', ['pm', 'pe'])
            ->exists();
    }

    // PM only: edit Budgetory (deductions + buckets)
    public function manageBudgetory(User $user, Project $project): bool
    {
        return $project->activeAssignments()
            ->where('user_id', $user->id)
            ->where('project_role', 'pm')
            ->exists();
    }

    // PM only: create/attach/remove client portal logins for this project.
    public function managePortalAccess(User $user, Project $project): bool
    {
        return $project->activeAssignments()
            ->where('user_id', $user->id)
            ->where('project_role', 'pm')
            ->exists();
    }

    // PM only: edit phase weightage overrides
    public function manageWeights(User $user, Project $project): bool
    {
        return $project->activeAssignments()
            ->where('user_id', $user->id)
            ->where('project_role', 'pm')
            ->exists();
    }
}

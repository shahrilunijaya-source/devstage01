<?php

namespace App\Services;

use App\Models\Acl\ScopeBinding;
use App\Models\ClaimMilestone;
use App\Models\Notification;
use App\Models\Project;
use App\Models\User;
use App\Models\WbsItem;

class NotificationService
{
    /**
     * Notify every user holding an active ACL binding that covers the project —
     * project-scoped or tenant-scoped — optionally excluding the actor who
     * triggered the event. This is the URSB-native audience (scope bindings),
     * distinct from notifyProjectTeam() which uses the legacy assignment pivot.
     */
    public function notifyProjectBindings(Project $project, string $type, string $message, ?int $exceptUserId = null): void
    {
        $userIds = ScopeBinding::active()
            ->where(function ($q) use ($project): void {
                $q->where(fn ($w) => $w->where('scope_type', 'project')->where('scope_id', $project->id))
                    ->orWhere(fn ($w) => $w->where('scope_type', 'tenant')->where('tenant_id', $project->tenant_id));
            })
            ->pluck('user_id')
            ->unique()
            ->reject(fn ($id): bool => $exceptUserId !== null && (int) $id === $exceptUserId);

        foreach ($userIds as $userId) {
            $this->notify((int) $userId, $type, $message, $project->id);
        }
    }

    /**
     * Notify all users with specific roles on a project.
     */
    public function notifyProjectTeam(
        Project $project,
        string $type,
        string $message,
        array $roles = ['pm', 'pe', 'member']
    ): void {
        $userIds = $project->activeAssignments()
            ->whereIn('project_role', $roles)
            ->pluck('user_id')
            ->unique();

        foreach ($userIds as $userId) {
            Notification::create([
                'user_id' => $userId,
                'project_id' => $project->id,
                'type' => $type,
                'message' => $message,
                'read' => false,
            ]);
        }
    }

    /**
     * Notify all Directors.
     */
    public function notifyDirectors(string $type, string $message, ?int $projectId = null): void
    {
        $directors = User::where('system_role', 'director')->where('active', true)->pluck('id');

        foreach ($directors as $userId) {
            Notification::create([
                'user_id' => $userId,
                'project_id' => $projectId,
                'type' => $type,
                'message' => $message,
                'read' => false,
            ]);
        }
    }

    /**
     * Notify all feedback triagers (Admins + Directors).
     */
    public function notifyTriagers(string $type, string $message, ?int $projectId = null): void
    {
        $triagers = User::whereIn('system_role', ['admin', 'director'])
            ->where('active', true)
            ->pluck('id');

        foreach ($triagers as $userId) {
            Notification::create([
                'user_id' => $userId,
                'project_id' => $projectId,
                'type' => $type,
                'message' => $message,
                'read' => false,
            ]);
        }
    }

    /**
     * Notify a specific user.
     */
    public function notify(int $userId, string $type, string $message, ?int $projectId = null): void
    {
        Notification::create([
            'user_id' => $userId,
            'project_id' => $projectId,
            'type' => $type,
            'message' => $message,
            'read' => false,
        ]);
    }

    /**
     * Notify user about overdue task.
     */
    public function notifyTaskOverdue(User $user, Project $project, string $taskName): void
    {
        $this->notify(
            $user->id,
            'task_overdue',
            "Task overdue: {$taskName}",
            $project->id
        );
    }

    /**
     * Notify user about claim due soon.
     */
    public function notifyClaimDueSoon(User $user, Project $project, string $claimName, string $dueDate): void
    {
        $this->notify(
            $user->id,
            'claim_due_soon',
            "Claim milestone due soon: {$claimName} (due {$dueDate})",
            $project->id
        );
    }

    /**
     * Notify user about new issue.
     */
    public function notifyNewIssue(User $user, Project $project, string $issueTitle): void
    {
        $this->notify(
            $user->id,
            'new_issue',
            "New issue raised: {$issueTitle}",
            $project->id
        );
    }

    /**
     * Check for overdue tasks and notify assigned users.
     */
    public function checkOverdueTasks(): int
    {
        $count = 0;
        $today = now()->startOfDay();

        // Find all WBS items that are overdue (planned_end < today, no actual_end, is_leaf)
        $overdueTasks = WbsItem::query()
            ->where('is_leaf', true)
            ->where('planned_end', '<', $today)
            ->whereNull('actual_end')
            ->with(['project', 'assignments.user'])
            ->get();

        foreach ($overdueTasks as $task) {
            $project = $task->project;
            if (! $project) {
                continue;
            }

            // Notify assigned users
            foreach ($task->assignments as $assignment) {
                if ($assignment->user) {
                    // Check if we already sent this notification recently (last 24h)
                    $exists = Notification::where('user_id', $assignment->user->id)
                        ->where('project_id', $project->id)
                        ->where('type', 'task_overdue')
                        ->where('message', 'like', "%{$task->name}%")
                        ->where('created_at', '>=', now()->subDay())
                        ->exists();

                    if (! $exists) {
                        $this->notifyTaskOverdue($assignment->user, $project, $task->name);
                        $count++;
                    }
                }
            }
        }

        return $count;
    }

    /**
     * Check for claims due within 7 days and notify PM/PE.
     */
    public function checkClaimsDueSoon(): int
    {
        $count = 0;
        $today = now()->startOfDay();
        $sevenDaysFromNow = $today->copy()->addDays(7);

        // Find claim milestones due within 7 days
        $claimsDue = ClaimMilestone::query()
            ->whereBetween('target_date', [$today, $sevenDaysFromNow])
            ->whereNotIn('claim_status', ['received', 'cancelled'])
            ->with('project')
            ->get();

        foreach ($claimsDue as $claim) {
            $project = $claim->project;
            if (! $project) {
                continue;
            }

            // Notify PM and PEs
            $usersToNotify = $project->assignments()
                ->whereIn('project_role', ['pm', 'pe'])
                ->with('user')
                ->get()
                ->pluck('user')
                ->filter();

            foreach ($usersToNotify as $user) {
                // Check if we already sent this notification recently (last 24h)
                $exists = Notification::where('user_id', $user->id)
                    ->where('project_id', $project->id)
                    ->where('type', 'claim_due_soon')
                    ->where('message', 'like', "%{$claim->perkara}%")
                    ->where('created_at', '>=', now()->subDay())
                    ->exists();

                if (! $exists) {
                    $this->notifyClaimDueSoon(
                        $user,
                        $project,
                        $claim->perkara,
                        $claim->target_date->format('d/m/Y')
                    );
                    $count++;
                }
            }
        }

        return $count;
    }
}

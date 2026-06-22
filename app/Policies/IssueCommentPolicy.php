<?php

namespace App\Policies;

use App\Models\Issue;
use App\Models\IssueComment;
use App\Models\User;

class IssueCommentPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isAdmin() || $user->isDirector()) {
            return true;
        }

        return null;
    }

    /**
     * Anyone with an active assignment on the issue's project may comment
     * (PM, PE, Member, or Client). Gated by project membership, not role.
     */
    public function create(User $user, Issue $issue): bool
    {
        return $issue->project->activeAssignments()
            ->where('user_id', $user->id)
            ->exists();
    }

    public function delete(User $user, IssueComment $comment): bool
    {
        return $comment->user_id === $user->id;
    }
}

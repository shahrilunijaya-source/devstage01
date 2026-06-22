<?php

namespace App\Policies;

use App\Models\Project;
use App\Models\ProjectComment;
use App\Models\User;

class ProjectCommentPolicy
{
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isAdmin() || $user->isDirector()) {
            return true;
        }

        return null;
    }

    public function view(User $user, Project $project): bool
    {
        return (new ProjectPolicy)->view($user, $project);
    }

    public function create(User $user, Project $project): bool
    {
        return (new ProjectPolicy)->view($user, $project);
    }

    public function delete(User $user, ProjectComment $comment): bool
    {
        return $comment->user_id === $user->id;
    }
}

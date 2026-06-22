<?php

namespace App\Policies;

use App\Models\FeedbackItem;
use App\Models\User;

class FeedbackItemPolicy
{
    /**
     * Admins + Directors triage all feedback — full access.
     * (Differs from project policies in that NO project role is involved:
     * feedback is global, about the Track app itself.)
     */
    public function before(User $user, string $ability): ?bool
    {
        if ($user->isAdmin() || $user->isDirector()) {
            return true;
        }

        return null;
    }

    /** Any authenticated user can submit a bug report / feature request. */
    public function create(User $user): bool
    {
        return true;
    }

    /** The triage inbox is triager-only (granted via before()). */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /** A submitter can view their own item; triagers via before(). */
    public function view(User $user, FeedbackItem $item): bool
    {
        return $item->submitted_by === $user->id;
    }

    public function update(User $user, FeedbackItem $item): bool
    {
        return false; // triagers via before()
    }

    public function triage(User $user, FeedbackItem $item): bool
    {
        return false; // triagers via before()
    }

    public function delete(User $user, FeedbackItem $item): bool
    {
        return false; // triagers via before()
    }
}

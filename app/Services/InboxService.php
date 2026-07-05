<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Discussion;
use App\Models\Graph\ChangeRequest;
use App\Models\Portfolio\Session;
use App\Models\User;
use App\Services\AccessControl\PolicyDecisionPoint;
use Illuminate\Support\Collection;

/**
 * Cross-project "needs your attention" inbox. Surfaces the work items waiting on
 * THIS user across every project they can access, gated by what they may actually
 * act on (validate / approve) — so the inbox never lists work the user has no
 * authority to clear. Capability is checked against each ITEM (session / change
 * target), so it honours bindings at any scope level — a stage-scoped validator
 * still sees the sessions in that stage, not just project-wide grants.
 */
class InboxService
{
    public function __construct(private readonly PolicyDecisionPoint $pdp) {}

    /**
     * @return array{firewall: Collection, approval: Collection, change_approval: Collection, total: int}
     */
    public function forUser(User $user): array
    {
        $projectIds = $this->pdp->accessibleProjectIds($user, 'view');

        if ($projectIds === []) {
            return ['firewall' => collect(), 'approval' => collect(), 'change_approval' => collect(), 'discussions' => collect(), 'total' => 0];
        }

        $sessions = Session::whereIn('project_id', $projectIds)
            ->whereIn('phase', ['firewall_review', 'post_session'])
            ->with('project', 'stage')
            ->get();

        $firewall = $sessions
            ->where('phase', 'firewall_review')
            ->filter(fn (Session $s): bool => $this->pdp->allows($user, 'validate', $s))
            ->values();

        $approval = $sessions
            ->where('phase', 'post_session')
            ->filter(fn (Session $s): bool => $this->pdp->allows($user, 'approve', $s))
            ->values();

        // Draft change requests awaiting approval — excluding the raiser (SoD).
        // Capability is checked on the change's target object (finest scope).
        $changeApproval = ChangeRequest::whereIn('project_id', $projectIds)
            ->where('status', 'draft')
            ->where('raised_by', '!=', $user->id)
            ->with('project', 'target')
            ->get()
            // Require a live target and approve on it — never fall back to the
            // broader project scope (that would surface CRs to project-wide
            // approvers who lack rights on the specific object).
            ->filter(fn (ChangeRequest $c): bool => $c->target !== null && $this->pdp->allows($user, 'approve', $c->target))
            ->values();

        // Open discussions assigned to this user (visibility-filtered for clients).
        $discussions = Discussion::whereIn('project_id', $projectIds)
            ->open()
            ->where('assigned_to', $user->id)
            ->visibleTo($user)
            ->with('project')
            ->get();

        return [
            'firewall' => $firewall,
            'approval' => $approval,
            'change_approval' => $changeApproval,
            'discussions' => $discussions,
            'total' => $firewall->count() + $approval->count() + $changeApproval->count() + $discussions->count(),
        ];
    }
}

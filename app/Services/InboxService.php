<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Graph\ChangeRequest;
use App\Models\Portfolio\Session;
use App\Models\Project;
use App\Models\User;
use App\Services\AccessControl\PolicyDecisionPoint;
use Illuminate\Support\Collection;

/**
 * Cross-project "needs your attention" inbox. Surfaces the work items waiting on
 * THIS user across every project they can access, gated by what they may actually
 * act on (validate / approve / baseline) — so the inbox never lists work the user
 * has no authority to clear.
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
            return ['firewall' => collect(), 'approval' => collect(), 'change_approval' => collect(), 'total' => 0];
        }

        // Capability per project, resolved once (the PDP caches the lookups too).
        $canValidate = [];
        $canApprove = [];
        foreach ($projectIds as $pid) {
            $project = Project::find($pid);
            if ($project === null) {
                continue;
            }
            $canValidate[$pid] = $this->pdp->allows($user, 'validate', $project);
            $canApprove[$pid] = $this->pdp->allows($user, 'approve', $project);
        }

        $sessions = Session::whereIn('project_id', $projectIds)
            ->whereIn('phase', ['firewall_review', 'post_session'])
            ->with('project', 'stage')
            ->get();

        $firewall = $sessions
            ->where('phase', 'firewall_review')
            ->filter(fn (Session $s): bool => $canValidate[$s->project_id] ?? false)
            ->values();

        $approval = $sessions
            ->where('phase', 'post_session')
            ->filter(fn (Session $s): bool => $canApprove[$s->project_id] ?? false)
            ->values();

        // Draft change requests awaiting approval — excluding the raiser (SoD).
        $changeApproval = ChangeRequest::whereIn('project_id', $projectIds)
            ->where('status', 'draft')
            ->where('raised_by', '!=', $user->id)
            ->with('project', 'target')
            ->get()
            ->filter(fn (ChangeRequest $c): bool => $canApprove[$c->project_id] ?? false)
            ->values();

        return [
            'firewall' => $firewall,
            'approval' => $approval,
            'change_approval' => $changeApproval,
            'total' => $firewall->count() + $approval->count() + $changeApproval->count(),
        ];
    }
}

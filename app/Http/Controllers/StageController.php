<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ObjectStatus;
use App\Models\Graph\EngObject;
use App\Models\Portfolio\Stage;
use App\Services\AccessControl\PolicyDecisionPoint;
use App\Services\Graph\BaselineService;
use App\Services\Knowledge\KnowledgeResolver;
use App\Services\NotificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Stage-level baselining (PRD §5, §9.7). Freezes the approved session outputs of
 * a stage into an immutable baseline.
 */
class StageController extends Controller
{
    /** Statuses that count as unresolved at the stage gate (PRD §9.5). */
    private const UNRESOLVED = [
        ObjectStatus::NEEDS_CONFIRMATION->value,
        ObjectStatus::CONFLICT_DETECTED->value,
        ObjectStatus::MISSING_UNKNOWN->value,
        ObjectStatus::DECISION_REQUIRED->value,
    ];

    public function __construct(private readonly PolicyDecisionPoint $pdp) {}

    /**
     * Stage gate readiness (PRD §9.5/§9.7) — the pre-baseline checklist: an
     * approved session, every item resolved, and the Knowledge Book's gate
     * criteria for the stage. Read-only; the actual baseline still runs through
     * baseline() with its own 'baseline' authority check.
     */
    public function gate(Request $request, Stage $stage, KnowledgeResolver $knowledge): View
    {
        $project = $stage->project;
        abort_unless($this->pdp->can($request->user(), 'view', $project)->permitted, 403, 'Access denied by ACL.');

        $sessionIds = $stage->sessions()->pluck('id');

        $unresolved = EngObject::whereIn('session_id', $sessionIds)
            ->whereIn('status', self::UNRESOLVED)
            ->count();

        $hasApproved = $stage->sessions()->where('status', 'approved')->exists();
        $baselined = $stage->status === 'baselined';

        $criteria = $knowledge->effectiveItems($project, $stage->stage)
            ->where('item_type', 'stage_gate')
            ->values();

        return view('stages.gate', [
            'stage' => $stage,
            'project' => $project,
            'unresolved' => $unresolved,
            'hasApproved' => $hasApproved,
            'baselined' => $baselined,
            'criteria' => $criteria,
            'ready' => $hasApproved && $unresolved === 0 && ! $baselined,
            'canBaseline' => $this->pdp->can($request->user(), 'baseline', $project)->permitted,
            'canEdit' => $this->pdp->can($request->user(), 'edit', $project)->permitted,
        ]);
    }

    /** Statuses a user may set by hand (baselined is reached only via baseline()). */
    private const MANUAL_STATUSES = ['not_started', 'in_progress', 'blocked'];

    /**
     * Set a stage's working status (PRD §5). Moving a stage TO blocked escalates
     * immediately to directors + the project team (CLAUDE.md escalation rule).
     */
    public function status(Request $request, Stage $stage, NotificationService $notifications): RedirectResponse
    {
        $project = $stage->project;
        abort_unless($this->pdp->can($request->user(), 'edit', $project)->permitted, 403, 'Access denied by ACL.');

        if ($stage->status === 'baselined') {
            return redirect()->route('stages.gate', $stage)
                ->with('error', 'A baselined stage is frozen; raise a change request instead.');
        }

        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', self::MANUAL_STATUSES)],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        $wasBlocked = $stage->status === 'blocked';

        $stage->update([
            'status' => $data['status'],
            'started_at' => $stage->started_at ?? ($data['status'] === 'in_progress' ? now() : $stage->started_at),
        ]);

        if ($data['status'] === 'blocked' && ! $wasBlocked) {
            $label = $stage->stage->label();
            $reason = filled($data['reason'] ?? null) ? " Reason: {$data['reason']}" : '';
            $message = "BLOCKED: {$label} stage in \"{$project->name}\".{$reason}";

            $notifications->notifyDirectors('stage_blocked', $message, $project->id);
            $notifications->notifyProjectBindings($project, 'stage_blocked', $message, $request->user()->id);
        }

        return redirect()->route('stages.gate', $stage)->with('status', 'Stage status updated.');
    }

    public function baseline(Request $request, Stage $stage, BaselineService $baselines, KnowledgeResolver $knowledge, NotificationService $notifications): RedirectResponse
    {
        $project = $stage->project;
        abort_unless($this->pdp->can($request->user(), 'baseline', $project)->permitted, 403, 'Access denied by ACL.');

        if (! $stage->sessions()->where('status', 'approved')->exists()) {
            return redirect()->route('portfolio.show', $project)
                ->with('error', 'Cannot baseline: the stage has no approved session yet.');
        }

        $baseline = $baselines->baseline($stage, [
            'knowledge_book_version' => $knowledge->pinnedBook($project)?->version,
            'approved_by' => $request->user()->id,
            'created_by' => $request->user()->id,
        ]);

        $notifications->notifyProjectBindings(
            $project, 'stage_baselined',
            "Stage {$stage->stage->value} baselined as {$baseline->version_label}.",
            $request->user()->id,
        );

        return redirect()->route('portfolio.show', $project)
            ->with('status', "Baseline {$baseline->version_label} created with {$baseline->baselineObjects()->count()} objects.");
    }
}

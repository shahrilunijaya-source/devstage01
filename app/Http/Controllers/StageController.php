<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Portfolio\Stage;
use App\Models\Portfolio\StageBaseline;
use App\Services\AccessControl\PolicyDecisionPoint;
use App\Services\Discussion\DiscussionService;
use App\Services\Graph\BaselineService;
use App\Services\Graph\Exceptions\BaselineReopenException;
use App\Services\Knowledge\KnowledgeResolver;
use App\Services\NotificationService;
use App\Services\Portfolio\StageGateService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Stage-level baselining (PRD §5, §9.7). Freezes the approved session outputs of
 * a stage into an immutable baseline. Gate readiness comes from StageGateService
 * — the single authority shared by the gate page and the baseline endpoint.
 */
class StageController extends Controller
{
    public function __construct(
        private readonly PolicyDecisionPoint $pdp,
        private readonly StageGateService $gates,
    ) {}

    /**
     * Stage gate readiness (PRD §9.5/§9.7) — the pre-baseline checklist plus the
     * Knowledge Book's advisory gate criteria for the stage.
     */
    public function gate(Request $request, Stage $stage, KnowledgeResolver $knowledge): View
    {
        $project = $stage->project;
        abort_unless($this->pdp->can($request->user(), 'view', $project)->permitted, 403, 'Access denied by ACL.');

        $gate = $this->gates->readiness($stage);

        $criteria = $knowledge->effectiveItems($project, $stage->stage)
            ->where('item_type', 'stage_gate')
            ->values();

        return view('stages.gate', [
            'stage' => $stage,
            'project' => $project,
            'gate' => $gate,
            'baselined' => $stage->status === 'baselined',
            'criteria' => $criteria,
            'canBaseline' => $this->pdp->can($request->user(), 'baseline', $project)->permitted,
            'canEdit' => $this->pdp->can($request->user(), 'edit', $project)->permitted,
            'discussions' => app(DiscussionService::class)->for($stage, $request->user()),
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

        $gate = $this->gates->readiness($stage);
        $opts = [
            'knowledge_book_version' => $knowledge->pinnedBook($project)?->version,
            'approved_by' => $request->user()->id,
            'created_by' => $request->user()->id,
        ];

        if (! $gate['ready']) {
            // Hard blockers can never be overridden.
            if ($gate['blockers'] !== []) {
                return redirect()->route('stages.gate', $stage)
                    ->with('error', 'Cannot baseline: '.implode(' · ', $gate['blockers']));
            }

            // Overridable failures need an explicit, recorded exception (spec §9).
            $reason = trim((string) $request->input('override_reason', ''));
            if (! $request->boolean('override') || $reason === '') {
                return redirect()->route('stages.gate', $stage)
                    ->with('error', 'Gate not met: '.implode(' · ', $gate['failing_overridable'])
                        .' — baseline with exceptions requires ticking the override and recording a reason.');
            }

            $opts['exceptions'] = [
                'checks' => $gate['failing_overridable'],
                'reason' => mb_substr($reason, 0, 500),
                'by' => $request->user()->id,
                'at' => now()->toIso8601String(),
            ];
        }

        $baseline = $baselines->baseline($stage, $opts);

        $suffix = isset($opts['exceptions']) ? ' (with recorded exceptions)' : '';
        $notifications->notifyProjectBindings(
            $project, 'stage_baselined',
            "Stage {$stage->stage->value} baselined as {$baseline->version_label}{$suffix}.",
            $request->user()->id,
        );

        return redirect()->route('portfolio.show', $project)
            ->with('status', "Baseline {$baseline->version_label} created with {$baseline->baselineObjects()->count()} objects{$suffix}.");
    }

    /**
     * Reopen the active baseline (spec §9 "Reopened") — unfreezes members for
     * refinement, versioned and audited. Same 'baseline' authority as freezing.
     */
    public function reopen(Request $request, StageBaseline $baseline, BaselineService $baselines, NotificationService $notifications): RedirectResponse
    {
        $project = $baseline->project;
        abort_unless($this->pdp->can($request->user(), 'baseline', $project)->permitted, 403, 'Access denied by ACL.');

        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        try {
            $baselines->reopen($baseline, $request->user()->id, $data['reason']);
        } catch (BaselineReopenException $e) {
            return redirect()->route('stages.gate', $baseline->stage)->with('error', $e->getMessage());
        }

        $notifications->notifyProjectBindings(
            $project, 'baseline_reopened',
            "Baseline {$baseline->version_label} reopened: {$data['reason']}",
            $request->user()->id,
        );

        return redirect()->route('stages.gate', $baseline->stage)
            ->with('status', "Baseline {$baseline->version_label} reopened — its objects are back in refinement.");
    }
}

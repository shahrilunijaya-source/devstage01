<?php

declare(strict_types=1);

namespace App\Services\Portfolio;

use App\Enums\LifecycleStage;
use App\Enums\ObjectStatus;
use App\Enums\ObjectType;
use App\Models\Graph\EngObject;
use App\Models\Portfolio\Stage;
use App\Services\Discussion\DiscussionService;

/**
 * Single authority for stage-gate readiness (PRD §9.5/§9.7). Both the gate page
 * AND the baseline endpoint consume this — previously the POST re-checked only
 * one of the gate page's conditions, so server enforcement diverged from the UI.
 *
 * Checks are either hard blockers (never overridable) or overridable: an
 * authorised user may baseline past a failing overridable check by recording an
 * exception + reason, which is frozen into the baseline's snapshot_meta.
 */
class StageGateService
{
    /** Statuses that count as unresolved at the stage gate (PRD §9.5). */
    public const UNRESOLVED = [
        ObjectStatus::NEEDS_CONFIRMATION->value,
        ObjectStatus::CONFLICT_DETECTED->value,
        ObjectStatus::MISSING_UNKNOWN->value,
        ObjectStatus::DECISION_REQUIRED->value,
    ];

    public function __construct(private readonly DiscussionService $discussions) {}

    /**
     * @return array{
     *     checks: array<int, array{key: string, label: string, pass: bool, detail: ?string, overridable: bool}>,
     *     ready: bool,
     *     blockers: array<int, string>,
     *     failing_overridable: array<int, string>,
     *     unresolved: int
     * }
     */
    public function readiness(Stage $stage): array
    {
        $sessionIds = $stage->sessions()->pluck('id');

        $unresolved = EngObject::whereIn('session_id', $sessionIds)
            ->whereIn('status', self::UNRESOLVED)
            ->count();

        $hasApproved = $stage->sessions()->where('status', 'approved')->exists();
        $baselined = $stage->status === 'baselined';

        $checks = [
            [
                'key' => 'approved_session',
                'label' => 'Has an approved session',
                'pass' => $hasApproved,
                'detail' => $hasApproved ? null : 'Approve at least one session first — a baseline freezes approved session output.',
                'overridable' => false,
            ],
            [
                'key' => 'items_resolved',
                'label' => 'All items resolved',
                'pass' => $unresolved === 0,
                'detail' => $unresolved > 0 ? "{$unresolved} unresolved item(s)" : null,
                'overridable' => true,
            ],
            [
                'key' => 'not_baselined',
                'label' => 'Stage not already baselined',
                'pass' => ! $baselined,
                'detail' => $baselined ? 'Reopen the current baseline before re-baselining.' : null,
                'overridable' => false,
            ],
        ];

        $blockingDiscussions = $this->discussions->blockingCountForStage($stage);
        $checks[] = [
            'key' => 'blocking_discussions',
            'label' => 'No open blocking discussions',
            'pass' => $blockingDiscussions === 0,
            'detail' => $blockingDiscussions > 0 ? "{$blockingDiscussions} open blocking discussion(s)" : null,
            'overridable' => true,
        ];

        // The chain's anchor: BRS cannot baseline until the project objective is
        // captured AND approved (spec §6) — overridable, so pre-existing projects
        // can still move with a recorded exception.
        if ($stage->stage === LifecycleStage::BRS) {
            $objective = EngObject::query()
                ->where('project_id', $stage->project_id)
                ->where('type', ObjectType::OBJECTIVE->value)
                ->orderByDesc('id')
                ->first();
            $approved = $objective?->status === ObjectStatus::CONFIRMED_BY_EVIDENCE;

            $checks[] = [
                'key' => 'objective_approved',
                'label' => 'Project objective approved',
                'pass' => $approved,
                'detail' => $objective === null
                    ? 'No objective captured yet — use the project Objective page.'
                    : ($approved ? null : "{$objective->ref} awaits approval."),
                'overridable' => true,
            ];
        }

        $predecessor = $this->requiredPredecessor($stage);
        if ($predecessor !== null) {
            $checks[] = [
                'key' => 'previous_stage',
                'label' => "Previous stage ({$predecessor->stage->label()}) baselined",
                'pass' => $predecessor->status === 'baselined',
                'detail' => $predecessor->status === 'baselined' ? null : "Stage {$predecessor->stage->label()} is '{$predecessor->status}'.",
                'overridable' => true,
            ];
        }

        $failing = array_values(array_filter($checks, fn (array $c): bool => ! $c['pass']));

        return [
            'checks' => $checks,
            'ready' => $failing === [],
            'blockers' => array_column(array_filter($failing, fn (array $c): bool => ! $c['overridable']), 'label'),
            'failing_overridable' => array_column(array_filter($failing, fn (array $c): bool => $c['overridable']), 'label'),
            'unresolved' => $unresolved,
        ];
    }

    /**
     * The nearest mandatory stage before this one in the same module (optional
     * stages are skipped) — the lifecycle order gate: URS cannot baseline before
     * BRS, and so on. Null for the first stage.
     */
    private function requiredPredecessor(Stage $stage): ?Stage
    {
        $stages = $stage->module->stages()->get();

        return $stages
            ->filter(fn (Stage $s): bool => ! $s->stage->isOptional() && $s->stage->order() < $stage->stage->order())
            ->sortByDesc(fn (Stage $s): int => $s->stage->order())
            ->first();
    }
}

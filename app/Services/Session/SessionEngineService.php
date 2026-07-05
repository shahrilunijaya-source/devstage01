<?php

declare(strict_types=1);

namespace App\Services\Session;

use App\Enums\ConfidenceLevel;
use App\Enums\ObjectStatus;
use App\Enums\ObjectType;
use App\Enums\RelationType;
use App\Models\Graph\EngObject;
use App\Models\Portfolio\Session;
use App\Models\User;
use App\Services\Graph\ObjectGraphService;
use App\Services\Graph\TraceService;
use App\Services\Session\Analysis\AnalysisResult;
use App\Services\Session\Analysis\DeterministicEvidenceAnalyst;
use App\Services\Session\Analysis\DraftedObject;
use App\Services\Session\Analysis\EvidenceAnalyst;
use App\Services\Session\Analysis\Exceptions\AnalysisException;
use App\Services\Session\Exceptions\SessionEngineException;

/**
 * The shared session engine (PRD §9). Runs the five-phase lifecycle: AI
 * pre-analysis → quality firewall → ready → in session (capture) → post-session.
 * Pre-analysis is deterministic and offline-safe here; an LLM provider can be
 * swapped in behind the same method without changing the workflow.
 */
class SessionEngineService
{
    /** Decisions a client may record against an item (PRD §9.3). */
    public const DECISIONS = ['confirm', 'correct', 'complete', 'decide'];

    public function __construct(
        private readonly ObjectGraphService $graph,
        private readonly TraceService $trace,
        private readonly EvidenceAnalyst $analyst,
        private readonly DeterministicEvidenceAnalyst $fallback,
    ) {}

    /**
     * Phase 1 — AI pre-analysis (PRD §9.3.1). For each Evidence in the session
     * that has not yet been analysed, draft a Finding and a Business Requirement,
     * trace-linked and bound to the evidence. Returns the number of objects drafted.
     */
    public function preAnalyze(Session $session): int
    {
        // Re-running before the firewall is legitimate (and idempotent per
        // evidence); running later would silently reset an approved session's
        // phase and let the whole chain replay — an uncontrolled reopen.
        if (! in_array($session->phase, ['pre_analysis', 'firewall_review'], true)) {
            throw new SessionEngineException(
                "Cannot run pre-analysis: session is in phase '{$session->phase}'. Reopen the baseline or use a change request instead.",
            );
        }

        $evidence = EngObject::where('session_id', $session->id)
            ->where('type', ObjectType::EVIDENCE->value)
            ->get();

        $drafted = 0;
        $scope = [
            'module_id' => $session->module_id,
            'stage_id' => $session->stage_id,
            'session_id' => $session->id,
        ];

        // Root of the chain (spec §6): drafted requirements also trace back to
        // the project objective, so RTM walks reach OBJ-… from any requirement.
        $objective = EngObject::where('project_id', $session->project_id)
            ->where('type', ObjectType::OBJECTIVE->value)
            ->orderByDesc('id')
            ->first();

        foreach ($evidence as $evd) {
            if ($evd->outgoingTraces()->where('relation_type', RelationType::DERIVED_FROM->value)->exists()) {
                continue; // already analysed
            }

            $result = $this->draftFor($evd);

            $finding = $this->materialize($result->finding, $evd, (int) $evd->tenant_id, (int) $session->project_id, $scope, $evd->ref, (int) $evd->id, $result->citations);
            $this->trace->link($evd, $finding, RelationType::DERIVED_FROM);
            $drafted++;

            foreach ($result->requirements as $reqDraft) {
                $requirement = $this->materialize($reqDraft, $evd, (int) $evd->tenant_id, (int) $session->project_id, $scope, $finding->ref, (int) $finding->id, $result->citations);
                $this->trace->link($finding, $requirement, RelationType::DERIVED_FROM);
                if ($objective !== null) {
                    $this->trace->link($objective, $requirement, RelationType::DERIVED_FROM);
                }
                $drafted++;
            }
        }

        $session->update(['phase' => 'firewall_review']);

        return $drafted;
    }

    /** Run the configured analyst, falling back to the deterministic one on failure. */
    private function draftFor(EngObject $evidence): AnalysisResult
    {
        try {
            return $this->analyst->analyze($evidence);
        } catch (AnalysisException) {
            return $this->fallback->analyze($evidence);
        }
    }

    /**
     * Turn an AI draft into a canonical object, citing its source (PRD §9.2).
     *
     * @param  array<string, mixed>  $scope
     * @param  array<int, string>  $citations
     */
    private function materialize(DraftedObject $draft, EngObject $evidence, int $tenantId, int $projectId, array $scope, string $sourceRef, int $sourceObjectId, array $citations): EngObject
    {
        return $this->graph->create($draft->type, $tenantId, $projectId, $draft->title, $scope + [
            'body' => $draft->body,
            'source' => $sourceRef,
            'source_object_id' => $sourceObjectId,
            'status' => ObjectStatus::NEEDS_CONFIRMATION,
            'confidence' => $draft->confidence,
            'impact' => $draft->impact,
            'attributes' => ['cites' => $citations],
        ]);
    }

    /**
     * Phase 2 — Quality firewall (PRD §9.3.2). Mandatory human review before the
     * session pack is presented. Only valid from firewall_review.
     */
    public function passFirewall(Session $session, User $reviewer): void
    {
        $this->assertPhase($session, 'firewall_review', 'quality firewall');

        $session->update([
            'phase' => 'ready',
            'firewall_approved_by' => $reviewer->id,
            'firewall_approved_at' => now(),
            'firewall_rejected_by' => null,
            'firewall_rejected_at' => null,
            'firewall_rejected_reason' => null,
        ]);
    }

    /**
     * Firewall send-back (spec §9 "Refinement Required"): the reviewer returns
     * the AI drafts to pre-analysis with a recorded reason, so more evidence can
     * be added and the drafts regenerated before human review runs again.
     */
    public function rejectFirewall(Session $session, User $reviewer, string $reason): void
    {
        $this->assertPhase($session, 'firewall_review', 'reject at quality firewall');

        $session->update([
            'phase' => 'pre_analysis',
            'firewall_rejected_by' => $reviewer->id,
            'firewall_rejected_at' => now(),
            'firewall_rejected_reason' => $reason,
        ]);
    }

    /** Open the evidence-led session (PRD §9.3.4). Only valid from ready. */
    public function startSession(Session $session): void
    {
        $this->assertPhase($session, 'ready', 'start session');
        $session->update(['phase' => 'in_session', 'status' => 'in_progress']);
    }

    /** Statuses that count as unresolved at the session exit gate (PRD §9.5). */
    private const UNRESOLVED = [
        ObjectStatus::NEEDS_CONFIRMATION->value,
        ObjectStatus::CONFLICT_DETECTED->value,
        ObjectStatus::MISSING_UNKNOWN->value,
        ObjectStatus::DECISION_REQUIRED->value,
    ];

    /** Phase 5 — post-session consolidation (PRD §9.3.5). Only valid from in_session. */
    public function consolidate(Session $session): void
    {
        $this->assertPhase($session, 'in_session', 'consolidate');
        $session->update(['phase' => 'post_session', 'status' => 'submitted']);
    }

    /**
     * Approve the session (PRD §9.7 exit gate). Every item must be resolved —
     * Confirmed, Corrected, Completed or Decided — before approval. Only valid
     * from post_session.
     */
    public function approveSession(Session $session, User $approver): void
    {
        $this->assertPhase($session, 'post_session', 'approve session');

        $unresolved = EngObject::where('session_id', $session->id)
            ->whereIn('status', self::UNRESOLVED)
            ->count();

        if ($unresolved > 0) {
            throw new SessionEngineException(
                "Cannot approve: {$unresolved} item(s) still unresolved. Every item must be Confirmed, Corrected, Completed or Decided.",
            );
        }

        $session->update([
            'phase' => 'approved',
            'status' => 'approved',
            'approved_by' => $approver->id,
            'approved_at' => now(),
        ]);
    }

    /**
     * Capture a client response against an item (PRD §9.3 four-point taxonomy).
     * Enforces risk-weighted validation: a high-impact, low-confidence item
     * cannot be quick-confirmed (PRD §9.4).
     *
     * @param  array<string, mixed>  $opts  title, body (for correct/complete)
     */
    public function capture(EngObject $object, string $decision, User $user, array $opts = []): EngObject
    {
        if (! in_array($decision, self::DECISIONS, true)) {
            throw new SessionEngineException("Unknown capture decision: {$decision}.");
        }

        // Capture is only meaningful while the session is live — outside it,
        // decisions would mutate reviewed (or approved) content unnoticed.
        $session = $object->session;
        if ($session !== null && $session->phase !== 'in_session') {
            throw new SessionEngineException(
                "Cannot capture: session is in phase '{$session->phase}', expected 'in_session'.",
            );
        }

        return match ($decision) {
            'confirm' => $this->confirm($object, $user),
            'correct' => $this->edit($object, $user, $opts, 'corrected'),
            'complete' => $this->edit($object, $user, $opts, 'completed'),
            'decide' => $this->decide($object, $user),
        };
    }

    private function confirm(EngObject $object, User $user): EngObject
    {
        if ($this->isHighImpact($object) && $object->confidence === ConfidenceLevel::LOW) {
            throw new SessionEngineException(
                'High-impact, low-confidence items cannot be quick-confirmed. Use Correct, Complete or Decide.',
            );
        }

        return $this->graph->update($object, [
            'status' => ObjectStatus::CONFIRMED_BY_EVIDENCE,
            'confidence' => ConfidenceLevel::HIGH,
        ], $user->id, 'confirmed in session');
    }

    /** @param  array<string, mixed>  $opts */
    private function edit(EngObject $object, User $user, array $opts, string $summary): EngObject
    {
        $changes = array_filter([
            'title' => $opts['title'] ?? null,
            'body' => $opts['body'] ?? null,
        ], fn ($v) => $v !== null);

        $changes['status'] = ObjectStatus::CONFIRMED_BY_EVIDENCE;

        return $this->graph->update($object, $changes, $user->id, $summary.' in session');
    }

    private function decide(EngObject $object, User $user): EngObject
    {
        $resolved = $this->graph->update($object, [
            'status' => ObjectStatus::CONFIRMED_BY_EVIDENCE,
            'confidence' => ConfidenceLevel::HIGH,
        ], $user->id, 'decision recorded in session');

        // Evidence is source, not a judgement call — only log decisions on hypotheses.
        if ($object->type !== ObjectType::EVIDENCE) {
            $this->recordDecision($resolved, $user);
        }

        return $resolved;
    }

    /** Log a first-class DECISION object resolving an item (PRD §9 decision register). */
    private function recordDecision(EngObject $target, User $user): void
    {
        $decision = $this->graph->create(
            ObjectType::DECISION,
            (int) $target->tenant_id,
            (int) $target->project_id,
            'Decision on '.$target->ref,
            [
                'module_id' => $target->module_id,
                'stage_id' => $target->stage_id,
                'session_id' => $target->session_id,
                'owner_user_id' => $user->id,
                'source' => $target->ref,
                'source_object_id' => $target->id,
                'status' => ObjectStatus::CONFIRMED_BY_EVIDENCE,
                'confidence' => ConfidenceLevel::HIGH,
                'body' => "Item {$target->ref} accepted by decision during session capture.",
                'changed_by' => $user->id,
                'change_summary' => 'decision recorded',
            ],
        );

        $this->trace->link($decision, $target, RelationType::RESOLVES, null, $user->id);
    }

    private function isHighImpact(EngObject $object): bool
    {
        return in_array($object->impact, ['high', 'critical'], true);
    }

    private function assertPhase(Session $session, string $expected, string $action): void
    {
        if ($session->phase !== $expected) {
            throw new SessionEngineException(
                "Cannot {$action}: session is in phase '{$session->phase}', expected '{$expected}'.",
            );
        }
    }
}

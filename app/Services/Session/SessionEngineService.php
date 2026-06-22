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
    ) {}

    /**
     * Phase 1 — AI pre-analysis (PRD §9.3.1). For each Evidence in the session
     * that has not yet been analysed, draft a Finding and a Business Requirement,
     * trace-linked and bound to the evidence. Returns the number of objects drafted.
     */
    public function preAnalyze(Session $session): int
    {
        $evidence = EngObject::where('session_id', $session->id)
            ->where('type', ObjectType::EVIDENCE->value)
            ->get();

        $drafted = 0;
        $scope = [
            'module_id' => $session->module_id,
            'stage_id' => $session->stage_id,
            'session_id' => $session->id,
        ];

        foreach ($evidence as $evd) {
            if ($evd->outgoingTraces()->where('relation_type', RelationType::DERIVED_FROM->value)->exists()) {
                continue; // already analysed
            }

            $finding = $this->graph->create(ObjectType::FINDING, (int) $evd->tenant_id, (int) $session->project_id, 'Finding: '.$evd->title, $scope + [
                'body' => $evd->body,
                'source' => $evd->ref,
                'source_object_id' => $evd->id,
                'status' => ObjectStatus::NEEDS_CONFIRMATION,
                'confidence' => ConfidenceLevel::MEDIUM,
                'impact' => 'medium',
            ]);

            $requirement = $this->graph->create(ObjectType::BUSINESS_REQUIREMENT, (int) $evd->tenant_id, (int) $session->project_id, 'Draft requirement from '.$evd->ref, $scope + [
                'body' => 'System shall address: '.($evd->title),
                'source' => $finding->ref,
                'source_object_id' => $finding->id,
                'status' => ObjectStatus::NEEDS_CONFIRMATION,
                'confidence' => ConfidenceLevel::LOW,
                'impact' => 'high',
            ]);

            $this->trace->link($evd, $finding, RelationType::DERIVED_FROM);
            $this->trace->link($finding, $requirement, RelationType::DERIVED_FROM);
            $drafted += 2;
        }

        $session->update(['phase' => 'firewall_review']);

        return $drafted;
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
        return $this->graph->update($object, [
            'status' => ObjectStatus::CONFIRMED_BY_EVIDENCE,
            'confidence' => ConfidenceLevel::HIGH,
        ], $user->id, 'decision recorded in session');
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

<?php

declare(strict_types=1);

namespace App\Services\Metrics;

use App\Enums\ObjectStatus;
use App\Enums\ObjectType;
use App\Models\Graph\ChangeRequest;
use App\Models\Graph\EngObject;
use App\Models\Graph\ObjectVersion;
use App\Models\Portfolio\Session;
use App\Models\Portfolio\StageBaseline;
use App\Models\Project;
use App\Services\Graph\TraceService;
use Illuminate\Support\Collection;

/**
 * Metrics & Quality Analytics Engine (PRD §17). Computes session and portfolio
 * metrics and surfaces red-flag anomalies — advisory signals, not judgments.
 */
class MetricsService
{
    /** Requirement-family object types that must trace back to evidence. */
    private const REQUIREMENT_TYPES = [
        ObjectType::USER_REQUIREMENT->value,
        ObjectType::FUNCTIONAL_REQUIREMENT->value,
        ObjectType::NON_FUNCTIONAL_REQUIREMENT->value,
        ObjectType::BUSINESS_REQUIREMENT->value,
    ];

    public function __construct(private readonly TraceService $trace) {}

    /**
     * Per-session capture metrics (PRD §10.7). Capture decisions are read from the
     * object-version history written by the session engine.
     *
     * @return array<string, mixed>
     */
    public function sessionMetrics(Session $session): array
    {
        $objectIds = EngObject::where('session_id', $session->id)->pluck('id');

        $summaries = ObjectVersion::whereIn('object_id', $objectIds)
            ->where('version', '>', 1)
            ->pluck('change_summary');

        $confirmations = $summaries->filter(fn ($s) => str_contains((string) $s, 'confirmed'))->count();
        $corrections = $summaries->filter(fn ($s) => str_contains((string) $s, 'corrected'))->count();
        $completions = $summaries->filter(fn ($s) => str_contains((string) $s, 'completed'))->count();
        $decisions = $summaries->filter(fn ($s) => str_contains((string) $s, 'decision'))->count();
        $total = $confirmations + $corrections + $completions + $decisions;

        $unresolved = EngObject::where('session_id', $session->id)
            ->whereIn('status', $this->unresolvedStatuses())->count();

        return [
            'items' => $objectIds->count(),
            'captures' => $total,
            'confirmations' => $confirmations,
            'corrections' => $corrections,
            'completions' => $completions,
            'decisions' => $decisions,
            'confirmation_rate' => $this->rate($confirmations, $total),
            'correction_rate' => $this->rate($corrections, $total),
            'unresolved' => $unresolved,
            'rubber_stamp' => $total >= 3 && $confirmations === $total && $corrections === 0,
        ];
    }

    /**
     * Portfolio-level quality metrics for a project (PRD §17.1).
     *
     * @return array<string, mixed>
     */
    public function projectMetrics(Project $project): array
    {
        $requirements = EngObject::where('project_id', $project->id)
            ->whereIn('type', self::REQUIREMENT_TYPES)->get();

        $withoutEvidence = $requirements->filter(fn (EngObject $r) => ! $this->tracesToEvidence($r))->count();
        $reqCount = $requirements->count();

        $objects = EngObject::where('project_id', $project->id);
        $totalObjects = (clone $objects)->count();
        $confirmed = (clone $objects)->where('status', ObjectStatus::CONFIRMED_BY_EVIDENCE->value)->count();

        return [
            'requirements' => $reqCount,
            'requirements_without_evidence' => $withoutEvidence,
            'traceability_completeness' => $reqCount > 0 ? $this->rate($reqCount - $withoutEvidence, $reqCount) : 100.0,
            'objects' => $totalObjects,
            'confirmed' => $confirmed,
            'confirmed_pct' => $this->rate($confirmed, $totalObjects),
            'baseline_churn' => $this->baselineChurn($project),
            'change_requests' => ChangeRequest::where('project_id', $project->id)->count(),
            'change_requests_applied' => ChangeRequest::where('project_id', $project->id)->where('status', 'applied')->count(),
            'red_flags' => $this->redFlags($project, $requirements, $withoutEvidence),
        ];
    }

    /**
     * Red-flag anomaly signals (PRD §17.3) — advisory only.
     *
     * @param  Collection<int, EngObject>  $requirements
     * @return array<int, array{code: string, label: string, detail: string}>
     */
    public function redFlags(Project $project, $requirements, int $withoutEvidence): array
    {
        $flags = [];

        if ($withoutEvidence > 0) {
            $flags[] = [
                'code' => 'missing_evidence',
                'label' => 'Requirements without evidence',
                'detail' => "{$withoutEvidence} requirement(s) have no traceable evidence.",
            ];
        }

        foreach (Session::where('project_id', $project->id)->get() as $session) {
            if ($this->sessionMetrics($session)['rubber_stamp']) {
                $flags[] = [
                    'code' => 'rubber_stamping',
                    'label' => 'Possible rubber-stamping',
                    'detail' => "Session '{$session->title}' shows 100% confirmation with 0% correction.",
                ];
            }
        }

        $churn = $this->baselineChurn($project);
        if ($churn >= 2) {
            $flags[] = [
                'code' => 'baseline_churn',
                'label' => 'High baseline churn',
                'detail' => "{$churn} baseline re-issues across stages.",
            ];
        }

        return $flags;
    }

    private function tracesToEvidence(EngObject $requirement): bool
    {
        foreach ($this->trace->reverseTrace($requirement) as $ancestor) {
            if (in_array($ancestor->type, [ObjectType::EVIDENCE, ObjectType::FINDING], true)) {
                return true;
            }
        }

        return false;
    }

    private function baselineChurn(Project $project): int
    {
        return StageBaseline::where('project_id', $project->id)
            ->selectRaw('stage_id, count(*) as c')->groupBy('stage_id')->pluck('c')
            ->sum(fn ($c) => max(0, (int) $c - 1));
    }

    /** @return array<int, string> */
    private function unresolvedStatuses(): array
    {
        return [
            ObjectStatus::NEEDS_CONFIRMATION->value,
            ObjectStatus::CONFLICT_DETECTED->value,
            ObjectStatus::MISSING_UNKNOWN->value,
            ObjectStatus::DECISION_REQUIRED->value,
        ];
    }

    private function rate(int $part, int $whole): float
    {
        return $whole > 0 ? round($part / $whole * 100, 1) : 0.0;
    }
}

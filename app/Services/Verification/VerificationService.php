<?php

declare(strict_types=1);

namespace App\Services\Verification;

use App\Enums\ObjectStatus;
use App\Enums\ObjectType;
use App\Enums\RelationType;
use App\Models\Graph\EngObject;
use App\Models\Graph\TraceRelationship;
use App\Models\Project;
use App\Models\User;
use App\Services\Graph\ObjectGraphService;
use App\Services\Graph\TraceService;
use Illuminate\Support\Collection;

/**
 * Verification & Validation register (PRD §17 traceability, VALIDATION stage).
 * Closes the requirement → test-case → result loop: every requirement is proven
 * (or shown unproven) by the test cases that VERIFY it and their latest results.
 *
 * Reuses the canonical object graph — a TEST_CASE and a TEST_RESULT are ordinary
 * graph objects, wired with VERIFIES / TRACES_TO edges, so V&V is fully traceable
 * and baselineable like every other artefact. No new tables.
 */
class VerificationService
{
    /** Requirement-family types that must be verified before a stage is trustworthy. */
    private const REQUIREMENT_TYPES = [
        ObjectType::BUSINESS_REQUIREMENT->value,
        ObjectType::USER_REQUIREMENT->value,
        ObjectType::FUNCTIONAL_REQUIREMENT->value,
        ObjectType::NON_FUNCTIONAL_REQUIREMENT->value,
    ];

    /** Valid test-result outcomes, worst-first (precedence when a requirement has many cases). */
    public const OUTCOMES = ['fail', 'blocked', 'pass'];

    public function __construct(
        private readonly ObjectGraphService $graph,
        private readonly TraceService $trace,
    ) {}

    /**
     * Build the V&V matrix for a project: each requirement with its test cases,
     * each case's latest outcome, and a derived verification status.
     *
     * @return array<string, mixed>
     */
    public function register(Project $project): array
    {
        $requirements = EngObject::forProject($project->id)
            ->whereIn('type', self::REQUIREMENT_TYPES)
            ->orderBy('ref')->get();

        $cases = EngObject::forProject($project->id)
            ->where('type', ObjectType::TEST_CASE->value)
            ->orderBy('ref')->get()->keyBy('id');

        $casesByRequirement = $this->casesByRequirement($project, $cases);
        $caseIds = $cases->keys()->all();
        $outcomes = $this->latestOutcomes($project, $caseIds);
        $defectsByCase = $this->openDefectsByCase($project, $caseIds);

        $rows = $requirements->map(function (EngObject $req) use ($casesByRequirement, $cases, $outcomes, $defectsByCase): array {
            $caseIds = $casesByRequirement[$req->id] ?? [];

            $caseRows = collect($caseIds)
                ->map(fn (int $id): ?array => $cases->has($id) ? [
                    'case' => $cases->get($id),
                    'outcome' => $outcomes[$id] ?? null,
                ] : null)
                ->filter()->values();

            $defects = collect($caseIds)
                ->flatMap(fn (int $id): array => $defectsByCase[$id] ?? [])
                ->values();

            return [
                'requirement' => $req,
                'cases' => $caseRows,
                'defects' => $defects,
                'status' => $this->statusFor($caseRows),
            ];
        });

        return [
            'project' => $project,
            'rows' => $rows,
            'summary' => $this->summarize($rows, $cases->count(), $outcomes),
        ];
    }

    /**
     * Add a test case that VERIFIES a requirement. The case inherits the
     * requirement's scope so it baselines alongside it.
     */
    public function addTestCase(EngObject $requirement, string $title, ?string $steps, User $user): EngObject
    {
        $case = $this->graph->create(
            ObjectType::TEST_CASE,
            (int) $requirement->tenant_id,
            (int) $requirement->project_id,
            $title,
            [
                'module_id' => $requirement->module_id,
                'stage_id' => $requirement->stage_id,
                'session_id' => $requirement->session_id,
                'owner_user_id' => $user->id,
                'source' => 'verification',
                'status' => ObjectStatus::NEEDS_CONFIRMATION,
                'classification' => $requirement->classification ?? 'internal',
                'body' => $steps,
                'attributes' => ['verifies' => $requirement->ref],
                'changed_by' => $user->id,
                'change_summary' => 'test case authored',
            ],
        );

        $this->trace->link($case, $requirement, RelationType::VERIFIES, null, $user->id);

        return $case;
    }

    /**
     * Record an execution result against a test case. Each result is a new
     * immutable TEST_RESULT object — history is preserved, the latest wins.
     */
    public function recordResult(EngObject $case, string $outcome, ?string $note, User $user): EngObject
    {
        if (! in_array($outcome, self::OUTCOMES, true)) {
            throw new \InvalidArgumentException("Invalid test outcome: {$outcome}");
        }

        $result = $this->graph->create(
            ObjectType::TEST_RESULT,
            (int) $case->tenant_id,
            (int) $case->project_id,
            ucfirst($outcome).' — '.$case->ref,
            [
                'module_id' => $case->module_id,
                'stage_id' => $case->stage_id,
                'session_id' => $case->session_id,
                'owner_user_id' => $user->id,
                'source' => 'verification',
                'status' => $outcome === 'pass' ? ObjectStatus::CONFIRMED_BY_EVIDENCE : ObjectStatus::DECISION_REQUIRED,
                'classification' => $case->classification ?? 'internal',
                'body' => $note,
                'attributes' => ['outcome' => $outcome, 'note' => $note],
                'changed_by' => $user->id,
                'change_summary' => "result recorded: {$outcome}",
            ],
        );

        $this->trace->link($result, $case, RelationType::TRACES_TO, $outcome, $user->id);

        return $result;
    }

    /**
     * Raise a defect against a (typically failed) test case. The DEFECT is a
     * graph object linked TRACES_TO the case, so it traces through to the
     * requirement the case verifies. Starts open.
     */
    public function raiseDefect(EngObject $case, string $title, ?string $detail, User $user): EngObject
    {
        $defect = $this->graph->create(
            ObjectType::DEFECT,
            (int) $case->tenant_id,
            (int) $case->project_id,
            $title,
            [
                'module_id' => $case->module_id,
                'stage_id' => $case->stage_id,
                'session_id' => $case->session_id,
                'owner_user_id' => $user->id,
                'source' => 'verification',
                'status' => ObjectStatus::DECISION_REQUIRED,
                'classification' => $case->classification ?? 'internal',
                'body' => $detail,
                'attributes' => ['state' => 'open', 'against' => $case->ref],
                'changed_by' => $user->id,
                'change_summary' => 'defect raised',
            ],
        );

        $this->trace->link($defect, $case, RelationType::TRACES_TO, 'defect', $user->id);

        return $defect;
    }

    /**
     * Resolve an open defect — a versioned state change (history preserved).
     */
    public function resolveDefect(EngObject $defect, ?string $note, User $user): EngObject
    {
        $attributes = ($defect->getAttribute('attributes') ?? []);
        $attributes['state'] = 'resolved';
        $attributes['resolution'] = $note;

        return $this->graph->update(
            $defect,
            ['attributes' => $attributes, 'status' => ObjectStatus::CONFIRMED_BY_EVIDENCE->value],
            $user->id,
            'defect resolved',
        );
    }

    /**
     * caseId => [DEFECT, ...] for defects still in the open state.
     *
     * @param  array<int, int>  $caseIds
     * @return array<int, array<int, EngObject>>
     */
    private function openDefectsByCase(Project $project, array $caseIds): array
    {
        if ($caseIds === []) {
            return [];
        }

        $edges = TraceRelationship::where('project_id', $project->id)
            ->where('relation_type', RelationType::TRACES_TO->value)
            ->whereIn('to_object_id', $caseIds)
            ->get();

        $defects = EngObject::whereIn('id', $edges->pluck('from_object_id')->unique()->all())
            ->where('type', ObjectType::DEFECT->value)
            ->get()->keyBy('id');

        $map = [];
        foreach ($edges as $edge) {
            $defect = $defects->get($edge->from_object_id);
            if ($defect === null) {
                continue;
            }
            if (($defect->getAttribute('attributes')['state'] ?? 'open') === 'open') {
                $map[$edge->to_object_id][] = $defect;
            }
        }

        return $map;
    }

    /**
     * requirementId => [testCaseId, ...] from VERIFIES edges (case → requirement).
     *
     * @param  Collection<int, EngObject>  $cases
     * @return array<int, array<int, int>>
     */
    private function casesByRequirement(Project $project, Collection $cases): array
    {
        $map = [];
        $edges = TraceRelationship::where('project_id', $project->id)
            ->where('relation_type', RelationType::VERIFIES->value)
            ->get();

        foreach ($edges as $edge) {
            if ($cases->has($edge->from_object_id)) {
                $map[$edge->to_object_id][] = $edge->from_object_id;
            }
        }

        return $map;
    }

    /**
     * caseId => latest outcome string, resolved by highest result id (newest run).
     *
     * @param  array<int, int>  $caseIds
     * @return array<int, string>
     */
    private function latestOutcomes(Project $project, array $caseIds): array
    {
        if ($caseIds === []) {
            return [];
        }

        $edges = TraceRelationship::where('project_id', $project->id)
            ->where('relation_type', RelationType::TRACES_TO->value)
            ->whereIn('to_object_id', $caseIds)
            ->get();

        $results = EngObject::whereIn('id', $edges->pluck('from_object_id')->unique()->all())
            ->where('type', ObjectType::TEST_RESULT->value)
            ->get()->keyBy('id');

        $outcomes = [];
        $bestId = [];

        foreach ($edges as $edge) {
            $result = $results->get($edge->from_object_id);
            if ($result === null) {
                continue;
            }

            $caseId = $edge->to_object_id;
            if (! isset($bestId[$caseId]) || $result->id > $bestId[$caseId]) {
                $bestId[$caseId] = (int) $result->id;
                $outcomes[$caseId] = $result->getAttribute('attributes')['outcome'] ?? null;
            }
        }

        return array_filter($outcomes, fn ($v): bool => $v !== null);
    }

    /**
     * Derive a requirement's verification status from its cases' latest outcomes.
     * Precedence: failing > blocked > pending (a case with no result yet) > verified.
     *
     * @param  Collection<int, array<string, mixed>>  $caseRows
     */
    private function statusFor(Collection $caseRows): string
    {
        if ($caseRows->isEmpty()) {
            return 'unverified';
        }

        $outcomes = $caseRows->pluck('outcome');

        if ($outcomes->contains('fail')) {
            return 'failing';
        }
        if ($outcomes->contains('blocked')) {
            return 'blocked';
        }
        if ($outcomes->contains(null)) {
            return 'pending';
        }

        return 'verified';
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @param  array<int, string>  $outcomes
     * @return array<string, mixed>
     */
    private function summarize(Collection $rows, int $caseCount, array $outcomes): array
    {
        $byStatus = $rows->countBy('status');
        $executed = count($outcomes);
        $passed = count(array_filter($outcomes, fn (string $o): bool => $o === 'pass'));

        return [
            'requirements' => $rows->count(),
            'verified' => $byStatus->get('verified', 0),
            'failing' => $byStatus->get('failing', 0),
            'blocked' => $byStatus->get('blocked', 0),
            'pending' => $byStatus->get('pending', 0),
            'unverified' => $byStatus->get('unverified', 0),
            'verified_pct' => $rows->count() > 0 ? round($byStatus->get('verified', 0) / $rows->count() * 100, 1) : 0.0,
            'cases' => $caseCount,
            'executed' => $executed,
            'pass_rate' => $executed > 0 ? round($passed / $executed * 100, 1) : 0.0,
            'open_defects' => $rows->sum(fn (array $row): int => $row['defects']->count()),
        ];
    }
}

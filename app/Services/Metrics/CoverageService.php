<?php

declare(strict_types=1);

namespace App\Services\Metrics;

use App\Enums\ObjectType;
use App\Models\Graph\EngObject;
use App\Models\Portfolio\Stage;
use App\Models\Project;
use App\Services\Graph\TraceReach;
use App\Services\Graph\TraceService;
use App\Services\Knowledge\KnowledgeResolver;
use Illuminate\Support\Collection;

/**
 * Coverage & completeness matrix (PRD §7 coverage rules, §17.1). The item-level
 * counterpart to MetricsService aggregates: shows exactly which requirements
 * lack evidence, which evidence is orphaned, and how each stage's question bank
 * is answered. All read-only and deterministic.
 */
class CoverageService
{
    /** Requirement-family types expected to trace back to evidence. */
    private const REQUIREMENT_TYPES = [
        ObjectType::BUSINESS_REQUIREMENT->value,
        ObjectType::USER_REQUIREMENT->value,
        ObjectType::FUNCTIONAL_REQUIREMENT->value,
        ObjectType::NON_FUNCTIONAL_REQUIREMENT->value,
    ];

    public function __construct(
        private readonly TraceService $trace,
        private readonly KnowledgeResolver $knowledge,
    ) {}

    /** @return array<string, mixed> */
    public function matrix(Project $project): array
    {
        // One in-memory graph snapshot reused by both reverse (requirement→source)
        // and forward (evidence→downstream) passes — no per-object DB walks.
        $reach = $this->trace->projectReach($project->id);
        $requirements = $this->requirementCoverage($project, $reach);
        $evidence = $this->evidenceUsage($project, $reach);

        $covered = $requirements->where('covered', true)->count();
        $used = $evidence->where('used', true)->count();

        return [
            'project' => $project,
            'requirements' => $requirements,
            'evidence' => $evidence,
            'stages' => $this->stageCoverage($project),
            'summary' => [
                'requirements' => $requirements->count(),
                'covered' => $covered,
                'uncovered' => $requirements->count() - $covered,
                'coverage_pct' => $this->pct($covered, $requirements->count()),
                'evidence' => $evidence->count(),
                'used' => $used,
                'orphan' => $evidence->count() - $used,
            ],
        ];
    }

    /**
     * Each requirement with the evidence/finding refs it traces back to.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function requirementCoverage(Project $project, TraceReach $reach): Collection
    {
        return EngObject::forProject($project->id)
            ->whereIn('type', self::REQUIREMENT_TYPES)
            ->orderBy('ref')->get()
            ->map(function (EngObject $req) use ($reach): array {
                $sources = collect($reach->reverse($req))
                    ->filter(fn (EngObject $o): bool => in_array($o->type, [ObjectType::EVIDENCE, ObjectType::FINDING], true))
                    ->pluck('ref')->values();

                return ['object' => $req, 'sources' => $sources, 'covered' => $sources->isNotEmpty()];
            });
    }

    /**
     * Each evidence object with the downstream refs it feeds (orphan if none).
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function evidenceUsage(Project $project, TraceReach $reach): Collection
    {
        return EngObject::forProject($project->id)
            ->where('type', ObjectType::EVIDENCE->value)
            ->orderBy('ref')->get()
            ->map(function (EngObject $ev) use ($reach): array {
                $downstream = collect($reach->forward($ev))
                    ->filter(fn (EngObject $o): bool => in_array(
                        $o->type,
                        [ObjectType::FINDING, ObjectType::BUSINESS_REQUIREMENT, ObjectType::USER_REQUIREMENT,
                            ObjectType::FUNCTIONAL_REQUIREMENT, ObjectType::NON_FUNCTIONAL_REQUIREMENT],
                        true,
                    ))
                    ->pluck('ref')->values();

                return ['object' => $ev, 'downstream' => $downstream, 'used' => $downstream->isNotEmpty()];
            });
    }

    /**
     * Per-stage question-bank coverage: how many methodology questions exist vs
     * requirements captured in that stage (PRD §7).
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function stageCoverage(Project $project): Collection
    {
        // One grouped query for requirement counts across all stages, instead of
        // a COUNT(*) per stage.
        $reqCounts = EngObject::where('project_id', $project->id)
            ->whereIn('type', self::REQUIREMENT_TYPES)
            ->whereNotNull('stage_id')
            ->selectRaw('stage_id, count(*) as c')
            ->groupBy('stage_id')
            ->pluck('c', 'stage_id');

        return Stage::where('project_id', $project->id)
            ->with('module')
            ->get()
            ->map(function (Stage $stage) use ($project, $reqCounts): array {
                // Pass $project (already loaded) so $stage->project doesn't lazy-load per row.
                $questions = $this->knowledge->questionBank($project, $stage->stage)->count();
                $requirements = (int) ($reqCounts[$stage->id] ?? 0);

                return [
                    'stage' => $stage,
                    'label' => $stage->stage->label(),
                    'module' => $stage->module?->name,
                    'questions' => $questions,
                    'requirements' => $requirements,
                    'coverage_pct' => $questions > 0 ? $this->pct(min($requirements, $questions), $questions) : null,
                ];
            })
            ->filter(fn (array $row): bool => $row['questions'] > 0 || $row['requirements'] > 0)
            ->values();
    }

    private function pct(int $part, int $whole): float
    {
        return $whole > 0 ? round($part / $whole * 100, 1) : 0.0;
    }
}

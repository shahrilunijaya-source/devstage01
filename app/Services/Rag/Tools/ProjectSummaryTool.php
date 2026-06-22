<?php

namespace App\Services\Rag\Tools;

use App\Models\Project;
use App\Services\MoneySummaryService;
use App\Services\ProjectCalculator;

class ProjectSummaryTool implements ChatTool
{
    use ScopesProjects;

    public function __construct(private MoneySummaryService $money) {}

    public function name(): string
    {
        return 'project_summary';
    }

    public function description(): string
    {
        return 'A one-shot status snapshot for a single project: health, planned vs actual %, financials (budget/expenses/income), and open/high issue counts. Use for "give me the status of project X", "summarise project X".';
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'project_id' => ['type' => 'integer', 'description' => 'The project to summarise.'],
            ],
            'required' => ['project_id'],
        ];
    }

    public function handle(array $input, array $allowedProjectIds): array
    {
        $requested = isset($input['project_id']) ? (int) $input['project_id'] : null;

        // A named-but-unauthorised project must error, never silently summarise a
        // different (in-scope) project — that would mislead the user.
        if ($requested !== null && ! in_array($requested, $allowedProjectIds, true)) {
            return ['error' => 'That project is not in your accessible set.'];
        }

        $ids = $this->resolveProjectIds($input, $allowedProjectIds);
        $project = $requested !== null
            ? Project::query()->whereIn('id', $ids)->find($requested)
            : Project::query()->whereIn('id', $ids)->first();

        if (! $project) {
            return ['error' => 'No accessible project matched.'];
        }

        $calc = new ProjectCalculator($project);
        $open = $project->issues()->whereNotIn('status', ['resolved', 'closed'])->get();

        return [
            'project_id' => $project->id,
            'code' => $project->code,
            'name' => $project->name,
            'health' => $calc->projectHealthFlag(),
            'planned_pct' => round($calc->overallPlannedPct() * 100, 1),
            'actual_pct' => round($calc->overallActualPct() * 100, 1),
            'variance_pct' => round($calc->overallVariancePct() * 100, 1),
            'financials' => $this->money->forProject($project),
            'issues' => [
                'open' => $open->count(),
                'high' => $open->whereIn('severity', ['high', 'critical'])->count(),
            ],
        ];
    }
}

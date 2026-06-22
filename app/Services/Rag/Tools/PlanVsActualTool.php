<?php

namespace App\Services\Rag\Tools;

use App\Models\Project;
use App\Services\ProjectCalculator;

class PlanVsActualTool implements ChatTool
{
    use ScopesProjects;

    public function name(): string
    {
        return 'plan_vs_actual';
    }

    public function description(): string
    {
        return 'Get computed planned % vs actual % progress and schedule variance/health for one or more projects. Use for "% plan vs actual", "are we behind schedule", "project health".';
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'project_id' => ['type' => 'integer', 'description' => 'Optional. Omit for all projects in scope.'],
            ],
        ];
    }

    public function handle(array $input, array $allowedProjectIds): array
    {
        $ids = $this->resolveProjectIds($input, $allowedProjectIds);

        $projects = Project::query()->whereIn('id', $ids)->get()->map(function (Project $p) {
            $calc = new ProjectCalculator($p);

            return [
                'project_id' => $p->id,
                'code' => $p->code,
                'planned_pct' => round($calc->overallPlannedPct() * 100, 1),
                'actual_pct' => round($calc->overallActualPct() * 100, 1),
                'variance_pct' => round($calc->overallVariancePct() * 100, 1),
                'health' => $calc->projectHealthFlag(),
            ];
        });

        return ['projects' => $projects->values()->all()];
    }
}

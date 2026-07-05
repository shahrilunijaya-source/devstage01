<?php

namespace App\Services\Rag\Tools;

use App\Models\Graph\EngObject;
use App\Models\Portfolio\Stage;
use App\Models\Project;

/**
 * Live status snapshot of one URSB project: stage progress, baselines, and
 * object counts by type/status straight from the canonical graph. Replaces the
 * Track PMS financial summary tool, which depended on removed services.
 */
class ProjectStatusTool implements ChatTool
{
    use ScopesProjects;

    public function name(): string
    {
        return 'project_status';
    }

    public function description(): string
    {
        return 'A live status snapshot for a single project: lifecycle stage statuses per module, baselined-stage count, and requirement/risk/issue/defect object counts by status. Use for "status of project X", "how far along is X", "what is baselined in X".';
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

        // A named-but-unauthorised project must error, never silently summarise
        // a different (in-scope) project — that would mislead the user.
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

        $stages = Stage::query()
            ->where('project_id', $project->id)
            ->with('module:id,name')
            ->orderBy('module_id')
            ->orderBy('id')
            ->get();

        $objectCounts = EngObject::query()
            ->where('project_id', $project->id)
            ->selectRaw('type, status, count(*) as n')
            ->groupBy('type', 'status')
            ->get()
            ->map(fn ($row): array => [
                'type' => $row->type->value,
                'status' => $row->status->value,
                'count' => (int) $row->n,
            ])
            ->all();

        return [
            'project_id' => $project->id,
            'code' => $project->code,
            'name' => $project->name,
            'stages' => $stages->map(fn (Stage $s): array => [
                'module' => $s->module?->name,
                'stage' => $s->stage->value,
                'status' => $s->status,
            ])->all(),
            'baselined_stages' => $stages->where('status', 'baselined')->count(),
            'object_counts' => $objectCounts,
        ];
    }
}

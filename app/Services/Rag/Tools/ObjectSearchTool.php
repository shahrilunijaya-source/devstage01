<?php

namespace App\Services\Rag\Tools;

use App\Models\Graph\EngObject;
use App\Models\Project;

/**
 * Find canonical engineering objects (requirements, risks, decisions, ...) by
 * reference or keyword across the caller's accessible projects. Read-only,
 * scoped hard to $allowedProjectIds.
 */
class ObjectSearchTool implements ChatTool
{
    use ScopesProjects;

    private const MAX_RESULTS = 10;

    public function name(): string
    {
        return 'object_search';
    }

    public function description(): string
    {
        return 'Search engineering objects (requirements, risks, issues, decisions, test cases, design components) by reference (e.g. "BRS-REQ-0001") or keyword. Returns ref, type, status and title. Use for "find requirement about X", "what is RISK-0002", "list open risks mentioning Y".';
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string', 'description' => 'Reference or keyword to search for.'],
                'project_id' => ['type' => 'integer', 'description' => 'Optional: restrict to one project.'],
            ],
            'required' => ['query'],
        ];
    }

    public function handle(array $input, array $allowedProjectIds): array
    {
        $query = trim((string) ($input['query'] ?? ''));
        if ($query === '') {
            return ['error' => 'Empty search query.'];
        }

        $requested = isset($input['project_id']) ? (int) $input['project_id'] : null;
        if ($requested !== null && ! in_array($requested, $allowedProjectIds, true)) {
            return ['error' => 'That project is not in your accessible set.'];
        }

        $ids = $this->resolveProjectIds($input, $allowedProjectIds);
        if ($ids === []) {
            return ['results' => []];
        }

        $term = $this->escapeLike(mb_substr($query, 0, 100));

        $objects = EngObject::query()
            ->whereIn('project_id', $ids)
            ->where(function ($q) use ($term): void {
                $q->where('ref', 'like', "%{$term}%")
                    ->orWhere('title', 'like', "%{$term}%");
            })
            ->orderBy('ref')
            ->limit(self::MAX_RESULTS)
            ->get(['id', 'ref', 'type', 'status', 'title', 'project_id']);

        $codes = Project::whereIn('id', $objects->pluck('project_id')->unique())->pluck('code', 'id');

        return [
            'results' => $objects->map(fn (EngObject $o): array => [
                'ref' => $o->ref,
                'type' => $o->type->value,
                'status' => $o->status->value,
                'title' => $o->title,
                'project' => $codes[$o->project_id] ?? null,
            ])->all(),
        ];
    }

    private function escapeLike(string $value): string
    {
        return addcslashes($value, '%_\\');
    }
}

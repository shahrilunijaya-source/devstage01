<?php

namespace App\Services\Rag\Tools;

use App\Models\Issue;

class QueryIssuesTool implements ChatTool
{
    use ScopesProjects;

    private const OPEN_EXCLUDES = ['resolved', 'closed'];

    public function name(): string
    {
        return 'query_issues';
    }

    public function description(): string
    {
        return 'List or count project issues. Filter by status (open|resolved|closed|all), severity, or minimum age in days. Use for "list issues", "how many issues", "unresolved issues older than N days".';
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'project_id' => ['type' => 'integer', 'description' => 'Optional. Omit to search all projects in scope.'],
                'status' => ['type' => 'string', 'enum' => ['open', 'resolved', 'closed', 'all'], 'description' => 'Default open.'],
                'severity' => ['type' => 'string', 'enum' => ['low', 'medium', 'high', 'critical']],
                'min_age_days' => ['type' => 'integer', 'description' => 'Only issues reported at least this many days ago.'],
            ],
        ];
    }

    public function handle(array $input, array $allowedProjectIds): array
    {
        $ids = $this->resolveProjectIds($input, $allowedProjectIds);
        $status = $input['status'] ?? 'open';

        $q = Issue::query()->whereIn('project_id', $ids);

        if ($status === 'open') {
            $q->whereNotIn('status', self::OPEN_EXCLUDES);
        } elseif ($status !== 'all') {
            $q->where('status', $status);
        }
        if (! empty($input['severity'])) {
            $q->where('severity', $input['severity']);
        }
        if (! empty($input['min_age_days'])) {
            $q->where('reported_date', '<=', now()->subDays((int) $input['min_age_days']));
        }

        $issues = $q->orderByDesc('reported_date')->limit(50)->get();

        return [
            'count' => $issues->count(),
            'issues' => $issues->map(fn (Issue $i) => [
                'id' => $i->id,
                'project_id' => $i->project_id,
                'title' => $i->title,
                'status' => $i->status,
                'severity' => $i->severity,
                'reported_date' => $i->reported_date?->format('d/m/Y'),
                'resolution' => $i->resolution,
            ])->all(),
        ];
    }
}

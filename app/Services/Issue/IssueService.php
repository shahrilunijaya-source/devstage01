<?php

declare(strict_types=1);

namespace App\Services\Issue;

use App\Enums\ObjectStatus;
use App\Enums\ObjectType;
use App\Models\Graph\EngObject;
use App\Models\Project;
use App\Models\User;
use App\Services\Graph\ObjectGraphService;
use Illuminate\Support\Collection;

/**
 * Project issue log (PRD §17). A lightweight operational register for blockers,
 * actions and concerns — distinct from a DEFECT, which is bound to a failed
 * verification. Issues are project-level graph objects; no new tables.
 */
class IssueService
{
    public const SEVERITIES = ['high', 'medium', 'low'];

    public function __construct(private readonly ObjectGraphService $graph) {}

    /** @return array<string, mixed> */
    public function forProject(Project $project): array
    {
        $issues = EngObject::forProject($project->id)
            ->where('type', ObjectType::ISSUE->value)
            ->with('owner:id,name')
            ->get();

        [$open, $resolved] = $issues->partition(
            fn (EngObject $i): bool => ($i->getAttribute('attributes')['state'] ?? 'open') === 'open'
        );

        return [
            'project' => $project,
            'open' => $this->sortBySeverity($open),
            'resolved' => $resolved->sortByDesc('id')->values(),
            'summary' => [
                'open' => $open->count(),
                'resolved' => $resolved->count(),
                'high' => $open->filter(fn (EngObject $i): bool => ($i->getAttribute('attributes')['severity'] ?? null) === 'high')->count(),
            ],
        ];
    }

    /** Raise a new open issue. */
    public function raise(Project $project, string $title, ?string $body, string $severity, User $user): EngObject
    {
        if (! in_array($severity, self::SEVERITIES, true)) {
            throw new \InvalidArgumentException("Invalid severity: {$severity}");
        }

        return $this->graph->create(
            ObjectType::ISSUE,
            (int) $project->tenant_id,
            $project->id,
            $title,
            [
                'owner_user_id' => $user->id,
                'source' => 'issue',
                'status' => ObjectStatus::DECISION_REQUIRED,
                'impact' => $severity,
                'body' => $body,
                'attributes' => ['state' => 'open', 'severity' => $severity],
                'changed_by' => $user->id,
                'change_summary' => 'issue raised',
            ],
        );
    }

    /** Resolve an open issue — a versioned state change. */
    public function resolve(EngObject $issue, ?string $note, User $user): EngObject
    {
        $attributes = $issue->getAttribute('attributes') ?? [];
        $attributes['state'] = 'resolved';
        $attributes['resolution'] = $note;

        return $this->graph->update(
            $issue,
            ['attributes' => $attributes, 'status' => ObjectStatus::CONFIRMED_BY_EVIDENCE->value],
            $user->id,
            'issue resolved',
        );
    }

    /**
     * @param  Collection<int, EngObject>  $issues
     * @return Collection<int, EngObject>
     */
    private function sortBySeverity(Collection $issues): Collection
    {
        $rank = array_flip(self::SEVERITIES);

        return $issues->sortBy([
            fn (EngObject $i): int => $rank[$i->getAttribute('attributes')['severity'] ?? 'low'] ?? 99,
            fn (EngObject $i): int => -$i->id,
        ])->values();
    }
}

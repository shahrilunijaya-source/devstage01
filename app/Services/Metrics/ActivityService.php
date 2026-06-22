<?php

declare(strict_types=1);

namespace App\Services\Metrics;

use App\Models\Graph\ChangeRequest;
use App\Models\Graph\EngObject;
use App\Models\Graph\ObjectVersion;
use App\Models\Portfolio\StageBaseline;
use App\Models\Project;
use App\Models\User;
use App\Services\AccessControl\PolicyDecisionPoint;
use Illuminate\Support\Collection;

/**
 * Chronological audit feed for a project (PRD §12, §17). Merges the immutable
 * object-version history, stage baselines and change requests into one
 * timeline. Object events are filtered to what the viewer may see (deny-by-
 * default extends to the audit trail).
 */
class ActivityService
{
    public function __construct(private readonly PolicyDecisionPoint $pdp) {}

    /**
     * @return Collection<int, array<string, mixed>> newest first
     */
    public function feed(Project $project, User $user, int $limit = 100): Collection
    {
        return $this->objectEvents($project, $user)
            ->concat($this->baselineEvents($project))
            ->concat($this->changeEvents($project))
            ->filter(fn (array $e): bool => $e['at'] !== null)
            ->sortByDesc(fn (array $e) => $e['at']->timestamp)
            ->take($limit)
            ->values();
    }

    /** @return Collection<int, array<string, mixed>> */
    private function objectEvents(Project $project, User $user): Collection
    {
        $visibleIds = EngObject::forProject($project->id)->get()
            ->filter(fn (EngObject $o): bool => $this->pdp->can($user, 'view', $o)->permitted)
            ->keyBy('id');

        if ($visibleIds->isEmpty()) {
            return collect();
        }

        return ObjectVersion::with('changedBy')
            ->whereIn('object_id', $visibleIds->keys())
            ->latest('created_at')
            ->limit(200)
            ->get()
            ->map(function (ObjectVersion $v) use ($visibleIds): array {
                $object = $visibleIds->get($v->object_id);

                return [
                    'at' => $v->created_at,
                    'kind' => $v->version === 1 ? 'created' : 'updated',
                    'actor' => $v->changedBy?->name,
                    'ref' => $object?->ref ?? '—',
                    'summary' => $v->change_summary ?? '—',
                    'link' => $object ? route('objects.show', $object) : null,
                ];
            });
    }

    /** @return Collection<int, array<string, mixed>> */
    private function baselineEvents(Project $project): Collection
    {
        return StageBaseline::with('approver')
            ->where('project_id', $project->id)
            ->get()
            ->map(fn (StageBaseline $b): array => [
                'at' => $b->approved_at ?? $b->created_at,
                'kind' => 'baseline',
                'actor' => $b->approver?->name,
                'ref' => $b->version_label,
                'summary' => "Stage baseline frozen ({$b->status})",
                'link' => route('baselines.show', $b),
            ]);
    }

    /** @return Collection<int, array<string, mixed>> */
    private function changeEvents(Project $project): Collection
    {
        return ChangeRequest::with('raisedBy')
            ->where('project_id', $project->id)
            ->get()
            ->map(fn (ChangeRequest $c): array => [
                'at' => $c->applied_at ?? $c->decided_at ?? $c->created_at,
                'kind' => "change {$c->status}",
                'actor' => $c->raisedBy?->name,
                'ref' => $c->ref,
                'summary' => $c->title,
                'link' => route('changes.show', $c),
            ]);
    }
}

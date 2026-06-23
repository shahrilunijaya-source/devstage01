<?php

declare(strict_types=1);

namespace App\Services\Graph;

use App\Enums\RelationType;
use App\Models\Graph\EngObject;
use App\Models\Graph\TraceRelationship;

/**
 * Build and walk the traceability graph (PRD §12.3). Edges are idempotent;
 * walks are cycle-safe.
 */
class TraceService
{
    public function link(
        EngObject $from,
        EngObject $to,
        RelationType $relation,
        ?string $note = null,
        ?int $createdBy = null,
    ): TraceRelationship {
        return TraceRelationship::firstOrCreate(
            [
                'from_object_id' => $from->id,
                'to_object_id' => $to->id,
                'relation_type' => $relation->value,
            ],
            [
                'project_id' => $from->project_id,
                'note' => $note,
                'created_by' => $createdBy,
            ],
        );
    }

    /**
     * Snapshot the whole project graph into memory (2 queries) so many objects
     * can be walked without per-object DB access. Use when walking more than a
     * couple of start nodes (matrix/coverage registers).
     */
    public function projectReach(int $projectId): TraceReach
    {
        $objects = EngObject::where('project_id', $projectId)->get()->keyBy('id')->all();

        $out = [];
        $in = [];
        foreach (TraceRelationship::where('project_id', $projectId)->get(['from_object_id', 'to_object_id']) as $edge) {
            $out[$edge->from_object_id][] = $edge->to_object_id;
            $in[$edge->to_object_id][] = $edge->from_object_id;
        }

        return new TraceReach($objects, $out, $in);
    }

    /**
     * Forward traceability: objects reachable by following outgoing edges,
     * depth-first, in visit order (excludes the start object).
     *
     * @return array<int, EngObject>
     */
    public function forwardTrace(EngObject $start): array
    {
        return $this->walk($start, 'forward');
    }

    /**
     * Reverse traceability: objects reachable by following incoming edges.
     *
     * @return array<int, EngObject>
     */
    public function reverseTrace(EngObject $start): array
    {
        return $this->walk($start, 'reverse');
    }

    /**
     * @return array<int, EngObject>
     */
    private function walk(EngObject $start, string $direction): array
    {
        // Traceability never crosses a project boundary — scope every hop to the
        // start object's project so a stray cross-project/tenant edge can't leak
        // foreign objects into a walk (and its exports).
        $projectId = $start->project_id;

        $visited = [$start->id => true];
        $result = [];
        $frontier = [$start->id];

        // Breadth-first by FRONTIER: one edge query + one node query per depth
        // level (2 × depth) instead of one EngObject::find() per node (O(N)).
        // Walks are shallow, so this collapses the per-requirement matrix/coverage
        // explosions from hundreds of queries to a handful.
        while ($frontier !== []) {
            $edges = ($direction === 'forward'
                ? TraceRelationship::whereIn('from_object_id', $frontier)
                : TraceRelationship::whereIn('to_object_id', $frontier))
                ->where('project_id', $projectId)
                ->get(['from_object_id', 'to_object_id']);

            $nextIds = [];
            foreach ($edges as $edge) {
                $nextId = $direction === 'forward' ? $edge->to_object_id : $edge->from_object_id;
                if (! isset($visited[$nextId])) {
                    $visited[$nextId] = true;
                    $nextIds[] = $nextId;
                }
            }

            if ($nextIds === []) {
                break;
            }

            $objects = EngObject::where('project_id', $projectId)
                ->whereIn('id', $nextIds)->get()->keyBy('id');

            $frontier = [];
            foreach ($nextIds as $nextId) {           // preserve discovery order
                $obj = $objects->get($nextId);
                if ($obj !== null) {                  // skip soft-deleted nodes, don't expand them
                    $result[] = $obj;
                    $frontier[] = $nextId;
                }
            }
        }

        return $result;
    }
}

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
        $visited = [$start->id => true];
        $result = [];
        $queue = [$start];

        // Traceability never crosses a project boundary — scope every hop to the
        // start object's project so a stray cross-project/tenant edge can't leak
        // foreign objects into a walk (and its exports).
        $projectId = $start->project_id;

        while ($queue !== []) {
            $current = array_shift($queue);

            $edges = ($direction === 'forward'
                ? TraceRelationship::where('from_object_id', $current->id)
                : TraceRelationship::where('to_object_id', $current->id))
                ->where('project_id', $projectId)
                ->get();

            foreach ($edges as $edge) {
                $nextId = $direction === 'forward' ? $edge->to_object_id : $edge->from_object_id;

                if (isset($visited[$nextId])) {
                    continue;
                }

                $visited[$nextId] = true;
                $next = EngObject::where('project_id', $projectId)->find($nextId);

                if ($next !== null) {
                    $result[] = $next;
                    $queue[] = $next;
                }
            }
        }

        return $result;
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Graph;

use App\Models\Graph\EngObject;

/**
 * An in-memory snapshot of one project's traceability graph. Built once (two
 * queries: all objects + all edges), then walked any number of times with zero
 * further DB access — replacing the per-requirement reverseTrace() explosion in
 * the matrix/coverage registers (O(R × depth) queries → 2).
 *
 * Walk semantics match TraceService::forwardTrace/reverseTrace exactly:
 * breadth-first, discovery order, excludes the start node, cycle-safe,
 * project-scoped (foreign nodes can't appear — they were never loaded).
 */
final class TraceReach
{
    /**
     * @param  array<int, EngObject>  $objects  keyed by id (project-scoped)
     * @param  array<int, array<int,int>>  $out  from_id => [to_id, ...]
     * @param  array<int, array<int,int>>  $in  to_id   => [from_id, ...]
     */
    public function __construct(
        private readonly array $objects,
        private readonly array $out,
        private readonly array $in,
    ) {}

    /** @return array<int, EngObject> downstream reach (outgoing edges) */
    public function forward(EngObject $start): array
    {
        return $this->walk((int) $start->id, $this->out);
    }

    /** @return array<int, EngObject> upstream reach (incoming edges) */
    public function reverse(EngObject $start): array
    {
        return $this->walk((int) $start->id, $this->in);
    }

    /**
     * @param  array<int, array<int,int>>  $adj
     * @return array<int, EngObject>
     */
    private function walk(int $startId, array $adj): array
    {
        $visited = [$startId => true];
        $result = [];
        $frontier = [$startId];

        while ($frontier !== []) {
            $next = [];
            foreach ($frontier as $id) {
                foreach ($adj[$id] ?? [] as $neighbourId) {
                    if (isset($visited[$neighbourId])) {
                        continue;
                    }
                    $visited[$neighbourId] = true;
                    if (isset($this->objects[$neighbourId])) {   // skip soft-deleted / out-of-project
                        $result[] = $this->objects[$neighbourId];
                        $next[] = $neighbourId;
                    }
                }
            }
            $frontier = $next;
        }

        return $result;
    }
}

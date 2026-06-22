<?php

declare(strict_types=1);

namespace App\Services\Graph;

use App\Enums\ObjectType;
use App\Models\Graph\ObjectIdSequence;
use Illuminate\Support\Facades\DB;

/**
 * Mints permanent human-readable IDs (PRD §12.6): {PREFIX}-{NNNN}, zero-padded,
 * sequence allocated per (project, type) under a row lock. Two projects each
 * start at EVD-0001 — uniqueness is per-project (objects.unique[project_id, ref]).
 */
class ObjectIdGenerator
{
    public function next(int $projectId, ObjectType $type): string
    {
        return DB::transaction(function () use ($projectId, $type): string {
            ObjectIdSequence::query()->firstOrCreate(
                ['project_id' => $projectId, 'type' => $type->value],
                ['next_seq' => 1],
            );

            $seq = ObjectIdSequence::query()
                ->where('project_id', $projectId)
                ->where('type', $type->value)
                ->lockForUpdate()
                ->first();

            $n = $seq->next_seq;
            $seq->update(['next_seq' => $n + 1]);

            return sprintf('%s-%04d', $type->idPrefix(), $n);
        });
    }
}

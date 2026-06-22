<?php

declare(strict_types=1);

namespace App\Services\Graph;

use App\Models\Graph\BaselineObject;
use App\Models\Graph\EngObject;
use App\Models\Portfolio\Stage;
use App\Models\Portfolio\StageBaseline;
use Illuminate\Support\Facades\DB;

/**
 * Freeze approved session outputs into an immutable stage baseline (PRD §5, §12.5).
 * Re-baselining mints a new sequence and supersedes the prior baseline.
 */
class BaselineService
{
    /**
     * @param  array<string, mixed>  $opts  knowledge_book_version, approved_by, created_by
     */
    public function baseline(Stage $stage, array $opts = []): StageBaseline
    {
        return DB::transaction(function () use ($stage, $opts): StageBaseline {
            $approvedSessionIds = $stage->sessions()->where('status', 'approved')->pluck('id')->all();

            $objects = EngObject::query()
                ->where('stage_id', $stage->id)
                ->where(function ($q) use ($approvedSessionIds): void {
                    $q->whereNull('session_id');
                    if ($approvedSessionIds !== []) {
                        $q->orWhereIn('session_id', $approvedSessionIds);
                    }
                })
                ->get();

            $sequence = (int) ($stage->baselines()->max('sequence') ?? 0) + 1;

            // Supersede the prior active baseline, if any.
            $stage->baselines()->where('status', 'approved')->update(['status' => 'superseded']);

            $baseline = StageBaseline::create([
                'stage_id' => $stage->id,
                'project_id' => $stage->project_id,
                'module_id' => $stage->module_id,
                'version_label' => sprintf('%s v%d.0', $stage->stage->value, $sequence),
                'sequence' => $sequence,
                'status' => 'approved',
                'knowledge_book_version' => $opts['knowledge_book_version'] ?? null,
                'snapshot_meta' => [
                    'object_count' => $objects->count(),
                    'session_ids' => $approvedSessionIds,
                ],
                'approved_by' => $opts['approved_by'] ?? null,
                'created_by' => $opts['created_by'] ?? null,
                'approved_at' => now(),
            ]);

            foreach ($objects as $object) {
                BaselineObject::create([
                    'stage_baseline_id' => $baseline->id,
                    'object_id' => $object->id,
                    'object_version' => (int) $object->current_version,
                    'ref' => $object->ref,
                    'type' => $object->type->value,
                ]);

                $object->update(['baseline_id' => $baseline->id]);
            }

            $stage->update([
                'status' => 'baselined',
                'current_baseline_id' => $baseline->id,
                'gate_passed_at' => now(),
            ]);

            return $baseline;
        });
    }
}

<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ObjectType;
use App\Models\Graph\EngObject;
use App\Models\SystemSetting;
use App\Services\Knowledge\EvidenceIndexer;
use Illuminate\Console\Command;

/**
 * Backfills the RAG index for evidence captured before embeddings were enabled,
 * or to repair the index after a key change. Indexing at capture time is the
 * primary path; this sweeps the rest. Idempotent — re-running re-indexes cleanly.
 */
class RagIndexEvidence extends Command
{
    protected $signature = 'rag:index-evidence {--project= : Limit to a single project id}';

    protected $description = 'Index all session evidence into the RAG substrate for retrieval';

    public function handle(EvidenceIndexer $indexer): int
    {
        if (blank(SystemSetting::get('voyage_api_key'))) {
            $this->warn('Voyage API key is not configured — nothing to index.');

            return self::SUCCESS;
        }

        $query = EngObject::where('type', ObjectType::EVIDENCE->value)
            ->when($this->option('project'), fn ($q, $id) => $q->where('project_id', $id));

        $objects = 0;
        $chunks = 0;

        $query->chunkById(100, function ($batch) use ($indexer, &$objects, &$chunks): void {
            foreach ($batch as $evidence) {
                $chunks += $indexer->index($evidence);
                $objects++;
            }
        });

        $this->info("Indexed {$objects} evidence object(s) into {$chunks} chunk(s).");

        return self::SUCCESS;
    }
}

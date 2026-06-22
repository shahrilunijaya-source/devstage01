<?php

declare(strict_types=1);

namespace App\Services\Knowledge;

use App\Models\Knowledge\ProjectKnowledgeItem;
use App\Models\RagChunk;
use App\Services\Rag\Chunker;
use App\Services\Rag\VoyageClient;
use Illuminate\Support\Facades\DB;

/**
 * Indexes structured knowledge into the RAG substrate (PRD §7.5), reusing the
 * existing Chunker + Voyage embedding pipeline. Project-scoped knowledge is
 * tenant-isolated; global KRISA indexing is a follow-up (requires a nullable
 * rag_chunks.project_id and is not needed for the Phase-1 slice).
 */
class KnowledgeIndexer
{
    public function __construct(
        private readonly Chunker $chunker,
        private readonly VoyageClient $voyage,
    ) {}

    /** Index a tenant/project-isolated knowledge item (PRD §7.3). */
    public function indexProjectItem(ProjectKnowledgeItem $item): int
    {
        $text = trim(($item->title ?? '').' '.($item->body ?? ''));

        if ($text === '') {
            return 0;
        }

        $pieces = $this->chunker->chunk($text);

        if ($pieces === []) {
            return 0;
        }

        $vectors = $this->voyage->embed(array_map(fn ($p) => $p['text'], $pieces), 'document');

        DB::transaction(function () use ($item, $pieces, $vectors): void {
            RagChunk::query()
                ->where('project_id', $item->project_id)
                ->where('source_type', 'knowledge')
                ->where('source_id', $item->id)
                ->delete();

            foreach ($pieces as $i => $piece) {
                RagChunk::create([
                    'project_id' => $item->project_id,
                    'tenant_id' => $item->tenant_id,
                    'scope' => 'project',
                    'source_type' => 'knowledge',
                    'source_id' => $item->id,
                    'source_label' => 'Knowledge: '.$item->title,
                    'chunk_text' => $piece['text'],
                    'embedding' => $vectors[$i] ?? [],
                    'token_count' => $piece['tokens'],
                    'content_hash' => hash('sha256', 'knowledge|'.$item->id.'|'.$piece['text']),
                ]);
            }
        });

        return count($pieces);
    }
}

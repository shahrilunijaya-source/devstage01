<?php

namespace App\Services\Rag;

use App\Models\Project;
use App\Models\RagChunk;
use Illuminate\Support\Facades\DB;

/**
 * Write side of RAG: turn a source (DB row or extracted document text) into
 * embedded chunks in rag_chunks. Idempotent — re-indexing unchanged content is a
 * no-op (content_hash match), so observer-driven re-saves and Drive re-syncs are
 * cheap and never duplicate rows.
 */
class RagIndexer
{
    public function __construct(
        private Chunker $chunker,
        private VoyageClient $voyage,
        private SourceTextBuilder $builder,
    ) {}

    /**
     * Re-index one structured source row (comment, issue, weekly_update, …) or
     * the project_meta synthetic chunk. Deletes the source's chunks when the row
     * is gone or has no indexable text.
     */
    public function reindexSource(int $projectId, string $sourceType, ?int $sourceId): void
    {
        if ($sourceType === 'project_meta') {
            $row = Project::find($projectId);
        } else {
            $cls = SourceTextBuilder::modelClassFor($sourceType);
            $row = $cls ? $cls::find($sourceId) : null;
        }

        if (! $row) {
            $this->deleteSource($projectId, $sourceType, $sourceId);

            return;
        }

        ['label' => $label, 'text' => $text] = $this->builder->build($sourceType, $row);

        if (trim($text) === '') {
            $this->deleteSource($projectId, $sourceType, $sourceId);

            return;
        }

        $this->store($projectId, $sourceType, $sourceId, $label, $text);
    }

    /**
     * Store extracted document text (used by the Drive sync path). source_id is
     * the rag_documents.id.
     */
    public function indexDocument(int $projectId, int $ragDocumentId, string $label, string $text): int
    {
        return $this->store($projectId, 'document', $ragDocumentId, $label, $text);
    }

    /**
     * Chunk, hash-compare, and (only if changed) embed + replace the chunks for
     * one source. Returns the number of chunks now stored.
     */
    private function store(int $projectId, string $sourceType, ?int $sourceId, string $label, string $text): int
    {
        $pieces = $this->chunker->chunk($text);
        if ($pieces === []) {
            $this->deleteSource($projectId, $sourceType, $sourceId);

            return 0;
        }

        $multi = count($pieces) > 1;
        $desired = [];
        foreach ($pieces as $i => $piece) {
            $hash = hash('sha256', $sourceType.'|'.($sourceId ?? 'null').'|'.$piece['text']);
            $desired[$hash] = [
                'label' => $multi ? $label.' (part '.($i + 1).')' : $label,
                'text' => $piece['text'],
                'tokens' => $piece['tokens'],
            ];
        }

        $existing = RagChunk::query()
            ->where('project_id', $projectId)
            ->where('source_type', $sourceType)
            ->when($sourceId === null, fn ($q) => $q->whereNull('source_id'), fn ($q) => $q->where('source_id', $sourceId))
            ->pluck('content_hash')
            ->all();

        sort($existing);
        $desiredHashes = array_keys($desired);
        sort($desiredHashes);

        // Unchanged → skip Voyage entirely.
        if ($existing === $desiredHashes) {
            return count($desired);
        }

        $vectors = $this->voyage->embed(array_map(fn ($d) => $d['text'], array_values($desired)), 'document');

        DB::transaction(function () use ($projectId, $sourceType, $sourceId, $desired, $vectors) {
            $this->deleteSource($projectId, $sourceType, $sourceId);

            $i = 0;
            foreach ($desired as $hash => $d) {
                RagChunk::create([
                    'project_id' => $projectId,
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                    'source_label' => $d['label'],
                    'chunk_text' => $d['text'],
                    'embedding' => $vectors[$i] ?? [],
                    'token_count' => $d['tokens'],
                    'content_hash' => $hash,
                ]);
                $i++;
            }
        });

        return count($desired);
    }

    public function deleteSource(int $projectId, string $sourceType, ?int $sourceId): void
    {
        RagChunk::query()
            ->where('project_id', $projectId)
            ->where('source_type', $sourceType)
            ->when($sourceId === null, fn ($q) => $q->whereNull('source_id'), fn ($q) => $q->where('source_id', $sourceId))
            ->delete();
    }
}

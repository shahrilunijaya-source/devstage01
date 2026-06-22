<?php

namespace App\Services\Rag;

use App\Models\RagChunk;
use Illuminate\Support\Collection;

/**
 * Read side of RAG: fetch project-scoped chunks, rank them against the question
 * vector with cosine similarity (computed in PHP — viable at this scale), and
 * assemble a token-budgeted, citation-labeled context block for the LLM.
 */
class RagRetriever
{
    /**
     * Candidate chunks for the given projects, plus optionally the shared global
     * KRISA knowledge. Project chunks are ALWAYS gated by project_id — the
     * access-control boundary — so cross-project/cross-tenant leakage is
     * impossible. Global chunks are read-only methodology, safe to share.
     *
     * @param  array<int, int>  $projectIds
     */
    public function scopedChunks(array $projectIds, ?int $tenantId = null, bool $includeGlobal = true): Collection
    {
        $hasProjects = $projectIds !== [];

        if (! $hasProjects && ! $includeGlobal) {
            return collect();
        }

        return RagChunk::query()
            ->where(function ($query) use ($projectIds, $hasProjects, $includeGlobal): void {
                if ($hasProjects) {
                    $query->where(function ($q) use ($projectIds): void {
                        $q->where('scope', 'project')->whereIn('project_id', $projectIds);
                    });
                }

                if ($includeGlobal) {
                    $query->orWhere('scope', 'global');
                }
            })
            ->get(['id', 'project_id', 'tenant_id', 'scope', 'source_type', 'source_id', 'source_label', 'chunk_text', 'embedding', 'token_count']);
    }

    /**
     * Top-K chunks by cosine similarity, filtered by a minimum score. Returns
     * [['chunk' => RagChunk, 'score' => float], …] sorted high→low.
     *
     * @param  array<int, float>  $queryVec
     * @return array<int, array{chunk: RagChunk, score: float}>
     */
    public function cosineTopK(array $queryVec, Collection $chunks, int $k = 8, float $minScore = 0.35): array
    {
        $qNorm = $this->norm($queryVec);
        if ($qNorm === 0.0) {
            return [];
        }

        $scored = [];
        foreach ($chunks as $chunk) {
            $vec = $chunk->embedding;
            if (! is_array($vec) || $vec === []) {
                continue;
            }
            $score = $this->cosine($queryVec, $vec, $qNorm);
            if ($score >= $minScore) {
                $scored[] = ['chunk' => $chunk, 'score' => $score];
            }
        }

        usort($scored, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($scored, 0, $k);
    }

    /**
     * Build the numbered CONTEXT string for the prompt, capped at ~maxTokens.
     *
     * @param  array<int, array{chunk: RagChunk, score: float}>  $scored
     */
    public function assembleContext(array $scored, int $maxTokens = 6000): string
    {
        $blocks = [];
        $used = 0;
        $n = 1;
        foreach ($scored as $item) {
            $chunk = $item['chunk'];
            $cost = (int) ($chunk->token_count ?: ceil(mb_strlen($chunk->chunk_text) / 4));
            if ($used + $cost > $maxTokens && $blocks) {
                break;
            }
            $blocks[] = "[{$n}] {$chunk->source_label}:\n{$chunk->chunk_text}";
            $used += $cost;
            $n++;
        }

        return implode("\n\n", $blocks);
    }

    private function cosine(array $a, array $b, float $aNorm): float
    {
        $dot = 0.0;
        $bNorm = 0.0;
        $len = min(count($a), count($b));
        for ($i = 0; $i < $len; $i++) {
            $dot += $a[$i] * $b[$i];
            $bNorm += $b[$i] * $b[$i];
        }
        $bNorm = sqrt($bNorm);

        return $bNorm === 0.0 ? 0.0 : $dot / ($aNorm * $bNorm);
    }

    private function norm(array $v): float
    {
        $sum = 0.0;
        foreach ($v as $x) {
            $sum += $x * $x;
        }

        return sqrt($sum);
    }
}

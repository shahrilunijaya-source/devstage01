<?php

namespace App\Services\Rag;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin REST wrapper for Voyage AI embeddings (model voyage-3, 1024 dims,
 * multilingual — handles Malay + English). No official PHP SDK, so we call the
 * endpoint directly. API key comes from admin Settings (SystemSetting).
 */
class VoyageClient
{
    private const ENDPOINT = 'https://api.voyageai.com/v1/embeddings';

    private const MODEL = 'voyage-3';

    public const DIMENSIONS = 1024;

    /**
     * Embed one or more texts. $inputType must be 'document' when indexing stored
     * content and 'query' when embedding a user question — Voyage tunes the vector
     * space per side, which improves retrieval quality.
     *
     * @param  string[]  $texts
     * @return array<int, array<int, float>> one float[] per input, in order
     */
    public function embed(array $texts, string $inputType = 'document'): array
    {
        $texts = array_values($texts);
        if ($texts === []) {
            return [];
        }

        $key = SystemSetting::get('voyage_api_key');
        if (empty($key)) {
            throw new RuntimeException('Voyage API key is not configured.');
        }

        $response = Http::withToken($key)
            ->timeout(60)
            ->retry(2, 500)
            ->post(self::ENDPOINT, [
                'input' => $texts,
                'model' => self::MODEL,
                'input_type' => $inputType,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Voyage embedding failed: '.$response->status().' '.$response->body());
        }

        // Voyage may return rows out of order — sort by index before stripping.
        $rows = $response->json('data', []);
        usort($rows, fn ($a, $b) => ($a['index'] ?? 0) <=> ($b['index'] ?? 0));

        return array_map(fn ($row) => $row['embedding'], $rows);
    }

    /**
     * Convenience: embed a single string and return its vector.
     *
     * @return array<int, float>
     */
    public function embedOne(string $text, string $inputType = 'document'): array
    {
        return $this->embed([$text], $inputType)[0] ?? [];
    }
}

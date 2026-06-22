<?php

namespace App\Services\Rag;

/**
 * Pure, dependency-free text chunker. Splits long text into ~500-token windows
 * with ~80-token overlap so retrieval keeps surrounding context. Token counts
 * are estimated as ceil(chars / 4) — good enough for budgeting Malay + English
 * without bundling a tokenizer.
 */
class Chunker
{
    public function __construct(
        private int $maxTokens = 500,
        private int $overlapTokens = 80,
    ) {}

    /**
     * @return array<int, array{text: string, tokens: int}>
     */
    public function chunk(string $text): array
    {
        $text = $this->normalize($text);
        if ($text === '') {
            return [];
        }

        // Prefer to break on sentence ends / newlines so chunks stay coherent.
        $units = preg_split('/(?<=[.!?。])\s+|\n+/u', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [$text];

        $chunks = [];
        $current = [];
        $currentTokens = 0;

        foreach ($units as $unit) {
            $unit = trim($unit);
            if ($unit === '') {
                continue;
            }

            $t = $this->tokens($unit);

            // A single oversized unit: flush, then hard-split it by characters.
            if ($t > $this->maxTokens) {
                if ($current) {
                    $chunks[] = $this->join($current);
                    $current = [];
                    $currentTokens = 0;
                }
                foreach ($this->hardSplit($unit) as $piece) {
                    $chunks[] = $piece;
                }

                continue;
            }

            if ($currentTokens + $t > $this->maxTokens && $current) {
                $chunks[] = $this->join($current);
                $current = $this->overlapTail($current);
                $currentTokens = $this->tokens($this->join($current));
            }

            $current[] = $unit;
            $currentTokens += $t;
        }

        if ($current) {
            $chunks[] = $this->join($current);
        }

        return array_map(fn (string $c) => ['text' => $c, 'tokens' => $this->tokens($c)], $chunks);
    }

    public function tokens(string $s): int
    {
        return (int) ceil(mb_strlen($s) / 4);
    }

    private function normalize(string $text): string
    {
        // Collapse runs of spaces/tabs but keep paragraph newlines meaningful.
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;

        return trim($text);
    }

    private function join(array $units): string
    {
        return trim(implode(' ', $units));
    }

    /**
     * Keep the trailing units whose combined size is ~overlapTokens, to seed the
     * next chunk with shared context.
     */
    private function overlapTail(array $units): array
    {
        $tail = [];
        $tokens = 0;
        for ($i = count($units) - 1; $i >= 0; $i--) {
            $t = $this->tokens($units[$i]);
            if ($tokens + $t > $this->overlapTokens && $tail) {
                break;
            }
            array_unshift($tail, $units[$i]);
            $tokens += $t;
        }

        return $tail;
    }

    /**
     * @return array<int, string>
     */
    private function hardSplit(string $unit): array
    {
        $maxChars = $this->maxTokens * 4;
        $pieces = [];
        $len = mb_strlen($unit);
        for ($start = 0; $start < $len; $start += $maxChars) {
            $pieces[] = trim(mb_substr($unit, $start, $maxChars));
        }

        return array_values(array_filter($pieces, fn ($p) => $p !== ''));
    }
}

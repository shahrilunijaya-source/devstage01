<?php

namespace App\Services\Sentiment;

use App\Models\Issue;
use App\Models\ProjectComment;
use App\Models\SentimentScore;
use App\Models\WeeklyUpdate;
use App\Services\Rag\AnthropicClient;
use App\Services\Rag\RagService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Scores the morale of one PM/PE-authored row (weekly update, comment, issue) on
 * a 1–5 scale using Claude Haiku, and stores exactly one current SentimentScore
 * per source row. The label is always derived from the numeric score so the two
 * can never disagree. Gated by the shared AI flag (RagService::enabled()).
 */
class SentimentScorer
{
    /** source_type => Eloquent model class. Mirrors SourceTextBuilder's map for the 3 morale sources. */
    public const SOURCE_MAP = [
        'weekly_update' => WeeklyUpdate::class,
        'comment' => ProjectComment::class,
        'issue' => Issue::class,
    ];

    private const SYSTEM_PROMPT = <<<'PROMPT'
        You rate the MORALE/SENTIMENT of a short project-team note on a 1 to 5 scale.
        1 = very negative / distressed / blocked, 3 = neutral / matter-of-fact,
        5 = very positive / confident / on track. Judge the human tone and outlook,
        not whether the news is "good" administratively.

        Output ONLY a JSON object, no prose and no markdown fences, matching exactly:
        {"score": <integer 1-5>, "label": "negative|neutral|positive", "summary": "<≤12 words on the tone>"}
        PROMPT;

    public function __construct(private AnthropicClient $client) {}

    public static function enabled(): bool
    {
        return RagService::enabled();
    }

    public static function sourceTypeFor(Model $model): ?string
    {
        return array_search($model::class, self::SOURCE_MAP, true) ?: null;
    }

    public static function modelClassFor(string $sourceType): ?string
    {
        return self::SOURCE_MAP[$sourceType] ?? null;
    }

    /**
     * Score one row. Returns the stored SentimentScore, or null when the row has
     * no human narrative to judge (in which case any existing score is removed).
     */
    public function score(string $sourceType, Model $row): ?SentimentScore
    {
        $text = $this->textFor($sourceType, $row);

        if (trim($text) === '') {
            SentimentScore::where('source_type', $sourceType)->where('source_id', $row->getKey())->delete();

            return null;
        }

        // Public method — guard the FK the observer already checks before dispatch.
        if ((int) $row->project_id <= 0) {
            return null;
        }

        $raw = $this->client->answer(AnthropicClient::MODEL_HAIKU, self::SYSTEM_PROMPT, $text, 256);
        $parsed = $this->parse($raw);

        if ($parsed === null) {
            return null;
        }

        return SentimentScore::updateOrCreate(
            ['source_type' => $sourceType, 'source_id' => $row->getKey()],
            [
                'project_id' => (int) $row->project_id,
                'label' => $this->labelFor($parsed['score']),
                'score' => $parsed['score'],
                'summary' => $parsed['summary'],
                'model' => AnthropicClient::MODEL_HAIKU,
                'source_date' => $this->sourceDateFor($sourceType, $row),
                'scored_at' => now(),
            ],
        );
    }

    /**
     * The human narrative we judge — deliberately excludes metadata (status,
     * severity, dates) so the score reflects tone, not administrative facts.
     */
    public function textFor(string $sourceType, Model $row): string
    {
        return match ($sourceType) {
            'comment' => (string) $row->body,
            'weekly_update' => trim(implode("\n", array_filter([$row->narrative_last_week, $row->blockers]))),
            'issue' => trim(implode("\n", array_filter([$row->title, $row->description, $row->resolution]))),
            default => '',
        };
    }

    /**
     * @return array{score:int, summary:string}|null null when no usable score parsed
     */
    public function parse(string $raw): ?array
    {
        $text = trim($raw);
        if (str_starts_with($text, '```')) {
            $text = trim((string) preg_replace(['/^```(?:json)?\s*/', '/\s*```$/'], '', $text));
        }

        $decoded = json_decode($text, true);
        if (! is_array($decoded) || ! isset($decoded['score']) || ! is_numeric($decoded['score'])) {
            return null;
        }

        $score = max(1, min(5, (int) round((float) $decoded['score'])));
        $summary = trim((string) ($decoded['summary'] ?? ''));

        return ['score' => $score, 'summary' => $summary];
    }

    /**
     * Label for a single note from its integer 1–5 score (stored on each row).
     */
    public function labelFor(int $score): string
    {
        return match (true) {
            $score <= 2 => 'negative',
            $score >= 4 => 'positive',
            default => 'neutral',
        };
    }

    /**
     * Label for an aggregate (averaged) morale value. Wider neutral band than a
     * single note's labelFor() so a mixed average doesn't read as strongly one way.
     * One source of truth for the dashboard card and the query_sentiment tool.
     */
    public static function moraleLabelFor(float $avg): string
    {
        return match (true) {
            $avg <= 2.4 => 'negative',
            $avg >= 3.6 => 'positive',
            default => 'neutral',
        };
    }

    private function sourceDateFor(string $sourceType, Model $row): Carbon
    {
        $date = match ($sourceType) {
            'weekly_update' => $row->week_ending,
            'issue' => $row->reported_date,
            default => null,
        };

        return $date ? Carbon::parse($date) : ($row->created_at ?? now());
    }
}

<?php

namespace App\Services\Rag\Tools;

use App\Models\SentimentScore;
use App\Services\Sentiment\SentimentScorer;

class QuerySentimentTool implements ChatTool
{
    use ScopesProjects;

    public function name(): string
    {
        return 'query_sentiment';
    }

    public function description(): string
    {
        return 'Read team morale/sentiment scored from weekly updates, comments and issues. Returns per-project average score (1=very negative … 5=very positive), a negative/neutral/positive breakdown, and the most negative recent notes. Use for "how is morale on project X", "is the team stressed", "which project has the worst morale".';
    }

    public function schema(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'project_id' => ['type' => 'integer', 'description' => 'Optional. Omit to cover all projects in scope.'],
            ],
        ];
    }

    public function handle(array $input, array $allowedProjectIds): array
    {
        $ids = $this->resolveProjectIds($input, $allowedProjectIds);

        $projects = [];
        foreach ($ids as $pid) {
            $scores = SentimentScore::where('project_id', $pid)->get();
            $avg = $scores->isEmpty() ? null : round($scores->avg('score'), 1);

            $projects[] = [
                'project_id' => $pid,
                'count' => $scores->count(),
                'avg_score' => $avg,
                'morale' => $avg === null ? null : SentimentScorer::moraleLabelFor($avg),
                'breakdown' => [
                    'negative' => $scores->where('label', 'negative')->count(),
                    'neutral' => $scores->where('label', 'neutral')->count(),
                    'positive' => $scores->where('label', 'positive')->count(),
                ],
                // Lowest score first; break ties toward the most recent note ("recent" per the description).
                'most_negative' => $scores->sortBy([['score', 'asc'], ['source_date', 'desc']])->take(3)->values()->map(fn (SentimentScore $s) => [
                    'source_type' => $s->source_type,
                    'score' => $s->score,
                    'summary' => $s->summary,
                    'date' => $s->source_date?->format('d/m/Y'),
                ])->all(),
            ];
        }

        return ['projects' => $projects];
    }
}

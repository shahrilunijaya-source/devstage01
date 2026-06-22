<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ProjectInsight;
use App\Models\User;
use App\Services\Rag\AnthropicClient;

class ProjectInsightService
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
        You are a project-management advisor for a delivery PMO. You are given the
        current facts of ONE project. Produce a concise, decision-useful insight.

        Rules:
        - Base every statement ONLY on the supplied facts. Never invent numbers, names, or events.
        - Be specific and concise. Currency is MYR. Dates are DD/MM/YYYY.
        - Surface the most material risks first; recommend a concrete next action for each.
        - "flags" are anomalies worth an immediate look (slippage, deadline/budget risk, stale work); use an empty array when nothing is wrong.
        - "forecast": will the project hit its next milestone / deadline at the current pace, and why.

        Output ONLY a JSON object, no prose and no markdown fences, matching exactly:
        {"summary": string, "risks": [{"risk": string, "action": string}], "flags": [string], "forecast": string}
        PROMPT;

    public function __construct(private AnthropicClient $client) {}

    public function generate(Project $project, User $user): ProjectInsight
    {
        try {
            $raw = $this->client->answer(
                AnthropicClient::MODEL_HAIKU,
                self::SYSTEM_PROMPT,
                $this->buildInput($project),
                1024,
            );
        } catch (\RuntimeException $e) {
            throw $e;
        } catch (\Throwable $e) {
            throw new \RuntimeException('Anthropic request failed: '.$e->getMessage(), 0, $e);
        }

        $content = $this->parse($raw);

        return ProjectInsight::updateOrCreate(
            ['project_id' => $project->id],
            [
                'content' => $content,
                'model' => AnthropicClient::MODEL_HAIKU,
                'generated_at' => now(),
                'generated_by' => $user->id,
            ],
        );
    }

    public function buildInput(Project $project): string
    {
        $calc = new ProjectCalculator($project);
        $planned = round($calc->overallPlannedPct() * 100, 1);
        $actual = round($calc->overallActualPct() * 100, 1);
        $variance = round($actual - $planned, 1);
        $health = $project->health_override ?? $calc->projectHealthFlag();
        $money = app(MoneySummaryService::class)->forProject($project);
        $today = $project->dynamic_date_or_today;

        $openIssues = $project->issues()->where('status', 'open')->count();

        $nextMilestone = $project->wbsItems()
            ->where('is_milestone', true)
            ->whereDate('planned_end', '>=', $today)
            ->orderBy('planned_end')
            ->first();

        $nextClaim = $project->claimMilestones()
            ->whereNotNull('target_date')
            ->whereDate('target_date', '>=', $today)
            ->orderBy('target_date')
            ->first();

        $slipping = $project->wbsItems()->where('is_leaf', true)->get()
            ->map(fn ($item) => [
                'name' => $item->name,
                'plan' => round($calc->computePlannedPct($item) * 100, 1),
                'var' => round($calc->computeVariancePct($item) * 100, 1),
                'pic' => $item->pic,
            ])
            ->sortBy('var')
            ->take(5);

        $lines = [];
        $lines[] = "Project: {$project->name} (category: {$project->category})";
        $lines[] = "Health: {$health}";
        $lines[] = 'Today: '.$today->format('d/m/Y').', planned end: '.($project->planned_end?->format('d/m/Y') ?? 'n/a');
        $lines[] = "Progress: planned {$planned}%, actual {$actual}%, variance {$variance}%";
        $lines[] = "Open issues: {$openIssues}";
        $lines[] = 'Budget (MYR): contract '.number_format((float) ($project->contract_value ?? 0))
            .', spent '.number_format((float) ($money['expenses'] ?? 0))
            .', billed '.number_format((float) ($money['income'] ?? 0))
            .', remaining '.number_format((float) ($money['remaining'] ?? 0));
        $lines[] = $nextMilestone
            ? "Next milestone: {$nextMilestone->name} due ".$nextMilestone->planned_end?->format('d/m/Y')
            : 'Next milestone: none upcoming';
        $lines[] = $nextClaim
            ? 'Next claim: '.$nextClaim->perkara.' due '.optional($nextClaim->target_date)->format('d/m/Y')
            : 'Next claim: none upcoming';

        if ($slipping->isNotEmpty()) {
            $lines[] = 'Top slipping tasks (name | plan% | variance% | PIC):';
            foreach ($slipping as $s) {
                $lines[] = "- {$s['name']} | {$s['plan']}% | {$s['var']}% | ".($s['pic'] ?? 'n/a');
            }
        }

        $lines[] = '';
        $lines[] = 'Return the JSON insight for this project now.';

        return implode("\n", $lines);
    }

    private function parse(string $raw): array
    {
        $text = trim($raw);

        if (str_starts_with($text, '```')) {
            $text = preg_replace('/^```(?:json)?\s*/', '', $text);
            $text = preg_replace('/\s*```$/', '', (string) $text);
            $text = trim((string) $text);
        }

        $decoded = json_decode($text, true);

        if (is_array($decoded) && array_key_exists('summary', $decoded)) {
            return [
                'summary' => (string) ($decoded['summary'] ?? ''),
                'risks' => $this->normalizeRisks($decoded['risks'] ?? []),
                'flags' => array_values(array_filter(array_map(
                    fn ($f) => is_string($f) ? $f : (string) json_encode($f),
                    (array) ($decoded['flags'] ?? []),
                ))),
                'forecast' => (string) ($decoded['forecast'] ?? ''),
            ];
        }

        return ['summary' => $text, 'risks' => [], 'flags' => [], 'forecast' => ''];
    }

    private function normalizeRisks(mixed $risks): array
    {
        $out = [];
        foreach ((array) $risks as $r) {
            if (is_array($r)) {
                $out[] = [
                    'risk' => (string) ($r['risk'] ?? ''),
                    'action' => (string) ($r['action'] ?? ''),
                ];
            }
        }

        return $out;
    }
}

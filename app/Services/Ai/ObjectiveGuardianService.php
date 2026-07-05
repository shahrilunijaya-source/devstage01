<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Models\Graph\EngObject;
use App\Models\User;
use App\Services\Portfolio\ObjectiveService;
use App\Services\Rag\AnthropicClient;
use App\Services\Rag\RagService;

/**
 * Objective Guardian (spec §7): classifies a proposed change against the
 * approved project objective — aligned / potential_improvement /
 * scope_expansion / possible_conflict / direct_conflict /
 * insufficient_information. ADVISORY ONLY: it never blocks by itself; humans
 * override with a recorded reason. Degrades honestly (insufficient_information)
 * when the objective or the AI is unavailable — an assessment failure must
 * never stop a change request from being raised.
 */
class ObjectiveGuardianService
{
    public const CLASSIFICATIONS = [
        'aligned', 'potential_improvement', 'scope_expansion',
        'possible_conflict', 'direct_conflict', 'insufficient_information',
    ];

    /** Classifications whose approval requires a recorded override reason. */
    public const OVERRIDE_REQUIRED = ['direct_conflict'];

    public function __construct(
        private readonly AnthropicClient $anthropic,
        private readonly ObjectiveService $objectives,
        private readonly AiUsageLogger $usage,
    ) {}

    /**
     * @return array<string, mixed> classification payload + provenance
     */
    public function assess(EngObject $target, string $proposal, User $user): array
    {
        $objective = $this->objectives->objectiveFor($target->project);

        if ($objective === null) {
            return $this->fallback('No project objective has been captured — capture and approve it so changes can be checked against it.');
        }

        if (! RagService::enabled()) {
            return $this->fallback('AI is not enabled — the alignment of this change was not assessed. Review it against the objective manually.') + ['objective_ref' => $objective->ref];
        }

        $prompt = strtr((string) config('prompts.guardian.assess'), [
            '{objective}' => $objective->title.' — '.mb_substr((string) $objective->body, 0, 1500),
            '{ref}' => $target->ref,
            '{title}' => $target->title,
            '{proposal}' => mb_substr($proposal, 0, 3000),
        ]);

        try {
            $result = $this->anthropic->answerWithUsage(AnthropicClient::MODEL_SONNET, (string) config('prompts.guardian.system'), $prompt, 1200);
        } catch (\Throwable $e) {
            report($e);

            return $this->fallback('The AI service was unavailable — alignment not assessed. Review manually.') + ['objective_ref' => $objective->ref];
        }

        $this->usage->log($user->id, (int) $target->project_id, 'guardian.assess', AnthropicClient::MODEL_SONNET, $result['tokens_in'], $result['tokens_out']);

        $payload = json_decode(trim(preg_replace('/^```(?:json)?|```$/m', '', $result['text']) ?? $result['text']), true);

        if (! is_array($payload) || ! in_array($payload['classification'] ?? null, self::CLASSIFICATIONS, true)) {
            return $this->fallback('The AI reply was not a valid assessment — review manually.') + ['objective_ref' => $objective->ref];
        }

        return $payload + [
            'objective_ref' => $objective->ref,
            'model' => AnthropicClient::MODEL_SONNET,
            'prompt_version' => (string) config('prompts.version'),
            'assessed_at' => now()->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function fallback(string $reason): array
    {
        return [
            'classification' => 'insufficient_information',
            'rationale' => $reason,
            'benefits' => [],
            'risks' => [],
            'questions' => [],
            'confidence' => 'high',
            'prompt_version' => (string) config('prompts.version'),
            'assessed_at' => now()->toIso8601String(),
        ];
    }
}

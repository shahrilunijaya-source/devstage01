<?php

declare(strict_types=1);

namespace App\Services\Ai;

use App\Enums\ObjectStatus;
use App\Enums\ObjectType;
use App\Enums\RelationType;
use App\Models\AiSuggestion;
use App\Models\Graph\EngObject;
use App\Models\User;
use App\Services\Ai\Exceptions\RefinementException;
use App\Services\Graph\ObjectGraphService;
use App\Services\Graph\TraceService;
use App\Services\Portfolio\ObjectiveService;
use App\Services\Rag\AnthropicClient;

/**
 * AI Refinement Engine (spec §10). Every action produces a STORED suggestion —
 * recommendation, rationale, sources, confidence — which a human accepts,
 * accepts-with-edits, or rejects. AI never overwrites content: acceptance flows
 * through ObjectGraphService as a normal immutable version.
 *
 * Strict-JSON discipline with ONE repair retry; on a second failure the action
 * refuses honestly (RefinementException) instead of fabricating output.
 */
class RefinementService
{
    public const ACTIONS = ['improve', 'challenge', 'generate_ac', 'find_missing', 'check_objective'];

    /** Actions whose acceptance changes content (vs advisory acknowledgement). */
    private const APPLYING_ACTIONS = ['improve', 'generate_ac'];

    public function __construct(
        private readonly AnthropicClient $anthropic,
        private readonly ObjectGraphService $graph,
        private readonly TraceService $trace,
        private readonly ObjectiveService $objectives,
        private readonly AiUsageLogger $usage,
    ) {}

    public function suggest(EngObject $object, string $action, User $user): AiSuggestion
    {
        if (! in_array($action, self::ACTIONS, true)) {
            throw new RefinementException("Unknown refinement action: {$action}.");
        }

        // check_objective without a captured objective needs no LLM: the honest
        // classification is insufficient_information.
        if ($action === 'check_objective') {
            $objective = $this->objectives->objectiveFor($object->project);
            if ($objective === null) {
                return $this->store($object, $action, $user, null, [
                    'classification' => 'insufficient_information',
                    'rationale' => 'No project objective has been captured yet — nothing to check against. Capture and approve the objective first.',
                    'questions' => ['What is the approved project objective?'],
                    'confidence' => 'high',
                    'sources' => [],
                ]);
            }
        }

        $prompt = $this->buildPrompt($object, $action);
        $payload = $this->callStrictJson('refine.'.$action, $object, $user, config('prompts.refine.system'), $prompt, $this->requiredKeys($action));
        $payload['sources'] = $this->sourceRefs($object);

        return $this->store($object, $action, $user, AnthropicClient::MODEL_SONNET, $payload);
    }

    /**
     * Record the human decision. accept/accept_edit on 'improve' writes a new
     * object version; accept on 'generate_ac' mints ACCEPTANCE_CRITERION objects
     * linked REFINES → the requirement. Advisory actions just record the outcome.
     *
     * @param  array{title?: ?string, body?: ?string, note?: ?string}  $opts
     */
    public function decide(AiSuggestion $suggestion, string $decision, User $user, array $opts = []): AiSuggestion
    {
        if ($suggestion->status !== 'proposed') {
            throw new RefinementException('This suggestion has already been decided.');
        }
        if (! in_array($decision, ['accept', 'accept_edit', 'reject'], true)) {
            throw new RefinementException("Unknown decision: {$decision}.");
        }

        if ($decision === 'reject') {
            return $this->finish($suggestion, 'rejected', $user, $opts['note'] ?? null);
        }

        if ($suggestion->action === 'improve') {
            $title = $decision === 'accept_edit'
                ? ($opts['title'] ?? $suggestion->payload['proposed_title'] ?? null)
                : ($suggestion->payload['proposed_title'] ?? null);
            $body = $decision === 'accept_edit'
                ? ($opts['body'] ?? $suggestion->payload['proposed_body'] ?? null)
                : ($suggestion->payload['proposed_body'] ?? null);

            $changes = array_filter(['title' => $title, 'body' => $body], fn ($v) => filled($v));
            if ($changes !== []) {
                $this->graph->update($suggestion->object, $changes, $user->id,
                    "AI suggestion #{$suggestion->id} (improve) ".($decision === 'accept_edit' ? 'accepted with edits' : 'accepted'));
            }
        }

        if ($suggestion->action === 'generate_ac' && $decision === 'accept') {
            $this->mintAcceptanceCriteria($suggestion, $user);
        }

        return $this->finish($suggestion, $decision === 'accept_edit' ? 'accepted_edited' : 'accepted', $user, $opts['note'] ?? null);
    }

    private function mintAcceptanceCriteria(AiSuggestion $suggestion, User $user): void
    {
        $requirement = $suggestion->object;

        foreach ($suggestion->payload['criteria'] ?? [] as $criterion) {
            $ac = $this->graph->create(ObjectType::ACCEPTANCE_CRITERION,
                (int) $requirement->tenant_id, (int) $requirement->project_id,
                mb_substr((string) ($criterion['title'] ?? 'Acceptance criterion'), 0, 200), [
                    'module_id' => $requirement->module_id,
                    'stage_id' => $requirement->stage_id,
                    'session_id' => $requirement->session_id,
                    'body' => $criterion['body'] ?? null,
                    'owner_user_id' => $user->id,
                    'source' => "AI suggestion #{$suggestion->id}",
                    'source_object_id' => $requirement->id,
                    'status' => ObjectStatus::NEEDS_CONFIRMATION,
                    'changed_by' => $user->id,
                    'change_summary' => 'acceptance criterion accepted from AI suggestion',
                ]);

            $this->trace->link($ac, $requirement, RelationType::REFINES, null, $user->id);
        }
    }

    /**
     * Strict-JSON call with one repair retry, honest refusal after that.
     *
     * @param  array<int, string>  $requiredKeys
     * @return array<string, mixed>
     */
    private function callStrictJson(string $feature, EngObject $object, User $user, string $system, string $prompt, array $requiredKeys): array
    {
        $attempt = $prompt;

        for ($try = 1; $try <= 2; $try++) {
            try {
                $result = $this->anthropic->answerWithUsage(AnthropicClient::MODEL_SONNET, $system, $attempt, 1500);
            } catch (\Throwable $e) {
                report($e);
                throw new RefinementException('The AI service is unavailable right now — no suggestion was created.');
            }

            $this->usage->log($user->id, (int) $object->project_id, $feature, AnthropicClient::MODEL_SONNET, $result['tokens_in'], $result['tokens_out']);

            $payload = $this->parseJson($result['text']);
            if ($payload !== null && array_diff($requiredKeys, array_keys($payload)) === []) {
                return $payload;
            }

            // One repair hint, then give up honestly.
            $attempt = $prompt."\n\nYour previous reply was not valid JSON with the required keys (".implode(', ', $requiredKeys).'). Reply again with ONLY the JSON object.';
        }

        throw new RefinementException('The AI reply was not valid — no suggestion was created. Try again.');
    }

    /** @return array<string, mixed>|null */
    private function parseJson(string $text): ?array
    {
        $text = trim(preg_replace('/^```(?:json)?|```$/m', '', $text) ?? $text);
        $decoded = json_decode($text, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function buildPrompt(EngObject $object, string $action): string
    {
        $template = str_replace('{type}', $object->type->label(), (string) config("prompts.refine.{$action}"));

        $context = [
            "OBJECT {$object->ref} ({$object->type->value}, status {$object->status->value}, impact ".($object->impact ?? 'unset').'):',
            'TITLE: '.$object->title,
            'BODY: '.($object->body ?: '(none)'),
        ];

        foreach ($this->upstreamEvidence($object) as $evidence) {
            $context[] = "SOURCE {$evidence->ref}: ".mb_substr((string) ($evidence->body ?: $evidence->title), 0, 1500);
        }

        if ($action === 'check_objective') {
            $objective = $this->objectives->objectiveFor($object->project);
            $context[] = "PROJECT OBJECTIVE {$objective->ref}: {$objective->title} — ".mb_substr((string) $objective->body, 0, 1500);
        }

        return $template."\n\n".implode("\n", $context);
    }

    /** @return array<int, EngObject> nearest upstream evidence/finding context (capped). */
    private function upstreamEvidence(EngObject $object): array
    {
        return array_slice(array_filter(
            $this->trace->reverseTrace($object),
            fn (EngObject $o): bool => in_array($o->type, [ObjectType::EVIDENCE, ObjectType::FINDING], true),
        ), 0, 3);
    }

    /** @return array<int, string> */
    private function sourceRefs(EngObject $object): array
    {
        return array_map(fn (EngObject $o): string => $o->ref, $this->upstreamEvidence($object));
    }

    /** @return array<int, string> */
    private function requiredKeys(string $action): array
    {
        return match ($action) {
            'improve' => ['proposed_title', 'proposed_body', 'rationale'],
            'challenge' => ['rationale', 'issues', 'questions'],
            'generate_ac' => ['criteria', 'rationale'],
            'find_missing' => ['gaps', 'questions', 'rationale'],
            'check_objective' => ['classification', 'rationale'],
        };
    }

    /** @param  array<string, mixed>  $payload */
    private function store(EngObject $object, string $action, User $user, ?string $model, array $payload): AiSuggestion
    {
        return AiSuggestion::create([
            'tenant_id' => $object->tenant_id,
            'project_id' => $object->project_id,
            'object_id' => $object->id,
            'action' => $action,
            'payload' => $payload,
            'model' => $model,
            'prompt_version' => (string) config('prompts.version'),
            'created_by' => $user->id,
        ]);
    }

    private function finish(AiSuggestion $suggestion, string $status, User $user, ?string $note): AiSuggestion
    {
        $suggestion->update([
            'status' => $status,
            'decided_by' => $user->id,
            'decided_at' => now(),
            'decision_note' => $note !== null ? mb_substr($note, 0, 500) : null,
        ]);

        return $suggestion;
    }
}

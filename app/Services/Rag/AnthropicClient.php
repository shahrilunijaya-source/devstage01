<?php

namespace App\Services\Rag;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Thin REST wrapper for the Anthropic Messages API. Used to generate the final
 * grounded answer from retrieved context. temperature is forced to 0 — we want
 * deterministic, faithful answers, not creative ones. API key from admin Settings.
 */
class AnthropicClient
{
    private const ENDPOINT = 'https://api.anthropic.com/v1/messages';

    private const VERSION = '2023-06-01';

    public const MODEL_HAIKU = 'claude-haiku-4-5';

    public const MODEL_SONNET = 'claude-sonnet-4-6';

    /**
     * Send a single grounded request: a strict system prompt + a user turn that
     * carries the numbered CONTEXT followed by the question. Returns the plain
     * text answer.
     */
    public function answer(string $model, string $system, string $userContent, int $maxTokens = 1024): string
    {
        $key = SystemSetting::get('anthropic_api_key');
        if (empty($key)) {
            throw new RuntimeException('Anthropic API key is not configured.');
        }

        $response = Http::withHeaders([
            'x-api-key' => $key,
            'anthropic-version' => self::VERSION,
        ])
            ->timeout(120)
            ->retry(2, 800)
            ->post(self::ENDPOINT, [
                'model' => $model,
                'max_tokens' => $maxTokens,
                'temperature' => 0,
                'system' => $system,
                'messages' => [
                    ['role' => 'user', 'content' => $userContent],
                ],
            ]);

        if ($response->failed()) {
            // Status only — the body can echo the (attacker-controllable) prompt
            // content and would otherwise land verbatim in the application log.
            throw new RuntimeException('Anthropic request failed with HTTP '.$response->status().'.');
        }

        // content is an array of blocks; concatenate the text blocks.
        $text = collect($response->json('content', []))
            ->where('type', 'text')
            ->pluck('text')
            ->implode('');

        return trim($text);
    }

    /**
     * Run a tool-use conversation. Sends the messages + tool defs; whenever Claude
     * emits tool_use blocks, runs them via $executor and feeds tool_result back,
     * looping until Claude stops calling tools (or $maxHops is hit). Returns the
     * final assistant text plus a record of every tool call made.
     *
     * @param  array<int,array<string,mixed>>  $messages  starts with the user turn
     * @param  array<int,array<string,mixed>>  $tools  Anthropic tool definitions
     * @param  callable(string,array):array  $executor  (name, input) => result
     * @return array{text:string, tool_calls:array<int,array<string,mixed>>}
     */
    public function converse(string $model, string $system, array $messages, array $tools, callable $executor, int $maxHops = 4): array
    {
        $key = SystemSetting::get('anthropic_api_key');
        if (empty($key)) {
            throw new RuntimeException('Anthropic API key is not configured.');
        }

        $toolCalls = [];

        for ($hop = 0; $hop < $maxHops; $hop++) {
            $response = Http::withHeaders([
                'x-api-key' => $key,
                'anthropic-version' => self::VERSION,
            ])->timeout(120)->retry(2, 800)->post(self::ENDPOINT, [
                'model' => $model,
                'max_tokens' => 1500,
                'temperature' => 0,
                'system' => $system,
                'tools' => $tools,
                'messages' => $messages,
            ]);

            if ($response->failed()) {
                // Status only — the body can echo the (attacker-controllable) prompt
                // content and would otherwise land verbatim in the application log.
                throw new RuntimeException('Anthropic request failed with HTTP '.$response->status().'.');
            }

            $content = $response->json('content', []);
            $stop = $response->json('stop_reason');

            if ($stop !== 'tool_use') {
                return ['text' => $this->textFrom($content), 'tool_calls' => $toolCalls];
            }

            // Record the assistant turn (must be echoed back verbatim), then answer each tool_use.
            // A no-argument tool call arrives as input {} → PHP decodes it to an empty
            // array [], which json-encodes back as [] and Anthropic rejects (tool_use
            // input must be an object). Force empty inputs to {} before echoing.
            $messages[] = ['role' => 'assistant', 'content' => $this->normaliseToolInputs($content)];
            $results = [];
            foreach ($content as $block) {
                if (($block['type'] ?? null) !== 'tool_use') {
                    continue;
                }
                $input = is_array($block['input'] ?? null) ? $block['input'] : [];
                $result = $executor($block['name'], $input);
                $toolCalls[] = ['name' => $block['name'], 'input' => $input, 'result' => $result];
                $results[] = [
                    'type' => 'tool_result',
                    'tool_use_id' => $block['id'],
                    'content' => json_encode($result),
                ];
            }
            $messages[] = ['role' => 'user', 'content' => $results];
        }

        // Hit the hop cap — make one final no-tools call for a best-effort answer.
        $final = $this->answer($model, $system, 'Summarise what you found for the user based on the tool results so far.');

        return ['text' => $final, 'tool_calls' => $toolCalls];
    }

    private function textFrom(array $content): string
    {
        return trim(collect($content)->where('type', 'text')->pluck('text')->implode(''));
    }

    /**
     * Ensure each tool_use block's empty input serialises as {} not [] when the
     * assistant turn is echoed back to the API.
     *
     * @param  array<int,array<string,mixed>>  $content
     * @return array<int,array<string,mixed>>
     */
    private function normaliseToolInputs(array $content): array
    {
        foreach ($content as &$block) {
            if (($block['type'] ?? null) === 'tool_use' && empty($block['input'])) {
                $block['input'] = (object) [];
            }
        }

        return $content;
    }

    /**
     * Map a short UI choice ('haiku' | 'sonnet') to a concrete model id.
     */
    public static function resolveModel(?string $choice): string
    {
        return $choice === 'sonnet' ? self::MODEL_SONNET : self::MODEL_HAIKU;
    }
}

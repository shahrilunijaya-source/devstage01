<?php

declare(strict_types=1);

namespace App\Services\Session\Analysis;

use App\Enums\ConfidenceLevel;
use App\Enums\ObjectType;
use App\Models\Graph\EngObject;
use App\Services\Rag\AnthropicClient;
use App\Services\Session\Analysis\Exceptions\AnalysisException;
use Throwable;

/**
 * LLM-backed analyst (PRD §9.2). Asks Claude to read a piece of evidence and
 * draft a finding plus requirements as strict JSON, each grounded in — and
 * citing — that evidence. Any failure (no key, malformed output) raises
 * AnalysisException so the engine can fall back to the deterministic analyst.
 */
class LlmEvidenceAnalyst implements EvidenceAnalyst
{
    /** Requirement types the model may choose from. */
    private const REQUIREMENT_TYPES = [
        'business_requirement', 'user_requirement',
        'functional_requirement', 'non_functional_requirement',
    ];

    private const SYSTEM = <<<'PROMPT'
        You are a requirements analyst. Read ONE piece of evidence and draft structured
        requirements-engineering objects grounded strictly in that evidence. Do not invent
        facts beyond it. Respond with ONLY a JSON object, no prose, in exactly this shape:

        {
          "finding": {"title": string, "body": string, "confidence": "high|medium|low", "impact": "high|medium|low"},
          "requirements": [
            {"type": "business_requirement|user_requirement|functional_requirement|non_functional_requirement",
             "title": string, "body": string, "confidence": "high|medium|low", "impact": "high|medium|low"}
          ]
        }

        Provide 1-3 requirements. Confidence reflects how directly the evidence supports the
        draft. Keep titles under 120 characters.
        PROMPT;

    public function __construct(private readonly AnthropicClient $anthropic) {}

    public function analyze(EngObject $evidence): AnalysisResult
    {
        $user = "EVIDENCE {$evidence->ref} — {$evidence->title}\n\n".($evidence->body ?? '(no body text)');

        try {
            $raw = $this->anthropic->answer(AnthropicClient::MODEL_SONNET, self::SYSTEM, $user, 1500);
        } catch (Throwable $e) {
            throw new AnalysisException('LLM request failed: '.$e->getMessage(), 0, $e);
        }

        $data = $this->decode($raw);

        $finding = $this->draft($data['finding'] ?? null, ObjectType::FINDING, 'a finding');

        $requirements = [];
        foreach ($data['requirements'] ?? [] as $req) {
            $type = $this->requirementType($req['type'] ?? null);
            $requirements[] = $this->draft($req, $type, 'a requirement');
        }

        if ($requirements === []) {
            throw new AnalysisException('LLM returned no requirements.');
        }

        return new AnalysisResult($finding, $requirements, [$evidence->ref]);
    }

    /** @return array<string, mixed> */
    private function decode(string $raw): array
    {
        // Strip any ```json fences the model may wrap around the object.
        $clean = trim(preg_replace('/^```(?:json)?|```$/m', '', $raw) ?? $raw);

        $data = json_decode($clean, true);

        if (! is_array($data)) {
            throw new AnalysisException('LLM output was not valid JSON.');
        }

        return $data;
    }

    /**
     * @param  mixed  $spec
     */
    private function draft($spec, ObjectType $type, string $what): DraftedObject
    {
        if (! is_array($spec) || ! isset($spec['title'])) {
            throw new AnalysisException("LLM output missing {$what}.");
        }

        return new DraftedObject(
            $type,
            mb_substr((string) $spec['title'], 0, 250),
            isset($spec['body']) ? (string) $spec['body'] : null,
            $this->confidence($spec['confidence'] ?? null),
            $this->impact($spec['impact'] ?? null),
        );
    }

    private function requirementType(?string $value): ObjectType
    {
        return in_array($value, self::REQUIREMENT_TYPES, true)
            ? ObjectType::from($value)
            : ObjectType::BUSINESS_REQUIREMENT;
    }

    private function confidence(?string $value): ConfidenceLevel
    {
        return ConfidenceLevel::tryFrom((string) $value) ?? ConfidenceLevel::MEDIUM;
    }

    private function impact(?string $value): string
    {
        return in_array($value, ['high', 'medium', 'low'], true) ? $value : 'medium';
    }
}

<?php

declare(strict_types=1);

namespace App\Services\AccessControl;

/**
 * Immutable result of a Policy Decision Point evaluation (PRD §6.2).
 * `abstain` = no ACL rule matched; callers (e.g. Gate::before) fall through.
 */
final readonly class Decision
{
    /**
     * @param  array<int, string>  $redactedFields
     */
    public function __construct(
        public bool $permitted,
        public string $reason,
        public bool $abstain = false,
        public ?string $matchedRuleType = null,
        public ?int $matchedRuleId = null,
        public array $redactedFields = [],
    ) {}

    public static function permit(string $reason = 'granted', ?string $ruleType = null, ?int $ruleId = null): self
    {
        return new self(true, $reason, false, $ruleType, $ruleId);
    }

    public static function deny(string $reason = 'denied', ?string $ruleType = null, ?int $ruleId = null): self
    {
        return new self(false, $reason, false, $ruleType, $ruleId);
    }

    public static function abstain(string $reason = 'no matching ACL rule'): self
    {
        return new self(false, $reason, true);
    }
}

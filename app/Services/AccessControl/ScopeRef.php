<?php

declare(strict_types=1);

namespace App\Services\AccessControl;

/**
 * Normalized scope descriptor for an access target (PRD §6.2). Carries the full
 * scope tuple so binding coverage can be decided without extra queries.
 */
final readonly class ScopeRef
{
    public function __construct(
        public ?int $tenantId,
        public string $scopeType,     // tenant|project|module|stage|session|object
        public ?int $scopeId,
        public ?string $objectType,   // project|module|stage|session|evidence|...
        public ?int $projectId = null,
        public ?int $moduleId = null,
        public ?int $stageId = null,
        public ?int $sessionId = null,
        public ?string $status = null,
        public ?string $confidence = null,
        public ?string $classification = null,
    ) {}
}

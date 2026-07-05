<?php

declare(strict_types=1);

namespace App\Services\Session\Analysis;

use App\Enums\ConfidenceLevel;
use App\Enums\ObjectType;

/**
 * One AI-drafted hypothesis (a finding or a requirement) produced from evidence
 * during pre-analysis (PRD §9.2). Immutable value object crossing the analyst
 * boundary — the session engine turns it into a canonical object.
 */
final readonly class DraftedObject
{
    public function __construct(
        public ObjectType $type,
        public string $title,
        public ?string $body,
        public ConfidenceLevel $confidence,
        public string $impact,
    ) {}
}

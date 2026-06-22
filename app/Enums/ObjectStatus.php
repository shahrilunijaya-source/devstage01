<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Canonical object status classification (PRD §9.5), applied to every object.
 */
enum ObjectStatus: string
{
    case CONFIRMED_BY_EVIDENCE = 'confirmed_by_evidence';
    case NEEDS_CONFIRMATION = 'needs_confirmation';
    case CONFLICT_DETECTED = 'conflict_detected';
    case MISSING_UNKNOWN = 'missing_unknown';
    case DECISION_REQUIRED = 'decision_required';
    case NOT_APPLICABLE = 'not_applicable';

    public function label(): string
    {
        return match ($this) {
            self::CONFIRMED_BY_EVIDENCE => 'Confirmed by Evidence',
            self::NEEDS_CONFIRMATION => 'Needs Confirmation',
            self::CONFLICT_DETECTED => 'Conflict Detected',
            self::MISSING_UNKNOWN => 'Missing / Unknown',
            self::DECISION_REQUIRED => 'Decision Required',
            self::NOT_APPLICABLE => 'Not Applicable',
        };
    }
}

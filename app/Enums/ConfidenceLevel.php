<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * AI/evidence confidence on an object (PRD §9.2, §9.4). Drives risk-weighted
 * validation: low-confidence high-impact items cannot be quick-confirmed.
 */
enum ConfidenceLevel: string
{
    case HIGH = 'high';
    case MEDIUM = 'medium';
    case LOW = 'low';
}

<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Trace edge semantics between canonical objects (PRD §12.3 trace chain).
 */
enum RelationType: string
{
    case DERIVED_FROM = 'derived_from';
    case SATISFIES = 'satisfies';
    case VERIFIES = 'verifies';
    case REFINES = 'refines';
    case CONFLICTS_WITH = 'conflicts_with';
    case DEPENDS_ON = 'depends_on';
    case IMPLEMENTS = 'implements';
    case TRACES_TO = 'traces_to';
    case SUPERSEDES = 'supersedes';
    case MITIGATES = 'mitigates';

    /** Human-readable relation label, e.g. "derived from". */
    public function label(): string
    {
        return str_replace('_', ' ', $this->value);
    }

    /** The inverse relation, for reverse traceability walks where meaningful. */
    public function inverse(): self
    {
        return match ($this) {
            self::DERIVED_FROM => self::TRACES_TO,
            self::TRACES_TO => self::DERIVED_FROM,
            default => $this,
        };
    }
}

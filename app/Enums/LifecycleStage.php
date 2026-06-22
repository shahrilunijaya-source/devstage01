<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * URSB lifecycle stages (PRD §5, §10). One Module runs all stages in order.
 * SPECIALIST is optional; every other stage is mandatory.
 */
enum LifecycleStage: string
{
    case BRS = 'BRS';
    case URS = 'URS';
    case SRS = 'SRS';
    case SDS = 'SDS';
    case SLD_DBD_IFD = 'SLD_DBD_IFD';
    case SPECIALIST = 'SPECIALIST';
    case PROTOTYPE = 'PROTOTYPE';
    case VALIDATION = 'VALIDATION';
    case DEPLOY = 'DEPLOY';

    /** Sequence index for Gantt ordering / stage progression. */
    public function order(): int
    {
        return match ($this) {
            self::BRS => 1,
            self::URS => 2,
            self::SRS => 3,
            self::SDS => 4,
            self::SLD_DBD_IFD => 5,
            self::SPECIALIST => 6,
            self::PROTOTYPE => 7,
            self::VALIDATION => 8,
            self::DEPLOY => 9,
        };
    }

    public function isOptional(): bool
    {
        return $this === self::SPECIALIST;
    }

    public function label(): string
    {
        return match ($this) {
            self::SLD_DBD_IFD => 'SLD/DBD/IFD',
            default => $this->value,
        };
    }

    /** All stages in lifecycle order — used to auto-seed stages on Module create. */
    public static function ordered(): array
    {
        $cases = self::cases();
        usort($cases, fn (self $a, self $b): int => $a->order() <=> $b->order());

        return $cases;
    }
}

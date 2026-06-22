<?php

declare(strict_types=1);

namespace App\Services\Change\Exceptions;

/**
 * Raised when separation of duties is violated (PRD §6.3). Extends
 * ChangeManagementException so existing PEP guards surface it uniformly.
 */
class SeparationOfDutiesException extends ChangeManagementException
{
    //
}

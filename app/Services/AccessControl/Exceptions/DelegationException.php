<?php

declare(strict_types=1);

namespace App\Services\AccessControl\Exceptions;

use RuntimeException;

/**
 * Raised when a delegation is invalid — most often when a delegator tries to
 * delegate access they do not themselves hold (PRD §6.3).
 */
class DelegationException extends RuntimeException {}

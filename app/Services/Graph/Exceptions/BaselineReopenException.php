<?php

declare(strict_types=1);

namespace App\Services\Graph\Exceptions;

use RuntimeException;

/** Thrown when a baseline cannot be reopened (wrong status, already superseded). */
class BaselineReopenException extends RuntimeException {}

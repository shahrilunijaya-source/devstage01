<?php

declare(strict_types=1);

namespace App\Services\Ai\Exceptions;

use RuntimeException;

/** Honest refusal from the refinement engine — never fabricated content. */
class RefinementException extends RuntimeException {}

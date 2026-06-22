<?php

declare(strict_types=1);

namespace App\Services\Session\Analysis\Exceptions;

use RuntimeException;

/**
 * Raised when an analyst cannot produce usable drafts (no API key, malformed
 * model output, etc.). The session engine catches it and falls back to the
 * deterministic analyst so pre-analysis never hard-fails.
 */
class AnalysisException extends RuntimeException {}

<?php

declare(strict_types=1);

namespace App\Services\Session\Analysis;

use App\Models\Graph\EngObject;

/**
 * Drafts traceable findings and requirements from a piece of evidence (PRD §9.2).
 * Implementations may be deterministic/offline or LLM-backed; the session engine
 * depends only on this contract.
 */
interface EvidenceAnalyst
{
    /** @throws Exceptions\AnalysisException when drafting fails irrecoverably */
    public function analyze(EngObject $evidence): AnalysisResult;
}

<?php

declare(strict_types=1);

namespace App\Services\Session\Analysis;

use App\Enums\ConfidenceLevel;
use App\Enums\ObjectType;
use App\Models\Graph\EngObject;

/**
 * Offline, deterministic analyst (PRD §9.2). Drafts a single finding and a
 * single business requirement per evidence with no external calls — the safe
 * default and the fallback when the LLM analyst is unavailable.
 */
class DeterministicEvidenceAnalyst implements EvidenceAnalyst
{
    public function analyze(EngObject $evidence): AnalysisResult
    {
        $finding = new DraftedObject(
            ObjectType::FINDING,
            'Finding: '.$evidence->title,
            $evidence->body,
            ConfidenceLevel::MEDIUM,
            'medium',
        );

        $requirement = new DraftedObject(
            ObjectType::BUSINESS_REQUIREMENT,
            'Draft requirement from '.$evidence->ref,
            'System shall address: '.$evidence->title,
            ConfidenceLevel::LOW,
            'high',
        );

        return new AnalysisResult($finding, [$requirement], [$evidence->ref]);
    }
}

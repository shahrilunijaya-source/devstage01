<?php

declare(strict_types=1);

namespace App\Services\Session\Analysis;

/**
 * The drafts produced from one piece of evidence: a finding plus one or more
 * requirements, with the evidence refs each draft cites (PRD §9.2 traceability).
 */
final readonly class AnalysisResult
{
    /**
     * @param  array<int, DraftedObject>  $requirements
     * @param  array<int, string>  $citations  evidence refs the drafts derive from
     */
    public function __construct(
        public DraftedObject $finding,
        public array $requirements,
        public array $citations,
    ) {}
}

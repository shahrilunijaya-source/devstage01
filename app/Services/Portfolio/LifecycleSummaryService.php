<?php

declare(strict_types=1);

namespace App\Services\Portfolio;

use App\Models\Project;
use App\Services\Design\DesignService;
use App\Services\Issue\IssueService;
use App\Services\Prototype\PrototypeService;
use App\Services\Verification\RtmService;
use App\Services\Verification\VerificationService;

/**
 * One-glance lifecycle snapshot for a project: how far each requirement has
 * travelled along evidence → design → prototype → verification → traceability.
 * Composes the per-stage registers so the project page tells the whole story.
 */
class LifecycleSummaryService
{
    public function __construct(
        private readonly DesignService $design,
        private readonly PrototypeService $prototype,
        private readonly VerificationService $verification,
        private readonly RtmService $rtm,
        private readonly IssueService $issues,
    ) {}

    /** @return array<string, mixed> */
    public function summarize(Project $project): array
    {
        // Compute the V&V register once and feed it to the RTM so register()
        // isn't executed twice.
        $vv = $this->verification->register($project);
        $verification = $vv['summary'];

        return [
            'requirements' => $verification['requirements'],
            'designed_pct' => $this->design->register($project)['summary']['designed_pct'],
            'prototyped_pct' => $this->prototype->register($project)['summary']['demoed_pct'],
            'verified_pct' => $verification['verified_pct'],
            'traced_pct' => $this->rtm->matrix($project, $vv['rows'])['summary']['traced_pct'],
            'open_defects' => $verification['open_defects'],
            'open_issues' => $this->issues->forProject($project)['summary']['open'],
        ];
    }
}

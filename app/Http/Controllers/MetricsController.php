<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Portfolio\Session;
use App\Models\Project;
use App\Services\AccessControl\PolicyDecisionPoint;
use App\Services\Metrics\CoverageService;
use App\Services\Metrics\MetricsService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Metrics & Quality Analytics dashboard (PRD §17). Read-only, ACL view-gated.
 */
class MetricsController extends Controller
{
    public function __construct(
        private readonly PolicyDecisionPoint $pdp,
        private readonly MetricsService $metrics,
    ) {}

    public function show(Request $request, Project $project): View
    {
        abort_unless($this->pdp->can($request->user(), 'view', $project)->permitted, 403, 'Access denied by ACL.');

        $sessions = Session::where('project_id', $project->id)
            ->with('stage')
            ->get()
            ->map(fn (Session $s): array => ['session' => $s, 'metrics' => $this->metrics->sessionMetrics($s)]);

        return view('metrics.show', [
            'project' => $project,
            'metrics' => $this->metrics->projectMetrics($project),
            'sessions' => $sessions,
        ]);
    }

    /** Coverage & completeness matrix (PRD §7) — item-level traceability gaps. */
    public function coverage(Request $request, Project $project, CoverageService $coverage): View
    {
        abort_unless($this->pdp->can($request->user(), 'view', $project)->permitted, 403, 'Access denied by ACL.');

        return view('metrics.coverage', $coverage->matrix($project));
    }
}

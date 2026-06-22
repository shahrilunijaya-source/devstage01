<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ObjectType;
use App\Models\Graph\EngObject;
use App\Models\Portfolio\Session;
use App\Models\Project;
use App\Services\AccessControl\PolicyDecisionPoint;
use App\Services\Metrics\ActivityService;
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

    /** Chronological audit timeline for the project (PRD §12, §17). */
    public function activity(Request $request, Project $project, ActivityService $activity): View
    {
        abort_unless($this->pdp->can($request->user(), 'view', $project)->permitted, 403, 'Access denied by ACL.');

        return view('metrics.activity', [
            'project' => $project,
            'events' => $activity->feed($project, $request->user()),
        ]);
    }

    /** Risk register — every RISK object for the project, worst impact first (PRD §17). */
    public function risks(Request $request, Project $project): View
    {
        abort_unless($this->pdp->can($request->user(), 'view', $project)->permitted, 403, 'Access denied by ACL.');

        $user = $request->user();
        $rank = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];

        $risks = EngObject::forProject($project->id)
            ->where('type', ObjectType::RISK->value)
            ->with('owner', 'sourceObject')
            ->orderByDesc('id')
            ->get()
            ->filter(fn ($r) => $this->pdp->allows($user, 'view', $r))
            ->sortBy(fn ($r) => $rank[$r->impact] ?? 4)
            ->values();

        return view('metrics.risks', [
            'project' => $project,
            'risks' => $risks,
        ]);
    }

    /** Decision register — every DECISION object recorded for the project (PRD §9). */
    public function decisions(Request $request, Project $project): View
    {
        abort_unless($this->pdp->can($request->user(), 'view', $project)->permitted, 403, 'Access denied by ACL.');

        $user = $request->user();

        $decisions = EngObject::forProject($project->id)
            ->where('type', ObjectType::DECISION->value)
            ->with('owner', 'sourceObject')
            ->orderByDesc('id')
            ->get()
            ->filter(fn ($d) => $this->pdp->allows($user, 'view', $d))
            ->values();

        return view('metrics.decisions', [
            'project' => $project,
            'decisions' => $decisions,
        ]);
    }
}

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
use Illuminate\Support\Collection;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

        return view('metrics.risks', [
            'project' => $project,
            'risks' => $this->riskObjects($project, $request->user()),
        ]);
    }

    /** Risk register as a CSV download. */
    public function risksCsv(Request $request, Project $project): StreamedResponse
    {
        abort_unless($this->pdp->can($request->user(), 'view', $project)->permitted, 403, 'Access denied by ACL.');

        return $this->streamCsv(
            "{$project->code}-risks.csv",
            ['Ref', 'Impact', 'Risk', 'Status', 'Source', 'Owner'],
            $this->riskObjects($project, $request->user())->map(fn (EngObject $r): array => [
                $r->ref, $r->impact, $r->title, $r->status?->value,
                $r->sourceObject?->ref ?? $r->source, $r->owner?->name,
            ]),
        );
    }

    /** Decision register — every DECISION object recorded for the project (PRD §9). */
    public function decisions(Request $request, Project $project): View
    {
        abort_unless($this->pdp->can($request->user(), 'view', $project)->permitted, 403, 'Access denied by ACL.');

        return view('metrics.decisions', [
            'project' => $project,
            'decisions' => $this->decisionObjects($project, $request->user()),
        ]);
    }

    /** Decision register as a CSV download. */
    public function decisionsCsv(Request $request, Project $project): StreamedResponse
    {
        abort_unless($this->pdp->can($request->user(), 'view', $project)->permitted, 403, 'Access denied by ACL.');

        return $this->streamCsv(
            "{$project->code}-decisions.csv",
            ['Ref', 'Decision', 'Resolves', 'By', 'When'],
            $this->decisionObjects($project, $request->user())->map(fn (EngObject $d): array => [
                $d->ref, $d->title, $d->sourceObject?->ref ?? $d->source,
                $d->owner?->name, optional($d->created_at)->toDateTimeString(),
            ]),
        );
    }

    /** RISK objects the user may view, worst impact first. */
    private function riskObjects(Project $project, $user): Collection
    {
        $rank = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];

        return EngObject::forProject($project->id)
            ->where('type', ObjectType::RISK->value)
            ->with('owner', 'sourceObject')
            ->orderByDesc('id')
            ->get()
            ->filter(fn ($r) => $this->pdp->allows($user, 'view', $r))
            ->sortBy(fn ($r) => $rank[$r->impact] ?? 4)
            ->values();
    }

    /** DECISION objects the user may view, newest first. */
    private function decisionObjects(Project $project, $user): Collection
    {
        return EngObject::forProject($project->id)
            ->where('type', ObjectType::DECISION->value)
            ->with('owner', 'sourceObject')
            ->orderByDesc('id')
            ->get()
            ->filter(fn ($d) => $this->pdp->allows($user, 'view', $d))
            ->values();
    }

    /**
     * Stream a collection of rows as a CSV download.
     *
     * @param  array<int, string>  $headers
     * @param  Collection<int, array<int, mixed>>  $rows
     */
    private function streamCsv(string $filename, array $headers, Collection $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                fputcsv($out, array_map([self::class, 'csvSafe'], $row));
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /**
     * Neutralise spreadsheet formula injection: a cell beginning with =, +, -, @
     * (or a control char) is executed as a formula by Excel/Sheets on open. Prefix
     * such values with a tab so they render as literal text.
     */
    public static function csvSafe(mixed $value): string
    {
        $value = (string) $value;

        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "\t".$value : $value;
    }
}

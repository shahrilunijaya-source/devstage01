<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\AccessControl\PolicyDecisionPoint;
use App\Services\Verification\RtmService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Requirements Traceability Matrix (PRD §17) — the end-to-end compliance
 * deliverable. Read-only; gated by project 'view'. Exportable as CSV and PDF.
 */
class RtmController extends Controller
{
    public function __construct(
        private readonly PolicyDecisionPoint $pdp,
        private readonly RtmService $rtm,
    ) {}

    public function index(Request $request, Project $project): View
    {
        $this->authorizeView($request, $project);

        return view('rtm.index', $this->rtm->matrix($project));
    }

    public function pdf(Request $request, Project $project): Response
    {
        $this->authorizeView($request, $project);

        $data = $this->rtm->matrix($project) + ['generatedBy' => $request->user()->name, 'generatedAt' => now()];

        return Pdf::loadView('rtm.pdf', $data)->download("rtm-{$project->code}.pdf");
    }

    public function csv(Request $request, Project $project): StreamedResponse
    {
        $this->authorizeView($request, $project);

        $rows = $this->rtm->matrix($project)['rows'];
        $safe = MetricsController::csvSafe(...);

        return response()->streamDownload(function () use ($rows, $safe): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Requirement', 'Title', 'Status', 'Evidence/Findings', 'Design', 'Test cases', 'Open defects', 'Verification']);
            foreach ($rows as $row) {
                fputcsv($out, array_map($safe, [
                    $row['requirement']->ref, $row['requirement']->title, $row['status'],
                    $row['support']->implode(' '), $row['design']->implode(' '),
                    (string) $row['cases'], (string) $row['open_defects'], $row['verification'],
                ]));
            }
            fclose($out);
        }, "rtm-{$project->code}.csv", ['Content-Type' => 'text/csv']);
    }

    private function authorizeView(Request $request, Project $project): void
    {
        abort_unless($this->pdp->can($request->user(), 'view', $project)->permitted, 403, 'Access denied by ACL.');
    }
}

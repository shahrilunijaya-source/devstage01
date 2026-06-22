<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ObjectType;
use App\Models\Graph\EngObject;
use App\Models\Project;
use App\Services\AccessControl\PolicyDecisionPoint;
use App\Services\NotificationService;
use App\Services\Verification\VerificationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Verification & Validation register (PRD §17). Read gated by 'view'; authoring
 * test cases and recording results are validation activities gated by 'validate'.
 */
class VerificationController extends Controller
{
    public function __construct(
        private readonly PolicyDecisionPoint $pdp,
        private readonly VerificationService $verification,
    ) {}

    public function index(Request $request, Project $project): View
    {
        abort_unless($this->pdp->can($request->user(), 'view', $project)->permitted, 403, 'Access denied by ACL.');

        return view('verification.index', $this->verification->register($project) + [
            'canValidate' => $this->pdp->can($request->user(), 'validate', $project)->permitted,
        ]);
    }

    /** Author a test case that verifies a requirement. */
    public function storeTestCase(Request $request, EngObject $requirement): RedirectResponse
    {
        $this->authorizeValidate($request, $requirement);
        $this->assertRequirement($requirement);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'steps' => ['nullable', 'string', 'max:10000'],
        ]);

        $case = $this->verification->addTestCase($requirement, $data['title'], $data['steps'] ?? null, $request->user());

        return $this->back($requirement->project, "Test case {$case->ref} added for {$requirement->ref}.");
    }

    /** Record a pass/fail/blocked result against a test case. */
    public function recordResult(Request $request, EngObject $case, NotificationService $notifications): RedirectResponse
    {
        $this->authorizeValidate($request, $case);
        abort_unless($case->type === ObjectType::TEST_CASE, 404);

        $data = $request->validate([
            'outcome' => ['required', 'in:pass,fail,blocked'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $this->verification->recordResult($case, $data['outcome'], $data['note'] ?? null, $request->user());

        // A failing requirement is a real risk to the baseline — surface it.
        if ($data['outcome'] === 'fail') {
            $notifications->notifyProjectBindings(
                $case->project, 'verification_failed',
                "Test case {$case->ref} failed — a requirement is no longer verified.",
                $request->user()->id,
            );
        }

        return $this->back($case->project, "Result recorded for {$case->ref}: {$data['outcome']}.");
    }

    /** Export the V&V matrix as compliance evidence (PRD §17 traceability). */
    public function csv(Request $request, Project $project): StreamedResponse
    {
        abort_unless($this->pdp->can($request->user(), 'view', $project)->permitted, 403, 'Access denied by ACL.');

        $rows = $this->verification->register($project)['rows'];
        $safe = MetricsController::csvSafe(...);

        return response()->streamDownload(function () use ($rows, $safe): void {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Requirement', 'Title', 'Status', 'Test case', 'Test title', 'Latest result']);
            foreach ($rows as $row) {
                /** @var EngObject $req */
                $req = $row['requirement'];
                if ($row['cases']->isEmpty()) {
                    fputcsv($out, array_map($safe, [$req->ref, $req->title, $row['status'], '—', '—', '—']));

                    continue;
                }
                foreach ($row['cases'] as $c) {
                    fputcsv($out, array_map($safe, [
                        $req->ref, $req->title, $row['status'],
                        $c['case']->ref, $c['case']->title, $c['outcome'] ?? 'not run',
                    ]));
                }
            }
            fclose($out);
        }, "vnv-{$project->code}.csv", ['Content-Type' => 'text/csv']);
    }

    private function authorizeValidate(Request $request, EngObject $object): void
    {
        abort_unless($this->pdp->can($request->user(), 'validate', $object->project)->permitted, 403, 'Access denied by ACL.');
    }

    private function assertRequirement(EngObject $object): void
    {
        abort_unless(in_array($object->type, [
            ObjectType::BUSINESS_REQUIREMENT, ObjectType::USER_REQUIREMENT,
            ObjectType::FUNCTIONAL_REQUIREMENT, ObjectType::NON_FUNCTIONAL_REQUIREMENT,
        ], true), 404);
    }

    private function back(Project $project, string $message): RedirectResponse
    {
        return redirect()->route('verification.index', $project)->with('status', $message);
    }
}

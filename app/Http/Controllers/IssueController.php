<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ObjectType;
use App\Models\Graph\EngObject;
use App\Models\Project;
use App\Services\AccessControl\PolicyDecisionPoint;
use App\Services\Issue\IssueService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Project issue log (PRD §17). Read gated by 'view'; raising/resolving issues
 * gated by 'edit'.
 */
class IssueController extends Controller
{
    public function __construct(
        private readonly PolicyDecisionPoint $pdp,
        private readonly IssueService $issues,
    ) {}

    public function index(Request $request, Project $project): View
    {
        abort_unless($this->pdp->can($request->user(), 'view', $project)->permitted, 403, 'Access denied by ACL.');

        return view('issues.index', $this->issues->forProject($project) + [
            'canEdit' => $this->pdp->can($request->user(), 'edit', $project)->permitted,
            'severities' => IssueService::SEVERITIES,
        ]);
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        abort_unless($this->pdp->can($request->user(), 'edit', $project)->permitted, 403, 'Access denied by ACL.');

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:10000'],
            'severity' => ['required', 'in:'.implode(',', IssueService::SEVERITIES)],
        ]);

        $issue = $this->issues->raise($project, $data['title'], $data['body'] ?? null, $data['severity'], $request->user());

        return redirect()->route('issues.index', $project)->with('status', "Issue {$issue->ref} raised.");
    }

    public function resolve(Request $request, EngObject $issue): RedirectResponse
    {
        abort_unless($this->pdp->can($request->user(), 'edit', $issue->project)->permitted, 403, 'Access denied by ACL.');
        abort_unless($issue->type === ObjectType::ISSUE, 404);

        $data = $request->validate(['note' => ['nullable', 'string', 'max:2000']]);

        $this->issues->resolve($issue, $data['note'] ?? null, $request->user());

        return redirect()->route('issues.index', $issue->project)->with('status', "Issue {$issue->ref} resolved.");
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\AccessControl\PolicyDecisionPoint;
use App\Services\Portfolio\ObjectiveService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Project Objective Baseline (spec §6). View gated by 'view', capture/revise by
 * 'edit', approval by 'approve' — the same authorities as the rest of the
 * lifecycle.
 */
class ObjectiveController extends Controller
{
    public function __construct(
        private readonly PolicyDecisionPoint $pdp,
        private readonly ObjectiveService $objectives,
    ) {}

    public function show(Request $request, Project $project): View
    {
        abort_unless($this->pdp->can($request->user(), 'view', $project)->permitted, 403, 'Access denied by ACL.');

        return view('objective.show', [
            'project' => $project,
            'objective' => $this->objectives->objectiveFor($project),
            'canEdit' => $this->pdp->can($request->user(), 'edit', $project)->permitted,
            'canApprove' => $this->pdp->can($request->user(), 'approve', $project)->permitted,
        ]);
    }

    public function edit(Request $request, Project $project): View
    {
        abort_unless($this->pdp->can($request->user(), 'edit', $project)->permitted, 403, 'Access denied by ACL.');

        return view('objective.edit', [
            'project' => $project,
            'objective' => $this->objectives->objectiveFor($project),
            'wizard' => $request->boolean('wizard'),
        ]);
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        abort_unless($this->pdp->can($request->user(), 'edit', $project)->permitted, 403, 'Access denied by ACL.');

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'business_problem' => ['required', 'string', 'max:10000'],
            ...collect(ObjectiveService::FIELDS)
                ->mapWithKeys(fn (string $f): array => [$f => ['nullable', 'string', 'max:5000']])
                ->all(),
        ]);

        $objective = $this->objectives->capture($project, $data, $request->user());

        return redirect()->route('objective.show', $project)
            ->with('status', "Objective {$objective->ref} saved (v{$objective->current_version}). It needs approval before the BRS gate counts it.");
    }

    public function approve(Request $request, Project $project): RedirectResponse
    {
        abort_unless($this->pdp->can($request->user(), 'approve', $project)->permitted, 403, 'Access denied by ACL.');

        try {
            $objective = $this->objectives->approve($project, $request->user());
        } catch (RuntimeException $e) {
            return redirect()->route('objective.show', $project)->with('error', $e->getMessage());
        }

        return redirect()->route('objective.show', $project)
            ->with('status', "Objective {$objective->ref} approved — it now anchors the requirement chain.");
    }
}

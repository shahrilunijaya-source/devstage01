<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Graph\ChangeRequest;
use App\Models\Graph\EngObject;
use App\Models\Project;
use App\Services\AccessControl\PolicyDecisionPoint;
use App\Services\Change\ChangeManagementService;
use App\Services\Change\Exceptions\ChangeManagementException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Change Management Engine surface (PRD §9.3.3). Raising a change needs 'edit',
 * approval 'approve', applying 'baseline' — all via the PDP.
 */
class ChangeRequestController extends Controller
{
    public function __construct(
        private readonly PolicyDecisionPoint $pdp,
        private readonly ChangeManagementService $engine,
    ) {}

    public function index(Request $request, Project $project): View
    {
        abort_unless($this->pdp->can($request->user(), 'view', $project)->permitted, 403, 'Access denied by ACL.');

        return view('changes.index', [
            'project' => $project,
            'changes' => ChangeRequest::where('project_id', $project->id)
                ->with('target', 'raisedBy')->latest()->get(),
        ]);
    }

    public function create(Request $request, EngObject $object): View
    {
        $this->authorizeEdit($request, $object);

        return view('changes.create', [
            'object' => $object,
            'impact' => $this->engine->analyzeImpact($object),
        ]);
    }

    public function store(Request $request, EngObject $object): RedirectResponse
    {
        $this->authorizeEdit($request, $object);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'new_title' => ['nullable', 'string', 'max:255'],
            'new_body' => ['nullable', 'string'],
        ]);

        $proposed = array_filter([
            'title' => $data['new_title'] ?? null,
            'body' => $data['new_body'] ?? null,
        ], fn ($v) => $v !== null);

        $cr = $this->engine->open($object, $request->user(), $data['title'], $data['description'] ?? null, $proposed);

        return redirect()->route('changes.show', $cr)->with('status', "Change request {$cr->ref} opened.");
    }

    public function show(Request $request, ChangeRequest $change): View
    {
        abort_unless($this->pdp->can($request->user(), 'view', $change->project)->permitted, 403, 'Access denied by ACL.');

        return view('changes.show', [
            'change' => $change->load('target', 'raisedBy', 'decidedBy'),
            'canApprove' => $this->pdp->can($request->user(), 'approve', $change->project)->permitted,
            'canApply' => $this->pdp->can($request->user(), 'baseline', $change->project)->permitted,
            'isRaiser' => (int) $change->raised_by === (int) $request->user()->id,
            'sodEnabled' => (bool) config('acl.separation_of_duties'),
        ]);
    }

    public function approve(Request $request, ChangeRequest $change): RedirectResponse
    {
        abort_unless($this->pdp->can($request->user(), 'approve', $change->project)->permitted, 403, 'Access denied by ACL.');

        return $this->guard($change, fn () => $this->engine->approve($change, $request->user()), 'Change request approved.');
    }

    public function reject(Request $request, ChangeRequest $change): RedirectResponse
    {
        abort_unless($this->pdp->can($request->user(), 'approve', $change->project)->permitted, 403, 'Access denied by ACL.');

        return $this->guard($change, fn () => $this->engine->reject($change, $request->user()), 'Change request rejected.');
    }

    public function apply(Request $request, ChangeRequest $change): RedirectResponse
    {
        abort_unless($this->pdp->can($request->user(), 'baseline', $change->project)->permitted, 403, 'Access denied by ACL.');

        return $this->guard($change, fn () => $this->engine->apply($change, $request->user()),
            'Change applied. Affected downstream objects flagged for re-confirmation.');
    }

    private function authorizeEdit(Request $request, EngObject $object): void
    {
        abort_unless($this->pdp->can($request->user(), 'edit', $object->project)->permitted, 403, 'Access denied by ACL.');
    }

    private function guard(ChangeRequest $change, callable $action, string $okMessage): RedirectResponse
    {
        try {
            $action();
        } catch (ChangeManagementException $e) {
            return redirect()->route('changes.show', $change)->with('error', $e->getMessage());
        }

        return redirect()->route('changes.show', $change)->with('status', $okMessage);
    }
}

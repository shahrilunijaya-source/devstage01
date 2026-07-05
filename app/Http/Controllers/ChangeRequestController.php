<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Graph\ChangeRequest;
use App\Models\Graph\EngObject;
use App\Models\Project;
use App\Services\AccessControl\PolicyDecisionPoint;
use App\Services\Change\ChangeManagementService;
use App\Services\Change\Exceptions\ChangeManagementException;
use App\Services\NotificationService;
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
        private readonly NotificationService $notifications,
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

        $this->notifications->notifyProjectBindings(
            $object->project, 'change_raised',
            "Change request {$cr->ref} raised on {$object->ref}: {$cr->title}.",
            $request->user()->id,
        );

        return redirect()->route('changes.show', $cr)->with('status', "Change request {$cr->ref} opened.");
    }

    public function show(Request $request, ChangeRequest $change): View
    {
        abort_unless($this->pdp->can($request->user(), 'view', $change->project)->permitted, 403, 'Access denied by ACL.');

        $change->load('target', 'raisedBy', 'decidedBy');

        // Downstream objects flagged by this CR, awaiting impact disposition.
        $flagged = $change->status === 'applied'
            ? EngObject::where('project_id', $change->project_id)
                ->where('attributes->impact_from', $change->ref)
                ->get()
                ->filter(fn (EngObject $o): bool => $this->pdp->allows($request->user(), 'view', $o))
                ->values()
            : collect();

        return view('changes.show', [
            'change' => $change,
            'flagged' => $flagged,
            'canApprove' => $this->pdp->can($request->user(), 'approve', $change->project)->permitted,
            'canApply' => $this->pdp->can($request->user(), 'baseline', $change->project)->permitted,
            'isRaiser' => (int) $change->raised_by === (int) $request->user()->id,
            'sodEnabled' => (bool) config('acl.separation_of_duties'),
        ]);
    }

    public function approve(Request $request, ChangeRequest $change): RedirectResponse
    {
        abort_unless($this->pdp->can($request->user(), 'approve', $change->project)->permitted, 403, 'Access denied by ACL.');

        $data = $request->validate(['override_reason' => ['nullable', 'string', 'max:500']]);

        return $this->guard($change, fn () => $this->engine->approve($change, $request->user(), $data['override_reason'] ?? null), 'Change request approved.',
            $request, 'change_approved', "Your change request {$change->ref} was approved.");
    }

    public function reject(Request $request, ChangeRequest $change): RedirectResponse
    {
        abort_unless($this->pdp->can($request->user(), 'approve', $change->project)->permitted, 403, 'Access denied by ACL.');

        return $this->guard($change, fn () => $this->engine->reject($change, $request->user()), 'Change request rejected.',
            $request, 'change_rejected', "Your change request {$change->ref} was rejected.");
    }

    public function apply(Request $request, ChangeRequest $change): RedirectResponse
    {
        abort_unless($this->pdp->can($request->user(), 'baseline', $change->project)->permitted, 403, 'Access denied by ACL.');

        return $this->guard($change, fn () => $this->engine->apply($change, $request->user()),
            'Change applied. Affected downstream objects flagged for re-confirmation.',
            $request, 'change_applied', "Your change request {$change->ref} was applied.");
    }

    /** Disposition one downstream object flagged by an applied CR (spec §13). */
    public function disposition(Request $request, ChangeRequest $change, EngObject $object): RedirectResponse
    {
        abort_unless($this->pdp->can($request->user(), 'edit', $change->project)->permitted, 403, 'Access denied by ACL.');

        $data = $request->validate(['impact' => ['required', 'in:no_impact,update_required,invalidated']]);

        try {
            $this->engine->disposition($change, $object, $data['impact'], $request->user());
        } catch (ChangeManagementException $e) {
            return redirect()->route('changes.show', $change)->with('error', $e->getMessage());
        }

        return redirect()->route('changes.show', $change)
            ->with('status', "{$object->ref} dispositioned as ".str_replace('_', ' ', $data['impact']).'.');
    }

    private function authorizeEdit(Request $request, EngObject $object): void
    {
        abort_unless($this->pdp->can($request->user(), 'edit', $object->project)->permitted, 403, 'Access denied by ACL.');
    }

    /**
     * Run a change-engine action; on success notify the raiser of the outcome
     * (unless they are the actor), on failure surface the engine message.
     */
    private function guard(ChangeRequest $change, callable $action, string $okMessage, Request $request, string $type, string $raiserMessage): RedirectResponse
    {
        try {
            $action();
        } catch (ChangeManagementException $e) {
            return redirect()->route('changes.show', $change)->with('error', $e->getMessage());
        }

        if ((int) $change->raised_by !== (int) $request->user()->id) {
            $this->notifications->notify((int) $change->raised_by, $type, $raiserMessage, $change->project_id);
        }

        return redirect()->route('changes.show', $change)->with('status', $okMessage);
    }
}

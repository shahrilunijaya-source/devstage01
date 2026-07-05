<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ObjectStatus;
use App\Enums\ObjectType;
use App\Models\Graph\EngObject;
use App\Models\Portfolio\Session;
use App\Services\AccessControl\PolicyDecisionPoint;
use App\Services\Discussion\DiscussionService;
use App\Services\Graph\ObjectGraphService;
use App\Services\Knowledge\EvidenceIndexer;
use App\Services\Knowledge\KnowledgeResolver;
use App\Services\NotificationService;
use App\Services\Session\ConflictDetectionService;
use App\Services\Session\Exceptions\SessionEngineException;
use App\Services\Session\SessionEngineService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Drives one requirement session through the five-phase engine (PRD §9).
 * Reads gated by 'view', engine actions by 'edit'/'validate' via the PDP.
 */
class SessionController extends Controller
{
    public function __construct(
        private readonly PolicyDecisionPoint $pdp,
        private readonly SessionEngineService $engine,
    ) {}

    public function show(Request $request, Session $session, KnowledgeResolver $knowledge): View
    {
        $this->authorizeView($request, $session);
        $session->load(['stage', 'module', 'project']);

        $objects = EngObject::where('session_id', $session->id)
            ->orderBy('type')->orderBy('id')->get();

        return view('sessions.show', [
            'session' => $session,
            'objects' => $objects,
            'questions' => $knowledge->questionBank($session->project, $session->stage->stage),
            'canEdit' => $this->pdp->can($request->user(), 'edit', $session->project)->permitted,
            'canValidate' => $this->pdp->can($request->user(), 'validate', $session->project)->permitted,
            'canApprove' => $this->pdp->can($request->user(), 'approve', $session->project)->permitted,
            'discussions' => app(DiscussionService::class)->for($session, $request->user()),
        ]);
    }

    /**
     * Capture a piece of evidence into the session as an immutable EVIDENCE
     * object (PRD §8 — "evidence enters as immutable source"). Accepted only in
     * the pre-analysis phase, before the AI drafts hypotheses from it.
     */
    public function addEvidence(Request $request, Session $session, ObjectGraphService $graph, EvidenceIndexer $indexer): RedirectResponse
    {
        $this->authorizeEdit($request, $session);

        if ($session->phase !== 'pre_analysis') {
            return $this->back($session, 'Evidence can only be added during the pre-analysis phase.', true);
        }

        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'source_type' => ['required', 'in:paste,file'],
            'text' => ['required_if:source_type,paste', 'nullable', 'string'],
            // Allow-list document/image evidence only. `mimes` validates against the
            // content-guessed type (finfo), not the spoofable client Content-Type,
            // so an executable renamed to .pdf is still rejected.
            'file' => ['required_if:source_type,file', 'nullable', 'file', 'max:10240',
                'mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,csv,md,jpg,jpeg,png,gif,webp'],
            'classification' => ['nullable', 'in:public,internal,confidential,restricted'],
        ]);

        $attributes = ['kind' => $data['source_type']];
        $body = null;

        if ($data['source_type'] === 'file') {
            $file = $request->file('file');
            $path = $file->store("evidence/{$session->project_id}");
            $attributes += [
                'filename' => $file->getClientOriginalName(),
                'mime' => $file->getMimeType(), // server-derived, not the client header

                'size' => $file->getSize(),
                'path' => $path,
            ];
        } else {
            $body = $data['text'];
        }

        $object = $graph->create(
            ObjectType::EVIDENCE,
            $session->tenant_id ?? $session->project->tenant_id,
            $session->project_id,
            $data['label'],
            [
                'module_id' => $session->module_id,
                'stage_id' => $session->stage_id,
                'session_id' => $session->id,
                'owner_user_id' => $request->user()->id,
                'source' => 'upload',
                'status' => ObjectStatus::CONFIRMED_BY_EVIDENCE, // source-of-truth, not a hypothesis
                'classification' => $data['classification'] ?? 'internal',
                'attributes' => $attributes,
                'body' => $body,
                'changed_by' => $request->user()->id,
                'change_summary' => 'evidence captured',
            ],
        );

        // Make the evidence retrievable across the project corpus. Best-effort —
        // a missing key or embedding failure must never block the capture itself.
        try {
            $indexer->index($object);
        } catch (\Throwable $e) {
            report($e);
        }

        return $this->back($session, "Evidence {$object->ref} captured.");
    }

    public function preAnalyze(Request $request, Session $session): RedirectResponse
    {
        $this->authorizeEdit($request, $session);

        try {
            $n = $this->engine->preAnalyze($session);
        } catch (SessionEngineException $e) {
            return $this->back($session, $e->getMessage(), true);
        }

        return $this->back($session, "AI pre-analysis drafted {$n} objects. Awaiting quality-firewall review.");
    }

    public function firewall(Request $request, Session $session): RedirectResponse
    {
        abort_unless($this->pdp->can($request->user(), 'validate', $session->project)->permitted, 403, 'Access denied by ACL.');

        return $this->guard($session, fn () => $this->engine->passFirewall($session, $request->user()),
            'Quality firewall passed — session is ready.');
    }

    /** Firewall send-back (spec §9 "Refinement Required") — recorded reason required. */
    public function rejectFirewall(Request $request, Session $session): RedirectResponse
    {
        abort_unless($this->pdp->can($request->user(), 'validate', $session->project)->permitted, 403, 'Access denied by ACL.');

        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        return $this->guard($session, fn () => $this->engine->rejectFirewall($session, $request->user(), $data['reason']),
            'Sent back to pre-analysis — add evidence or re-run the AI drafts, then resubmit to the firewall.');
    }

    public function start(Request $request, Session $session): RedirectResponse
    {
        $this->authorizeEdit($request, $session);

        return $this->guard($session, fn () => $this->engine->startSession($session), 'Session started.');
    }

    public function scanConflicts(Request $request, Session $session, ConflictDetectionService $detector, NotificationService $notifications): RedirectResponse
    {
        $this->authorizeEdit($request, $session);

        $count = $detector->scan($session, $request->user()->id);

        if ($count > 0) {
            $notifications->notifyProjectBindings(
                $session->project, 'conflict_detected',
                "{$count} conflict group(s) flagged in session \"{$session->title}\" — resolve before approval.",
            );
        }

        return $this->back($session, $count === 0
            ? 'Conflict scan complete — no conflicting requirements found.'
            : "Conflict scan flagged {$count} conflict group(s). Resolve the flagged items before approval.");
    }

    public function consolidate(Request $request, Session $session): RedirectResponse
    {
        $this->authorizeEdit($request, $session);

        return $this->guard($session, fn () => $this->engine->consolidate($session), 'Session consolidated — ready for approval.');
    }

    public function approve(Request $request, Session $session, NotificationService $notifications): RedirectResponse
    {
        abort_unless($this->pdp->can($request->user(), 'approve', $session->project)->permitted, 403, 'Access denied by ACL.');

        try {
            $this->engine->approveSession($session, $request->user());
        } catch (SessionEngineException $e) {
            return $this->back($session, $e->getMessage(), true);
        }

        $notifications->notifyProjectBindings(
            $session->project, 'session_approved',
            "Session \"{$session->title}\" approved — ready for baseline.",
            $request->user()->id,
        );

        return $this->back($session, 'Session approved. Its objects can now be rolled into the stage baseline.');
    }

    public function capture(Request $request, Session $session, EngObject $object): RedirectResponse
    {
        $this->authorizeEdit($request, $session);
        abort_unless((int) $object->session_id === (int) $session->id, 404);

        $data = $request->validate([
            'decision' => ['required', 'in:confirm,correct,complete,decide'],
            'title' => ['nullable', 'string', 'max:255'],
            'body' => ['nullable', 'string'],
        ]);

        // Correct/Complete carry the human's revised text; confirm/decide ignore it.
        $opts = array_filter([
            'title' => $data['title'] ?? null,
            'body' => $data['body'] ?? null,
        ], fn ($v) => $v !== null && $v !== '');

        return $this->guard($session, fn () => $this->engine->capture($object, $data['decision'], $request->user(), $opts),
            "Item {$object->ref}: {$data['decision']} recorded.");
    }

    private function guard(Session $session, callable $action, string $okMessage): RedirectResponse
    {
        try {
            $action();
        } catch (SessionEngineException $e) {
            return $this->back($session, $e->getMessage(), true);
        }

        return $this->back($session, $okMessage);
    }

    private function authorizeView(Request $request, Session $session): void
    {
        abort_unless($this->pdp->can($request->user(), 'view', $session->project)->permitted, 403, 'Access denied by ACL.');
    }

    private function authorizeEdit(Request $request, Session $session): void
    {
        abort_unless($this->pdp->can($request->user(), 'edit', $session->project)->permitted, 403, 'Access denied by ACL.');
    }

    private function back(Session $session, string $message, bool $error = false): RedirectResponse
    {
        return redirect()->route('sessions.show', $session)
            ->with($error ? 'error' : 'status', $message);
    }
}

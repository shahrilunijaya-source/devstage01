<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Discussion;
use App\Models\DiscussionComment;
use App\Models\Graph\EngObject;
use App\Models\Portfolio\Session;
use App\Models\Portfolio\Stage;
use App\Models\Portfolio\StageBaseline;
use App\Models\Project;
use App\Services\AccessControl\PolicyDecisionPoint;
use App\Services\Discussion\DiscussionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;

/**
 * Discussions (spec §12). Opening/commenting needs 'view' (clients included —
 * their threads are forced client-visible); resolving, blocking flags and
 * conversions need 'edit'. Visibility filtering happens in the query layer
 * (Discussion::visibleTo), never in the template.
 */
class DiscussionController extends Controller
{
    /** Whitelisted attachment points. */
    private const DISCUSSABLES = [
        'project' => Project::class,
        'stage' => Stage::class,
        'session' => Session::class,
        'baseline' => StageBaseline::class,
        'object' => EngObject::class,
    ];

    public function __construct(
        private readonly PolicyDecisionPoint $pdp,
        private readonly DiscussionService $discussions,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'discussable_kind' => ['required', 'in:'.implode(',', array_keys(self::DISCUSSABLES))],
            'discussable_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
            'visibility' => ['nullable', 'in:internal,client'],
            'blocking' => ['nullable', 'boolean'],
        ]);

        $discussable = self::DISCUSSABLES[$data['discussable_kind']]::findOrFail($data['discussable_id']);
        $project = $discussable instanceof Project ? $discussable : $discussable->project;

        abort_unless($this->pdp->can($request->user(), 'view', $project)->permitted, 403, 'Access denied by ACL.');

        // Blocking threads gate the stage — that is an editorial act.
        $blocking = (bool) ($data['blocking'] ?? false);
        if ($blocking) {
            abort_unless($this->pdp->can($request->user(), 'edit', $project)->permitted, 403, 'Blocking discussions need edit rights.');
        }

        $this->discussions->open($discussable, $project, $request->user(), $data['title'], $data['body'], [
            'visibility' => $data['visibility'] ?? 'internal',
            'blocking' => $blocking,
        ]);

        return back()->with('status', 'Discussion opened.');
    }

    public function comment(Request $request, Discussion $discussion): RedirectResponse
    {
        $this->authorizeThread($request, $discussion, 'view');

        $data = $request->validate(['body' => ['required', 'string', 'max:10000']]);

        $this->discussions->comment($discussion, $request->user(), $data['body']);

        return back()->with('status', 'Comment added.');
    }

    public function resolve(Request $request, Discussion $discussion): RedirectResponse
    {
        $this->authorizeThread($request, $discussion, 'edit');

        $this->discussions->resolve($discussion, $request->user());

        return back()->with('status', 'Discussion resolved.');
    }

    public function reopen(Request $request, Discussion $discussion): RedirectResponse
    {
        $this->authorizeThread($request, $discussion, 'edit');

        $this->discussions->reopen($discussion);

        return back()->with('status', 'Discussion reopened.');
    }

    public function convert(Request $request, Discussion $discussion, DiscussionComment $comment): RedirectResponse
    {
        $this->authorizeThread($request, $discussion, 'edit');
        abort_unless((int) $comment->discussion_id === (int) $discussion->id, 404);

        $data = $request->validate([
            'target' => ['required', 'in:requirement,risk,decision,issue,change_request'],
        ]);

        try {
            $ref = $this->discussions->convert($comment, $data['target'], $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', "Comment converted to {$ref}.");
    }

    private function authorizeThread(Request $request, Discussion $discussion, string $action): void
    {
        abort_unless($this->pdp->can($request->user(), $action, $discussion->project)->permitted, 403, 'Access denied by ACL.');

        // Internal threads never exist for client users — not even by id.
        abort_if($request->user()->isClient() && $discussion->visibility !== 'client', 404);
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Discussion;

use App\Enums\ObjectStatus;
use App\Enums\ObjectType;
use App\Enums\RelationType;
use App\Models\Discussion;
use App\Models\DiscussionComment;
use App\Models\Graph\EngObject;
use App\Models\Portfolio\Session;
use App\Models\Portfolio\Stage;
use App\Models\Portfolio\StageBaseline;
use App\Models\Project;
use App\Models\User;
use App\Services\Change\ChangeManagementService;
use App\Services\Graph\ObjectGraphService;
use App\Services\Graph\TraceService;
use App\Services\NotificationService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use InvalidArgumentException;

/**
 * Contextual discussions (spec §12): open/comment/resolve/reopen threads at any
 * level, and convert a comment into a first-class graph artefact (requirement,
 * risk, decision, issue) or a change request — closing the loop from
 * conversation to record.
 */
class DiscussionService
{
    /** Comment conversion targets → graph object types. */
    public const CONVERT_TYPES = [
        'requirement' => ObjectType::BUSINESS_REQUIREMENT,
        'risk' => ObjectType::RISK,
        'decision' => ObjectType::DECISION,
        'issue' => ObjectType::ISSUE,
    ];

    public function __construct(
        private readonly ObjectGraphService $graph,
        private readonly TraceService $trace,
        private readonly ChangeManagementService $changes,
        private readonly NotificationService $notifications,
    ) {}

    /** Threads for one entity, visibility-filtered for the viewer. */
    public function for(Model $discussable, User $viewer): Collection
    {
        return Discussion::query()
            ->where('discussable_type', $discussable->getMorphClass())
            ->where('discussable_id', $discussable->getKey())
            ->visibleTo($viewer)
            ->with(['comments.user', 'opener', 'assignee', 'resolver'])
            ->orderByDesc('status')
            ->orderByDesc('id')
            ->get();
    }

    /**
     * @param  array{visibility?: string, blocking?: bool, assigned_to?: ?int, due_at?: ?string}  $opts
     */
    public function open(Model $discussable, Project $project, User $user, string $title, string $body, array $opts = []): Discussion
    {
        // Clients can never author internal notes — force their threads visible.
        $visibility = $user->isClient() ? 'client' : ($opts['visibility'] ?? 'internal');

        $discussion = Discussion::create([
            'tenant_id' => $project->tenant_id,
            'project_id' => $project->id,
            'discussable_type' => $discussable->getMorphClass(),
            'discussable_id' => $discussable->getKey(),
            'title' => $title,
            'visibility' => $visibility,
            'blocking' => (bool) ($opts['blocking'] ?? false),
            'opened_by' => $user->id,
            'assigned_to' => $opts['assigned_to'] ?? null,
            'due_at' => $opts['due_at'] ?? null,
        ]);

        $discussion->comments()->create(['user_id' => $user->id, 'body' => $body]);

        if ($discussion->assigned_to !== null && $discussion->assigned_to !== $user->id) {
            $this->notifications->notify(
                (int) $discussion->assigned_to, 'discussion_assigned',
                "Discussion \"{$title}\" assigned to you.", $project->id,
            );
        }

        return $discussion;
    }

    public function comment(Discussion $discussion, User $user, string $body): DiscussionComment
    {
        return $discussion->comments()->create(['user_id' => $user->id, 'body' => $body]);
    }

    public function resolve(Discussion $discussion, User $user): void
    {
        $discussion->update([
            'status' => 'resolved',
            'resolved_by' => $user->id,
            'resolved_at' => now(),
        ]);
    }

    public function reopen(Discussion $discussion): void
    {
        $discussion->update(['status' => 'open', 'resolved_by' => null, 'resolved_at' => null]);
    }

    /**
     * Convert a comment into a first-class record. Graph targets mint an object
     * (trace-linked to the discussed object when there is one); 'change_request'
     * opens a CR against the discussed object.
     */
    public function convert(DiscussionComment $comment, string $target, User $user): string
    {
        $discussion = $comment->discussion;
        $discussable = $discussion->discussable;

        if ($target === 'change_request') {
            if (! $discussable instanceof EngObject) {
                throw new InvalidArgumentException('A change request needs a target object — this discussion is not attached to one.');
            }

            $cr = $this->changes->open($discussable, $user, $discussion->title, $comment->body);
            $comment->update(['converted_ref' => $cr->ref]);

            return $cr->ref;
        }

        $type = self::CONVERT_TYPES[$target] ?? throw new InvalidArgumentException("Unknown conversion target: {$target}.");

        $scope = $discussable instanceof EngObject
            ? ['module_id' => $discussable->module_id, 'stage_id' => $discussable->stage_id, 'session_id' => $discussable->session_id]
            : ($discussable instanceof Stage
                ? ['module_id' => $discussable->module_id, 'stage_id' => $discussable->id]
                : ($discussable instanceof Session
                    ? ['module_id' => $discussable->module_id, 'stage_id' => $discussable->stage_id, 'session_id' => $discussable->id]
                    : ($discussable instanceof StageBaseline
                        ? ['module_id' => $discussable->module_id, 'stage_id' => $discussable->stage_id]
                        : [])));

        $object = $this->graph->create($type, (int) $discussion->tenant_id, (int) $discussion->project_id,
            $discussion->title, $scope + [
                'body' => $comment->body,
                'owner_user_id' => $user->id,
                'source' => "discussion #{$discussion->id}",
                'status' => ObjectStatus::NEEDS_CONFIRMATION,
                'changed_by' => $user->id,
                'change_summary' => "converted from discussion comment by {$user->name}",
            ]);

        if ($discussable instanceof EngObject) {
            $this->trace->link($discussable, $object, RelationType::DERIVED_FROM, null, $user->id);
        }

        $comment->update(['converted_ref' => $object->ref]);

        return $object->ref;
    }

    /**
     * Open blocking discussions that hold a stage's gate: on the stage itself,
     * its sessions, its baselines, or any object scoped to it.
     */
    public function blockingCountForStage(Stage $stage): int
    {
        $sessionIds = $stage->sessions()->pluck('id')->all();
        $baselineIds = $stage->baselines()->pluck('id')->all();
        $objectIds = EngObject::where('stage_id', $stage->id)->pluck('id')->all();

        return Discussion::query()
            ->where('project_id', $stage->project_id)
            ->open()
            ->where('blocking', true)
            ->where(function ($q) use ($stage, $sessionIds, $baselineIds, $objectIds): void {
                $q->where(fn ($w) => $w->where('discussable_type', Stage::class)->where('discussable_id', $stage->id));
                if ($sessionIds !== []) {
                    $q->orWhere(fn ($w) => $w->where('discussable_type', Session::class)->whereIn('discussable_id', $sessionIds));
                }
                if ($baselineIds !== []) {
                    $q->orWhere(fn ($w) => $w->where('discussable_type', StageBaseline::class)->whereIn('discussable_id', $baselineIds));
                }
                if ($objectIds !== []) {
                    $q->orWhere(fn ($w) => $w->where('discussable_type', EngObject::class)->whereIn('discussable_id', $objectIds));
                }
            })
            ->count();
    }
}

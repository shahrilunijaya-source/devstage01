<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\ObjectStatus;
use App\Enums\ObjectType;
use App\Models\Graph\EngObject;
use App\Models\Graph\TraceRelationship;
use App\Models\Project;
use App\Services\AccessControl\PolicyDecisionPoint;
use App\Services\Graph\TraceService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Single canonical-object inspection + traceability (PRD §12.3). Shows the
 * object, its immutable version history, direct relationships, and the full
 * forward/reverse trace reach — all gated and redacted by the PDP.
 */
class ObjectController extends Controller
{
    public function __construct(private readonly PolicyDecisionPoint $pdp) {}

    private const PER_PAGE = 25;

    /** Max search-term length accepted before truncation. */
    private const MAX_QUERY = 100;

    /** Escape LIKE metacharacters so the term matches literally. */
    private function escapeLike(string $term): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
    }

    /** Filterable, ACL-scoped browser over a project's canonical objects (PRD §12). */
    public function index(Request $request, Project $project): View
    {
        $user = $request->user();

        abort_unless($this->pdp->can($user, 'view', $project)->permitted, 403, 'Access denied by ACL.');

        $filters = [
            'type' => $request->query('type'),
            'status' => $request->query('status'),
            'q' => trim((string) $request->query('q', '')),
        ];

        // Cap length and neutralise LIKE wildcards so a search term can't force a
        // pathological leading-`%` scan or inject `%`/`_` matching semantics.
        $needle = $this->escapeLike(Str::limit($filters['q'], self::MAX_QUERY, ''));

        $matches = EngObject::forProject($project->id)
            ->when($filters['type'], fn ($query, $type) => $query->where('type', $type))
            ->when($filters['status'], fn ($query, $status) => $query->where('status', $status))
            ->when($filters['q'] !== '', fn ($query) => $query->where(
                fn ($q) => $q->where('ref', 'like', "%{$needle}%")
                    ->orWhere('title', 'like', "%{$needle}%")
                    ->orWhere('body', 'like', "%{$needle}%"),
            ))
            ->orderBy('type')->orderBy('ref')
            ->get()
            // Deny-by-default: drop objects the viewer may not see (e.g. restricted).
            // Bulk visibility filter → non-audited allows() (the page view itself is
            // audited once by the project gate above).
            ->filter(fn (EngObject $o): bool => $this->pdp->allows($user, 'view', $o))
            ->values();

        $rows = $matches->map(fn (EngObject $o): array => [
            'object' => $o,
            'title' => in_array('title', $this->pdp->filterFields($user, $o, ['title']), true) ? '[redacted]' : $o->title,
        ]);

        $page = LengthAwarePaginator::resolveCurrentPage();
        $paginator = new LengthAwarePaginator(
            $rows->forPage($page, self::PER_PAGE)->values(),
            $rows->count(),
            self::PER_PAGE,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return view('graph.index', [
            'project' => $project,
            'rows' => $paginator,
            'total' => $matches->count(),
            'filters' => $filters,
            'typeCounts' => $matches->countBy(fn (EngObject $o): string => $o->type->value),
            'types' => ObjectType::cases(),
            'statuses' => ObjectStatus::cases(),
        ]);
    }

    public function show(Request $request, EngObject $object, TraceService $trace): View
    {
        $user = $request->user();

        abort_unless($this->pdp->can($user, 'view', $object)->permitted, 403, 'Access denied by ACL.');

        $object->load(['project.tenant', 'module', 'stage', 'session', 'owner', 'baseline']);

        return view('graph.object', [
            'object' => $object,
            'redacted' => $this->pdp->filterFields($user, $object, ['title', 'body']),
            'versions' => $object->versions()->orderByDesc('version')->get(),
            'outgoing' => $this->neighbours($object->outgoingTraces()->with('toObject')->get(), 'toObject', $user),
            'incoming' => $this->neighbours($object->incomingTraces()->with('fromObject')->get(), 'fromObject', $user),
            'forward' => $this->visible($trace->forwardTrace($object), $user),
            'reverse' => $this->visible($trace->reverseTrace($object), $user),
            'canEdit' => $this->pdp->can($user, 'edit', $object)->permitted,
        ]);
    }

    /**
     * Map trace edges to display rows {relation, object}, dropping neighbours
     * the viewer may not see (deny-by-default extends to the graph walk).
     *
     * @param  Collection<int, TraceRelationship>  $edges
     * @return Collection<int, array{relation:string, object:EngObject}>
     */
    private function neighbours(Collection $edges, string $rel, $user): Collection
    {
        return $edges
            ->map(fn ($edge) => ['relation' => $edge->relation_type->label(), 'object' => $edge->{$rel}])
            ->filter(fn (array $row): bool => $row['object'] !== null && $this->pdp->allows($user, 'view', $row['object']))
            ->values();
    }

    /**
     * @param  array<int, EngObject>  $objects
     * @return Collection<int, EngObject>
     */
    private function visible(array $objects, $user): Collection
    {
        return collect($objects)
            ->filter(fn (EngObject $o): bool => $this->pdp->allows($user, 'view', $o))
            ->values();
    }
}

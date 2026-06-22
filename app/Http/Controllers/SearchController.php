<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Graph\EngObject;
use App\Services\AccessControl\PolicyDecisionPoint;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Cross-project object search (PRD §12). Searches canonical objects across every
 * project the user may retrieve, then drops any the PDP would not let them view —
 * so results never leak across the access boundary.
 */
class SearchController extends Controller
{
    private const MAX_QUERY = 100;

    private const LIMIT = 100;

    public function __construct(private readonly PolicyDecisionPoint $pdp) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        $term = Str::limit(trim((string) $request->query('q', '')), self::MAX_QUERY, '');

        $groups = collect();

        if ($term !== '') {
            $projectIds = $this->pdp->accessibleProjectIds($user, 'view');
            $needle = $this->escapeLike($term);

            $groups = EngObject::query()
                ->whereIn('project_id', $projectIds)
                ->where(fn ($q) => $q->where('ref', 'like', "%{$needle}%")
                    ->orWhere('title', 'like', "%{$needle}%"))
                ->with('project')
                ->orderBy('project_id')->orderBy('type')->orderBy('ref')
                ->limit(self::LIMIT)
                ->get()
                ->filter(fn (EngObject $o): bool => $this->pdp->allows($user, 'view', $o))
                ->groupBy(fn (EngObject $o): string => $o->project?->name ?? 'Project #'.$o->project_id);
        }

        return view('search.index', [
            'term' => $term,
            'groups' => $groups,
            'total' => $groups->flatten()->count(),
        ]);
    }

    /** Escape LIKE metacharacters so the term matches literally. */
    private function escapeLike(string $term): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
    }
}

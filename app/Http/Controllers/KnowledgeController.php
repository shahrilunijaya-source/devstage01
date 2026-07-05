<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\LifecycleStage;
use App\Models\Project;
use App\Services\AccessControl\PolicyDecisionPoint;
use App\Services\Knowledge\KnowledgeResolver;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/**
 * Read-only browser over a project's pinned Knowledge Book (PRD §7). Shows the
 * curated KRISA methodology — deliverables, question banks, stage gates and
 * glossary — that drives the project's sessions. ACL view-gated.
 */
class KnowledgeController extends Controller
{
    public function __construct(
        private readonly PolicyDecisionPoint $pdp,
        private readonly KnowledgeResolver $knowledge,
    ) {}

    public function show(Request $request, Project $project): View
    {
        abort_unless($this->pdp->can($request->user(), 'view', $project)->permitted, 403, 'Access denied by ACL.');

        $stage = $this->resolveStage($request->query('stage'));
        $items = $this->knowledge->effectiveItems($project, $stage);
        $byType = $items->groupBy('item_type');

        return view('knowledge.show', [
            'project' => $project,
            'book' => $this->knowledge->pinnedBook($project),
            'stage' => $stage,
            'stages' => LifecycleStage::ordered(),
            'deliverables' => $byType->get('deliverable', collect()),
            'questions' => $byType->get('question', collect())->groupBy(fn ($i) => $i->stage?->value ?? 'general'),
            'gates' => $byType->get('stage_gate', collect()),
            'glossary' => $byType->get('glossary_term', collect()),
            'total' => $items->count(),
        ]);
    }

    private function resolveStage(?string $value): ?LifecycleStage
    {
        return $value !== null ? LifecycleStage::tryFrom($value) : null;
    }
}

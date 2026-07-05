<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Knowledge\ProjectKnowledgeItem;
use App\Models\Project;
use App\Services\AccessControl\PolicyDecisionPoint;
use App\Services\Knowledge\KnowledgeIndexer;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Project-specific knowledge log (PRD §7.3): approved assumptions and lessons
 * learned. Captured items are tenant/project-isolated and indexed into the RAG
 * substrate so they inform future grounded answers.
 */
class ProjectKnowledgeController extends Controller
{
    /** Item types surfaced in this log. */
    private const TYPES = ['approved_assumption', 'lesson_learned'];

    public function __construct(private readonly PolicyDecisionPoint $pdp) {}

    public function index(Request $request, Project $project): View
    {
        abort_unless($this->pdp->can($request->user(), 'view', $project)->permitted, 403, 'Access denied by ACL.');

        $items = ProjectKnowledgeItem::where('project_id', $project->id)
            ->whereIn('item_type', self::TYPES)
            ->with('approver:id,name')
            ->latest()
            ->get()
            ->groupBy('item_type');

        return view('project-knowledge.index', [
            'project' => $project,
            'assumptions' => $items->get('approved_assumption', collect()),
            'lessons' => $items->get('lesson_learned', collect()),
            'canEdit' => $this->pdp->can($request->user(), 'edit', $project)->permitted,
        ]);
    }

    public function store(Request $request, Project $project, KnowledgeIndexer $indexer): RedirectResponse
    {
        abort_unless($this->pdp->can($request->user(), 'edit', $project)->permitted, 403, 'Access denied by ACL.');

        $data = $request->validate([
            'item_type' => ['required', 'in:approved_assumption,lesson_learned'],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['nullable', 'string', 'max:10000'],
        ]);

        $item = ProjectKnowledgeItem::create([
            'tenant_id' => $project->tenant_id,
            'project_id' => $project->id,
            'item_type' => $data['item_type'],
            'title' => $data['title'],
            'body' => $data['body'] ?? null,
            'status' => 'approved',
            'approved_by' => $request->user()->id,
        ]);

        // Make it retrievable. Best-effort — never block on embeddings.
        try {
            $indexer->indexProjectItem($item);
        } catch (\Throwable $e) {
            report($e);
        }

        $label = $data['item_type'] === 'approved_assumption' ? 'Assumption' : 'Lesson';

        return redirect()->route('project-knowledge.index', $project)->with('status', "{$label} recorded.");
    }
}

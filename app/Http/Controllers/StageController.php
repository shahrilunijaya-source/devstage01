<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Portfolio\Stage;
use App\Services\AccessControl\PolicyDecisionPoint;
use App\Services\Graph\BaselineService;
use App\Services\Knowledge\KnowledgeResolver;
use App\Services\NotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Stage-level baselining (PRD §5, §9.7). Freezes the approved session outputs of
 * a stage into an immutable baseline.
 */
class StageController extends Controller
{
    public function __construct(private readonly PolicyDecisionPoint $pdp) {}

    public function baseline(Request $request, Stage $stage, BaselineService $baselines, KnowledgeResolver $knowledge, NotificationService $notifications): RedirectResponse
    {
        $project = $stage->project;
        abort_unless($this->pdp->can($request->user(), 'baseline', $project)->permitted, 403, 'Access denied by ACL.');

        if (! $stage->sessions()->where('status', 'approved')->exists()) {
            return redirect()->route('portfolio.show', $project)
                ->with('error', 'Cannot baseline: the stage has no approved session yet.');
        }

        $baseline = $baselines->baseline($stage, [
            'knowledge_book_version' => $knowledge->pinnedBook($project)?->version,
            'approved_by' => $request->user()->id,
            'created_by' => $request->user()->id,
        ]);

        $notifications->notifyProjectBindings(
            $project, 'stage_baselined',
            "Stage {$stage->stage->value} baselined as {$baseline->version_label}.",
            $request->user()->id,
        );

        return redirect()->route('portfolio.show', $project)
            ->with('status', "Baseline {$baseline->version_label} created with {$baseline->baselineObjects()->count()} objects.");
    }
}

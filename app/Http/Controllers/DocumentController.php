<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Graph\BaselineObject;
use App\Models\Graph\ObjectVersion;
use App\Models\Portfolio\StageBaseline;
use App\Services\AccessControl\PolicyDecisionPoint;
use App\Services\Document\DeckBuilder;
use App\Services\Document\PptxExporter;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Generated document views over a baseline (PRD §13, §14): documents are views
 * of the canonical model, not the source of truth.
 */
class DocumentController extends Controller
{
    public function __construct(private readonly PolicyDecisionPoint $pdp) {}

    public function baseline(Request $request, StageBaseline $baseline): View
    {
        $data = $this->gather($request, $baseline);

        return view('documents.baseline', $data);
    }

    public function baselinePdf(Request $request, StageBaseline $baseline): Response
    {
        $data = $this->gather($request, $baseline);

        $pdf = Pdf::loadView('documents.baseline-pdf', $data);

        return $pdf->download("{$baseline->version_label}.pdf");
    }

    /** Stakeholder review deck — a generated slide view of the baseline (PRD §13). */
    public function deck(Request $request, StageBaseline $baseline, DeckBuilder $builder): View
    {
        $baseline->load('stage.project');

        abort_unless(
            $this->pdp->can($request->user(), 'view', $baseline->stage->project)->permitted,
            403,
            'Access denied by ACL.',
        );

        return view('documents.deck', $builder->build($baseline, $request->user()));
    }

    /** Native PowerPoint export of the review deck (PRD §13). */
    public function deckPptx(Request $request, StageBaseline $baseline, DeckBuilder $builder, PptxExporter $exporter): BinaryFileResponse
    {
        $baseline->load('stage.project');

        abort_unless(
            $this->pdp->can($request->user(), 'view', $baseline->stage->project)->permitted,
            403,
            'Access denied by ACL.',
        );

        $path = $exporter->export($builder->build($baseline, $request->user()));

        return response()->download($path, "{$baseline->version_label}.pptx")->deleteFileAfterSend();
    }

    /** @return array<string, mixed> */
    private function gather(Request $request, StageBaseline $baseline): array
    {
        $baseline->load('stage.module', 'stage.project.tenant', 'approver');
        $project = $baseline->stage->project;

        abort_unless($this->pdp->can($request->user(), 'view', $project)->permitted, 403, 'Access denied by ACL.');

        $user = $request->user();

        // Render from the immutable frozen snapshots, not the live objects.
        // Apply field-level redaction for unauthorised viewers (PRD §6.3 ACL-05).
        $items = BaselineObject::where('stage_baseline_id', $baseline->id)
            ->orderBy('type')->orderBy('ref')
            ->get()
            ->map(function (BaselineObject $bo) use ($user): array {
                $snapshot = ObjectVersion::where('object_id', $bo->object_id)
                    ->where('version', $bo->object_version)->first()?->snapshot ?? [];

                $object = $bo->object;
                $redacted = $object !== null
                    ? $this->pdp->filterFields($user, $object, ['title', 'body'])
                    : [];

                return [
                    'ref' => $bo->ref,
                    'object_id' => $bo->object_id,
                    'type' => $bo->type->value,
                    'version' => $bo->object_version,
                    'title' => in_array('title', $redacted, true) ? '[redacted]' : ($snapshot['title'] ?? ''),
                    'body' => in_array('body', $redacted, true) ? '[redacted]' : ($snapshot['body'] ?? null),
                    'status' => $snapshot['status'] ?? null,
                ];
            });

        return [
            'baseline' => $baseline,
            'project' => $project,
            'groups' => $items->groupBy('type'),
            'canEdit' => $this->pdp->can($request->user(), 'edit', $project)->permitted,
        ];
    }
}

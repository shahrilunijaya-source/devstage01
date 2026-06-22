<?php

declare(strict_types=1);

namespace App\Services\Document;

use App\Enums\ObjectType;
use App\Models\Graph\BaselineObject;
use App\Models\Graph\ObjectVersion;
use App\Models\Portfolio\StageBaseline;
use App\Models\User;
use App\Services\AccessControl\PolicyDecisionPoint;
use Illuminate\Support\Collection;

/**
 * Builds a stakeholder review deck (PRD §13) from a frozen stage baseline. The
 * deck is a generated VIEW over the immutable object-version snapshots, not a
 * stored artefact — re-rendering always reflects the pinned baseline. Field
 * redaction is applied per viewer (PRD §6.3 ACL-05), same as documents.
 */
class DeckBuilder
{
    /** Types that earn their own content section, in presentation order. */
    private const SECTION_ORDER = [
        'business_requirement', 'user_requirement', 'functional_requirement',
        'non_functional_requirement', 'business_rule', 'finding', 'decision',
        'assumption', 'acceptance_criterion',
    ];

    public function __construct(private readonly PolicyDecisionPoint $pdp) {}

    /**
     * @return array<string, mixed> slide-ready data for the deck view
     */
    public function build(StageBaseline $baseline, User $viewer): array
    {
        $baseline->loadMissing('stage.module', 'stage.project.tenant');
        $project = $baseline->stage->project;

        $items = $this->items($baseline, $viewer);
        $byType = $items->groupBy('type');

        $sections = collect(self::SECTION_ORDER)
            ->filter(fn (string $type): bool => $byType->has($type))
            ->map(fn (string $type): array => [
                'type' => $type,
                'heading' => ObjectType::from($type)->pluralLabel(),
                'items' => $byType->get($type),
            ])
            ->values();

        $risks = $byType->get('risk', collect());

        return [
            'baseline' => $baseline,
            'project' => $project,
            'stageLabel' => $baseline->stage->stage->label(),
            'sections' => $sections,
            'risks' => $risks,
            'summary' => [
                'total' => $items->count(),
                'requirements' => $items->whereIn('type', ['business_requirement', 'user_requirement', 'functional_requirement', 'non_functional_requirement'])->count(),
                'risks' => $risks->count(),
                'confirmed' => $items->where('status', 'confirmed_by_evidence')->count(),
                'types' => $byType->keys()->count(),
            ],
        ];
    }

    /**
     * Frozen baseline members as slide rows, with redaction applied.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function items(StageBaseline $baseline, User $viewer): Collection
    {
        return BaselineObject::where('stage_baseline_id', $baseline->id)
            ->orderBy('type')->orderBy('ref')
            ->get()
            ->map(function (BaselineObject $bo) use ($viewer): array {
                $snapshot = ObjectVersion::where('object_id', $bo->object_id)
                    ->where('version', $bo->object_version)->first()?->snapshot ?? [];

                $redacted = $bo->object !== null
                    ? $this->pdp->filterFields($viewer, $bo->object, ['title', 'body'])
                    : [];

                return [
                    'ref' => $bo->ref,
                    'type' => $bo->type->value,
                    'title' => in_array('title', $redacted, true) ? '[redacted]' : ($snapshot['title'] ?? ''),
                    'body' => in_array('body', $redacted, true) ? '[redacted]' : ($snapshot['body'] ?? null),
                    'status' => $snapshot['status'] ?? null,
                ];
            });
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Knowledge;

use App\Enums\LifecycleStage;
use App\Models\Knowledge\KnowledgeBook;
use App\Models\Knowledge\KnowledgeBookItem;
use App\Models\Knowledge\ProjectKnowledgePin;
use App\Models\Project;
use Illuminate\Support\Collection;

/**
 * Resolves the effective methodology for a project: its pinned KB version plus
 * per-stage overrides and project-specific extensions (PRD §7.4).
 */
class KnowledgeResolver
{
    public function pin(
        Project $project,
        string $kbVersion,
        ?string $methodologyVersion = null,
        ?int $pinnedBy = null,
    ): ProjectKnowledgePin {
        $book = KnowledgeBook::where('kind', 'krisa')->where('version', $kbVersion)->firstOrFail();
        $methodology = $methodologyVersion !== null
            ? KnowledgeBook::where('kind', 'methodology')->where('version', $methodologyVersion)->first()
            : null;

        return ProjectKnowledgePin::updateOrCreate(
            ['project_id' => $project->id],
            [
                'knowledge_book_id' => $book->id,
                'methodology_book_id' => $methodology?->id,
                'pinned_at' => now(),
                'pinned_by' => $pinnedBy,
            ],
        );
    }

    public function pinnedBook(Project $project): ?KnowledgeBook
    {
        $pin = ProjectKnowledgePin::where('project_id', $project->id)->first();

        return $pin?->knowledgeBook;
    }

    /**
     * Effective KB items for a project at a stage (stage-tagged items + items
     * that apply to all stages), minus codes excluded by the pin overrides.
     *
     * @return Collection<int, KnowledgeBookItem>
     */
    public function effectiveItems(Project $project, ?LifecycleStage $stage = null): Collection
    {
        $pin = ProjectKnowledgePin::where('project_id', $project->id)->first();
        $book = $pin?->knowledgeBook;

        if ($book === null) {
            return collect();
        }

        $excluded = (array) ($pin->overrides['exclude_codes'] ?? []);

        return $book->items()
            ->when($stage !== null, fn ($q) => $q->where(function ($inner) use ($stage): void {
                $inner->where('stage', $stage->value)->orWhereNull('stage');
            }))
            ->orderBy('sort_order')
            ->get()
            ->reject(fn (KnowledgeBookItem $item): bool => in_array($item->code, $excluded, true))
            ->values();
    }

    /**
     * The question bank for a stage (PRD §7.1 question banks).
     *
     * @return Collection<int, KnowledgeBookItem>
     */
    public function questionBank(Project $project, LifecycleStage $stage): Collection
    {
        return $this->effectiveItems($project, $stage)
            ->where('item_type', 'question')
            ->values();
    }
}

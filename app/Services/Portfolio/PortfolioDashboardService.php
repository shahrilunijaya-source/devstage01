<?php

declare(strict_types=1);

namespace App\Services\Portfolio;

use App\Enums\LifecycleStage;
use App\Models\Graph\EngObject;
use App\Models\Portfolio\Module;
use App\Models\Portfolio\Stage;
use App\Models\Project;
use App\Models\User;
use App\Services\AccessControl\PolicyDecisionPoint;
use Illuminate\Support\Collection;

/**
 * Derives the portfolio dashboard (PRD §5) entirely from existing state — no
 * dashboard tables. Deliverable status = stage status; the Gantt = stage order
 * + started_at/gate_passed_at; risks = open RISK objects; baselines = the
 * stage rollups. Everything is scoped through the Policy Decision Point.
 */
class PortfolioDashboardService
{
    /** Stage statuses that count as delivered for progress maths. */
    private const DONE_STATUSES = ['baselined'];

    public function __construct(private readonly PolicyDecisionPoint $pdp) {}

    /**
     * Per-project dashboards the user may view, with rolled-up health.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function forUser(User $user): Collection
    {
        $allowedIds = $this->pdp->accessibleProjectIds($user, 'view');

        if ($allowedIds === []) {
            return collect();
        }

        $projects = Project::with(['tenant', 'modules.stages'])
            ->whereIn('id', $allowedIds)
            ->orderBy('name')
            ->get();

        $openRisksByProject = $this->openRiskCounts($allowedIds);

        return $projects->map(fn (Project $project): array => $this->projectCard($project, (int) ($openRisksByProject[$project->id] ?? 0)));
    }

    /** @return array<string, mixed> */
    private function projectCard(Project $project, int $openRisks): array
    {
        $modules = $project->modules->map(fn (Module $module): array => $this->moduleRow($module));

        $totalStages = $modules->sum('total');
        $doneStages = $modules->sum('done');
        $blocked = $modules->sum('blocked');

        return [
            'project' => $project,
            'tenant' => $project->tenant,
            'modules' => $modules,
            'progress' => $totalStages > 0 ? (int) round($doneStages / $totalStages * 100) : 0,
            'baselineCount' => $modules->sum('baselines'),
            'openRisks' => $openRisks,
            'blockedStages' => $blocked,
            'nextMilestone' => $modules->pluck('nextMilestone')->filter()->first(),
            'health' => $this->health($blocked, $openRisks, $totalStages, $doneStages),
        ];
    }

    /** @return array<string, mixed> */
    private function moduleRow(Module $module): array
    {
        $ordered = $module->stages->sortBy(fn (Stage $s): int => $s->stage->order())->values();

        $done = $ordered->filter(fn (Stage $s): bool => in_array($s->status, self::DONE_STATUSES, true))->count();
        $blocked = $ordered->filter(fn (Stage $s): bool => $s->status === 'blocked')->count();
        $baselines = $ordered->sum(fn (Stage $s): int => $s->baselines->count());

        $next = $ordered->first(fn (Stage $s): bool => ! in_array($s->status, self::DONE_STATUSES, true));

        return [
            'module' => $module,
            'stages' => $ordered->map(fn (Stage $s): array => [
                'label' => $s->stage->label(),
                'key' => $s->stage->value,
                'order' => $s->stage->order(),
                'optional' => $s->stage->isOptional(),
                'status' => $s->status,
                'started_at' => $s->started_at,
                'gate_passed_at' => $s->gate_passed_at,
            ]),
            'total' => $ordered->count(),
            'done' => $done,
            'blocked' => $blocked,
            'baselines' => $baselines,
            'progress' => $ordered->count() > 0 ? (int) round($done / $ordered->count() * 100) : 0,
            'nextMilestone' => $next ? $next->stage->label() : null,
        ];
    }

    /**
     * Open (still-live) RISK objects per project — anything not marked
     * Not Applicable. Single grouped query.
     *
     * @param  array<int, int>  $projectIds
     * @return array<int, int>
     */
    private function openRiskCounts(array $projectIds): array
    {
        return EngObject::query()
            ->whereIn('project_id', $projectIds)
            ->where('type', 'risk')
            ->where('status', '!=', 'not_applicable')
            ->selectRaw('project_id, count(*) as aggregate')
            ->groupBy('project_id')
            ->pluck('aggregate', 'project_id')
            ->map(fn ($n): int => (int) $n)
            ->all();
    }

    /** Coarse health roll-up shown as a pill. */
    private function health(int $blocked, int $openRisks, int $total, int $done): string
    {
        if ($blocked > 0) {
            return 'blocked';
        }

        if ($total > 0 && $done === $total) {
            return 'complete';
        }

        if ($openRisks > 0) {
            return 'at_risk';
        }

        return 'on_track';
    }

    /**
     * Portfolio-wide totals for the executive summary band, derived from the
     * already-computed per-project cards (no extra queries).
     *
     * @param  Collection<int, array<string, mixed>>  $cards
     * @return array<string, mixed>
     */
    public function summarize(Collection $cards): array
    {
        return [
            'projects' => $cards->count(),
            'byHealth' => collect(['on_track', 'at_risk', 'blocked', 'complete'])
                ->mapWithKeys(fn (string $h): array => [$h => $cards->where('health', $h)->count()]),
            'openRisks' => (int) $cards->sum('openRisks'),
            'baselines' => (int) $cards->sum('baselineCount'),
            'blockedStages' => (int) $cards->sum('blockedStages'),
            'avgProgress' => $cards->isNotEmpty() ? (int) round($cards->avg('progress')) : 0,
        ];
    }

    /** Lifecycle stages in canonical order, for the Gantt header. */
    public function lifecycleColumns(): array
    {
        return array_map(
            fn (LifecycleStage $s): array => ['key' => $s->value, 'label' => $s->label(), 'optional' => $s->isOptional()],
            LifecycleStage::ordered(),
        );
    }
}

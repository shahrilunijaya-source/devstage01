<?php

declare(strict_types=1);

namespace App\Services\Session;

use App\Enums\ConfidenceLevel;
use App\Enums\ObjectStatus;
use App\Enums\ObjectType;
use App\Enums\RelationType;
use App\Models\Graph\EngObject;
use App\Models\Portfolio\Session;
use App\Services\Graph\ObjectGraphService;
use App\Services\Graph\TraceService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Deterministic conflict detection over a session's drafted requirements
 * (PRD §9). Flags requirements whose intent collides — currently near-identical
 * titles — as CONFLICT_DETECTED and records a CONFLICT object linking them, so
 * the session exit gate blocks until a human resolves the clash.
 */
class ConflictDetectionService
{
    private const REQUIREMENT_TYPES = [
        ObjectType::BUSINESS_REQUIREMENT->value,
        ObjectType::USER_REQUIREMENT->value,
        ObjectType::FUNCTIONAL_REQUIREMENT->value,
        ObjectType::NON_FUNCTIONAL_REQUIREMENT->value,
    ];

    public function __construct(
        private readonly ObjectGraphService $graph,
        private readonly TraceService $trace,
    ) {}

    /**
     * Scan a session and flag duplicate-intent requirements. Returns the number
     * of conflict groups recorded.
     */
    public function scan(Session $session, ?int $detectedBy = null): int
    {
        $requirements = EngObject::where('session_id', $session->id)
            ->whereIn('type', self::REQUIREMENT_TYPES)
            ->whereNull('baseline_id')
            ->get();

        $groups = $requirements
            ->groupBy(fn (EngObject $r): string => $this->normalize($r->title))
            ->filter(fn (Collection $group): bool => $group->count() >= 2 && ! $this->alreadyFlagged($group));

        foreach ($groups as $group) {
            $this->recordConflict($session, $group, $detectedBy);
        }

        return $groups->count();
    }

    /** @param  Collection<int, EngObject>  $group */
    private function recordConflict(Session $session, Collection $group, ?int $detectedBy): void
    {
        DB::transaction(function () use ($session, $group, $detectedBy): void {
            $refs = $group->pluck('ref')->implode(', ');

            $conflict = $this->graph->create(
                ObjectType::CONFLICT,
                (int) $session->project->tenant_id,
                (int) $session->project_id,
                'Conflicting requirements: '.$group->first()->title,
                [
                    'module_id' => $session->module_id,
                    'stage_id' => $session->stage_id,
                    'session_id' => $session->id,
                    'status' => ObjectStatus::CONFIRMED_BY_EVIDENCE, // the conflict record itself is a fact
                    'confidence' => ConfidenceLevel::HIGH,
                    'impact' => 'high',
                    'body' => "{$group->count()} requirements share near-identical intent and must be reconciled: {$refs}.",
                    'attributes' => ['conflicting_refs' => $group->pluck('ref')->values()->all()],
                    'changed_by' => $detectedBy,
                    'change_summary' => 'conflict detected',
                ],
            );

            foreach ($group as $requirement) {
                $this->trace->link($conflict, $requirement, RelationType::CONFLICTS_WITH, null, $detectedBy);

                if ($requirement->status !== ObjectStatus::CONFLICT_DETECTED) {
                    $this->graph->update(
                        $requirement,
                        ['status' => ObjectStatus::CONFLICT_DETECTED],
                        $detectedBy,
                        'flagged: conflict detected',
                    );
                }
            }
        });
    }

    /** True when any member already sits in a recorded conflict. */
    private function alreadyFlagged(Collection $group): bool
    {
        return $group->contains(fn (EngObject $r): bool => $r->status === ObjectStatus::CONFLICT_DETECTED);
    }

    private function normalize(string $title): string
    {
        return preg_replace('/[^a-z0-9]+/', '', strtolower($title)) ?? $title;
    }
}

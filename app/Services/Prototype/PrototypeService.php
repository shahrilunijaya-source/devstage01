<?php

declare(strict_types=1);

namespace App\Services\Prototype;

use App\Enums\ObjectStatus;
use App\Enums\ObjectType;
use App\Enums\RelationType;
use App\Models\Graph\EngObject;
use App\Models\Graph\TraceRelationship;
use App\Models\Project;
use App\Models\User;
use App\Services\Graph\ObjectGraphService;
use App\Services\Graph\TraceService;
use Illuminate\Support\Collection;

/**
 * Prototype register (PRD §12 — PROTOTYPE stage). Tracks the prototype elements
 * that IMPLEMENT each requirement through a build lifecycle. A prototype element
 * is an ordinary graph object — no new tables.
 */
class PrototypeService
{
    /** Requirement-family types prototypes are anchored on. */
    private const REQUIREMENT_TYPES = [
        ObjectType::BUSINESS_REQUIREMENT->value,
        ObjectType::USER_REQUIREMENT->value,
        ObjectType::FUNCTIONAL_REQUIREMENT->value,
        ObjectType::NON_FUNCTIONAL_REQUIREMENT->value,
    ];

    /** Prototype build states, most-advanced last. */
    public const STATES = ['planned', 'in_progress', 'built', 'demoed'];

    public function __construct(
        private readonly ObjectGraphService $graph,
        private readonly TraceService $trace,
    ) {}

    /** @return array<string, mixed> */
    public function register(Project $project): array
    {
        $requirements = EngObject::forProject($project->id)
            ->whereIn('type', self::REQUIREMENT_TYPES)
            ->orderBy('ref')->get();

        $elementsByRequirement = $this->elementsByRequirement($project);

        $rows = $requirements->map(function (EngObject $req) use ($elementsByRequirement): array {
            $elements = collect($elementsByRequirement[$req->id] ?? []);

            return [
                'requirement' => $req,
                'elements' => $elements,
                'status' => $this->statusFor($elements),
            ];
        });

        $demoed = $rows->where('status', 'demoed')->count();

        return [
            'project' => $project,
            'rows' => $rows,
            'summary' => [
                'requirements' => $rows->count(),
                'demoed' => $demoed,
                'in_progress' => $rows->whereIn('status', ['planned', 'in_progress', 'built'])->count(),
                'not_started' => $rows->where('status', 'none')->count(),
                'demoed_pct' => $rows->count() > 0 ? round($demoed / $rows->count() * 100, 1) : 0.0,
                'elements' => collect($elementsByRequirement)->flatten(1)->count(),
            ],
        ];
    }

    /** Add a prototype element that IMPLEMENTS a requirement. Starts 'planned'. */
    public function addElement(EngObject $requirement, string $title, ?string $body, User $user): EngObject
    {
        $element = $this->graph->create(
            ObjectType::PROTOTYPE_ELEMENT,
            (int) $requirement->tenant_id,
            (int) $requirement->project_id,
            $title,
            [
                'module_id' => $requirement->module_id,
                'stage_id' => $requirement->stage_id,
                'session_id' => $requirement->session_id,
                'owner_user_id' => $user->id,
                'source' => 'prototype',
                'status' => ObjectStatus::NEEDS_CONFIRMATION,
                'classification' => $requirement->classification ?? 'internal',
                'body' => $body,
                'attributes' => ['state' => 'planned', 'implements' => $requirement->ref],
                'changed_by' => $user->id,
                'change_summary' => 'prototype element planned',
            ],
        );

        $this->trace->link($element, $requirement, RelationType::IMPLEMENTS, null, $user->id);

        return $element;
    }

    /** Advance a prototype element to a new build state — a versioned change. */
    public function setState(EngObject $element, string $state, User $user): EngObject
    {
        if (! in_array($state, self::STATES, true)) {
            throw new \InvalidArgumentException("Invalid prototype state: {$state}");
        }

        $attributes = $element->getAttribute('attributes') ?? [];
        $attributes['state'] = $state;

        return $this->graph->update(
            $element,
            [
                'attributes' => $attributes,
                'status' => $state === 'demoed' ? ObjectStatus::CONFIRMED_BY_EVIDENCE->value : ObjectStatus::NEEDS_CONFIRMATION->value,
            ],
            $user->id,
            "prototype state: {$state}",
        );
    }

    /**
     * Overall prototype status for a requirement = most-advanced element state.
     *
     * @param  Collection<int, EngObject>  $elements
     */
    private function statusFor(Collection $elements): string
    {
        if ($elements->isEmpty()) {
            return 'none';
        }

        $states = $elements->map(fn (EngObject $e): string => $e->getAttribute('attributes')['state'] ?? 'planned');

        foreach (array_reverse(self::STATES) as $state) {
            if ($states->contains($state)) {
                return $state;
            }
        }

        return 'planned';
    }

    /**
     * requirementId => [PROTOTYPE_ELEMENT, ...] from IMPLEMENTS edges (element → requirement).
     *
     * @return array<int, array<int, EngObject>>
     */
    private function elementsByRequirement(Project $project): array
    {
        $edges = TraceRelationship::where('project_id', $project->id)
            ->where('relation_type', RelationType::IMPLEMENTS->value)
            ->get();

        if ($edges->isEmpty()) {
            return [];
        }

        $elements = EngObject::whereIn('id', $edges->pluck('from_object_id')->unique()->all())
            ->where('type', ObjectType::PROTOTYPE_ELEMENT->value)
            ->get()->keyBy('id');

        $map = [];
        foreach ($edges as $edge) {
            $element = $elements->get($edge->from_object_id);
            if ($element !== null) {
                $map[$edge->to_object_id][] = $element;
            }
        }

        return $map;
    }
}

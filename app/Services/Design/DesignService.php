<?php

declare(strict_types=1);

namespace App\Services\Design;

use App\Enums\ObjectStatus;
use App\Enums\ObjectType;
use App\Enums\RelationType;
use App\Models\Graph\EngObject;
use App\Models\Graph\TraceRelationship;
use App\Models\Project;
use App\Models\User;
use App\Services\Graph\ObjectGraphService;
use App\Services\Graph\TraceService;

/**
 * Design register (PRD §12 — SDS/SLD/DBD stages). Captures how each requirement
 * is satisfied by design objects (decisions, components, interfaces, database
 * objects). A design object SATISFIES a requirement, so the link feeds straight
 * into the traceability matrix's downstream column. No new tables — design
 * artefacts are ordinary graph objects.
 */
class DesignService
{
    /** Requirement-family types design is anchored on. */
    private const REQUIREMENT_TYPES = [
        ObjectType::BUSINESS_REQUIREMENT->value,
        ObjectType::USER_REQUIREMENT->value,
        ObjectType::FUNCTIONAL_REQUIREMENT->value,
        ObjectType::NON_FUNCTIONAL_REQUIREMENT->value,
    ];

    /** Design artefact types authorable here. */
    public const DESIGN_TYPES = [
        'design_decision' => ObjectType::DESIGN_DECISION,
        'design_component' => ObjectType::DESIGN_COMPONENT,
        'interface' => ObjectType::INTERFACE,
        'database_object' => ObjectType::DATABASE_OBJECT,
    ];

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

        $designByRequirement = $this->designByRequirement($project);

        $rows = $requirements->map(function (EngObject $req) use ($designByRequirement): array {
            $designs = collect($designByRequirement[$req->id] ?? []);

            return [
                'requirement' => $req,
                'designs' => $designs,
                'designed' => $designs->isNotEmpty(),
            ];
        });

        $designed = $rows->where('designed', true)->count();

        return [
            'project' => $project,
            'rows' => $rows,
            'summary' => [
                'requirements' => $rows->count(),
                'designed' => $designed,
                'undesigned' => $rows->count() - $designed,
                'designed_pct' => $rows->count() > 0 ? round($designed / $rows->count() * 100, 1) : 0.0,
                'designs' => collect($designByRequirement)->flatten(1)->count(),
            ],
        ];
    }

    /**
     * Capture a design object that SATISFIES a requirement.
     */
    public function addDesign(EngObject $requirement, string $typeKey, string $title, ?string $body, User $user): EngObject
    {
        $type = self::DESIGN_TYPES[$typeKey] ?? null;
        if ($type === null) {
            throw new \InvalidArgumentException("Unknown design type: {$typeKey}");
        }

        $design = $this->graph->create(
            $type,
            (int) $requirement->tenant_id,
            (int) $requirement->project_id,
            $title,
            [
                'module_id' => $requirement->module_id,
                'stage_id' => $requirement->stage_id,
                'session_id' => $requirement->session_id,
                'owner_user_id' => $user->id,
                'source' => 'design',
                'status' => ObjectStatus::NEEDS_CONFIRMATION,
                'classification' => $requirement->classification ?? 'internal',
                'body' => $body,
                'attributes' => ['satisfies' => $requirement->ref],
                'changed_by' => $user->id,
                'change_summary' => 'design authored',
            ],
        );

        $this->trace->link($design, $requirement, RelationType::SATISFIES, null, $user->id);

        return $design;
    }

    /**
     * requirementId => [DESIGN EngObject, ...] from SATISFIES edges (design → requirement).
     *
     * @return array<int, array<int, EngObject>>
     */
    private function designByRequirement(Project $project): array
    {
        $edges = TraceRelationship::where('project_id', $project->id)
            ->where('relation_type', RelationType::SATISFIES->value)
            ->get();

        if ($edges->isEmpty()) {
            return [];
        }

        $designs = EngObject::forProject($project->id)
            ->whereIn('id', $edges->pluck('from_object_id')->unique()->all())
            ->whereIn('type', array_map(fn (ObjectType $t): string => $t->value, array_values(self::DESIGN_TYPES)))
            ->get()->keyBy('id');

        $map = [];
        foreach ($edges as $edge) {
            $design = $designs->get($edge->from_object_id);
            if ($design !== null) {
                $map[$edge->to_object_id][] = $design;
            }
        }

        return $map;
    }
}

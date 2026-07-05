<?php

declare(strict_types=1);

namespace App\Services\Portfolio;

use App\Enums\ConfidenceLevel;
use App\Enums\ObjectStatus;
use App\Enums\ObjectType;
use App\Enums\RelationType;
use App\Models\Graph\EngObject;
use App\Models\Project;
use App\Models\User;
use App\Services\Graph\ObjectGraphService;
use App\Services\Graph\TraceService;
use Illuminate\Support\Arr;
use RuntimeException;

/**
 * Project Objective Baseline (spec §6): the structured statement of why the
 * project exists — the anchor every requirement, suggestion and change is
 * evaluated against. Stored as a first-class OBJECTIVE graph object at project
 * level (no module/stage/session), so it inherits permanent identity,
 * immutable versioning, tracing, ACL and redaction like every other artefact.
 *
 * Approval discipline: any revision returns the objective to
 * NEEDS_CONFIRMATION — a changed anchor must be re-approved before it counts.
 */
class ObjectiveService
{
    /** Structured fields captured into the objective's attribute bag. */
    public const FIELDS = [
        'sponsor', 'current_situation', 'desired_outcome', 'target_users',
        'scope', 'out_of_scope', 'success_measures',
        'business_constraints', 'technical_constraints', 'regulatory',
        'budget_assumption', 'timeline_assumption', 'known_risks', 'stakeholders',
    ];

    public function __construct(
        private readonly ObjectGraphService $graph,
        private readonly TraceService $trace,
    ) {}

    public function objectiveFor(Project $project): ?EngObject
    {
        return EngObject::query()
            ->where('project_id', $project->id)
            ->where('type', ObjectType::OBJECTIVE->value)
            ->orderByDesc('id')
            ->first();
    }

    public function isApproved(Project $project): bool
    {
        return $this->objectiveFor($project)?->status === ObjectStatus::CONFIRMED_BY_EVIDENCE;
    }

    /**
     * Create the objective, or revise it as a new immutable version. `title` is
     * the objective statement; `business_problem` becomes the body (redactable
     * like any object body); everything else lands in attributes.
     *
     * @param  array<string, mixed>  $data
     */
    public function capture(Project $project, array $data, User $user): EngObject
    {
        $attributes = array_filter(
            Arr::only($data, self::FIELDS),
            fn ($v): bool => filled($v),
        );

        $existing = $this->objectiveFor($project);

        if ($existing !== null) {
            return $this->graph->update($existing, [
                'title' => $data['title'],
                'body' => $data['business_problem'] ?? null,
                'attributes' => $attributes,
                'status' => ObjectStatus::NEEDS_CONFIRMATION,
            ], $user->id, 'objective revised — re-approval required');
        }

        return $this->graph->create(ObjectType::OBJECTIVE, (int) $project->tenant_id, (int) $project->id, $data['title'], [
            'body' => $data['business_problem'] ?? null,
            'attributes' => $attributes,
            'owner_user_id' => $user->id,
            'source' => 'guided setup',
            'status' => ObjectStatus::NEEDS_CONFIRMATION,
            'impact' => 'critical',
            'changed_by' => $user->id,
            'change_summary' => 'objective captured',
        ]);
    }

    /**
     * Approve the objective: confirmed status + a first-class APPROVAL object
     * linked APPROVES, mirroring the baseline sign-off discipline.
     */
    public function approve(Project $project, User $approver): EngObject
    {
        $objective = $this->objectiveFor($project);
        if ($objective === null) {
            throw new RuntimeException('No objective has been captured for this project yet.');
        }
        if ($objective->status === ObjectStatus::CONFIRMED_BY_EVIDENCE) {
            return $objective;
        }

        $confirmed = $this->graph->update($objective, [
            'status' => ObjectStatus::CONFIRMED_BY_EVIDENCE,
            'confidence' => ConfidenceLevel::HIGH,
        ], $approver->id, 'objective approved');

        $approval = $this->graph->create(ObjectType::APPROVAL, (int) $project->tenant_id, (int) $project->id,
            "Sign-off: objective {$confirmed->ref}", [
                'owner_user_id' => $approver->id,
                'source' => $confirmed->ref,
                'source_object_id' => $confirmed->id,
                'status' => ObjectStatus::CONFIRMED_BY_EVIDENCE,
                'confidence' => ConfidenceLevel::HIGH,
                'body' => "Project objective {$confirmed->ref} (v{$confirmed->current_version}) approved.",
                'attributes' => ['objective_id' => $confirmed->id, 'objective_version' => $confirmed->current_version],
                'changed_by' => $approver->id,
                'change_summary' => 'objective sign-off recorded',
            ]);

        $this->trace->link($approval, $confirmed, RelationType::APPROVES, null, $approver->id);

        return $confirmed;
    }
}

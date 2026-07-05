<?php

declare(strict_types=1);

namespace App\Services\Change;

use App\Enums\ObjectStatus;
use App\Enums\ObjectType;
use App\Models\Graph\ChangeRequest;
use App\Models\Graph\EngObject;
use App\Models\User;
use App\Services\Ai\ObjectiveGuardianService;
use App\Services\Change\Exceptions\ChangeManagementException;
use App\Services\Change\Exceptions\SeparationOfDutiesException;
use App\Services\Graph\ObjectGraphService;
use App\Services\Graph\ObjectIdGenerator;
use App\Services\Graph\TraceService;
use Illuminate\Support\Facades\DB;

/**
 * Change Management Engine (PRD §9.3.3, Module 6). Intake → impact analysis with
 * auto-trace → approval → apply with versioned supersession → regenerate affected
 * outputs. The only sanctioned path for editing a baselined object (PRD §13.10).
 */
class ChangeManagementService
{
    public function __construct(
        private readonly ObjectIdGenerator $ids,
        private readonly TraceService $trace,
        private readonly ObjectGraphService $graph,
        private readonly ObjectiveGuardianService $guardian,
    ) {}

    /**
     * Intake a change request, compute its impact (PRD §15.6 CHG-01) and run the
     * Objective Guardian alignment check (spec §7 — advisory, degrades honestly).
     *
     * @param  array<string, mixed>  $proposedChanges  title, body, status, confidence
     */
    public function open(EngObject $target, User $user, string $title, ?string $description = null, array $proposedChanges = []): ChangeRequest
    {
        $proposalText = trim($title.'. '.($description ?? '').' '.json_encode($proposedChanges));

        return ChangeRequest::create([
            'ref' => $this->ids->next((int) $target->project_id, ObjectType::CHANGE_REQUEST),
            'project_id' => $target->project_id,
            'target_object_id' => $target->id,
            'raised_by' => $user->id,
            'title' => $title,
            'description' => $description,
            'proposed_changes' => $proposedChanges,
            'impact' => $this->analyzeImpact($target),
            'guardian_assessment' => $this->guardian->assess($target, $proposalText, $user),
            'status' => 'draft',
        ]);
    }

    /**
     * Auto-trace impact: every object up- and down-stream of the target (PRD §15.6).
     *
     * @return array<string, mixed>
     */
    public function analyzeImpact(EngObject $target): array
    {
        $downstream = $this->trace->forwardTrace($target);
        $upstream = $this->trace->reverseTrace($target);

        $affected = collect($downstream)->merge($upstream)
            ->unique('id')
            ->map(fn (EngObject $o): string => $o->ref)
            ->values()
            ->all();

        return [
            'affected_refs' => $affected,
            'downstream' => count($downstream),
            'upstream' => count($upstream),
            'total' => count($affected),
        ];
    }

    public function approve(ChangeRequest $cr, User $user, ?string $overrideReason = null): void
    {
        $this->assertStatus($cr, 'draft', 'approve');
        $this->assertSeparationOfDuties($cr, $user, 'approve');

        // Guardian is advisory — but approving past a direct conflict with the
        // project objective requires a recorded override reason (spec §7).
        $classification = $cr->guardian_assessment['classification'] ?? null;
        if (in_array($classification, ObjectiveGuardianService::OVERRIDE_REQUIRED, true) && blank($overrideReason)) {
            throw new ChangeManagementException(
                'The Objective Guardian classified this change as a direct conflict with the project objective — approving it requires a recorded override reason.',
            );
        }

        $cr->update([
            'status' => 'approved',
            'decided_by' => $user->id,
            'decided_at' => now(),
            'override_reason' => filled($overrideReason) ? mb_substr($overrideReason, 0, 500) : null,
        ]);
    }

    public function reject(ChangeRequest $cr, User $user): void
    {
        $this->assertStatus($cr, 'draft', 'reject');
        $cr->update(['status' => 'rejected', 'decided_by' => $user->id, 'decided_at' => now()]);
    }

    /**
     * Apply an approved change: mutate the target (versioned, supersedes prior),
     * then flag downstream objects for re-confirmation (PRD §15.6 CHG-03).
     */
    public function apply(ChangeRequest $cr, User $user): void
    {
        $this->assertStatus($cr, 'approved', 'apply');
        $this->assertSeparationOfDuties($cr, $user, 'apply');

        DB::transaction(function () use ($cr, $user): void {
            $target = $cr->target;

            $changes = array_filter([
                'title' => $cr->proposed_changes['title'] ?? null,
                'body' => $cr->proposed_changes['body'] ?? null,
                'status' => $cr->proposed_changes['status'] ?? null,
                'confidence' => $cr->proposed_changes['confidence'] ?? null,
            ], fn ($v) => $v !== null);

            if ($changes !== []) {
                $this->graph->update($target, $changes, $user->id, "applied change request {$cr->ref}", allowBaselined: true);
            }

            // Regenerate affected outputs: downstream objects need re-confirmation,
            // each explicitly marked review_required until dispositioned (spec §13).
            foreach ($this->trace->forwardTrace($target) as $affected) {
                $attributes = ($affected->getAttribute('attributes') ?? []) + [];
                $attributes['impact'] = 'review_required';
                $attributes['impact_from'] = $cr->ref;

                $this->graph->update(
                    $affected,
                    ['status' => ObjectStatus::NEEDS_CONFIRMATION, 'attributes' => $attributes],
                    $user->id,
                    "flagged by change request {$cr->ref}",
                    allowBaselined: true,
                );
            }

            $cr->update(['status' => 'applied', 'applied_at' => now()]);
        });
    }

    /**
     * Disposition one downstream object flagged by an applied CR (spec §13):
     * no_impact (confirmed, flag cleared), update_required (stays unresolved),
     * invalidated (retired as not_applicable).
     */
    public function disposition(ChangeRequest $cr, EngObject $object, string $impact, User $user): EngObject
    {
        if ($cr->status !== 'applied') {
            throw new ChangeManagementException('Impact disposition only applies to an applied change request.');
        }
        if (! in_array($impact, ['no_impact', 'update_required', 'invalidated'], true)) {
            throw new ChangeManagementException("Unknown impact disposition: {$impact}.");
        }
        if (($object->getAttribute('attributes')['impact_from'] ?? null) !== $cr->ref) {
            throw new ChangeManagementException("Object {$object->ref} was not flagged by {$cr->ref}.");
        }

        $attributes = $object->getAttribute('attributes') ?? [];
        $attributes['impact'] = $impact;

        $changes = ['attributes' => $attributes];
        if ($impact === 'no_impact') {
            $changes['status'] = ObjectStatus::CONFIRMED_BY_EVIDENCE;
        } elseif ($impact === 'invalidated') {
            $changes['status'] = ObjectStatus::NOT_APPLICABLE;
        }

        return $this->graph->update($object, $changes, $user->id, "impact disposition '{$impact}' for {$cr->ref}", allowBaselined: true);
    }

    /** The change author cannot approve or apply their own request (PRD §6.3). */
    private function assertSeparationOfDuties(ChangeRequest $cr, User $user, string $action): void
    {
        if (config('acl.separation_of_duties') && (int) $cr->raised_by === (int) $user->id) {
            throw new SeparationOfDutiesException(
                "Separation of duties: the change author cannot {$action} their own request.",
            );
        }
    }

    private function assertStatus(ChangeRequest $cr, string $expected, string $action): void
    {
        if ($cr->status !== $expected) {
            throw new ChangeManagementException(
                "Cannot {$action}: change request is '{$cr->status}', expected '{$expected}'.",
            );
        }
    }
}

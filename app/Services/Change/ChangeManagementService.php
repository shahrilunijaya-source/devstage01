<?php

declare(strict_types=1);

namespace App\Services\Change;

use App\Enums\ObjectStatus;
use App\Enums\ObjectType;
use App\Models\Graph\ChangeRequest;
use App\Models\Graph\EngObject;
use App\Models\User;
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
    ) {}

    /**
     * Intake a change request and compute its impact (PRD §15.6 CHG-01).
     *
     * @param  array<string, mixed>  $proposedChanges  title, body, status, confidence
     */
    public function open(EngObject $target, User $user, string $title, ?string $description = null, array $proposedChanges = []): ChangeRequest
    {
        return ChangeRequest::create([
            'ref' => $this->ids->next((int) $target->project_id, ObjectType::CHANGE_REQUEST),
            'project_id' => $target->project_id,
            'target_object_id' => $target->id,
            'raised_by' => $user->id,
            'title' => $title,
            'description' => $description,
            'proposed_changes' => $proposedChanges,
            'impact' => $this->analyzeImpact($target),
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

    public function approve(ChangeRequest $cr, User $user): void
    {
        $this->assertStatus($cr, 'draft', 'approve');
        $this->assertSeparationOfDuties($cr, $user, 'approve');
        $cr->update(['status' => 'approved', 'decided_by' => $user->id, 'decided_at' => now()]);
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

            // Regenerate affected outputs: downstream objects need re-confirmation.
            foreach ($this->trace->forwardTrace($target) as $affected) {
                $this->graph->update(
                    $affected,
                    ['status' => ObjectStatus::NEEDS_CONFIRMATION],
                    $user->id,
                    "flagged by change request {$cr->ref}",
                    allowBaselined: true,
                );
            }

            $cr->update(['status' => 'applied', 'applied_at' => now()]);
        });
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

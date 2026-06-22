<?php

declare(strict_types=1);

namespace App\Services\Verification;

use App\Enums\ObjectType;
use App\Models\Graph\EngObject;
use App\Models\Project;
use App\Services\Graph\TraceService;
use Illuminate\Support\Collection;

/**
 * Requirements Traceability Matrix (PRD §17) — the end-to-end compliance view.
 * One row per requirement, showing the full chain: upstream evidence/findings
 * that support it, downstream design that satisfies it, and the verification
 * (test cases / results / open defects) that prove it. Broken links are flagged
 * so auditors see exactly where traceability stops.
 *
 * Composes the existing trace graph and the V&V register — no new storage.
 */
class RtmService
{
    /** Requirement-family types the matrix is anchored on. */
    private const REQUIREMENT_TYPES = [
        ObjectType::BUSINESS_REQUIREMENT->value,
        ObjectType::USER_REQUIREMENT->value,
        ObjectType::FUNCTIONAL_REQUIREMENT->value,
        ObjectType::NON_FUNCTIONAL_REQUIREMENT->value,
    ];

    /** Upstream sources that justify a requirement. */
    private const SUPPORT_TYPES = [ObjectType::EVIDENCE, ObjectType::FINDING];

    /** Downstream design/spec artefacts that satisfy a requirement. */
    private const DESIGN_TYPES = [
        ObjectType::DESIGN_DECISION, ObjectType::DESIGN_COMPONENT,
        ObjectType::INTERFACE, ObjectType::DATABASE_OBJECT, ObjectType::ACCEPTANCE_CRITERION,
    ];

    public function __construct(
        private readonly TraceService $trace,
        private readonly VerificationService $verification,
    ) {}

    /** @return array<string, mixed> */
    public function matrix(Project $project): array
    {
        $requirements = EngObject::forProject($project->id)
            ->whereIn('type', self::REQUIREMENT_TYPES)
            ->orderBy('ref')->get();

        // V&V register, indexed by requirement id for O(1) lookup.
        $verification = collect($this->verification->register($project)['rows'])
            ->keyBy(fn (array $row): int => (int) $row['requirement']->id);

        $rows = $requirements->map(function (EngObject $req) use ($verification): array {
            $support = collect($this->trace->reverseTrace($req))
                ->filter(fn (EngObject $o): bool => in_array($o->type, self::SUPPORT_TYPES, true))
                ->pluck('ref')->values();

            $design = collect($this->trace->forwardTrace($req))
                ->filter(fn (EngObject $o): bool => in_array($o->type, self::DESIGN_TYPES, true))
                ->pluck('ref')->values();

            $vv = $verification->get((int) $req->id);
            $cases = $vv['cases'] ?? collect();
            $defects = $vv['defects'] ?? collect();
            $vvStatus = $vv['status'] ?? 'unverified';

            return [
                'requirement' => $req,
                'support' => $support,
                'design' => $design,
                'cases' => $cases->count(),
                'open_defects' => $defects->count(),
                'verification' => $vvStatus,
                'status' => $this->rtmStatus($support->isNotEmpty(), $vvStatus, $defects->count()),
            ];
        });

        return [
            'project' => $project,
            'rows' => $rows,
            'summary' => $this->summarize($rows),
        ];
    }

    /**
     * Overall traceability verdict for one requirement, worst-first:
     * unsupported (no evidence) > broken (failing/blocked or open defects) >
     * unverified (no tests) > pending (tests not all passed) > traced (verified).
     */
    private function rtmStatus(bool $supported, string $vvStatus, int $openDefects): string
    {
        if (! $supported) {
            return 'unsupported';
        }
        if ($openDefects > 0 || in_array($vvStatus, ['failing', 'blocked'], true)) {
            return 'broken';
        }
        if ($vvStatus === 'unverified') {
            return 'unverified';
        }
        if ($vvStatus === 'pending') {
            return 'pending';
        }

        return 'traced';
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function summarize(Collection $rows): array
    {
        $byStatus = $rows->countBy('status');
        $traced = $byStatus->get('traced', 0);

        return [
            'requirements' => $rows->count(),
            'traced' => $traced,
            'unsupported' => $byStatus->get('unsupported', 0),
            'broken' => $byStatus->get('broken', 0),
            'unverified' => $byStatus->get('unverified', 0),
            'pending' => $byStatus->get('pending', 0),
            'traced_pct' => $rows->count() > 0 ? round($traced / $rows->count() * 100, 1) : 0.0,
        ];
    }
}

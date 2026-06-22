<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AuditLog extends Model
{
    protected $fillable = ['user_id', 'action', 'entity_type', 'entity_id', 'project_id', 'before', 'after'];

    protected $casts = ['before' => 'array', 'after' => 'array'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public static function record(string $action, string $entityType, int $entityId, array $before = [], array $after = []): void
    {
        static::create([
            'user_id' => auth()->id(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'project_id' => static::resolveProjectId($entityType, $entityId),
            'before' => $before,
            'after' => $after,
        ]);
    }

    /** Resolve the owning project_id for an audited entity, or null for global events. */
    public static function resolveProjectId(string $entityType, int $entityId): ?int
    {
        $direct = [
            'WbsItem' => 'wbs_items',
            'LedgerEntry' => 'ledger_entries',
            'ClaimMilestone' => 'claim_milestones',
            'Issue' => 'issues',
            'WeeklyUpdate' => 'weekly_updates',
            'MonthlyReport' => 'monthly_reports',
            'ScurveSnapshot' => 'scurve_snapshots',
            'Budgetory' => 'budgetories',
            // URSB canonical graph + portfolio entities (all carry project_id).
            'EngObject' => 'objects',
            'Module' => 'modules',
            'Stage' => 'stages',
            'Session' => 'requirement_sessions',
            'StageBaseline' => 'stage_baselines',
        ];

        if (isset($direct[$entityType])) {
            $pid = DB::table($direct[$entityType])->where('id', $entityId)->value('project_id');

            return $pid !== null ? (int) $pid : null;
        }

        if ($entityType === 'ClaimSubmission') {
            $pid = DB::table('claim_submissions')
                ->where('claim_submissions.id', $entityId)
                ->join('claim_milestones', 'claim_submissions.claim_milestone_id', '=', 'claim_milestones.id')
                ->value('claim_milestones.project_id');

            return $pid !== null ? (int) $pid : null;
        }

        if ($entityType === 'BudgetoryBucket') {
            $pid = DB::table('budgetory_buckets')
                ->where('budgetory_buckets.id', $entityId)
                ->join('budgetories', 'budgetory_buckets.budgetory_id', '=', 'budgetories.id')
                ->value('budgetories.project_id');

            return $pid !== null ? (int) $pid : null;
        }

        if ($entityType === 'Project') {
            return DB::table('projects')->where('id', $entityId)->exists() ? $entityId : null;
        }

        return null; // User, Position, SalaryBand, unknown → global
    }

    /** Backfill project_id on existing rows. Idempotent; returns rows updated. */
    public static function backfillProjectIds(): int
    {
        $count = 0;
        foreach (static::whereNull('project_id')->select(['id', 'entity_type', 'entity_id'])->cursor() as $log) {
            $pid = static::resolveProjectId($log->entity_type, (int) $log->entity_id);
            if ($pid !== null) {
                static::whereKey($log->id)->update(['project_id' => $pid]);
                $count++;
            }
        }

        return $count;
    }
}

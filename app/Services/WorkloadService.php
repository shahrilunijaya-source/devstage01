<?php

namespace App\Services;

use App\Models\Project;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class WorkloadService
{
    /**
     * Per-project heaviness score (0–100) plus its factor breakdown.
     *
     * @return array{behind:float,deadline:float,value:float,issues:float,scope:float,planned_pct:float,actual_pct:float,open_issues:int,leaf_tasks:int,total:float,flags:string[]}
     */
    public function projectHeaviness(Project $project, ?float $maxContractValue = null): array
    {
        $max = $maxContractValue ?? $this->maxContractValue();
        $flags = [];

        // ── Behind: planned% − actual% (WBS, via ProjectCalculator) ──
        $items = $project->wbsItems()->orderBy('level')->orderBy('sort_order')->get();
        if ($items->isEmpty()) {
            $flags[] = 'no_wbs';
            $plannedPct = 0.0;
            $actualPct = 0.0;
        } else {
            $calc = new ProjectCalculator($project, $items);
            $plannedPct = round($calc->overallPlannedPct() * 100, 2);
            $actualPct = round($calc->overallActualPct() * 100, 2);
        }
        $behind = $this->clamp($plannedPct - $actualPct);

        // ── Deadline: closeness to planned_end ──
        $deadline = $this->deadlineScore($project, $flags);

        // ── Value: contract size relative to active portfolio ──
        $contract = (float) ($project->contract_value ?? 0);
        if ($contract <= 0) {
            $flags[] = 'no_value';
        }
        $value = $max > 0 ? $this->clamp($contract / $max * 100) : 0.0;

        // ── Issues: open (unresolved) issues, saturating at issue_cap ──
        // Reuse the eager-loaded count from heatmap() when present; otherwise fall
        // back to the shared Issue::scopeOpen definition (open + in_progress).
        $openIssues = $project->open_issues_count
            ?? $project->issues()->open()->count();
        $issueCap = (int) config('workload.issue_cap') ?: 1;
        $issues = $this->clamp($openIssues / $issueCap * 100);

        // ── Scope: leaf-task count, saturating at scope_cap ──
        $leafTasks = $items->where('is_leaf', true)->count();
        $scopeCap = (int) config('workload.scope_cap') ?: 1;
        $scope = $this->clamp($leafTasks / $scopeCap * 100);

        $w = config('workload.weights');
        $total = $w['behind'] * $behind
            + $w['deadline'] * $deadline
            + $w['value'] * $value
            + $w['issues'] * $issues
            + $w['scope'] * $scope;

        return [
            'behind' => $behind,
            'deadline' => $deadline,
            'value' => $value,
            'issues' => $issues,
            'scope' => $scope,
            'planned_pct' => $plannedPct,
            'actual_pct' => $actualPct,
            'open_issues' => $openIssues,
            'leaf_tasks' => $leafTasks,
            'total' => round($total, 2),
            'flags' => $flags,
        ];
    }

    private function deadlineScore(Project $project, array &$flags): float
    {
        if (! $project->planned_start || ! $project->planned_end) {
            $flags[] = 'no_dates';

            return 0.0;
        }

        $start = Carbon::parse($project->planned_start)->startOfDay();
        $end = Carbon::parse($project->planned_end)->startOfDay();
        $today = $project->dynamic_date_or_today->copy()->startOfDay();

        if ($today->lte($start)) {
            return 0.0;
        }
        if ($today->gte($end)) {
            return 100.0;
        }

        $elapsed = $start->diffInDays($today);
        $total = $start->diffInDays($end) ?: 1;

        return $this->clamp($elapsed / $total * 100);
    }

    /** Max contract value across active (non-inactive) projects. */
    public function maxContractValue(): float
    {
        $inactive = config('workload.inactive_statuses');

        return (float) (Project::whereNotIn('status', $inactive)->max('contract_value') ?? 0);
    }

    /**
     * A person's total workload across their active PM/PE assignments.
     *
     * @return array{user:User,total:float,band:string,role_mix:array<string,int>,rows:array<int,array>}
     */
    public function personLoad(User $user, ?float $maxContractValue = null, ?array $heavinessByProject = null, ?array $peCountByProject = null): array
    {
        $inactive = config('workload.inactive_statuses');
        $max = $maxContractValue ?? $this->maxContractValue();

        $assignments = $user->projectAssignments()
            ->whereNull('removed_at')
            ->whereIn('project_role', ['pm', 'pe'])
            ->whereHas('project', fn ($q) => $q->whereNotIn('status', $inactive))
            ->with('project')
            ->get();

        $rows = [];
        $total = 0.0;
        $roleMix = ['pm' => 0, 'pe' => 0];

        foreach ($assignments as $assignment) {
            $project = $assignment->project;
            $h = $heavinessByProject[$project->id] ?? $this->projectHeaviness($project, $max);

            if ($assignment->project_role === 'pm') {
                $share = (float) config('workload.pm_share');
            } else {
                $nPe = $peCountByProject[$project->id]
                    ?? $project->activeAssignments()->where('project_role', 'pe')->count();
                $nPe = $nPe ?: 1;
                $share = (float) config('workload.pe_share') / $nPe;
            }

            $contribution = round($share * $h['total'], 1);
            $total += $contribution;
            $roleMix[$assignment->project_role]++;

            $rows[] = [
                'project' => $project,
                'role' => $assignment->project_role,
                'heaviness' => $h['total'],
                'behind' => $h['behind'],
                'deadline' => $h['deadline'],
                'value' => $h['value'],
                'issues' => $h['issues'],
                'scope' => $h['scope'],
                'open_issues' => $h['open_issues'],
                'leaf_tasks' => $h['leaf_tasks'],
                'share' => round($share, 3),
                'contribution' => $contribution,
                'flags' => $h['flags'],
            ];
        }

        usort($rows, fn ($a, $b) => $b['contribution'] <=> $a['contribution']);
        $total = round($total, 1);

        return [
            'user' => $user,
            'total' => $total,
            'band' => $this->bandFor($total),
            'role_mix' => $roleMix,
            'rows' => $rows,
        ];
    }

    /**
     * Every active PM/PE with their workload, heaviest first.
     *
     * @return Collection<int,array>
     */
    public function heatmap(): Collection
    {
        $inactive = config('workload.inactive_statuses');

        $projects = Project::whereNotIn('status', $inactive)
            ->with('activeAssignments.user')
            ->withCount(['issues as open_issues_count' => fn ($q) => $q->open()])
            ->get();

        $max = (float) ($projects->max('contract_value') ?? 0);

        // Compute heaviness + active-PE count ONCE per project (not once per person).
        $heavinessByProject = [];
        $peCountByProject = [];
        foreach ($projects as $project) {
            $heavinessByProject[$project->id] = $this->projectHeaviness($project, $max);
            $peCountByProject[$project->id] = $project->activeAssignments
                ->where('project_role', 'pe')->count();
        }

        $users = $projects
            ->flatMap(fn ($project) => $project->activeAssignments->whereIn('project_role', ['pm', 'pe']))
            ->map(fn ($assignment) => $assignment->user)
            ->filter()
            ->unique('id')
            ->values();

        return $users
            ->map(fn ($user) => $this->personLoad($user, $max, $heavinessByProject, $peCountByProject))
            ->sortByDesc('total')
            ->values();
    }

    public function bandFor(float $load): string
    {
        $bands = config('workload.bands'); // ascending lower bounds
        $band = 'light';
        foreach ($bands as $name => $lowerBound) {
            if ($load >= $lowerBound) {
                $band = $name;
            }
        }

        return $band;
    }

    /** Compact one-line load label, e.g. "140 · Overloaded". */
    public function formatSummary(float $total, string $band): string
    {
        return number_format($total, 0).' · '.ucfirst($band);
    }

    private function clamp(float $x): float
    {
        return round(max(0.0, min(100.0, $x)), 2);
    }
}

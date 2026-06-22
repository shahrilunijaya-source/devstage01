<?php

namespace App\Services;

use App\Mail\MonthlyReportMail;
use App\Models\MonthlyReport;
use App\Models\Position;
use App\Models\Project;
use App\Models\ProjectWeekNote;
use App\Models\ReportDelivery;
use App\Models\SystemSetting;
use App\Models\User;
use App\Services\Rag\AnthropicClient;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class MonthlyReportService
{
    public function __construct(private Project $project) {}

    /**
     * Stamp an immutable monthly report: build the frozen snapshot, persist it
     * as a finalised record, and render the PDF to storage. The report is a
     * point-in-time stamp — it is never edited after this.
     */
    public function generate(Carbon $month, ?int $byUserId = null): MonthlyReport
    {
        $month = $month->copy()->startOfMonth();

        $snapshot = $this->buildSnapshot($month);
        // AI insight is generated once, here, and frozen with the rest of the
        // snapshot (best-effort — null if AI is unavailable; never blocks a stamp).
        $snapshot['ai_insight'] = $this->generateInsight($snapshot, $month);

        $report = $this->project->monthlyReports()->create([
            'reporting_month' => $month->toDateString(),
            'snapshot_data' => $snapshot,
            'status' => 'finalised',
            'generated_by' => $byUserId,
            'finalised_at' => now(),
        ]);

        $pdfPath = 'reports/'.$this->project->code.'/'.$month->format('Y-m').'.pdf';
        Storage::disk('public')->put(
            $pdfPath,
            Pdf::loadView('projects.reports.monthly-pdf', ['project' => $this->project, 'report' => $report])
                ->setPaper('a4', 'portrait')
                ->output()
        );
        $report->update(['pdf_path' => $pdfPath]);

        return $report;
    }

    /**
     * Generate the AI executive insight for the report from its frozen figures.
     * Best-effort: returns null (and never throws) if the AI client/key is
     * absent or the call fails, so a stamp is never blocked by AI availability.
     */
    public function generateInsight(array $snap, Carbon $month): ?string
    {
        // Soft dependency on the RAG Anthropic client — degrade if it's absent.
        if (! class_exists(AnthropicClient::class)) {
            return null;
        }
        if (empty(SystemSetting::get('anthropic_api_key'))) {
            return null;
        }

        try {
            $lines = [
                'Project: '.$this->project->name.' ('.$this->project->code.')',
                'Reporting month: '.$month->format('F Y'),
                'Planned progress: '.($snap['overall_planned_pct'] ?? 0).'%',
                'Actual progress: '.($snap['overall_actual_pct'] ?? 0).'%',
                'Variance: '.($snap['overall_variance'] ?? 0).' points',
                'Health flag: '.($snap['health'] ?? 'n/a'),
            ];

            if (! empty($snap['budget']['total'])) {
                $b = $snap['budget'];
                $lines[] = sprintf(
                    'Budget: total RM %s, spent to date RM %s, spent this month RM %s, remaining RM %s',
                    number_format($b['total'], 0),
                    number_format($b['spent_to_date'] ?? 0, 0),
                    number_format($b['spent_this_month'] ?? 0, 0),
                    number_format($b['remaining'] ?? 0, 0),
                );
            }

            if (! empty($snap['claim_totals'])) {
                $by = $snap['claim_totals']['by_status'] ?? [];
                $lines[] = sprintf(
                    'Claims: total RM %s (received RM %s, approved RM %s, submitted RM %s)',
                    number_format($snap['claim_totals']['total_amount'] ?? 0, 0),
                    number_format($by['received'] ?? 0, 0),
                    number_format($by['approved'] ?? 0, 0),
                    number_format($by['submitted'] ?? 0, 0),
                );
            }

            if (! empty($snap['issues'])) {
                $titles = collect($snap['issues'])->take(6)
                    ->map(fn ($i) => ($i['severity'] ?? '').': '.($i['title'] ?? ''))
                    ->implode('; ');
                $lines[] = 'Open issues ('.count($snap['issues']).'): '.$titles;
            }

            if (! empty($snap['wbs_tree'])) {
                $movers = collect($snap['wbs_tree'])
                    ->where('depth', 1)
                    ->map(fn ($t) => $t['name'].' '.$t['actual_pct'].'% (plan '.$t['planned_pct'].'%)')
                    ->take(6)->implode('; ');
                if ($movers !== '') {
                    $lines[] = 'Top-level WBS: '.$movers;
                }
            }

            $system = 'You are a senior project management analyst writing the executive insight for an INTERNAL monthly progress report. '
                .'Using ONLY the figures provided, write 2 to 4 sentences of plain, factual prose covering: where the project stands, the plan-vs-actual gap, money/claims status, and the key risks. '
                .'No markdown, no bullet points, no headings, no preamble. Do not invent numbers.';

            $insight = (new AnthropicClient)->answer(
                AnthropicClient::MODEL_SONNET,
                $system,
                "Report data:\n".implode("\n", $lines),
                500,
            );

            return $insight !== '' ? $insight : null;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    /**
     * Internal distribution list: the project's PM + PEs (primary recipients),
     * with every active Director and Admin on CC (org-wide oversight).
     *
     * @return array{to: array<int,string>, cc: array<int,string>}
     */
    public function internalRecipients(): array
    {
        $this->project->loadMissing('activeAssignments.user');

        $team = $this->project->activeAssignments
            ->whereIn('project_role', ['pm', 'pe'])
            ->map(fn ($a) => $a->user)
            ->filter(fn ($u) => $u && $u->active && $u->email)
            ->pluck('email')->unique()->values()->all();

        $oversight = User::query()
            ->where('active', true)
            ->whereIn('system_role', ['director', 'admin'])
            ->whereNotNull('email')
            ->pluck('email')->unique()->values()->all();

        return [
            'to' => $team,
            // CC oversight, minus anyone already in To (a director who is also the PM)
            'cc' => array_values(array_diff($oversight, $team)),
        ];
    }

    /**
     * Email the stamped report (PDF attached + link) to the internal audience
     * and log the delivery. Failures are recorded, not thrown.
     */
    public function distribute(MonthlyReport $report, ?int $byUserId = null): ?ReportDelivery
    {
        $rcpt = $this->internalRecipients();
        $to = $rcpt['to'] ?: $rcpt['cc'];   // fall back to oversight if no team assigned
        $cc = $rcpt['to'] ? $rcpt['cc'] : [];

        if (empty($to)) {
            return null;
        }

        $subject = $this->project->code.' Monthly Progress Report — '.$report->reporting_month->format('F Y');
        $status = 'sent';
        $error = null;

        try {
            Mail::to($to)->cc($cc)->send(new MonthlyReportMail(
                project: $this->project,
                report: $report,
                subjectLine: $subject,
                internal: true,
                viewUrl: route('projects.reports.monthly.show', [$this->project, $report]),
            ));
        } catch (\Throwable $e) {
            $status = 'failed';
            $error = $e->getMessage();
            report($e);
        }

        return $report->deliveries()->create([
            'delivery_method' => 'in_app_email',
            'status' => $status,
            'recipients_to' => $to,
            'recipients_cc' => $cc,
            'subject' => $subject,
            'error' => $error,
            'sent_at' => now(),
            'sent_by' => $byUserId,
        ]);
    }

    public function buildSnapshot(Carbon $month): array
    {
        $project = $this->project;
        $monthStart = $month->copy()->startOfMonth();
        $monthEnd = $month->copy()->endOfMonth();

        $calc = new ProjectCalculator($project);

        // WBS leaf items active this month (drives the client PDF table)
        $activeLeaves = $project->wbsItems()
            ->where('is_leaf', true)
            ->where(fn ($q) => $q
                ->whereBetween('planned_start', [$monthStart, $monthEnd])
                ->orWhereBetween('planned_end', [$monthStart, $monthEnd])
                ->orWhere(fn ($q2) => $q2
                    ->where('planned_start', '<=', $monthStart)
                    ->where('planned_end', '>=', $monthEnd))
            )
            ->get();
        $activeItems = $activeLeaves->map(fn ($item) => [
            'id' => $item->id,
            'wbs_code' => $item->wbs_code,
            'name' => $item->name,
            'planned_pct' => round($calc->computePlannedPct($item) * 100, 2),
            'actual_pct' => round($calc->computeActualPct($item) * 100, 2),
            'variance' => round($calc->computeVariancePct($item) * 100, 2),
            'actual_start' => $item->actual_start?->format('Y-m-d'),
            'actual_end' => $item->actual_end?->format('Y-m-d'),
            'pic' => $item->pic,
        ])->toArray();

        // Full project WBS tree (down to level 3) with rolled-up planned/actual %,
        // mirroring the live Plan-vs-Actual page. Powers the level 1/2/3 picker on the
        // report — the view shows rows where depth <= chosen level.
        $depthOf = fn ($code) => $code ? substr_count($code, '.') + 1 : 1;
        $wbsTree = $project->wbsItems()
            ->orderBy('sort_order')
            ->get()
            ->filter(fn ($n) => $depthOf($n->wbs_code) <= 3)
            ->map(fn ($n) => [
                'wbs_code' => $n->wbs_code,
                'name' => $n->name,
                'depth' => $depthOf($n->wbs_code),
                'is_milestone' => (bool) $n->is_milestone,
                'planned_pct' => round($calc->computePlannedPct($n) * 100, 2),
                'actual_pct' => round($calc->computeActualPct($n) * 100, 2),
                'variance' => round($calc->computeVariancePct($n) * 100, 2),
                'planned_start' => $n->planned_start?->format('Y-m-d'),
                'planned_end' => $n->planned_end?->format('Y-m-d'),
            ])->values()->toArray();

        // Budget summary — Best Case Scenario amount (allocatable_amount × best_case_pct%). This is the operating budget PMs carry forward.
        $project->loadMissing('budgetory');
        $totalBudget = (float) ($project->budgetory?->best_case_amount ?? 0.0);
        $totalExpenses = $project->ledgerEntries()->where('txn_type', 'expense')->sum('amount');
        $monthExpenses = $project->ledgerEntries()
            ->where('txn_type', 'expense')
            ->whereBetween('txn_date', [$monthStart, $monthEnd])
            ->sum('amount');
        $monthIncome = $project->ledgerEntries()
            ->where('txn_type', 'income')
            ->whereBetween('txn_date', [$monthStart, $monthEnd])
            ->sum('amount');

        // Claims this month
        $claims = $project->claimMilestones()
            ->with(['submissions' => fn ($q) => $q->whereBetween('submitted_date', [$monthStart, $monthEnd])])
            ->get()
            ->flatMap(fn ($m) => $m->submissions)
            ->map(fn ($s) => [
                'reference' => $s->claim_reference,
                'amount' => $s->submitted_amount,
                'status' => $s->status,
                'date' => $s->submitted_date?->format('Y-m-d'),
            ])->toArray();

        // Open issues
        $issues = $project->issues()
            ->whereNotIn('status', ['closed'])
            ->get(['title', 'severity', 'status', 'reported_date'])
            ->toArray();

        // Next month tasks
        $nextMonthStart = $monthEnd->copy()->addDay()->startOfMonth();
        $nextMonthEnd = $nextMonthStart->copy()->endOfMonth();
        $nextTasks = $project->wbsItems()
            ->where('is_leaf', true)
            ->whereBetween('planned_start', [$nextMonthStart, $nextMonthEnd])
            ->get(['name', 'planned_start', 'planned_end', 'pic'])
            ->toArray();

        // Weekly cadence activity (ProjectWeekNote) — free-text daily actuals/plans
        // for each week whose Monday falls in the reporting month.
        $rateResolver = app(DailyRateService::class);
        $positions = Position::all()->keyBy('id');
        $weekNotes = ProjectWeekNote::where('project_id', $project->id)
            ->whereBetween('week_start', [$monthStart, $monthEnd])
            ->with('updatedBy')
            ->orderBy('week_start')
            ->get()
            ->map(function ($n) use ($rateResolver, $positions) {
                // Per-week Money Out (frozen) — same math as the cadence box:
                // staff dedication cost (rate × hc × alloc% × days) + free-form expenses.
                $staff = collect($n->staff_allocation ?? [])->map(function ($r) use ($rateResolver, $positions) {
                    $pos = $positions[$r['position_id'] ?? 0] ?? null;
                    if (! $pos) {
                        return null;
                    }
                    $hc = (float) ($r['hc'] ?? 0);
                    $alloc = (float) ($r['alloc'] ?? 0);
                    $days = (float) ($r['days'] ?? 0);
                    $amount = $rateResolver->forPosition($pos) * $hc * ($alloc / 100) * $days;

                    return ['label' => $pos->name." (HC {$hc} · {$alloc}% · {$days}d)", 'amount' => round($amount, 2)];
                })->filter()->values()->all();

                $expenses = collect($n->finance_lines ?? [])
                    ->filter(fn ($l) => ($l['type'] ?? 'expense') === 'expense')
                    ->map(fn ($l) => ['label' => $l['desc'] ?? '', 'amount' => (float) ($l['amount'] ?? 0)])
                    ->filter(fn ($l) => $l['amount'] > 0)
                    ->values()->all();

                $total = collect($staff)->sum('amount') + collect($expenses)->sum('amount');

                return [
                    'week_start' => $n->week_start?->format('Y-m-d'),
                    'updated_by' => optional($n->updatedBy)->name,
                    'daily_actuals' => $n->daily_actuals ?? [],
                    'daily_plans' => $n->daily_plans ?? [],
                    'money_out' => ['staff' => $staff, 'expenses' => $expenses, 'total' => round($total, 2)],
                ];
            })->toArray();

        // Full claim register — current status of every claim milestone, plus totals
        $milestones = $project->claimMilestones()->orderBy('sort_order')->get();
        $claimRegister = $milestones->map(fn ($m) => [
            'segment' => $m->segment,
            'perkara' => $m->perkara,
            'percentage' => (float) $m->percentage,
            'amount' => (float) $m->amount,
            'target_date' => $m->target_date ? Carbon::parse($m->target_date)->format('Y-m-d') : null,
            'claim_status' => $m->claim_status,
            'received_date' => $m->received_date ? Carbon::parse($m->received_date)->format('Y-m-d') : null,
            'status_note' => $m->status_note,
        ])->values()->toArray();
        $claimTotals = [
            'total_amount' => (float) $milestones->sum('amount'),
            'by_status' => collect(['pending', 'submitted', 'approved', 'received', 'rejected'])
                ->mapWithKeys(fn ($st) => [$st => (float) $milestones->where('claim_status', $st)->sum('amount')])
                ->toArray(),
        ];

        // S-curve series, frozen: full planned curve + recorded monthly actuals up to the reporting month
        $scurvePlanned = collect($calc->scurveData())
            ->map(fn ($p) => ['x' => $p['month_end'], 'y' => $p['planned_pct']])
            ->values()->toArray();
        $scurveActual = $project->scurveSnapshots()
            ->where('month_end', '<=', $monthEnd)
            ->orderBy('month_end')
            ->get(['month_end', 'actual_pct'])
            ->map(fn ($s) => ['x' => $s->month_end->format('Y-m-d'), 'y' => (float) $s->actual_pct])
            ->toArray();

        // Financial summary (mirrors the ledger/portfolio money tiles) + this-month spend
        $money = (new MoneySummaryService)->forProject($project);
        $money['spent_this_month'] = (float) $monthExpenses;

        // Project info — frozen administrative / contract particulars
        $projectInfo = [
            'code' => $project->code,
            'category' => $project->category,
            'status' => $project->computed_status,
            'client' => $project->client,
            'pm' => optional($project->pm)->name,
            'contractor' => $project->contractor,
            'procurement_method' => $project->procurement_method,
            'contract_value' => $project->contract_value !== null ? (float) $project->contract_value : null,
            'currency' => $project->currency,
            'planned_start' => $project->planned_start?->format('Y-m-d'),
            'planned_end' => $project->planned_end?->format('Y-m-d'),
            'contract_start' => $project->contract_start_date?->format('Y-m-d'),
            'contract_end' => $project->contract_end_date?->format('Y-m-d'),
            'sst_date' => $project->sst_date?->format('Y-m-d'),
            'company_agreement_date' => $project->company_agreement_date?->format('Y-m-d'),
            'bond_value' => $project->bond_value !== null ? (float) $project->bond_value : null,
            'bond_submission_date' => $project->bond_submission_date?->format('Y-m-d'),
            'description' => $project->description,
        ];

        return [
            'overall_planned_pct' => round($calc->overallPlannedPct() * 100, 2),
            'overall_actual_pct' => round($calc->overallActualPct() * 100, 2),
            'overall_variance' => round($calc->overallVariancePct() * 100, 2),
            'health' => $project->health_override ?? $calc->projectHealthFlag(),
            'project_info' => $projectInfo,
            'active_tasks' => $activeItems,
            'wbs_tree' => $wbsTree,
            'budget' => [
                'total' => $totalBudget,
                'spent_to_date' => $totalExpenses,
                'spent_this_month' => $monthExpenses,
                'remaining' => $totalBudget - $totalExpenses,
                'variance' => $totalBudget - $totalExpenses,
                'income_this_month' => $monthIncome,
            ],
            'claims' => $claims,
            'issues' => $issues,
            'next_month_tasks' => $nextTasks,
            'week_notes' => $weekNotes,
            'claim_register' => $claimRegister,
            'claim_totals' => $claimTotals,
            'scurve' => ['planned' => $scurvePlanned, 'actual' => $scurveActual],
            'money' => $money,
        ];
    }
}

<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use App\Models\Project;
use App\Services\MonthlyReportService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerateMonthlyReports extends Command
{
    protected $signature = 'reports:generate-monthly
        {--month= : Reporting month as YYYY-MM (defaults to the previous month)}
        {--project= : Limit to a single project (id or code)}';

    protected $description = 'Stamp and distribute the monthly report for active projects (runs on the 1st)';

    public function handle(): int
    {
        $month = $this->option('month')
            ? Carbon::createFromFormat('Y-m', $this->option('month'))->startOfMonth()
            : now('Asia/Kuala_Lumpur')->subMonthNoOverflow()->startOfMonth();

        $projects = Project::query()
            ->when($this->option('project'), function ($q, $p) {
                $q->where('id', $p)->orWhere('code', $p);
            })
            ->get();

        $this->info("Stamping reports for {$month->format('F Y')} — {$projects->count()} project(s).");

        $made = 0;
        foreach ($projects as $project) {
            // Idempotent: never double-stamp a month (cron may fire late or be re-run).
            $exists = $project->monthlyReports()
                ->whereDate('reporting_month', $month->toDateString())
                ->exists();

            if ($exists) {
                $this->line("  skip {$project->code} — already stamped");

                continue;
            }

            try {
                $service = new MonthlyReportService($project);
                $report = $service->generate($month->copy(), byUserId: null);
                $delivery = $service->distribute($report, byUserId: null);

                AuditLog::record('monthly_report.auto_generated', 'MonthlyReport', $report->id, [], [
                    'month' => $month->format('Y-m'),
                    'distribution' => $delivery?->status ?? 'no_recipients',
                ]);

                $made++;
                $this->info("  stamped {$project->code} — distribution: ".($delivery?->status ?? 'no recipients'));
            } catch (\Throwable $e) {
                report($e);
                $this->error("  failed {$project->code}: {$e->getMessage()}");
            }
        }

        $this->info("Done — {$made} report(s) stamped for {$month->format('F Y')}.");

        return self::SUCCESS;
    }
}

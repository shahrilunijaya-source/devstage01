<?php

namespace App\Http\Controllers\Project;

use App\Http\Controllers\Controller;
use App\Models\CalendarEvent;
use App\Models\Position;
use App\Models\Project;
use App\Models\ProjectWeekNote;
use App\Models\SystemSetting;
use App\Services\DailyRateService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class CalendarController extends Controller
{
    public function index(Project $project)
    {
        $this->authorize('view', $project);

        return view('projects.calendar', compact('project'));
    }

    public function events(Project $project)
    {
        $this->authorize('view', $project);

        $start = request('start') ? Carbon::parse(request('start')) : Carbon::now()->startOfMonth();
        $end = request('end') ? Carbon::parse(request('end')) : Carbon::now()->endOfMonth();

        $events = [];

        // WBS milestones
        $milestones = $project->wbsItems()
            ->where('is_milestone', true)
            ->whereBetween('planned_end', [$start, $end])
            ->get();
        foreach ($milestones as $m) {
            $events[] = [
                'id' => 'wbs-'.$m->id,
                'title' => '◆ '.$m->name,
                'start' => $m->planned_end?->format('Y-m-d'),
                'color' => '#F97316',
                'type' => 'milestone',
                'allDay' => true,
            ];
        }

        // WBS tasks (planned dates)
        $tasks = $project->wbsItems()
            ->where('is_leaf', true)
            ->where('is_milestone', false)
            ->where(fn ($q) => $q
                ->whereBetween('planned_start', [$start, $end])
                ->orWhereBetween('planned_end', [$start, $end])
            )->limit(50)->get();
        foreach ($tasks as $t) {
            $events[] = [
                'id' => 'task-'.$t->id,
                'title' => $t->name,
                'start' => $t->planned_start?->format('Y-m-d'),
                'end' => $t->planned_end?->addDay()->format('Y-m-d'), // FullCalendar end is exclusive
                'color' => '#00B8A9',
                'type' => 'task',
                'allDay' => true,
            ];
        }

        // Claim deadlines
        $claims = $project->claimMilestones()
            ->whereNotNull('target_date')
            ->whereBetween('target_date', [$start, $end])
            ->get();
        foreach ($claims as $c) {
            $events[] = [
                'id' => 'claim-'.$c->id,
                'title' => '💰 '.$c->perkara,
                'start' => $c->target_date->format('Y-m-d'),
                'color' => '#8B5CF6',
                'type' => 'claim',
                'allDay' => true,
            ];
        }

        // Weekly cadence — daily_actuals, daily_plans, finance_lines, staff_allocation
        $weekStartBound = $start->copy()->subDays(6); // week containing $start may begin earlier
        $notes = ProjectWeekNote::where('project_id', $project->id)
            ->whereBetween('week_start', [$weekStartBound, $end])
            ->get();

        $dayKeys = ['mon', 'tue', 'wed', 'thu', 'fri', 'sat', 'sun'];
        $rateResolver = app(DailyRateService::class);
        $positionsCache = [];

        foreach ($notes as $n) {
            $weekMon = Carbon::parse($n->week_start);

            // daily_plans — weekly plan input (distinct from WBS plan)
            foreach (($n->daily_plans ?? []) as $dk => $text) {
                $idx = array_search($dk, $dayKeys, true);
                if ($idx === false || ! trim((string) $text)) {
                    continue;
                }
                $date = $weekMon->copy()->addDays($idx);
                if ($date->lt($start) || $date->gt($end)) {
                    continue;
                }
                $events[] = [
                    'id' => 'wn-plan-'.$n->id.'-'.$dk,
                    'title' => '📝 '.$text,
                    'start' => $date->format('Y-m-d'),
                    'color' => '#6366F1', // indigo — weekly plan
                    'type' => 'week_plan',
                    'notes' => 'Weekly plan ('.strtoupper($dk).')',
                    'allDay' => true,
                ];
            }

            // daily_actuals — actuals logged per day
            foreach (($n->daily_actuals ?? []) as $dk => $text) {
                $idx = array_search($dk, $dayKeys, true);
                if ($idx === false || ! trim((string) $text)) {
                    continue;
                }
                $date = $weekMon->copy()->addDays($idx);
                if ($date->lt($start) || $date->gt($end)) {
                    continue;
                }
                $events[] = [
                    'id' => 'wn-act-'.$n->id.'-'.$dk,
                    'title' => '✅ '.$text,
                    'start' => $date->format('Y-m-d'),
                    'color' => '#F59E0B', // amber — actuals
                    'type' => 'week_actual',
                    'notes' => 'Actual ('.strtoupper($dk).')',
                    'allDay' => true,
                ];
            }

            // finance_lines on week_start
            foreach (($n->finance_lines ?? []) as $i => $line) {
                $desc = trim((string) ($line['desc'] ?? ''));
                $amount = (float) ($line['amount'] ?? 0);
                if ($desc === '' || $amount <= 0) {
                    continue;
                }
                if ($weekMon->lt($start) || $weekMon->gt($end)) {
                    continue;
                }
                $type = ($line['type'] ?? 'expense') === 'income' ? 'income' : 'expense';
                $events[] = [
                    'id' => 'wn-fin-'.$n->id.'-'.$i,
                    'title' => ($type === 'income' ? '＋' : '−').' '.number_format($amount, 2).' · '.$desc,
                    'start' => $weekMon->format('Y-m-d'),
                    'color' => $type === 'income' ? '#10B981' : '#F43F5E', // emerald / rose
                    'type' => $type === 'income' ? 'week_income' : 'week_expense',
                    'notes' => 'Weekly '.$type,
                    'allDay' => true,
                ];
            }

            // staff_allocation on week_start
            $staff = $n->staff_allocation ?? [];
            if (! empty($staff) && $weekMon->between($start, $end)) {
                $posIds = array_filter(array_column($staff, 'position_id'));
                foreach ($posIds as $pid) {
                    if (! isset($positionsCache[$pid])) {
                        $positionsCache[$pid] = Position::find($pid);
                    }
                }
                $totalCost = 0;
                $lines = [];
                foreach ($staff as $row) {
                    $pos = $positionsCache[$row['position_id'] ?? 0] ?? null;
                    if (! $pos) {
                        continue;
                    }
                    $rate = $rateResolver->forPosition($pos);
                    $hc = (float) ($row['hc'] ?? 0);
                    $alloc = (float) ($row['alloc'] ?? 0);
                    $days = (float) ($row['days'] ?? 0);
                    $amt = $rate * $hc * ($alloc / 100) * $days;
                    if ($amt <= 0) {
                        continue;
                    }
                    $totalCost += $amt;
                    $lines[] = $pos->name." (HC {$hc} · {$alloc}% · {$days}d)";
                }
                if ($totalCost > 0) {
                    $events[] = [
                        'id' => 'wn-staff-'.$n->id,
                        'title' => '👥 Staff · '.number_format($totalCost, 2),
                        'start' => $weekMon->format('Y-m-d'),
                        'color' => '#64748B', // slate — staff cost
                        'type' => 'week_staff',
                        'notes' => implode("\n", $lines),
                        'allDay' => true,
                    ];
                }
            }
        }

        // Manual calendar events
        $manual = $project->calendarEvents()
            ->where(fn ($q) => $q
                ->whereBetween('start_at', [$start, $end])
                ->orWhereBetween('end_at', [$start, $end])
            )->get();
        foreach ($manual as $e) {
            $events[] = [
                'id' => 'event-'.$e->id,
                'title' => $e->title,
                'start' => $e->start_at->format('Y-m-d\TH:i:s'),
                'end' => $e->end_at?->format('Y-m-d\TH:i:s'),
                'color' => '#003D3A',
                'type' => 'meeting',
                'notes' => $e->notes,
                'allDay' => false,
            ];
        }

        // Public holidays
        $holidays = SystemSetting::where('key', 'like', 'holiday_%')
            ->whereBetween('value', [$start->format('Y-m-d'), $end->format('Y-m-d')])
            ->get();
        foreach ($holidays as $h) {
            $events[] = [
                'id' => 'holiday-'.$h->id,
                'title' => '🏖️ '.$h->description,
                'start' => $h->value,
                'color' => '#DC2626',
                'type' => 'holiday',
                'allDay' => true,
            ];
        }

        return response()->json($events);
    }

    public function storeEvent(Request $request, Project $project)
    {
        $this->authorize('view', $project);

        $data = $request->validate([
            'title' => 'required|string|max:255',
            'event_type' => 'required|in:meeting,holiday,task,claim_deadline',
            'start_at' => 'required|date',
            'end_at' => 'nullable|date|after_or_equal:start_at',
            'notes' => 'nullable|string',
        ]);

        $project->calendarEvents()->create(array_merge($data, [
            'created_by' => auth()->id(),
        ]));

        return back()->with('success', 'Event added to calendar.');
    }

    public function destroyEvent(Project $project, CalendarEvent $event)
    {
        $this->authorize('view', $project);
        if ($event->created_by !== auth()->id() && ! auth()->user()->isAdmin()) {
            abort(403);
        }
        $event->delete();

        return back()->with('success', 'Event removed.');
    }
}

@extends('layouts.app')
@section('page-title', $report->reporting_month->format('F Y') . ' Report')
@section('page-sub', 'Month-end stamp · immutable')

@section('topbar-actions')
    <a href="{{ route('projects.reports.monthly', $project) }}" class="btn-secondary">← All Reports</a>
    <a href="{{ route('projects.reports.monthly.pdf', [$project, $report]) }}" class="btn-primary">Download PDF</a>
@endsection

@section('content')
@php
    $snap = $report->snapshot_data ?? [];

    // small inline section-label helper used to chunk the long report
    $sectionLabel = fn ($text) => '<div class="flex items-center gap-3 pt-3 pb-1">'
        .'<span class="text-[11px] font-bold uppercase tracking-widest text-gray-400">'.$text.'</span>'
        .'<div class="flex-1 h-px bg-gray-200/70"></div></div>';
@endphp

<div class="grid grid-cols-3 gap-6">
    <div class="col-span-2 space-y-4">

        {{-- Report header --}}
        <div class="bg-pine text-white rounded-2xl p-6">
            <div class="text-[11px] font-semibold uppercase tracking-widest text-white/50 mb-1">Monthly Progress Report</div>
            <div class="text-2xl font-bold tracking-tight">{{ $project->name }}</div>
            <div class="flex items-center gap-4 mt-2 text-[13px] text-white/70">
                <span>{{ $report->reporting_month->format('F Y') }}</span>
                @if($project->client)<span>{{ $project->client }}</span>@endif
                @if(isset($snap['health']))
                <span class="font-semibold text-teal uppercase text-[11px]">{{ $snap['health'] }} ●</span>
                @endif
            </div>
        </div>

        {{-- ════════ OVERVIEW ════════ --}}
        {!! $sectionLabel('Overview') !!}

        {{-- Project Information (frozen) --}}
        @php $pi = $snap['project_info'] ?? []; @endphp
        @if(!empty($pi))
        @php
            $fmtDate = fn ($d) => $d ? \Carbon\Carbon::parse($d)->format('d/m/Y') : null;
            $period = ($pi['contract_start'] ?? $pi['planned_start'])
                ? $fmtDate($pi['contract_start'] ?? $pi['planned_start']).' – '.($fmtDate($pi['contract_end'] ?? $pi['planned_end']) ?? '—')
                : null;
            $infoRows = array_filter([
                'Project Code' => $pi['code'] ?? null,
                'Category' => $pi['category'] ? ucfirst($pi['category']) : null,
                'Status' => $pi['status'] ? ucfirst(str_replace('_', ' ', $pi['status'])) : null,
                'Client' => $pi['client'] ?? null,
                'Project Manager' => $pi['pm'] ?? null,
                'Contractor' => $pi['contractor'] ?? null,
                'Procurement' => $pi['procurement_method'] ?? null,
                'Contract Value' => !empty($pi['contract_value']) ? 'RM '.number_format($pi['contract_value'], 0) : null,
                'Contract Period' => $period,
                'SST Date' => $fmtDate($pi['sst_date'] ?? null),
                'Agreement Date' => $fmtDate($pi['company_agreement_date'] ?? null),
                'Bond' => !empty($pi['bond_value']) ? 'RM '.number_format($pi['bond_value'], 0).(!empty($pi['bond_submission_date']) ? ' · '.$fmtDate($pi['bond_submission_date']) : '') : null,
            ], fn ($v) => $v !== null && $v !== '');
        @endphp
        <x-card>
            <x-card-header title="Project Information"/>
            <div class="p-5 grid grid-cols-3 gap-x-6 gap-y-3 text-[13px]">
                @foreach($infoRows as $label => $val)
                <div>
                    <div class="text-gray-500 mb-0.5">{{ $label }}</div>
                    <div class="font-semibold text-gray-800">{{ $val }}</div>
                </div>
                @endforeach
            </div>
            @if(!empty($pi['description']))
            <div class="px-5 pb-5 -mt-1">
                <div class="text-gray-500 text-[12px] mb-1">Description</div>
                <div class="text-[13px] text-gray-700 leading-relaxed whitespace-pre-wrap">{{ $pi['description'] }}</div>
            </div>
            @endif
        </x-card>
        @endif

        {{-- KPI snapshot --}}
        <div class="grid grid-cols-3 gap-4">
            <div class="stat-card">
                <div class="stat-value text-teal">{{ $snap['overall_planned_pct'] ?? '—' }}%</div>
                <div class="stat-label">Planned</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">{{ $snap['overall_actual_pct'] ?? '—' }}%</div>
                <div class="stat-label">Actual</div>
            </div>
            <div class="stat-card">
                <div class="stat-value {{ ($snap['overall_variance'] ?? 0) >= 0 ? 'text-green-600' : 'text-red-600' }}">
                    {{ ($snap['overall_variance'] ?? 0) >= 0 ? '+' : '' }}{{ $snap['overall_variance'] ?? '—' }}%
                </div>
                <div class="stat-label">Variance</div>
            </div>
        </div>

        {{-- Budget / Monetary (directly under the percentages) --}}
        @if(!empty($snap['budget']['total']))
        @php
            $bTotal = (float) ($snap['budget']['total'] ?? 0);
            $bSpent = (float) ($snap['budget']['spent_to_date'] ?? 0);
            $bUsedPct = $bTotal > 0 ? round($bSpent / $bTotal * 100) : 0;
            $barClass = $bUsedPct < 80 ? 'bg-teal' : ($bUsedPct <= 100 ? 'bg-amber-500' : 'bg-red-500');
        @endphp
        <x-card>
            <x-card-header title="Budget & Monetary"/>
            <div class="p-5 grid grid-cols-4 gap-4 text-[13px]">
                <div><div class="text-gray-500 mb-0.5">Total Budget</div><div class="font-semibold">RM {{ number_format($bTotal,0) }}</div></div>
                <div><div class="text-gray-500 mb-0.5">Spent to Date</div><div class="font-semibold text-amber-600">RM {{ number_format($bSpent,0) }}</div></div>
                <div><div class="text-gray-500 mb-0.5">This Month</div><div class="font-semibold text-amber-600">RM {{ number_format($snap['budget']['spent_this_month'] ?? 0,0) }}</div></div>
                <div><div class="text-gray-500 mb-0.5">Remaining</div><div class="font-semibold {{ $snap['budget']['remaining'] >= 0 ? 'text-green-600' : 'text-red-600' }}">RM {{ number_format($snap['budget']['remaining'],0) }}</div></div>
            </div>
            <div class="px-5 pb-2">
                <div class="flex justify-between text-[11px] text-gray-400 mb-1">
                    <span>{{ $bUsedPct }}% of budget used</span>
                    @if(!empty($snap['money']['income']))
                    <span>Income received: <span class="font-semibold text-green-600">RM {{ number_format($snap['money']['income'],0) }}</span></span>
                    @endif
                </div>
                <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden">
                    <div class="h-full {{ $barClass }} rounded-full" style="width: {{ min(100, max(0, $bUsedPct)) }}%"></div>
                </div>
            </div>
            <div class="pb-3"></div>
        </x-card>
        @endif

        {{-- AI Insight — generated at stamp time, frozen (falls back to a legacy exec summary) --}}
        @php $aiInsight = $snap['ai_insight'] ?? $report->executive_summary; @endphp
        @if(!empty($aiInsight))
        <x-card>
            <div class="flex items-center gap-2 px-5 py-4 border-b border-gray-100">
                <svg class="w-4 h-4 text-teal" fill="currentColor" viewBox="0 0 24 24"><path d="M11 2 8.5 8.5 2 11l6.5 2.5L11 20l2.5-6.5L20 11l-6.5-2.5L11 2zm8 9 1 3 3 1-3 1-1 3-1-3-3-1 3-1 1-3z"/></svg>
                <span class="text-[14px] font-semibold text-gray-900">AI Insight</span>
                <span class="text-[9px] font-bold uppercase tracking-wider px-1.5 py-0.5 rounded-full bg-teal/15 text-teal-700">Track AI</span>
            </div>
            <div class="p-5">
                <div class="text-[13px] text-gray-700 leading-relaxed whitespace-pre-wrap">{{ $aiInsight }}</div>
            </div>
        </x-card>
        @endif

        {{-- ════════ PROGRESS ════════ --}}
        {!! $sectionLabel('Progress') !!}

        {{-- S-Curve (frozen at generation) — headline visual, sits with the KPIs --}}
        @if(!empty($snap['scurve']['planned']))
        <x-card>
            <x-card-header title="S-Curve (Progress %)" sub="Planned vs actual, frozen at generation"/>
            <div class="p-5" style="height:280px">
                <canvas id="scurve-monthly"></canvas>
            </div>
        </x-card>
        @endif

        {{-- Plan vs Actual — full WBS tree with a level 1 / 2 / 3 picker --}}
        @if(!empty($snap['wbs_tree']))
        <div x-data="{ level: 2 }">
        <x-card>
            <div class="flex items-center justify-between px-5 py-3 border-b border-gray-100">
                <div>
                    <div class="font-semibold text-gray-800">Plan vs Actual</div>
                    <div class="text-[12px] text-teal">WBS to level <span x-text="level"></span></div>
                </div>
                <div class="inline-flex rounded-lg border border-gray-200 overflow-hidden text-[12px] font-medium">
                    @foreach ([1, 2, 3] as $lvl)
                    <button type="button" @click="level = {{ $lvl }}" :class="level === {{ $lvl }} ? 'bg-teal text-white' : 'bg-white text-gray-600'" class="px-3 py-1 {{ $lvl > 1 ? 'border-l border-gray-200' : '' }}">Level {{ $lvl }}</button>
                    @endforeach
                </div>
            </div>
            <div class="overflow-x-auto" x-cloak>
            <table class="data-table text-[12px]">
                <thead><tr><th>WBS</th><th>Task</th><th class="text-right">Plan%</th><th class="text-right">Actual%</th><th class="text-right">Var%</th><th>Start</th><th>End</th></tr></thead>
                <tbody>
                @foreach($snap['wbs_tree'] as $t)
                <tr x-show="{{ $t['depth'] }} <= level" class="wbs-level-{{ min($t['depth'], 3) }}">
                    <td class="font-mono text-[11px] text-gray-400">{{ $t['wbs_code'] }}</td>
                    <td>
                        <span style="padding-left: {{ max(0, ($t['depth'] - 1)) * 16 }}px"
                            class="{{ $t['depth'] == 1 ? 'font-semibold text-gray-800' : 'text-gray-700' }}">{{ $t['name'] }}</span>
                    </td>
                    <td class="text-right text-teal">{{ $t['planned_pct'] }}%</td>
                    <td class="text-right font-semibold">{{ $t['actual_pct'] }}%</td>
                    <td class="text-right {{ ($t['variance'] ?? 0) >= 0 ? 'text-green-600' : 'text-red-500' }} font-semibold">
                        {{ ($t['variance'] ?? 0) >= 0 ? '+' : '' }}{{ $t['variance'] }}%
                    </td>
                    <td class="text-gray-400 whitespace-nowrap">{{ $t['planned_start'] ? \Carbon\Carbon::parse($t['planned_start'])->format('d/m/Y') : '—' }}</td>
                    <td class="text-gray-400 whitespace-nowrap">{{ $t['planned_end'] ? \Carbon\Carbon::parse($t['planned_end'])->format('d/m/Y') : '—' }}</td>
                </tr>
                @endforeach
                </tbody>
            </table>
            </div>
        </x-card>
        </div>
        @elseif(!empty($snap['active_tasks']))
        <x-card>
            <x-card-header title="Plan vs Actual (This Month)" :sub="count($snap['active_tasks']).' active tasks'"/>
            <div class="overflow-x-auto">
            <table class="data-table text-[12px]">
                <thead><tr><th>WBS</th><th>Task</th><th class="text-right">Plan%</th><th class="text-right">Actual%</th><th class="text-right">Var%</th><th>PIC</th></tr></thead>
                <tbody>
                @foreach($snap['active_tasks'] as $t)
                <tr>
                    <td class="font-mono text-[11px] text-gray-400">{{ $t['wbs_code'] }}</td>
                    <td>{{ $t['name'] }}</td>
                    <td class="text-right text-teal">{{ $t['planned_pct'] }}%</td>
                    <td class="text-right font-semibold">{{ $t['actual_pct'] }}%</td>
                    <td class="text-right {{ ($t['variance'] ?? 0) >= 0 ? 'text-green-600' : 'text-red-500' }} font-semibold">
                        {{ ($t['variance'] ?? 0) >= 0 ? '+' : '' }}{{ $t['variance'] }}%
                    </td>
                    <td class="text-gray-400">{{ $t['pic'] ?? '—' }}</td>
                </tr>
                @endforeach
                </tbody>
            </table>
            </div>
        </x-card>
        @endif

        {{-- ════════ ACTIVITY ════════ — week boxes side by side (horizontal strip) --}}
        @if(!empty($snap['week_notes']))
        {!! $sectionLabel('Activity') !!}
        @php $dayLabels = ['mon' => 'Mon', 'tue' => 'Tue', 'wed' => 'Wed', 'thu' => 'Thu', 'fri' => 'Fri', 'sat' => 'Sat', 'sun' => 'Sun']; @endphp
        <div class="flex gap-4 overflow-x-auto pb-2 -mx-1 px-1">
        @foreach($snap['week_notes'] as $w)
            <x-card class="w-60 shrink-0 flex flex-col self-stretch">
                <div class="px-4 py-3 border-b border-gray-100">
                    <div class="text-[13px] font-semibold text-gray-800">{{ \Carbon\Carbon::parse($w['week_start'])->format('d/m/Y') }}</div>
                    @if(!empty($w['updated_by']))<div class="text-[11px] text-gray-400">{{ $w['updated_by'] }}</div>@endif
                </div>
                <div class="p-4 space-y-2.5 flex-1">
                @foreach($dayLabels as $k => $lbl)
                    @php $act = data_get($w, 'daily_actuals.'.$k); @endphp
                    @if(!empty($act))
                    <div>
                        <div class="text-[10px] font-semibold uppercase tracking-wider text-gray-400">{{ $lbl }}</div>
                        <div class="text-[12px] text-gray-700 leading-snug">{{ $act }}</div>
                    </div>
                    @endif
                @endforeach
                @if(empty(array_filter($w['daily_actuals'] ?? [])))
                <div class="text-[12px] text-gray-300">No activity.</div>
                @endif
                </div>

                {{-- Money Out for the week (frozen) — pinned to the box bottom --}}
                @php $mo = $w['money_out'] ?? null; @endphp
                @if(!empty($mo) && ($mo['total'] ?? 0) > 0)
                <div class="px-4 py-3 border-t border-gray-100 bg-red-50/40">
                    <div class="flex items-center justify-between">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-red-700">Money Out</span>
                        <span class="text-[12px] font-bold text-gray-900 tabular-nums">RM {{ number_format($mo['total'], 2) }}</span>
                    </div>
                    <div class="mt-1 space-y-0.5">
                        @foreach($mo['staff'] ?? [] as $s)
                        <div class="flex justify-between gap-2 text-[11px]"><span class="text-gray-500 truncate" title="{{ $s['label'] }}">{{ $s['label'] }}</span><span class="tabular-nums text-gray-600 shrink-0">{{ number_format($s['amount'], 2) }}</span></div>
                        @endforeach
                        @foreach($mo['expenses'] ?? [] as $e)
                        <div class="flex justify-between gap-2 text-[11px]"><span class="text-gray-500 truncate" title="{{ $e['label'] }}">{{ $e['label'] }}</span><span class="tabular-nums text-gray-600 shrink-0">{{ number_format($e['amount'], 2) }}</span></div>
                        @endforeach
                    </div>
                </div>
                @else
                <div class="px-4 py-2.5 border-t border-gray-100 text-[11px] text-gray-300">No money out.</div>
                @endif
            </x-card>
        @endforeach
        </div>
        @endif

        {{-- ════════ CLAIMS ════════ --}}
        @if(!empty($snap['claim_register']))
        {!! $sectionLabel('Claims') !!}
        <x-card>
            <x-card-header title="Claim Register" :sub="count($snap['claim_register']).' milestones'"/>
            <div class="overflow-x-auto">
            <table class="data-table text-[12px]">
                <thead><tr>
                    <th>Segment</th><th>Perkara</th><th class="text-right">%</th>
                    <th class="text-right">Amount (MYR)</th><th>Target</th><th>Status</th><th>Received</th>
                </tr></thead>
                <tbody>
                @foreach($snap['claim_register'] as $c)
                @php
                    $cb = match($c['claim_status'] ?? 'pending') {
                        'submitted' => 'bg-blue-100 text-blue-700',
                        'approved'  => 'bg-green-100 text-green-700',
                        'received'  => 'bg-teal/15 text-teal-700',
                        'rejected'  => 'bg-red-100 text-red-700',
                        default     => 'bg-gray-100 text-gray-600',
                    };
                @endphp
                <tr>
                    <td class="text-gray-500">{{ $c['segment'] ?? '—' }}</td>
                    <td>{{ $c['perkara'] }}</td>
                    <td class="text-right">{{ number_format(($c['percentage'] ?? 0) * 100, 1) }}%</td>
                    <td class="text-right font-semibold">{{ number_format($c['amount'] ?? 0, 0) }}</td>
                    <td class="text-gray-500">{{ $c['target_date'] ? \Carbon\Carbon::parse($c['target_date'])->format('d/m/Y') : '—' }}</td>
                    <td><span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full {{ $cb }}">{{ $c['claim_status'] ?? 'pending' }}</span></td>
                    <td class="text-gray-500">{{ $c['received_date'] ? \Carbon\Carbon::parse($c['received_date'])->format('d/m/Y') : '—' }}</td>
                </tr>
                @endforeach
                </tbody>
                @if(!empty($snap['claim_totals']))
                <tr class="bg-gray-50">
                    <td colspan="3" class="text-right font-semibold text-gray-600 px-4 py-2.5">Total</td>
                    <td class="text-right font-bold px-4 py-2.5">{{ number_format($snap['claim_totals']['total_amount'] ?? 0, 0) }}</td>
                    <td colspan="3" class="text-[11px] text-gray-400 px-4 py-2.5">
                        Received RM {{ number_format($snap['claim_totals']['by_status']['received'] ?? 0,0) }} &middot;
                        Approved RM {{ number_format($snap['claim_totals']['by_status']['approved'] ?? 0,0) }} &middot;
                        Submitted RM {{ number_format($snap['claim_totals']['by_status']['submitted'] ?? 0,0) }}
                    </td>
                </tr>
                @endif
            </table>
            </div>
        </x-card>
        @endif

    </div>

    {{-- Right sidebar — report lifecycle & actions --}}
    <div class="space-y-4">

        {{-- Report status / meta --}}
        <x-card>
            <x-card-header title="Report Status"/>
            <div class="p-4 space-y-2 text-[12px]">
                <div class="flex items-center justify-between">
                    <span class="text-gray-400">Status</span>
                    <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full bg-teal/15 text-teal-700">Stamped</span>
                </div>
                <div class="flex items-center justify-between">
                    <span class="text-gray-400">Generated</span>
                    <span class="text-gray-700">{{ $report->generatedBy ? $report->generatedBy->name : 'System (auto)' }}</span>
                </div>
                @if($report->finalised_at)
                <div class="flex items-center justify-between">
                    <span class="text-gray-400">Stamped at</span>
                    <span class="text-gray-700">{{ $report->finalised_at->format('d/m/Y H:i') }}</span>
                </div>
                @endif
            </div>
        </x-card>

        {{-- Open Issues --}}
        <x-card>
            <x-card-header title="Open Issues" :sub="!empty($snap['issues']) ? count($snap['issues']).' open' : null"/>
            @if(!empty($snap['issues']))
            <div class="divide-y divide-gray-50">
            @foreach(array_slice($snap['issues'], 0, 5) as $i)
            <div class="px-4 py-2.5">
                <div class="text-[12px] font-medium text-gray-800">{{ $i['title'] }}</div>
                <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full
                    {{ $i['severity'] === 'critical' ? 'bg-red-100 text-red-700' : 'bg-orange-100 text-orange-700' }}">
                    {{ $i['severity'] }}
                </span>
            </div>
            @endforeach
            @if(count($snap['issues']) > 5)
            <div class="px-4 py-2 text-[11px] text-gray-400">+ {{ count($snap['issues']) - 5 }} more</div>
            @endif
            </div>
            @else
            <div class="px-4 py-5 text-center text-[12px] text-gray-300">No open issues.</div>
            @endif
        </x-card>

        {{-- Distribution log — internal email to directors, PM & PEs --}}
        @if($report->deliveries->isNotEmpty())
        <x-card>
            <x-card-header title="Distribution Log"/>
            <div class="divide-y divide-gray-50">
            @foreach($report->deliveries as $d)
            @php
                $badge = match($d->status) {
                    'sent'   => 'bg-green-100 text-green-700',
                    'failed' => 'bg-red-100 text-red-700',
                    default  => 'bg-gray-100 text-gray-600',
                };
            @endphp
            <div class="px-4 py-2.5 text-[12px]">
                <div class="flex items-center justify-between">
                    <div class="font-medium text-gray-700">{{ $d->sent_at->format('d/m/Y H:i') }}</div>
                    <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full {{ $badge }}">{{ $d->status ?? 'logged' }}</span>
                </div>
                <div class="text-gray-400">To: {{ collect($d->recipients_to)->join(', ') ?: '—' }}</div>
                @if(!empty($d->recipients_cc))
                <div class="text-gray-400">Cc: {{ collect($d->recipients_cc)->join(', ') }}</div>
                @endif
                <div class="text-gray-400">{{ $d->sentBy ? 'By: '.$d->sentBy->name : 'Automated (scheduler)' }}</div>
                @if($d->status === 'failed' && $d->error)
                <div class="text-red-500 mt-1 truncate" title="{{ $d->error }}">{{ \Illuminate\Support\Str::limit($d->error, 80) }}</div>
                @endif
            </div>
            @endforeach
            </div>
        </x-card>
        @endif
    </div>
</div>
@endsection

@push('scripts')
@if(!empty($report->snapshot_data['scurve']['planned']))
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
<script>
(function () {
    const el = document.getElementById('scurve-monthly');
    const sc = @json($report->snapshot_data['scurve']);
    if (!el || !sc || !sc.planned) return;
    new Chart(el, {
        type: 'line',
        data: { datasets: [
            { label: 'Planned %', data: sc.planned, borderColor: '#9CA3AF', borderDash: [6, 3], pointRadius: 0, fill: false, tension: 0.3 },
            { label: 'Actual %', data: sc.actual || [], borderColor: '#00B8A9', backgroundColor: 'rgba(0,184,169,0.08)', pointRadius: 2, fill: true, tension: 0.3 },
        ] },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: { mode: 'index', intersect: false },
            plugins: { legend: { labels: { boxWidth: 12, font: { size: 11 } } } },
            scales: {
                x: { type: 'time', time: { unit: 'month', displayFormats: { month: 'MMM yy' } }, grid: { display: false } },
                y: { min: 0, max: 100, ticks: { callback: v => v + '%' } },
            },
        },
    });
})();
</script>
@endif
@endpush

<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    /* Bundled Poppins for DomPDF (cannot use Google Fonts / woff2 here — needs local TTF) */
    @font-face { font-family: 'Poppins'; font-weight: 400; font-style: normal; src: url('{{ str_replace('\\', '/', public_path('fonts/poppins/Poppins-Regular.ttf')) }}') format('truetype'); }
    @font-face { font-family: 'Poppins'; font-weight: 500; font-style: normal; src: url('{{ str_replace('\\', '/', public_path('fonts/poppins/Poppins-Medium.ttf')) }}') format('truetype'); }
    @font-face { font-family: 'Poppins'; font-weight: 600; font-style: normal; src: url('{{ str_replace('\\', '/', public_path('fonts/poppins/Poppins-SemiBold.ttf')) }}') format('truetype'); }
    @font-face { font-family: 'Poppins'; font-weight: 700; font-style: normal; src: url('{{ str_replace('\\', '/', public_path('fonts/poppins/Poppins-Bold.ttf')) }}') format('truetype'); }

    body { font-family: 'Poppins', 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 11px; color: #1a1a1a; margin: 0; padding: 0; }
    h1 { font-size: 20px; font-weight: 700; color: #003D3A; margin: 0 0 4px 0; }
    h2 { font-size: 13px; font-weight: 600; color: #003D3A; border-bottom: 2px solid #00B8A9; padding-bottom: 4px; margin: 22px 0 10px; }

    .cover { background: #003D3A; color: #fff; padding: 38px 40px 30px; margin: -20px -20px 22px -20px; }
    .cover h1 { color: #fff; font-size: 22px; }
    .cover .tag { font-size: 10px; font-weight: 700; letter-spacing: 2px; text-transform: uppercase; color: rgba(255,255,255,0.5); }
    .cover .sub { color: rgba(255,255,255,0.62); font-size: 12px; margin-top: 6px; }
    .cover .meta { color: rgba(255,255,255,0.82); font-size: 11px; margin-top: 14px; }
    .cover .meta strong { color: #fff; font-weight: 600; }

    /* KPI strip — plain collapsed table (DomPDF-reliable) */
    .kpi { width: 100%; border-collapse: collapse; margin: 10px 0; }
    .kpi td { width: 25%; border: 1px solid #E5E7EB; padding: 11px 13px; vertical-align: top; }
    .kpi-value { font-size: 19px; font-weight: 700; color: #003D3A; }
    .kpi-label { font-size: 9px; font-weight: 600; text-transform: uppercase; letter-spacing: 0.05em; color: #6B7280; margin-top: 3px; }

    table.data { width: 100%; border-collapse: collapse; margin: 8px 0; font-size: 10px; }
    table.data th { background: #F9FAFB; border-bottom: 1px solid #E5E7EB; padding: 6px 8px; text-align: left; font-size: 9px; text-transform: uppercase; letter-spacing: 0.04em; color: #6B7280; }
    table.data td { border-bottom: 1px solid #F3F4F6; padding: 6px 8px; vertical-align: top; }
    table.data tr.lvl1 td { background: #F3FBFA; font-weight: 600; }
    table.data tr.total td { background: #F9FAFB; font-weight: 700; }
    .right { text-align: right; }

    .badge { display: inline-block; padding: 2px 8px; border-radius: 999px; font-size: 8px; font-weight: 700; text-transform: uppercase; }
    .badge-green { background: #D1FAE5; color: #065F46; }
    .badge-amber { background: #FEF3C7; color: #92400E; }
    .badge-red { background: #FEE2E2; color: #991B1B; }
    .badge-blue { background: #DBEAFE; color: #1D4ED8; }
    .badge-teal { background: #CCFBF1; color: #0F766E; }
    .badge-gray { background: #F3F4F6; color: #4B5563; }

    .narrative { background: #F9FAFB; border-radius: 6px; padding: 13px 14px; font-size: 11px; line-height: 1.65; color: #374151; white-space: pre-wrap; }
    .pos { color: #059669; } .neg { color: #DC2626; }
    .muted { color: #9CA3AF; }
    .wk-day { font-size: 8px; font-weight: 700; text-transform: uppercase; color: #6B7280; }
    .footnote { margin-top: 26px; padding-top: 8px; border-top: 1px solid #E5E7EB; font-size: 8.5px; color: #9CA3AF; }
</style>
</head>
<body>
@php
    $snap = $report->snapshot_data ?? [];
    $statusBadge = ['pending' => 'gray', 'submitted' => 'blue', 'approved' => 'green', 'received' => 'teal', 'rejected' => 'red'];
    $dayLabels = ['mon' => 'Mon', 'tue' => 'Tue', 'wed' => 'Wed', 'thu' => 'Thu', 'fri' => 'Fri', 'sat' => 'Sat', 'sun' => 'Sun'];
@endphp

<div class="cover">
    <div class="tag">Monthly Progress Report &middot; Internal</div>
    <h1>{{ $project->name }}</h1>
    <div class="sub">
        {{ $project->code }} &nbsp;·&nbsp; {{ $report->reporting_month->format('F Y') }}
    </div>
    <div class="meta">
        <strong>Project Manager:</strong> {{ optional($project->pm)->name ?? '—' }}
        &nbsp;·&nbsp; <strong>Client:</strong> {{ $project->client ?? '—' }}
        &nbsp;·&nbsp; <strong>Issued:</strong> {{ now()->format('d/m/Y') }}
    </div>
</div>

@php
    $pi = $snap['project_info'] ?? [];
    $fmtD = fn ($d) => $d ? \Carbon\Carbon::parse($d)->format('d/m/Y') : null;
    $piPeriod = ($pi['contract_start'] ?? $pi['planned_start'] ?? null)
        ? ($fmtD($pi['contract_start'] ?? $pi['planned_start']).' – '.($fmtD($pi['contract_end'] ?? $pi['planned_end']) ?? '—'))
        : null;
    $piRows = array_filter([
        'Project Code' => $pi['code'] ?? null,
        'Category' => ! empty($pi['category']) ? ucfirst($pi['category']) : null,
        'Status' => ! empty($pi['status']) ? ucfirst(str_replace('_', ' ', $pi['status'])) : null,
        'Client' => $pi['client'] ?? null,
        'Project Manager' => $pi['pm'] ?? null,
        'Contractor' => $pi['contractor'] ?? null,
        'Procurement' => $pi['procurement_method'] ?? null,
        'Contract Value' => ! empty($pi['contract_value']) ? 'RM '.number_format($pi['contract_value'], 0) : null,
        'Contract Period' => $piPeriod,
        'SST Date' => $fmtD($pi['sst_date'] ?? null),
        'Agreement Date' => $fmtD($pi['company_agreement_date'] ?? null),
        'Bond' => ! empty($pi['bond_value']) ? 'RM '.number_format($pi['bond_value'], 0).(! empty($pi['bond_submission_date']) ? ' · '.$fmtD($pi['bond_submission_date']) : '') : null,
    ], fn ($v) => $v !== null && $v !== '');
@endphp
@if(!empty($piRows))
<h2>1. Project Information</h2>
<table class="data">
<tbody>
@foreach($piRows as $label => $val)
<tr><td class="muted" style="width:32%">{{ $label }}</td><td>{{ $val }}</td></tr>
@endforeach
</tbody>
</table>
@if(!empty($pi['description']))
<div class="narrative" style="margin-top:8px">{{ $pi['description'] }}</div>
@endif
@endif

<h2>2. Overview</h2>
<table class="kpi">
<tr>
    <td><div class="kpi-value">{{ $snap['overall_planned_pct'] ?? 0 }}%</div><div class="kpi-label">Planned Progress</div></td>
    <td><div class="kpi-value">{{ $snap['overall_actual_pct'] ?? 0 }}%</div><div class="kpi-label">Actual Progress</div></td>
    <td><div class="kpi-value">{{ ($snap['overall_variance'] ?? 0) >= 0 ? '+' : '' }}{{ $snap['overall_variance'] ?? 0 }}%</div><div class="kpi-label">Variance</div></td>
    <td><div class="kpi-value"><span class="badge badge-{{ $snap['health'] ?? 'green' }}">{{ strtoupper($snap['health'] ?? 'N/A') }}</span></div><div class="kpi-label">Health</div></td>
</tr>
</table>

@if(!empty($snap['budget']['total']))
<h2>3. Financial Summary</h2>
@php
    $finCur      = $snap['project_info']['currency'] ?? 'MYR';
    $finTotal    = (float) ($snap['budget']['total'] ?? 0);
    $finCumul    = (float) ($snap['budget']['spent_to_date'] ?? 0);
    $finMonth    = (float) ($snap['budget']['spent_this_month'] ?? 0);
    $finVariance = (float) ($snap['budget']['variance'] ?? ($finTotal - $finCumul));
    $finIncome   = (float) ($snap['budget']['income_this_month'] ?? 0);
    $finRemPct   = $finTotal > 0 ? round(($finVariance / $finTotal) * 100, 1) : 0;
    $finRemClass = $finVariance < 0 ? 'neg' : ($finRemPct < 10 ? 'neg' : ($finRemPct < 30 ? '' : 'pos'));
@endphp
<table class="kpi" style="margin-bottom:0">
<tr>
    <td style="width:33.33%"><div class="kpi-value">{{ $finCur }} {{ number_format($finTotal, 0) }}</div><div class="kpi-label">Total Budget</div></td>
    <td style="width:33.33%"><div class="kpi-value">{{ $finCur }} {{ number_format($finCumul, 0) }}</div><div class="kpi-label">Cumulative Spent</div></td>
    <td style="width:33.33%"><div class="kpi-value">{{ $finCur }} {{ number_format($finMonth, 0) }}</div><div class="kpi-label">Spent This Month</div></td>
</tr>
<tr>
    <td style="width:33.33%"><div class="kpi-value {{ $finVariance >= 0 ? 'pos' : 'neg' }}">{{ $finCur }} {{ number_format(abs($finVariance), 0) }}</div><div class="kpi-label">Variance (Budget – Spent)</div></td>
    <td style="width:33.33%"><div class="kpi-value pos">{{ $finCur }} {{ number_format($finIncome, 0) }}</div><div class="kpi-label">Income This Month</div></td>
    <td style="width:33.33%"><div class="kpi-value {{ $finRemClass }}">{{ $finRemPct }}%</div><div class="kpi-label">Budget Remaining</div></td>
</tr>
</table>
@if(!empty($snap['money']['income']))
<div class="muted" style="font-size:10px;margin-top:5px;">Income received to date: <strong style="color:#059669;">{{ $finCur }} {{ number_format($snap['money']['income'], 0) }}</strong></div>
@endif
@endif

@php $aiInsight = $snap['ai_insight'] ?? $report->executive_summary; @endphp
@if(!empty($aiInsight))
<h2>4. AI Insight</h2>
<div class="narrative">{{ $aiInsight }}</div>
@endif

@if(!empty($snap['wbs_tree']))
<h2>5. Plan vs Actual (WBS)</h2>
<table class="data">
<thead><tr><th>WBS</th><th>Task</th><th class="right">Planned %</th><th class="right">Actual %</th><th class="right">Var %</th><th>Start</th><th>End</th></tr></thead>
<tbody>
@foreach($snap['wbs_tree'] as $t)
<tr class="{{ ($t['depth'] ?? 1) == 1 ? 'lvl1' : '' }}">
    <td class="muted">{{ $t['wbs_code'] }}</td>
    <td><span style="padding-left: {{ max(0, ($t['depth'] ?? 1) - 1) * 14 }}px;">{{ $t['name'] }}</span></td>
    <td class="right">{{ $t['planned_pct'] }}%</td>
    <td class="right"><strong>{{ $t['actual_pct'] }}%</strong></td>
    <td class="right {{ ($t['variance'] ?? 0) >= 0 ? 'pos' : 'neg' }}"><strong>{{ ($t['variance'] ?? 0) >= 0 ? '+' : '' }}{{ $t['variance'] }}%</strong></td>
    <td class="muted">{{ $t['planned_start'] ? \Carbon\Carbon::parse($t['planned_start'])->format('d/m/y') : '—' }}</td>
    <td class="muted">{{ $t['planned_end'] ? \Carbon\Carbon::parse($t['planned_end'])->format('d/m/y') : '—' }}</td>
</tr>
@endforeach
</tbody>
</table>
@elseif(!empty($snap['active_tasks']))
<h2>5. Plan vs Actual (Active Tasks)</h2>
<table class="data">
<thead><tr><th>WBS</th><th>Task</th><th class="right">Planned %</th><th class="right">Actual %</th><th class="right">Var %</th></tr></thead>
<tbody>
@foreach($snap['active_tasks'] as $t)
<tr>
    <td class="muted">{{ $t['wbs_code'] }}</td>
    <td>{{ $t['name'] }}</td>
    <td class="right">{{ $t['planned_pct'] }}%</td>
    <td class="right"><strong>{{ $t['actual_pct'] }}%</strong></td>
    <td class="right {{ ($t['variance'] ?? 0) >= 0 ? 'pos' : 'neg' }}"><strong>{{ ($t['variance'] ?? 0) >= 0 ? '+' : '' }}{{ $t['variance'] ?? 0 }}%</strong></td>
</tr>
@endforeach
</tbody>
</table>
@endif

@if(!empty($snap['week_notes']))
<h2>6. Weekly Activity</h2>
@foreach($snap['week_notes'] as $w)
<table class="data" style="margin-bottom:12px;">
<thead><tr><th colspan="2">Week of {{ \Carbon\Carbon::parse($w['week_start'])->format('d/m/Y') }}@if(!empty($w['updated_by']))<span class="muted" style="font-weight:400;text-transform:none;letter-spacing:0;"> — {{ $w['updated_by'] }}</span>@endif</th></tr></thead>
<tbody>
@php $hasRow = false; @endphp
@foreach($dayLabels as $k => $lbl)
    @php $act = data_get($w, 'daily_actuals.'.$k); @endphp
    @if(!empty($act))
    @php $hasRow = true; @endphp
    <tr>
        <td style="width:42px;" class="wk-day">{{ $lbl }}</td>
        <td>{{ $act }}</td>
    </tr>
    @endif
@endforeach
@unless($hasRow)<tr><td colspan="2" class="muted">No activity recorded.</td></tr>@endunless
@php $mo = $w['money_out'] ?? null; @endphp
@if(!empty($mo) && ($mo['total'] ?? 0) > 0)
<tr><td colspan="2" style="background:#fef2f2;color:#b91c1c;font-size:8px;font-weight:700;text-transform:uppercase;letter-spacing:0.04em;padding:4px 8px;">Money Out — RM {{ number_format($mo['total'], 2) }}</td></tr>
@foreach($mo['staff'] ?? [] as $s)
<tr><td colspan="2"><span class="muted">{{ $s['label'] }}</span> &nbsp;·&nbsp; RM {{ number_format($s['amount'], 2) }}</td></tr>
@endforeach
@foreach($mo['expenses'] ?? [] as $e)
<tr><td colspan="2"><span class="muted">{{ $e['label'] }}</span> &nbsp;·&nbsp; RM {{ number_format($e['amount'], 2) }}</td></tr>
@endforeach
@endif
</tbody>
</table>
@endforeach
@endif

@if(!empty($snap['claim_register']))
<h2>7. Claim Register</h2>
<table class="data">
<thead><tr><th>Segment</th><th>Perkara</th><th class="right">%</th><th class="right">Amount (MYR)</th><th>Target</th><th>Status</th><th>Received</th></tr></thead>
<tbody>
@foreach($snap['claim_register'] as $c)
<tr>
    <td class="muted">{{ $c['segment'] ?? '—' }}</td>
    <td>{{ $c['perkara'] }}</td>
    <td class="right">{{ number_format(($c['percentage'] ?? 0) * 100, 1) }}%</td>
    <td class="right">{{ number_format($c['amount'] ?? 0, 0) }}</td>
    <td class="muted">{{ $c['target_date'] ? \Carbon\Carbon::parse($c['target_date'])->format('d/m/y') : '—' }}</td>
    <td><span class="badge badge-{{ $statusBadge[$c['claim_status'] ?? 'pending'] ?? 'gray' }}">{{ $c['claim_status'] ?? 'pending' }}</span></td>
    <td class="muted">{{ $c['received_date'] ? \Carbon\Carbon::parse($c['received_date'])->format('d/m/y') : '—' }}</td>
</tr>
@endforeach
@if(!empty($snap['claim_totals']))
<tr class="total">
    <td colspan="3" class="right">Total</td>
    <td class="right">{{ number_format($snap['claim_totals']['total_amount'] ?? 0, 0) }}</td>
    <td colspan="3" class="muted" style="font-weight:400;">
        Received {{ number_format($snap['claim_totals']['by_status']['received'] ?? 0, 0) }} &middot;
        Approved {{ number_format($snap['claim_totals']['by_status']['approved'] ?? 0, 0) }} &middot;
        Submitted {{ number_format($snap['claim_totals']['by_status']['submitted'] ?? 0, 0) }}
    </td>
</tr>
@endif
</tbody>
</table>
@endif

@if(!empty($snap['issues']))
<h2>8. Risks &amp; Issues</h2>
<table class="data">
<thead><tr><th>Title</th><th>Severity</th><th>Status</th></tr></thead>
<tbody>
@foreach($snap['issues'] as $i)
<tr>
    <td>{{ $i['title'] }}</td>
    <td><span class="badge badge-{{ $i['severity']==='critical'?'red':'amber' }}">{{ $i['severity'] }}</span></td>
    <td>{{ $i['status'] }}</td>
</tr>
@endforeach
</tbody>
</table>
@endif

@if(!empty($snap['next_month_tasks']))
<h2>9. Next Month Plan</h2>
<table class="data">
<thead><tr><th>Task</th><th>Planned Start</th><th>Planned End</th></tr></thead>
<tbody>
@foreach($snap['next_month_tasks'] as $t)
<tr>
    <td>{{ $t['name'] }}</td>
    <td>{{ isset($t['planned_start']) ? \Carbon\Carbon::parse($t['planned_start'])->format('d/m/Y') : '—' }}</td>
    <td>{{ isset($t['planned_end']) ? \Carbon\Carbon::parse($t['planned_end'])->format('d/m/Y') : '—' }}</td>
</tr>
@endforeach
</tbody>
</table>
@endif

<div class="footnote">
    {{ $project->code }} &middot; Monthly Progress Report &middot; {{ $report->reporting_month->format('F Y') }} &middot; Internal use only — not for external distribution
</div>

</body>
</html>

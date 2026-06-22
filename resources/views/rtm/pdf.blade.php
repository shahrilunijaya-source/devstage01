<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: 130px 40px 70px; }
    * { font-family: DejaVu Sans, sans-serif; }
    body { color: #1b2430; font-size: 10px; }
    header { position: fixed; top: -95px; left: 0; right: 0; height: 80px;
        border-bottom: 2px solid #17365D; padding-bottom: 8px; }
    header .title { color: #17365D; font-size: 18px; font-weight: bold; }
    header .meta { color: #6c7d7d; font-size: 10px; margin-top: 3px; }
    footer { position: fixed; bottom: -50px; left: 0; right: 0; height: 30px;
        border-top: 1px solid #cdd5df; color: #6c7d7d; font-size: 9px; padding-top: 6px; }
    .pagenum:before { content: counter(page); }
    table { width: 100%; border-collapse: collapse; }
    th { text-align: left; color: #6c7d7d; font-size: 8px; text-transform: uppercase;
        border-bottom: 1px solid #2F75B5; padding: 5px 5px; }
    td { padding: 5px; border-bottom: 1px solid #e6e9f0; vertical-align: top; }
    .ref { font-family: DejaVu Sans Mono, monospace; color: #00807f; font-weight: bold; }
    .mono { font-family: DejaVu Sans Mono, monospace; font-size: 8px; color: #56627a; }
    .summary { margin-bottom: 14px; }
    .summary .badge { display: inline-block; background: #17365D; color: #fff; padding: 2px 8px;
        border-radius: 3px; font-size: 9px; margin-right: 6px; }
    .st { font-weight: bold; text-transform: uppercase; font-size: 8px; }
    .st-traced { color: #15803d; }
    .st-unsupported, .st-broken { color: #b91c1c; }
    .st-unverified, .st-pending { color: #b45309; }
</style>
</head>
<body>
<header>
    <div class="title">Requirements Traceability Matrix</div>
    <div class="meta">{{ $project->tenant->name }} · {{ $project->name }} ({{ $project->code }})</div>
</header>
<footer>
    URSB Platform · Generated view of the canonical model · {{ $generatedBy }} · {{ $generatedAt->toDayDateTimeString() }}
    · Page <span class="pagenum"></span>
</footer>

<div class="summary">
    <span class="badge">{{ $summary['traced_pct'] }}% fully traced</span>
    {{ $summary['requirements'] }} requirements ·
    {{ $summary['traced'] }} traced ·
    {{ $summary['unsupported'] }} unsupported ·
    {{ $summary['broken'] }} broken ·
    {{ $summary['unverified'] }} unverified ·
    {{ $summary['pending'] }} pending
</div>

<table>
    <thead><tr>
        <th style="width:90px;">Requirement</th>
        <th>Title</th>
        <th style="width:70px;">Status</th>
        <th style="width:110px;">Evidence / findings</th>
        <th style="width:90px;">Design</th>
        <th style="width:38px;">Tests</th>
        <th style="width:38px;">Defects</th>
    </tr></thead>
    <tbody>
    @forelse ($rows as $row)
        <tr>
            <td class="ref">{{ $row['requirement']->ref }}</td>
            <td>{{ $row['requirement']->title }}</td>
            <td><span class="st st-{{ $row['status'] }}">{{ $row['status'] }}</span></td>
            <td class="mono">{{ $row['support']->implode(', ') ?: '—' }}</td>
            <td class="mono">{{ $row['design']->implode(', ') ?: '—' }}</td>
            <td>{{ $row['cases'] }}</td>
            <td>{{ $row['open_defects'] }}</td>
        </tr>
    @empty
        <tr><td colspan="7">No requirements captured yet.</td></tr>
    @endforelse
    </tbody>
</table>
</body>
</html>

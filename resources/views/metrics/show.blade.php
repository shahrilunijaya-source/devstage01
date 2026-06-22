@extends('layouts.ursb')
@section('title', 'Metrics')
@section('content')
    <p class="sub" style="margin-bottom:6px;"><a href="{{ route('portfolio.show', $project) }}">← {{ $project->name }}</a></p>
    <h1 class="page">Quality metrics</h1>
    <p class="sub">Advisory signals over the canonical model (PRD §17) — not judgments.</p>

    <div class="cards">
        <div class="card"><div class="n">{{ $metrics['traceability_completeness'] }}%</div><div class="l">Traceability</div></div>
        <div class="card"><div class="n">{{ $metrics['confirmed_pct'] }}%</div><div class="l">Confirmed</div></div>
        <div class="card"><div class="n">{{ $metrics['requirements'] }}</div><div class="l">Requirements</div></div>
        <div class="card"><div class="n">{{ $metrics['requirements_without_evidence'] }}</div><div class="l">No evidence</div></div>
        <div class="card"><div class="n">{{ $metrics['objects'] }}</div><div class="l">Objects</div></div>
        <div class="card"><div class="n">{{ $metrics['baseline_churn'] }}</div><div class="l">Baseline churn</div></div>
        <div class="card"><div class="n">{{ $metrics['change_requests_applied'] }}/{{ $metrics['change_requests'] }}</div><div class="l">CRs applied</div></div>
    </div>

    <section>
        <h2 class="sec"><span>Red flags ({{ count($metrics['red_flags']) }})</span></h2>
        @forelse ($metrics['red_flags'] as $flag)
            <div class="flash err" style="margin-bottom:10px;">
                <strong>{{ $flag['label'] }}</strong> — {{ $flag['detail'] }}
            </div>
        @empty
            <div class="panel"><span class="sub">No anomalies detected.</span></div>
        @endforelse
    </section>

    <section>
        <h2 class="sec"><span>Session metrics</span></h2>
        <table>
            <thead><tr><th>Session</th><th>Stage</th><th>Items</th><th>Captures</th><th>Confirm</th><th>Correct</th><th>Unresolved</th><th>Signal</th></tr></thead>
            <tbody>
            @forelse ($sessions as $row)
                <tr>
                    <td><a href="{{ route('sessions.show', $row['session']) }}">{{ $row['session']->title }}</a></td>
                    <td>{{ $row['session']->stage->stage->label() }}</td>
                    <td>{{ $row['metrics']['items'] }}</td>
                    <td>{{ $row['metrics']['captures'] }}</td>
                    <td>{{ $row['metrics']['confirmation_rate'] }}%</td>
                    <td>{{ $row['metrics']['correction_rate'] }}%</td>
                    <td>{{ $row['metrics']['unresolved'] }}</td>
                    <td>@if ($row['metrics']['rubber_stamp'])<span class="pill warn">rubber-stamp?</span>@else<span class="pill on">ok</span>@endif</td>
                </tr>
            @empty
                <tr><td colspan="8" class="empty">No sessions.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
@endsection

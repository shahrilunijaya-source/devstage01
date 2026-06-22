@extends('layouts.ursb')
@section('title', 'Portfolio dashboard')
@section('content')
    @php
        $healthLabel = ['on_track' => 'on track', 'at_risk' => 'at risk', 'blocked' => 'blocked', 'complete' => 'complete'];
        $healthPill = ['on_track' => 'on', 'at_risk' => 'warn', 'blocked' => 'warn', 'complete' => 'on'];
        $cellColor = [
            'baselined' => '#1f6f43', 'in_review' => '#7a5b13', 'in_progress' => '#274b86',
            'blocked' => '#7a1f1f', 'not_started' => '#2a2f3a',
        ];
    @endphp

    <div style="display:flex; align-items:center; justify-content:space-between;">
        <h1 class="page">Portfolio dashboard</h1>
        <a class="btn ghost sm" href="{{ route('portfolio.index') }}">Project list</a>
    </div>
    <p class="sub">Live roll-up across every project you can see (PRD §5). Deliverables, lifecycle Gantt, baselines and open risks — all derived from the canonical model.</p>

    @forelse ($cards as $card)
        <section>
            <div style="display:flex; align-items:baseline; justify-content:space-between; gap:12px;">
                <h2 class="sec" style="margin-bottom:6px;">
                    <span>{{ $card['tenant']?->name }} · <a href="{{ route('portfolio.show', $card['project']) }}">{{ $card['project']->name }}</a></span>
                </h2>
                <span class="pill {{ $healthPill[$card['health']] }}">{{ $healthLabel[$card['health']] }}</span>
            </div>

            <div class="row" style="gap:10px; margin-bottom:14px;">
                <div class="panel" style="flex:1;">
                    <div class="sub" style="margin:0;">Progress</div>
                    <div style="font-size:24px; font-weight:700;">{{ $card['progress'] }}%</div>
                    <div style="height:6px; background:#2a2f3a; border-radius:4px; overflow:hidden; margin-top:6px;">
                        <div style="height:100%; width:{{ $card['progress'] }}%; background:#1f6f43;"></div>
                    </div>
                </div>
                <div class="panel" style="flex:1;">
                    <div class="sub" style="margin:0;">Baselines</div>
                    <div style="font-size:24px; font-weight:700;">{{ $card['baselineCount'] }}</div>
                </div>
                <div class="panel" style="flex:1;">
                    <div class="sub" style="margin:0;">Open risks</div>
                    <div style="font-size:24px; font-weight:700; color:{{ $card['openRisks'] > 0 ? '#e0a72f' : 'inherit' }};">{{ $card['openRisks'] }}</div>
                </div>
                <div class="panel" style="flex:1;">
                    <div class="sub" style="margin:0;">Next milestone</div>
                    <div style="font-size:16px; font-weight:600; margin-top:6px;">{{ $card['nextMilestone'] ?? '—' }}</div>
                </div>
            </div>

            @forelse ($card['modules'] as $row)
                <div style="margin-bottom:10px;">
                    <div style="display:flex; align-items:center; justify-content:space-between;">
                        <strong>{{ $row['module']->name }}</strong>
                        <span class="sub" style="margin:0;">{{ $row['done'] }}/{{ $row['total'] }} stages · {{ $row['progress'] }}%</span>
                    </div>
                    @php($byKey = $row['stages']->keyBy('key'))
                    <div style="display:grid; grid-template-columns:repeat({{ count($columns) }}, 1fr); gap:3px; margin-top:5px;">
                        @foreach ($columns as $col)
                            @php($cell = $byKey->get($col['key']))
                            @php($status = $cell['status'] ?? 'not_started')
                            <div title="{{ $col['label'] }} — {{ $status }}"
                                 style="padding:7px 4px; text-align:center; border-radius:4px; font-size:10.5px; letter-spacing:.3px;
                                        background:{{ $cellColor[$status] ?? '#2a2f3a' }};
                                        color:{{ $status === 'not_started' ? '#7c869b' : '#eef2f8' }};
                                        {{ $col['optional'] ? 'opacity:.72; border:1px dashed #4a5163;' : '' }}">
                                {{ $col['label'] }}
                            </div>
                        @endforeach
                    </div>
                </div>
            @empty
                <p class="sub">No modules yet.</p>
            @endforelse
        </section>
    @empty
        <section><p class="empty">No projects visible to you.</p></section>
    @endforelse

    <section>
        <p class="sub" style="margin:0;">
            Legend:
            <span style="color:#3fae6e;">■ baselined</span>
            <span style="color:#c9982a;">■ in review</span>
            <span style="color:#5384d6;">■ in progress</span>
            <span style="color:#c45656;">■ blocked</span>
            <span style="color:#7c869b;">■ not started</span>
            · dashed = optional stage
        </p>
    </section>
@endsection

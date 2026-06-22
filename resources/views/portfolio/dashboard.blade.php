@extends('layouts.ursb')
@section('title', 'Portfolio dashboard')
@section('content')
    @php
        $healthLabel = ['on_track' => 'on track', 'at_risk' => 'at risk', 'blocked' => 'blocked', 'complete' => 'complete'];
        $healthBadge = ['on_track' => 'badge-teal', 'at_risk' => 'badge-flag', 'blocked' => 'badge-danger', 'complete' => 'badge-pine'];
        $cellColor = [
            'baselined' => '#00B8A9', 'in_review' => '#C9982A', 'in_progress' => '#5384D6',
            'blocked' => '#DC2626', 'not_started' => '#E5E7EB',
        ];
        $cellText = [
            'baselined' => '#ffffff', 'in_review' => '#ffffff', 'in_progress' => '#ffffff',
            'blocked' => '#ffffff', 'not_started' => '#9CA3AF',
        ];
    @endphp

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-gray-900 tracking-tight">Portfolio dashboard</h1>
            <p class="text-[13px] text-gray-500 mt-0.5">Live roll-up across every project you can see (PRD §5). Deliverables, lifecycle Gantt, baselines and open risks — all derived from the canonical model.</p>
        </div>
        <div class="flex gap-2">
            <a class="btn-secondary" href="{{ route('portfolio.blocked') }}">What's blocked</a>
            <a class="btn-secondary" href="{{ route('portfolio.index') }}">Project list</a>
        </div>
    </div>

    @if ($summary['projects'] > 0)
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 mb-7">
            <div class="stat-card">
                <div class="stat-value">{{ $summary['projects'] }}</div>
                <div class="stat-label">Projects</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">{{ $summary['avgProgress'] }}%</div>
                <div class="stat-label">Avg progress</div>
            </div>
            <div class="stat-card">
                <div class="stat-value {{ $summary['byHealth']['blocked'] > 0 ? 'text-red-600' : '' }}">{{ $summary['byHealth']['blocked'] }}</div>
                <div class="stat-label">Blocked</div>
            </div>
            <div class="stat-card">
                <div class="stat-value {{ $summary['byHealth']['at_risk'] > 0 ? 'text-flag' : '' }}">{{ $summary['byHealth']['at_risk'] }}</div>
                <div class="stat-label">At risk</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">{{ $summary['openRisks'] }}</div>
                <div class="stat-label">Open risks</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">{{ $summary['baselines'] }}</div>
                <div class="stat-label">Baselines</div>
            </div>
        </div>
    @endif

    @forelse ($cards as $card)
        <div class="card card-pad mb-6">
            <div class="flex items-center justify-between gap-3 mb-4">
                <h2 class="text-[15px] font-semibold text-gray-900">
                    <span class="text-gray-500 font-normal">{{ $card['tenant']?->name }} ·</span>
                    <a class="hover:text-teal transition-colors" href="{{ route('portfolio.show', $card['project']) }}">{{ $card['project']->name }}</a>
                </h2>
                <span class="badge {{ $healthBadge[$card['health']] ?? 'badge-gray' }}">{{ $healthLabel[$card['health']] ?? $card['health'] }}</span>
            </div>

            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-5">
                <div class="stat-card">
                    <div class="stat-value">{{ $card['progress'] }}%</div>
                    <div class="stat-label">Progress</div>
                    <div class="h-1.5 bg-gray-100 rounded-full overflow-hidden mt-3">
                        <div class="h-full bg-teal" style="width:{{ $card['progress'] }}%;"></div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-value">{{ $card['baselineCount'] }}</div>
                    <div class="stat-label">Baselines</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value {{ $card['openRisks'] > 0 ? 'text-flag-500' : '' }}">{{ $card['openRisks'] }}</div>
                    <div class="stat-label">Open risks</div>
                </div>
                <div class="stat-card">
                    <div class="text-[15px] font-semibold text-gray-900 leading-tight">{{ $card['nextMilestone'] ?? '—' }}</div>
                    <div class="stat-label">Next milestone</div>
                </div>
            </div>

            @forelse ($card['modules'] as $row)
                <div class="mb-3">
                    <div class="flex items-center justify-between mb-1.5">
                        <strong class="text-[13px] font-semibold text-gray-800">{{ $row['module']->name }}</strong>
                        <span class="text-[12px] text-gray-500">{{ $row['done'] }}/{{ $row['total'] }} stages · {{ $row['progress'] }}%</span>
                    </div>
                    @php($byKey = $row['stages']->keyBy('key'))
                    <div style="display:grid; grid-template-columns:repeat({{ count($columns) }}, 1fr); gap:3px;">
                        @foreach ($columns as $col)
                            @php($cell = $byKey->get($col['key']))
                            @php($status = $cell['status'] ?? 'not_started')
                            <div title="{{ $col['label'] }} — {{ $status }}"
                                 class="text-center rounded-md text-[10.5px] font-medium tracking-wide py-1.5 px-1"
                                 style="background:{{ $cellColor[$status] ?? '#E5E7EB' }};
                                        color:{{ $cellText[$status] ?? '#9CA3AF' }};
                                        {{ $col['optional'] ? 'opacity:.7; border:1px dashed #9CA3AF;' : '' }}">
                                {{ $col['label'] }}
                            </div>
                        @endforeach
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-400 italic">No modules yet.</p>
            @endforelse
        </div>
    @empty
        <div class="card card-pad mb-6"><p class="text-sm text-gray-400 italic">No projects visible to you.</p></div>
    @endforelse

    <div class="card card-pad">
        <p class="text-[12px] text-gray-500 flex flex-wrap items-center gap-x-4 gap-y-1">
            <span class="section-title">Legend</span>
            <span class="inline-flex items-center gap-1.5"><span class="inline-block w-3 h-3 rounded-sm" style="background:#00B8A9;"></span> baselined</span>
            <span class="inline-flex items-center gap-1.5"><span class="inline-block w-3 h-3 rounded-sm" style="background:#C9982A;"></span> in review</span>
            <span class="inline-flex items-center gap-1.5"><span class="inline-block w-3 h-3 rounded-sm" style="background:#5384D6;"></span> in progress</span>
            <span class="inline-flex items-center gap-1.5"><span class="inline-block w-3 h-3 rounded-sm" style="background:#DC2626;"></span> blocked</span>
            <span class="inline-flex items-center gap-1.5"><span class="inline-block w-3 h-3 rounded-sm border border-gray-300" style="background:#E5E7EB;"></span> not started</span>
            <span class="text-gray-400">· dashed = optional stage</span>
        </p>
    </div>
@endsection

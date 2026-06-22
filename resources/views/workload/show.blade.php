@extends('layouts.app')
@section('page-title', e($load['user']->name) . ' — Workload')
@section('page-sub', 'Per-project heaviness breakdown')

@section('topbar-actions')
    @if(auth()->user()->isAdmin() || auth()->user()->isDirector())
        <a href="{{ route('workload.index') }}" class="btn-secondary">← All workload</a>
    @endif
@endsection

@section('content')
@php
    $rows    = $load['rows'];
    $maxC    = collect($rows)->max('contribution') ?: 1;
    $nProjects = count($rows);
    $nPm     = $load['role_mix']['pm'] ?? 0;
    $nPe     = $load['role_mix']['pe'] ?? 0;

    $bandAccent = [
        'light'      => 'text-gray-600',
        'moderate'   => 'text-teal',
        'heavy'      => 'text-amber-600',
        'overloaded' => 'text-rose-600',
    ][$load['band']] ?? 'text-gray-900';
@endphp

{{-- Header strip --}}
<div class="bg-white border border-gray-200 rounded-2xl p-5 mb-5 shadow-sm">
    <div class="flex items-start justify-between">
        <div>
            <div class="flex items-center gap-2 mb-1">
                <x-workload-band :band="$load['band']" />
                @if($nPm > 0)
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold tracking-wide bg-teal/10 text-teal-700">PM ×{{ $nPm }}</span>
                @endif
                @if($nPe > 0)
                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold tracking-wide bg-pine/10 text-pine">PE ×{{ $nPe }}</span>
                @endif
            </div>
            <h2 class="text-xl font-bold text-gray-900 tracking-tight">{{ $load['user']->name }}</h2>
            <div class="text-[12px] text-gray-500 mt-1">{{ $nProjects }} active {{ Str::plural('project', $nProjects) }}</div>
        </div>
        <div class="text-right flex-shrink-0 ml-4">
            <div class="text-[11px] font-semibold uppercase tracking-wide text-gray-400">Total load</div>
            <div class="text-3xl font-bold {{ $bandAccent }} tracking-tight leading-none mt-1">{{ number_format($load['total'], 1) }}</div>
        </div>
    </div>
</div>

{{-- KPI tiles --}}
<div class="grid grid-cols-4 gap-4 mb-5">
    <div class="stat-card">
        <div class="text-[11px] font-semibold uppercase tracking-wide text-gray-400 mb-2">Projects</div>
        <div class="stat-value text-gray-900">{{ $nProjects }}</div>
        <div class="text-[11px] text-gray-400 mt-1">Active assignments</div>
    </div>
    <div class="stat-card">
        <div class="text-[11px] font-semibold uppercase tracking-wide text-gray-400 mb-2">As PM</div>
        <div class="stat-value text-teal">{{ $nPm }}</div>
        <div class="text-[11px] text-gray-400 mt-1">0.65 share each</div>
    </div>
    <div class="stat-card">
        <div class="text-[11px] font-semibold uppercase tracking-wide text-gray-400 mb-2">As PE</div>
        <div class="stat-value text-pine">{{ $nPe }}</div>
        <div class="text-[11px] text-gray-400 mt-1">0.35 split across PEs</div>
    </div>
    <div class="stat-card">
        <div class="text-[11px] font-semibold uppercase tracking-wide text-gray-400 mb-2">Top driver</div>
        <div class="text-[15px] font-semibold text-gray-900 truncate">{{ isset($rows[0]) ? $rows[0]['project']->name : '—' }}</div>
        <div class="text-[11px] text-gray-400 mt-1">{{ isset($rows[0]) ? number_format($rows[0]['contribution'], 1).' contribution' : 'No assignments' }}</div>
    </div>
</div>

{{-- Project breakdown --}}
<x-card>
    <x-card-header title="Project Breakdown" sub="Contribution = role share × project heaviness" />
    <table class="data-table">
        <thead>
            <tr>
                <th>Project</th>
                <th>Role</th>
                <th class="text-right">Heaviness</th>
                <th class="text-right">Behind</th>
                <th class="text-right">Deadline</th>
                <th class="text-right">Value</th>
                <th class="text-right">Issues</th>
                <th class="text-right">Scope</th>
                <th class="w-1/5">Contribution</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($rows as $row)
                @php $cpct = min(100, round($row['contribution'] / $maxC * 100)); @endphp
                <tr>
                    <td class="font-medium">
                        <a href="{{ route('projects.show', $row['project']) }}"
                           class="text-teal hover:text-teal-700 font-semibold transition-colors">
                            {{ $row['project']->name }}
                        </a>
                        @foreach ($row['flags'] as $flag)
                            <span class="ml-1 inline-flex items-center px-1.5 py-0.5 rounded text-[9px] font-bold uppercase tracking-wide bg-amber-50 text-amber-700">{{ str_replace('_', ' ', $flag) }}</span>
                        @endforeach
                    </td>
                    <td><x-role-badge :role="$row['role']" /></td>
                    <td class="text-right tabular-nums">{{ number_format($row['heaviness'], 0) }}</td>
                    <td class="text-right tabular-nums text-gray-500">{{ number_format($row['behind'], 0) }}</td>
                    <td class="text-right tabular-nums text-gray-500">{{ number_format($row['deadline'], 0) }}</td>
                    <td class="text-right tabular-nums text-gray-500">{{ number_format($row['value'], 0) }}</td>
                    <td class="text-right tabular-nums {{ ($row['open_issues'] ?? 0) > 0 ? 'text-rose-600 font-semibold' : 'text-gray-500' }}">
                        {{ number_format($row['issues'], 0) }}@if(($row['open_issues'] ?? 0) > 0)<span class="text-[10px] font-normal text-gray-400"> · {{ $row['open_issues'] }} open</span>@endif
                    </td>
                    <td class="text-right tabular-nums text-gray-500">
                        {{ number_format($row['scope'], 0) }}@if(($row['leaf_tasks'] ?? 0) > 0)<span class="text-[10px] font-normal text-gray-400"> · {{ $row['leaf_tasks'] }}</span>@endif
                    </td>
                    <td>
                        <div class="flex items-center gap-3">
                            <div class="flex-1 h-2 bg-gray-100 rounded-full overflow-hidden min-w-[60px]">
                                <div class="h-full bg-teal rounded-full" style="width: {{ $cpct }}%"></div>
                            </div>
                            <span class="text-[13px] font-bold text-gray-800 tabular-nums w-10 text-right">{{ number_format($row['contribution'], 1) }}</span>
                        </div>
                    </td>
                </tr>
            @empty
                <tr><td colspan="9" class="text-center py-10 text-gray-400">No active assignments.</td></tr>
            @endforelse
        </tbody>
    </table>
    <p class="px-5 py-3 text-[11px] text-gray-400 border-t border-gray-100">
        Behind, Deadline, Value, Issues and Scope are 0&ndash;100 factor scores.
        Heaviness = 0.30·Behind + 0.25·Deadline + 0.20·Value + 0.15·Issues + 0.10·Scope.
        Contribution = role share × Heaviness (PM 0.65, PE 0.35 split across PEs).
    </p>
</x-card>
@endsection

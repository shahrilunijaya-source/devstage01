@extends('layouts.app')
@section('page-title', 'Workload Heatmap')
@section('page-sub', 'PM/PE heaviness across all active projects')

@section('content')
@php
    $people      = $heatmap->count();
    $overloaded  = $heatmap->where('band', 'overloaded')->count();
    $heavy       = $heatmap->where('band', 'heavy')->count();
    $maxLoad     = (float) ($heatmap->max('total') ?: 1);
    $avgLoad     = $people ? $heatmap->avg('total') : 0;
    $heaviest    = $heatmap->first();

    // band → bar + accent tones
    $barTone = [
        'light'      => 'bg-gray-300',
        'moderate'   => 'bg-teal',
        'heavy'      => 'bg-amber-400',
        'overloaded' => 'bg-rose-500',
    ];
@endphp

{{-- KPI tiles --}}
<div class="grid grid-cols-4 gap-4 mb-5">
    <div class="stat-card">
        <div class="text-[11px] font-semibold uppercase tracking-wide text-gray-400 mb-2">People tracked</div>
        <div class="stat-value text-gray-900">{{ $people }}</div>
        <div class="text-[11px] text-gray-400 mt-1">Active PM/PE assignees</div>
    </div>
    <div class="stat-card">
        <div class="text-[11px] font-semibold uppercase tracking-wide text-gray-400 mb-2">Overloaded</div>
        <div class="stat-value {{ $overloaded > 0 ? 'text-rose-600' : 'text-gray-900' }}">{{ $overloaded }}</div>
        <div class="text-[11px] text-gray-400 mt-1">Load &ge; 130</div>
    </div>
    <div class="stat-card">
        <div class="text-[11px] font-semibold uppercase tracking-wide text-gray-400 mb-2">Heavy</div>
        <div class="stat-value {{ $heavy > 0 ? 'text-amber-600' : 'text-gray-900' }}">{{ $heavy }}</div>
        <div class="text-[11px] text-gray-400 mt-1">Load 80&ndash;129</div>
    </div>
    <div class="stat-card">
        <div class="text-[11px] font-semibold uppercase tracking-wide text-gray-400 mb-2">Heaviest</div>
        <div class="stat-value text-teal">{{ number_format($maxLoad, 0) }}</div>
        <div class="text-[11px] text-gray-400 mt-1 truncate">
            {{ $heaviest ? $heaviest['user']->name : '—' }} · avg {{ number_format($avgLoad, 0) }}
        </div>
    </div>
</div>

{{-- Heatmap table --}}
<x-card>
    <x-card-header title="Workload Heatmap" sub="Heaviest first — bar scaled to the busiest person">
        <div class="hidden md:flex items-center gap-3 text-[10px] font-medium uppercase tracking-wide text-gray-400">
            <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm bg-gray-300"></span>Light</span>
            <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm bg-teal"></span>Moderate</span>
            <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm bg-amber-400"></span>Heavy</span>
            <span class="flex items-center gap-1"><span class="w-2.5 h-2.5 rounded-sm bg-rose-500"></span>Overloaded</span>
        </div>
    </x-card-header>

    <table class="data-table">
        <thead>
            <tr>
                <th>Person</th>
                <th>Role mix</th>
                <th class="text-right">Projects</th>
                <th class="w-2/5">Load</th>
                <th>Band</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($heatmap as $row)
                @php $pct = min(100, round($row['total'] / $maxLoad * 100)); @endphp
                <tr>
                    <td class="font-medium">
                        <a href="{{ route('workload.show', $row['user']) }}"
                           class="text-teal hover:text-teal-700 font-semibold transition-colors">
                            {{ $row['user']->name }}
                        </a>
                    </td>
                    <td>
                        <div class="flex items-center gap-1">
                            @if($row['role_mix']['pm'] > 0)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold tracking-wide bg-teal/10 text-teal-700">PM ×{{ $row['role_mix']['pm'] }}</span>
                            @endif
                            @if($row['role_mix']['pe'] > 0)
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold tracking-wide bg-pine/10 text-pine">PE ×{{ $row['role_mix']['pe'] }}</span>
                            @endif
                        </div>
                    </td>
                    <td class="text-right text-gray-500">{{ count($row['rows']) }}</td>
                    <td>
                        <div class="flex items-center gap-3">
                            <div class="flex-1 h-2 bg-gray-100 rounded-full overflow-hidden min-w-[80px]">
                                <div class="h-full {{ $barTone[$row['band']] ?? 'bg-gray-300' }} rounded-full transition-all"
                                     style="width: {{ $pct }}%"></div>
                            </div>
                            <span class="text-[13px] font-bold text-gray-800 tabular-nums w-12 text-right">{{ number_format($row['total'], 1) }}</span>
                        </div>
                    </td>
                    <td>
                        <x-workload-band :band="$row['band']" />
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-center py-10 text-gray-400">No active PM/PE assignments.</td></tr>
            @endforelse
        </tbody>
    </table>
</x-card>
@endsection

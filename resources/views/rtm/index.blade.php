@extends('layouts.ursb')
@section('title', $project->name.' — traceability matrix')
@section('content')
    <div class="mb-3">
        <a href="{{ route('portfolio.show', $project) }}" class="text-[13px] text-gray-500 hover:text-teal transition-colors">← {{ $project->name }}</a>
    </div>

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-gray-900 tracking-tight">Requirements traceability matrix</h1>
            <p class="text-[13px] text-gray-500 mt-0.5">End-to-end chain for every requirement — evidence that supports it, design that satisfies it, tests that prove it (PRD §17).</p>
        </div>
        <div class="flex gap-2">
            <a class="btn-secondary" href="{{ route('rtm.csv', $project) }}">Export CSV</a>
            <a class="btn-secondary" href="{{ route('rtm.pdf', $project) }}">Export PDF</a>
        </div>
    </div>

    @php
        $statusBadge = fn (string $s) => match ($s) {
            'traced' => 'badge-pine',
            'unsupported', 'broken' => 'badge-danger',
            'unverified', 'pending' => 'badge-flag',
            default => 'badge-gray',
        };
    @endphp

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mb-5">
        <div class="stat-card flex flex-col justify-center">
            <div class="stat-value text-4xl">{{ $summary['traced_pct'] }}%</div>
            <div class="stat-label">Fully traced</div>
            <x-meter :value="$summary['traced_pct']" color="pine" height="h-2" class="mt-3" />
        </div>
        <div class="card card-pad lg:col-span-2 flex flex-col justify-center">
            <div class="section-title mb-3">Requirement traceability mix · {{ $summary['requirements'] }} total</div>
            <x-stack-bar :segments="[
                ['label' => 'Traced', 'count' => $summary['traced'], 'fill' => '#003d3a'],
                ['label' => 'Pending', 'count' => $summary['pending'], 'fill' => '#00b8a9'],
                ['label' => 'Unverified', 'count' => $summary['unverified'], 'fill' => '#f59e0b'],
                ['label' => 'Broken', 'count' => $summary['broken'], 'fill' => '#dc2626'],
                ['label' => 'Unsupported', 'count' => $summary['unsupported'], 'fill' => '#9ca3af'],
            ]" />
        </div>
    </div>

    @if ($rows->isEmpty())
        <div class="card card-pad"><p class="text-sm text-gray-400 italic">No requirements captured yet. Requirements are drafted during sessions.</p></div>
    @else
        <div class="card overflow-hidden">
            <table class="data-table">
                <thead>
                    <tr>
                        <th class="w-[110px]">Requirement</th>
                        <th>Title</th>
                        <th class="w-[100px]">Status</th>
                        <th class="w-[150px]">Evidence / findings</th>
                        <th class="w-[120px]">Design</th>
                        <th class="w-[70px]">Tests</th>
                        <th class="w-[80px]">Defects</th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($rows as $row)
                    <tr>
                        <td><a href="{{ route('objects.show', $row['requirement']) }}" class="font-mono text-teal hover:text-teal-700">{{ $row['requirement']->ref }}</a></td>
                        <td class="text-gray-900 text-[13px]">{{ $row['requirement']->title }}</td>
                        <td><span class="badge {{ $statusBadge($row['status']) }}">{{ $row['status'] }}</span></td>
                        <td class="font-mono text-[11px] text-gray-500">{{ $row['support']->implode(', ') ?: '—' }}</td>
                        <td class="font-mono text-[11px] text-gray-500">{{ $row['design']->implode(', ') ?: '—' }}</td>
                        <td class="text-[13px] text-gray-700">{{ $row['cases'] }}</td>
                        <td class="text-[13px] {{ $row['open_defects'] > 0 ? 'text-red-600 font-semibold' : 'text-gray-400' }}">{{ $row['open_defects'] }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection

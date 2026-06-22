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

    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-5">
        <div class="stat-card"><div class="stat-value">{{ $summary['traced_pct'] }}%</div><div class="stat-label">Fully traced</div></div>
        <div class="stat-card"><div class="stat-value">{{ $summary['traced'] }}</div><div class="stat-label">Traced</div></div>
        <div class="stat-card"><div class="stat-value">{{ $summary['unsupported'] }}</div><div class="stat-label">Unsupported</div></div>
        <div class="stat-card"><div class="stat-value">{{ $summary['broken'] }}</div><div class="stat-label">Broken</div></div>
        <div class="stat-card"><div class="stat-value">{{ $summary['unverified'] }}</div><div class="stat-label">Unverified</div></div>
        <div class="stat-card"><div class="stat-value">{{ $summary['pending'] }}</div><div class="stat-label">Pending</div></div>
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

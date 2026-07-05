@extends('layouts.ursb')
@section('title', $project->name.' — risks')
@section('content')
    <div class="mb-3">
        <a href="{{ route('portfolio.show', $project) }}" class="text-[13px] text-gray-500 hover:text-teal transition-colors">← {{ $project->name }}</a>
    </div>

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-gray-900 tracking-tight">Risk register</h1>
            <p class="text-[13px] text-gray-500 mt-0.5">Every risk captured across the project, worst impact first (PRD §17). Each risk is a traceable object.</p>
        </div>
        <div class="flex gap-2">
            <a class="btn-secondary" href="{{ route('metrics.risks.csv', $project) }}">Export CSV</a>
            <a class="btn-secondary" href="{{ route('metrics.decisions', $project) }}">Decisions</a>
        </div>
    </div>

    @php
        $impactBadge = fn (?string $i) => match ($i) {
            'critical', 'high' => 'badge-danger',
            'medium' => 'badge-flag',
            default => 'badge-gray',
        };
    @endphp

    @if ($risks->isEmpty())
        <div class="card card-pad"><p class="text-sm text-gray-400 italic">No risks recorded yet. Risks are captured during evidence intake and sessions.</p></div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-5">
            @foreach (['critical' => 'Critical', 'high' => 'High', 'medium' => 'Medium'] as $key => $label)
                <div class="stat-card">
                    <div class="stat-value">{{ $risks->where('impact', $key)->count() }}</div>
                    <div class="stat-label">{{ $label }} impact</div>
                </div>
            @endforeach
        </div>

        <div class="card overflow-hidden">
            <table class="data-table">
                <thead>
                    <tr>
                        <th class="w-[110px]">Ref</th>
                        <th class="w-[90px]">Impact</th>
                        <th>Risk</th>
                        <th class="w-[150px]">Status</th>
                        <th class="w-[130px]">Source</th>
                        <th class="w-[140px]">Owner</th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($risks as $r)
                    <tr>
                        <td><a href="{{ route('objects.show', $r) }}" class="font-mono text-teal hover:text-teal-700">{{ $r->ref }}</a></td>
                        <td><span class="badge {{ $impactBadge($r->impact) }}">{{ $r->impact ?? '—' }}</span></td>
                        <td class="text-gray-900">{{ $r->title }}@if($r->body)<div class="text-[13px] text-gray-500 mt-1">{{ $r->body }}</div>@endif</td>
                        <td class="text-gray-500 text-[13px]">{{ $r->status?->value ?? '—' }}</td>
                        <td>
                            @if ($r->sourceObject)
                                <a href="{{ route('objects.show', $r->sourceObject) }}" class="font-mono text-[12px] text-teal hover:text-teal-700">{{ $r->sourceObject->ref }}</a>
                            @else
                                <span class="text-gray-400">{{ $r->source ?? '—' }}</span>
                            @endif
                        </td>
                        <td class="text-gray-500">{{ $r->owner?->name ?? '—' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection

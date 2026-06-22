@extends('layouts.ursb')
@section('title', $project->name.' — issues')
@section('content')
    <div class="mb-3">
        <a href="{{ route('portfolio.show', $project) }}" class="text-[13px] text-gray-500 hover:text-teal transition-colors">← {{ $project->name }}</a>
    </div>

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-gray-900 tracking-tight">Issue log</h1>
            <p class="text-[13px] text-gray-500 mt-0.5">Operational blockers, actions and concerns for this project (PRD §17). Each issue is a traceable object.</p>
        </div>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-lg bg-pine/10 text-pine text-[13px] px-4 py-2.5">{{ session('status') }}</div>
    @endif

    @php
        $sevBadge = fn (?string $s) => match ($s) {
            'high' => 'badge-danger',
            'medium' => 'badge-flag',
            default => 'badge-gray',
        };
    @endphp

    <div class="grid grid-cols-3 gap-3 mb-5">
        <div class="stat-card"><div class="stat-value">{{ $summary['open'] }}</div><div class="stat-label">Open</div></div>
        <div class="stat-card"><div class="stat-value">{{ $summary['high'] }}</div><div class="stat-label">High severity</div></div>
        <div class="stat-card"><div class="stat-value">{{ $summary['resolved'] }}</div><div class="stat-label">Resolved</div></div>
    </div>

    @if ($canEdit)
        <div class="card card-pad mb-5">
            <div class="section-title mb-2">Raise an issue</div>
            <form method="POST" action="{{ route('issues.store', $project) }}" class="space-y-2">
                @csrf
                <div class="flex gap-2">
                    <select name="severity" class="form-select text-[13px] w-[140px]">
                        @foreach ($severities as $s)
                            <option value="{{ $s }}">{{ ucfirst($s) }}</option>
                        @endforeach
                    </select>
                    <input type="text" name="title" placeholder="Issue title" required maxlength="255" class="form-input text-[13px] flex-1">
                </div>
                <textarea name="body" placeholder="Detail (optional)" maxlength="10000" rows="2" class="form-input text-[13px] w-full"></textarea>
                <button class="btn-primary text-[13px]">Raise issue</button>
            </form>
        </div>
    @endif

    <div class="card overflow-hidden mb-5">
        <div class="card-pad border-b border-gray-100"><span class="section-title">Open issues ({{ $open->count() }})</span></div>
        @if ($open->isEmpty())
            <div class="card-pad"><p class="text-sm text-gray-400 italic">No open issues.</p></div>
        @else
            <table class="data-table">
                <thead><tr><th class="w-[110px]">Ref</th><th class="w-[90px]">Severity</th><th>Issue</th><th class="w-[140px]">Owner</th>@if ($canEdit)<th class="w-[220px]">Resolve</th>@endif</tr></thead>
                <tbody>
                @foreach ($open as $issue)
                    @php $sev = $issue->getAttribute('attributes')['severity'] ?? 'low'; @endphp
                    <tr>
                        <td><a href="{{ route('objects.show', $issue) }}" class="font-mono text-[12px] text-teal hover:text-teal-700">{{ $issue->ref }}</a></td>
                        <td><span class="badge {{ $sevBadge($sev) }}">{{ $sev }}</span></td>
                        <td class="text-gray-900 text-[13px]">{{ $issue->title }}@if($issue->body)<div class="text-[12px] text-gray-500 mt-0.5">{{ $issue->body }}</div>@endif</td>
                        <td class="text-gray-500 text-[13px]">{{ $issue->owner?->name ?? '—' }}</td>
                        @if ($canEdit)
                            <td>
                                <form method="POST" action="{{ route('issues.resolve', $issue) }}" class="flex items-center gap-1.5">
                                    @csrf
                                    <input type="text" name="note" placeholder="resolution" class="form-input text-[12px] py-1 flex-1" maxlength="2000">
                                    <button class="btn-pine text-[12px] py-1 px-2.5">Resolve</button>
                                </form>
                            </td>
                        @endif
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>

    @if ($resolved->isNotEmpty())
        <details class="card overflow-hidden">
            <summary class="card-pad cursor-pointer select-none text-[13px] text-gray-600">Resolved issues ({{ $resolved->count() }})</summary>
            <table class="data-table">
                <tbody>
                @foreach ($resolved as $issue)
                    <tr>
                        <td class="w-[110px]"><a href="{{ route('objects.show', $issue) }}" class="font-mono text-[12px] text-gray-400 hover:text-teal">{{ $issue->ref }}</a></td>
                        <td class="text-gray-500 text-[13px] line-through">{{ $issue->title }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </details>
    @endif
@endsection

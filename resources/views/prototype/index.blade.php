@extends('layouts.ursb')
@section('title', $project->name.' — prototype')
@section('content')
    <div class="mb-3">
        <a href="{{ route('portfolio.show', $project) }}" class="text-[13px] text-gray-500 hover:text-teal transition-colors">← {{ $project->name }}</a>
    </div>

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-gray-900 tracking-tight">Prototype register</h1>
            <p class="text-[13px] text-gray-500 mt-0.5">Prototype elements implementing each requirement, tracked from planned to demoed (PRD §12). Each element is a traceable object.</p>
        </div>
        <a class="btn-secondary" href="{{ route('rtm.index', $project) }}">Traceability</a>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-lg bg-pine/10 text-pine text-[13px] px-4 py-2.5">{{ session('status') }}</div>
    @endif

    @php
        $stateBadge = fn (string $s) => match ($s) {
            'demoed' => 'badge-pine',
            'built' => 'badge-teal',
            'in_progress' => 'badge-flag',
            'planned' => 'badge-gray',
            default => 'badge-gray',
        };
    @endphp

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
        <div class="stat-card"><div class="stat-value">{{ $summary['demoed_pct'] }}%</div><div class="stat-label">Demoed</div></div>
        <div class="stat-card"><div class="stat-value">{{ $summary['in_progress'] }}</div><div class="stat-label">In progress</div></div>
        <div class="stat-card"><div class="stat-value">{{ $summary['not_started'] }}</div><div class="stat-label">Not started</div></div>
        <div class="stat-card"><div class="stat-value">{{ $summary['elements'] }}</div><div class="stat-label">Elements</div></div>
    </div>

    @if ($rows->isEmpty())
        <div class="card card-pad"><p class="text-sm text-gray-400 italic">No requirements captured yet. Requirements are drafted during sessions, then prototyped here.</p></div>
    @else
        <div class="space-y-3">
            @foreach ($rows as $row)
                @php $req = $row['requirement']; @endphp
                <div class="card card-pad">
                    <div class="flex items-center gap-2">
                        <a href="{{ route('objects.show', $req) }}" class="font-mono text-[13px] text-teal hover:text-teal-700">{{ $req->ref }}</a>
                        <span class="badge {{ $stateBadge($row['status']) }}">{{ $row['status'] === 'none' ? 'not started' : $row['status'] }}</span>
                    </div>
                    <div class="text-gray-900 text-sm mt-1">{{ $req->title }}</div>

                    @if ($row['elements']->isNotEmpty())
                        <table class="data-table mt-3">
                            <thead>
                                <tr>
                                    <th class="w-[110px]">Ref</th>
                                    <th>Element</th>
                                    <th class="w-[100px]">State</th>
                                    @if ($canEdit)<th class="w-[200px]">Advance</th>@endif
                                </tr>
                            </thead>
                            <tbody>
                            @foreach ($row['elements'] as $el)
                                @php $state = $el->getAttribute('attributes')['state'] ?? 'planned'; @endphp
                                <tr>
                                    <td><a href="{{ route('objects.show', $el) }}" class="font-mono text-[12px] text-teal hover:text-teal-700">{{ $el->ref }}</a></td>
                                    <td class="text-gray-900 text-[13px]">{{ $el->title }}</td>
                                    <td><span class="badge {{ $stateBadge($state) }}">{{ $state }}</span></td>
                                    @if ($canEdit)
                                        <td>
                                            <form method="POST" action="{{ route('prototype.state', $el) }}" class="flex items-center gap-1.5">
                                                @csrf
                                                <select name="state" class="form-select text-[12px] py-1 w-[120px]">
                                                    @foreach ($states as $s)
                                                        <option value="{{ $s }}" @selected($s === $state)>{{ str_replace('_', ' ', $s) }}</option>
                                                    @endforeach
                                                </select>
                                                <button class="btn-pine text-[12px] py-1 px-2.5">Set</button>
                                            </form>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    @endif

                    @if ($canEdit)
                        <details class="mt-3">
                            <summary class="text-[12px] text-teal hover:text-teal-700 cursor-pointer select-none">+ Add prototype element</summary>
                            <form method="POST" action="{{ route('prototype.store', $req) }}" class="mt-2 space-y-2">
                                @csrf
                                <input type="text" name="title" placeholder="Element title" required maxlength="255" class="form-input text-[13px] w-full">
                                <textarea name="body" placeholder="Detail (optional)" maxlength="10000" rows="2" class="form-input text-[13px] w-full"></textarea>
                                <button class="btn-primary text-[13px]">Add element</button>
                            </form>
                        </details>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
@endsection

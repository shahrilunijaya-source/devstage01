@extends('layouts.ursb')
@section('title', $project->name.' — design')
@section('content')
    <div class="mb-3">
        <a href="{{ route('portfolio.show', $project) }}" class="text-[13px] text-gray-500 hover:text-teal transition-colors">← {{ $project->name }}</a>
    </div>

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-gray-900 tracking-tight">Design register</h1>
            <p class="text-[13px] text-gray-500 mt-0.5">How each requirement is satisfied by design — decisions, components, interfaces, database objects (PRD §12). Each design object satisfies a requirement and traces through the matrix.</p>
        </div>
        <a class="btn-secondary" href="{{ route('rtm.index', $project) }}">Traceability</a>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-lg bg-pine/10 text-pine text-[13px] px-4 py-2.5">{{ session('status') }}</div>
    @endif

    @php
        $typeLabel = fn (\App\Enums\ObjectType $t) => $t->label();
    @endphp

    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-5">
        <div class="stat-card"><div class="stat-value">{{ $summary['designed_pct'] }}%</div><div class="stat-label">Designed</div><x-meter :value="$summary['designed_pct']" class="mt-3" /></div>
        <div class="stat-card"><div class="stat-value">{{ $summary['undesigned'] }}</div><div class="stat-label">Undesigned</div></div>
        <div class="stat-card"><div class="stat-value">{{ $summary['requirements'] }}</div><div class="stat-label">Requirements</div></div>
        <div class="stat-card"><div class="stat-value">{{ $summary['designs'] }}</div><div class="stat-label">Design objects</div></div>
    </div>

    @if ($rows->isEmpty())
        <div class="card card-pad"><p class="text-sm text-gray-400 italic">No requirements captured yet. Requirements are drafted during sessions, then designed here.</p></div>
    @else
        <div class="space-y-3">
            @foreach ($rows as $row)
                @php $req = $row['requirement']; @endphp
                <div class="card card-pad">
                    <div class="flex items-center gap-2">
                        <a href="{{ route('objects.show', $req) }}" class="font-mono text-[13px] text-teal hover:text-teal-700">{{ $req->ref }}</a>
                        <span class="badge {{ $row['designed'] ? 'badge-pine' : 'badge-gray' }}">{{ $row['designed'] ? 'designed' : 'undesigned' }}</span>
                    </div>
                    <div class="text-gray-900 text-sm mt-1">{{ $req->title }}</div>

                    @if ($row['designs']->isNotEmpty())
                        <table class="data-table mt-3">
                            <thead><tr><th class="w-[110px]">Ref</th><th class="w-[150px]">Type</th><th>Design</th></tr></thead>
                            <tbody>
                            @foreach ($row['designs'] as $d)
                                <tr>
                                    <td><a href="{{ route('objects.show', $d) }}" class="font-mono text-[12px] text-teal hover:text-teal-700">{{ $d->ref }}</a></td>
                                    <td><span class="badge badge-teal">{{ $typeLabel($d->type) }}</span></td>
                                    <td class="text-gray-900 text-[13px]">{{ $d->title }}@if($d->body)<div class="text-[12px] text-gray-500 mt-0.5">{{ $d->body }}</div>@endif</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    @endif

                    @if ($canEdit)
                        <details class="mt-3">
                            <summary class="text-[12px] text-teal hover:text-teal-700 cursor-pointer select-none">+ Add design</summary>
                            <form method="POST" action="{{ route('design.store', $req) }}" class="mt-2 space-y-2">
                                @csrf
                                <div class="flex gap-2">
                                    <select name="type" class="form-select text-[13px] w-[180px]">
                                        @foreach ($designTypes as $t)
                                            <option value="{{ $t }}">{{ ucwords(str_replace('_', ' ', $t)) }}</option>
                                        @endforeach
                                    </select>
                                    <input type="text" name="title" placeholder="Design title" required maxlength="255" class="form-input text-[13px] flex-1">
                                </div>
                                <textarea name="body" placeholder="Detail (optional)" maxlength="10000" rows="2" class="form-input text-[13px] w-full"></textarea>
                                <button class="btn-primary text-[13px]">Add design</button>
                            </form>
                        </details>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
@endsection

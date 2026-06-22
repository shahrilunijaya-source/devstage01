@extends('layouts.ursb')
@section('title', $project->name.' — decisions')
@section('content')
    <div class="mb-3">
        <a href="{{ route('portfolio.show', $project) }}" class="text-[13px] text-gray-500 hover:text-teal transition-colors">← {{ $project->name }}</a>
    </div>

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-gray-900 tracking-tight">Decision register</h1>
            <p class="text-[13px] text-gray-500 mt-0.5">Every judgement call recorded during sessions (PRD §9). Each decision resolves a specific item and is traceable.</p>
        </div>
        <a class="btn-secondary" href="{{ route('metrics.activity', $project) }}">Activity</a>
    </div>

    @if ($decisions->isEmpty())
        <div class="card card-pad"><p class="text-sm text-gray-400 italic">No decisions recorded yet. They are logged when a session item is resolved via “Decide”.</p></div>
    @else
        <div class="card overflow-hidden">
            <table class="data-table">
                <thead>
                    <tr>
                        <th class="w-[110px]">Ref</th>
                        <th>Decision</th>
                        <th class="w-[130px]">Resolves</th>
                        <th class="w-[140px]">By</th>
                        <th class="w-[160px]">When</th>
                    </tr>
                </thead>
                <tbody>
                @foreach ($decisions as $d)
                    <tr>
                        <td><a href="{{ route('objects.show', $d) }}" class="font-mono text-teal hover:text-teal-700">{{ $d->ref }}</a></td>
                        <td class="text-gray-900">{{ $d->title }}@if($d->body)<div class="text-[13px] text-gray-500 mt-1">{{ $d->body }}</div>@endif</td>
                        <td>
                            @if ($d->sourceObject)
                                <a href="{{ route('objects.show', $d->sourceObject) }}" class="font-mono text-[12px] text-teal hover:text-teal-700">{{ $d->sourceObject->ref }}</a>
                            @else
                                <span class="text-gray-400">{{ $d->source ?? '—' }}</span>
                            @endif
                        </td>
                        <td class="text-gray-500">{{ $d->owner?->name ?? '—' }}</td>
                        <td class="text-gray-500">{{ optional($d->created_at)->toDayDateTimeString() }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection

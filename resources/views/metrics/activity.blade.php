@extends('layouts.ursb')
@section('title', $project->name.' — activity')
@section('content')
    @php
        $badge = [
            'created' => 'badge-teal', 'updated' => 'badge-gray', 'baseline' => 'badge-pine',
        ];
        $dot = fn (string $kind) => match (true) {
            $kind === 'created' => 'bg-teal',
            $kind === 'baseline' => 'bg-pine',
            str_starts_with($kind, 'change') => 'bg-flag-500',
            default => 'bg-gray-300',
        };
    @endphp

    <div class="mb-3">
        <a href="{{ route('portfolio.show', $project) }}" class="text-[13px] text-gray-500 hover:text-teal transition-colors">← {{ $project->name }}</a>
    </div>

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-gray-900 tracking-tight">Activity</h1>
            <p class="text-[13px] text-gray-500 mt-0.5">Chronological audit trail — every object change, baseline and change request you can see (PRD §12).</p>
        </div>
        <a class="btn-secondary" href="{{ route('metrics.show', $project) }}">Metrics</a>
    </div>

    @if ($events->isEmpty())
        <div class="card card-pad"><p class="text-sm text-gray-400 italic">No activity yet.</p></div>
    @else
        <div class="card card-pad">
            <ol class="relative border-l border-gray-200 ml-2">
                @foreach ($events as $e)
                    <li class="mb-5 ml-5 last:mb-0">
                        <span class="absolute -left-[5px] w-2.5 h-2.5 rounded-full {{ $dot($e['kind']) }} ring-4 ring-white"></span>
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="badge {{ $badge[$e['kind']] ?? 'badge-gray' }}">{{ $e['kind'] }}</span>
                            @if ($e['link'])
                                <a href="{{ $e['link'] }}" class="font-mono text-[13px] text-teal hover:text-teal-700">{{ $e['ref'] }}</a>
                            @else
                                <span class="font-mono text-[13px] text-gray-700">{{ $e['ref'] }}</span>
                            @endif
                            <span class="text-[12px] text-gray-400 ml-auto">{{ optional($e['at'])->diffForHumans() }}</span>
                        </div>
                        <div class="text-[13px] text-gray-700 mt-1">{{ $e['summary'] }}</div>
                        <div class="text-[12px] text-gray-400 mt-0.5">
                            {{ $e['actor'] ?? 'system' }} · {{ optional($e['at'])->toDayDateTimeString() }}
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>
    @endif
@endsection

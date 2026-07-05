@extends('layouts.ursb')
@section('title', "What's blocked")
@section('content')
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-gray-900 tracking-tight">What's blocked</h1>
            <p class="text-[13px] text-gray-500 mt-0.5">Projects with a blocked stage or blocked health, across everything you can see.</p>
        </div>
        <a class="btn-secondary" href="{{ route('portfolio.dashboard') }}">Dashboard</a>
    </div>

    @forelse ($blocked as $card)
        <div class="card card-pad mb-5">
            <div class="flex items-center justify-between gap-3 mb-3">
                <h2 class="text-[15px] font-semibold text-gray-900">
                    <span class="text-gray-500 font-normal">{{ $card['tenant']?->name }} ·</span>
                    <a class="hover:text-teal transition-colors" href="{{ route('portfolio.show', $card['project']) }}">{{ $card['project']->name }}</a>
                </h2>
                <span class="badge badge-danger">{{ $card['blockedStages'] }} blocked stage{{ $card['blockedStages'] === 1 ? '' : 's' }}</span>
            </div>

            @php
                $blockedRows = collect($card['modules'])->flatMap(function ($m) {
                    return collect($m['stages'])
                        ->where('status', 'blocked')
                        ->map(fn ($s) => ['module' => $m['module']->name, 'stage' => $s['label']]);
                });
            @endphp

            @if ($blockedRows->isEmpty())
                <p class="text-[13px] text-gray-500">Health is blocked — review the project for the cause.</p>
            @else
                <table class="data-table">
                    <thead><tr><th class="w-[200px]">Module</th><th>Blocked stage</th></tr></thead>
                    <tbody>
                    @foreach ($blockedRows as $row)
                        <tr>
                            <td class="text-gray-500 text-[13px]">{{ $row['module'] }}</td>
                            <td class="text-gray-900">{{ $row['stage'] }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    @empty
        <div class="card card-pad text-center py-12">
            <p class="text-[15px] text-gray-700 font-medium">Nothing is blocked.</p>
            <p class="text-[13px] text-gray-400 mt-1">No blocked stages across your projects.</p>
        </div>
    @endforelse
@endsection

@extends('layouts.ursb')
@section('title', 'Action inbox')
@section('content')
    <div class="max-w-3xl">
        <h1 class="text-xl font-bold text-gray-900 tracking-tight mb-1">Action inbox</h1>
        <p class="text-[13px] text-gray-500 mb-6">Work waiting on you across every project you can act on.</p>

        @if ($inbox['total'] === 0)
            <div class="card card-pad text-center py-12">
                <p class="text-[15px] text-gray-700 font-medium">You're all caught up.</p>
                <p class="text-[13px] text-gray-400 mt-1">Nothing is waiting on your review or approval.</p>
            </div>
        @else
            @php
                $sections = [
                    ['key' => 'firewall', 'title' => 'Awaiting your quality-firewall review', 'verb' => 'Review', 'badge' => 'badge-flag'],
                    ['key' => 'approval', 'title' => 'Awaiting your approval', 'verb' => 'Approve', 'badge' => 'badge-teal'],
                ];
            @endphp

            @foreach ($sections as $sec)
                @if ($inbox[$sec['key']]->isNotEmpty())
                    <h2 class="section-title mt-6 mb-2">{{ $sec['title'] }} <span class="badge {{ $sec['badge'] }} ml-1">{{ $inbox[$sec['key']]->count() }}</span></h2>
                    <div class="card overflow-hidden">
                        <table class="data-table">
                            <thead><tr><th>Session</th><th class="w-[120px]">Stage</th><th class="w-[160px]">Project</th><th class="w-[100px]"></th></tr></thead>
                            <tbody>
                            @foreach ($inbox[$sec['key']] as $s)
                                <tr>
                                    <td class="text-gray-900">{{ $s->title }}</td>
                                    <td class="text-gray-500 text-[13px]">{{ $s->stage?->stage?->value ?? '—' }}</td>
                                    <td class="text-gray-500 text-[13px]">{{ $s->project?->name }}</td>
                                    <td><a href="{{ route('sessions.show', $s) }}" class="text-teal hover:text-teal-700 text-[13px] font-medium">{{ $sec['verb'] }} →</a></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            @endforeach

            @if ($inbox['change_approval']->isNotEmpty())
                <h2 class="section-title mt-6 mb-2">Change requests awaiting your approval <span class="badge badge-pine ml-1">{{ $inbox['change_approval']->count() }}</span></h2>
                <div class="card overflow-hidden">
                    <table class="data-table">
                        <thead><tr><th class="w-[110px]">Ref</th><th>Title</th><th class="w-[160px]">Project</th><th class="w-[100px]"></th></tr></thead>
                        <tbody>
                        @foreach ($inbox['change_approval'] as $c)
                            <tr>
                                <td class="font-mono text-[13px] text-gray-500">{{ $c->ref }}</td>
                                <td class="text-gray-900">{{ $c->title }}</td>
                                <td class="text-gray-500 text-[13px]">{{ $c->project?->name }}</td>
                                <td><a href="{{ route('changes.show', $c) }}" class="text-teal hover:text-teal-700 text-[13px] font-medium">Review →</a></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        @endif
    </div>
@endsection

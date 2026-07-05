@extends('layouts.ursb')
@section('title', 'Search')
@section('content')
    <div class="max-w-3xl">
        <h1 class="text-xl font-bold text-gray-900 tracking-tight mb-1">Search objects</h1>
        <p class="text-[13px] text-gray-500 mb-5">Across every project you can access. Matches reference or title.</p>

        <form method="GET" action="{{ route('search') }}" class="flex gap-2 mb-6">
            <input type="text" name="q" value="{{ $term }}" autofocus placeholder="reference or title…" class="form-input flex-1">
            <button type="submit" class="btn-primary">Search</button>
        </form>

        @if ($term === '')
            <div class="card card-pad"><p class="text-sm text-gray-400 italic">Type a reference (e.g. EVD-0001) or a keyword to begin.</p></div>
        @elseif ($total === 0)
            <div class="card card-pad"><p class="text-sm text-gray-400 italic">No objects match “{{ $term }}” in your accessible projects.</p></div>
        @else
            <p class="text-[13px] text-gray-500 mb-3">{{ $total }} result{{ $total === 1 ? '' : 's' }}.</p>
            @foreach ($groups as $projectName => $objects)
                <h2 class="section-title mt-5 mb-2">{{ $projectName }}</h2>
                <div class="card overflow-hidden">
                    <table class="data-table">
                        <thead><tr><th class="w-[120px]">Ref</th><th class="w-[150px]">Type</th><th>Title</th><th class="w-[150px]">Status</th></tr></thead>
                        <tbody>
                        @foreach ($objects as $o)
                            <tr>
                                <td><a href="{{ route('objects.show', $o) }}" class="font-mono text-teal hover:text-teal-700">{{ $o->ref }}</a></td>
                                <td class="text-gray-500 text-[13px]">{{ $o->type->label() }}</td>
                                <td class="text-gray-900">{{ $o->title }}</td>
                                <td class="text-gray-500 text-[13px]">{{ $o->status?->value ?? '—' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endforeach
        @endif
    </div>
@endsection

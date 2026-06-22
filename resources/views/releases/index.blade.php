@extends('layouts.app')
@section('page-title', "What's New")
@section('page-sub', 'Release notes and product updates')

@section('content')
@php
    $sections = [
        'new' => ['label' => 'New', 'color' => 'text-green-700', 'dot' => 'bg-green-500'],
        'improved' => ['label' => 'Improved', 'color' => 'text-blue-700', 'dot' => 'bg-blue-500'],
        'fixed' => ['label' => 'Fixed', 'color' => 'text-amber-700', 'dot' => 'bg-amber-500'],
    ];
@endphp

<div class="max-w-2xl space-y-5">
@forelse($releases as $release)
    <div class="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm">
        <div class="flex items-baseline justify-between mb-4 pb-3 border-b border-gray-100">
            <h2 class="text-[16px] font-bold text-gray-900">{{ $release->displayTitle() }}</h2>
            <span class="text-[12px] text-gray-400">{{ $release->released_at->format('d/m/Y') }}</span>
        </div>

        @foreach($sections as $key => $meta)
            @php $lines = $release->notes[$key] ?? []; @endphp
            @if(!empty($lines))
            <div class="mb-3 last:mb-0">
                <div class="flex items-center gap-1.5 mb-1.5">
                    <span class="w-1.5 h-1.5 rounded-full {{ $meta['dot'] }}"></span>
                    <span class="text-[11px] font-semibold uppercase tracking-wide {{ $meta['color'] }}">{{ $meta['label'] }}</span>
                </div>
                <ul class="space-y-1 pl-4">
                    @foreach($lines as $line)
                    <li class="text-[13px] text-gray-700 leading-relaxed list-disc">{{ $line }}</li>
                    @endforeach
                </ul>
            </div>
            @endif
        @endforeach
    </div>
@empty
    <div class="bg-white border border-gray-200 rounded-2xl p-12 text-center text-gray-300 shadow-sm">
        No release notes yet.
    </div>
@endforelse

<div>{{ $releases->links() }}</div>
</div>
@endsection

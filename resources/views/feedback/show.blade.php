@extends('layouts.app')
@section('page-title', 'Feedback — ' . Str::limit($item->title, 40))

@section('topbar-actions')
    <a href="{{ route('feedback.index') }}" class="btn-secondary">← My Feedback</a>
@endsection

@section('content')
@php
    $typeColor = $item->type === 'bug' ? 'bg-red-100 text-red-800' : 'bg-indigo-100 text-indigo-800';
    $statusColor = match ($item->status) {
        'new' => 'bg-blue-50 text-blue-700',
        'triaged' => 'bg-purple-50 text-purple-700',
        'in_progress' => 'bg-amber-50 text-amber-700',
        'resolved' => 'bg-green-50 text-green-700',
        'wont_fix' => 'bg-gray-100 text-gray-500',
        default => 'bg-gray-50 text-gray-500',
    };
@endphp

<div class="max-w-2xl">
<div class="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm">
    <div class="flex items-start justify-between mb-4">
        <div>
            <h2 class="text-[18px] font-bold text-gray-900">{{ $item->title }}</h2>
            <div class="flex items-center gap-2 mt-1.5">
                <span class="inline-flex px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $typeColor }}">{{ $item->type === 'bug' ? 'Bug' : 'Feature' }}</span>
                <span class="inline-flex px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $statusColor }}">{{ str_replace('_', ' ', $item->status) }}</span>
                <span class="text-[11px] text-gray-400">Priority: {{ ucfirst($item->priority) }}</span>
            </div>
        </div>
        <div class="text-[12px] text-gray-400 text-right">
            <div>Submitted {{ $item->created_at->format('d/m/Y') }}</div>
            <div>by {{ optional($item->submittedBy)->name ?? '—' }}</div>
        </div>
    </div>

    @if($item->description)
    <div class="text-[13px] text-gray-700 leading-relaxed whitespace-pre-line">{{ $item->description }}</div>
    @endif

    @if($item->page_url)
    <div class="mt-3 text-[11px] text-gray-400">Reported from: <span class="text-gray-500 break-all">{{ $item->page_url }}</span></div>
    @endif

    @include('feedback._attachments', ['item' => $item])

    @if($item->admin_response)
    <div class="mt-4 pt-4 border-t border-gray-100">
        <div class="bg-green-50 rounded-lg p-4">
            <div class="text-[11px] font-semibold uppercase tracking-wide text-green-600 mb-1">Response from the team</div>
            <div class="text-[13px] text-gray-700 leading-relaxed whitespace-pre-line">{{ $item->admin_response }}</div>
            @if($item->resolved_at)
            <div class="text-[11px] text-green-500 mt-1">{{ ucfirst(str_replace('_', ' ', $item->status)) }} {{ $item->resolved_at->format('d/m/Y') }}</div>
            @endif
        </div>
    </div>
    @endif
</div>
</div>
@endsection

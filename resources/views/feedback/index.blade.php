@extends('layouts.app')
@section('page-title', 'Feedback')
@section('page-sub', 'Report a bug or request a feature')

@section('topbar-actions')
    <button type="button" @click="$dispatch('open-modal', 'feedback-hub')" class="btn-primary">
        + New Feedback
    </button>
@endsection

@section('content')
@php
    $typeColor = fn ($t) => $t === 'bug' ? 'bg-red-100 text-red-800' : 'bg-indigo-100 text-indigo-800';
    $statusColor = fn ($s) => match ($s) {
        'new' => 'bg-blue-50 text-blue-700',
        'triaged' => 'bg-purple-50 text-purple-700',
        'in_progress' => 'bg-amber-50 text-amber-700',
        'resolved' => 'bg-green-50 text-green-700',
        'wont_fix' => 'bg-gray-100 text-gray-500',
        default => 'bg-gray-50 text-gray-500',
    };
@endphp

<div class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm">
    <table class="data-table">
        <thead><tr>
            <th>Title</th><th>Type</th><th>Status</th><th>Priority</th><th>Submitted</th><th>Files</th><th></th>
        </tr></thead>
        <tbody>
        @forelse($items as $item)
        <tr>
            <td>
                <div class="font-medium text-[13px] text-gray-900">{{ $item->title }}</div>
                @if($item->description)
                <div class="text-[11px] text-gray-400 mt-0.5">{{ Str::limit($item->description, 70) }}</div>
                @endif
            </td>
            <td>
                <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $typeColor($item->type) }}">
                    {{ $item->type === 'bug' ? 'Bug' : 'Feature' }}
                </span>
            </td>
            <td>
                <span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $statusColor($item->status) }}">
                    {{ str_replace('_', ' ', $item->status) }}
                </span>
            </td>
            <td class="text-[12px] text-gray-500">{{ ucfirst($item->priority) }}</td>
            <td class="text-[12px] text-gray-500">{{ $item->created_at->format('d/m/Y') }}</td>
            <td class="text-[12px] text-gray-400">{{ $item->attachments->count() ?: '—' }}</td>
            <td>
                <a href="{{ route('feedback.show', $item) }}" class="text-teal text-[12px] font-medium hover:text-teal-700">View →</a>
            </td>
        </tr>
        @empty
        <tr><td colspan="7" class="text-center py-12 text-gray-300">No feedback yet. Spotted a bug or have an idea? Submit it above.</td></tr>
        @endforelse
        </tbody>
    </table>
    <div class="p-4">{{ $items->links() }}</div>
</div>
@endsection

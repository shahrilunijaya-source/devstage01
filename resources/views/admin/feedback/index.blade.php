@extends('layouts.app')
@section('page-title', 'Feedback Triage')
@section('page-sub', 'Bug reports and feature requests from all users')

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

{{-- Stats --}}
<div class="grid grid-cols-4 gap-4 mb-5">
    <div class="stat-card">
        <div class="stat-value text-blue-600">{{ $stats['open'] }}</div>
        <div class="stat-label">Open</div>
    </div>
    <div class="stat-card">
        <div class="stat-value text-red-600">{{ $stats['bugs'] }}</div>
        <div class="stat-label">Open Bugs</div>
    </div>
    <div class="stat-card">
        <div class="stat-value text-indigo-600">{{ $stats['features'] }}</div>
        <div class="stat-label">Open Features</div>
    </div>
    <div class="stat-card">
        <div class="stat-value text-amber-600">{{ $stats['high'] }}</div>
        <div class="stat-label">High Priority Open</div>
    </div>
</div>

{{-- Filters --}}
<form method="GET" class="flex gap-3 mb-4">
    <select name="type" class="form-select text-[12px] w-32">
        <option value="">All Types</option>
        @foreach(['bug','feature'] as $t)
        <option value="{{ $t }}" @selected(request('type')==$t)>{{ ucfirst($t) }}</option>
        @endforeach
    </select>
    <select name="status" class="form-select text-[12px] w-40">
        <option value="">All Status</option>
        @foreach(['new','triaged','in_progress','resolved','closed','wont_fix'] as $s)
        <option value="{{ $s }}" @selected(request('status')==$s)>{{ ucwords(str_replace('_',' ',$s)) }}</option>
        @endforeach
    </select>
    <select name="priority" class="form-select text-[12px] w-32">
        <option value="">All Priority</option>
        @foreach(['low','medium','high'] as $p)
        <option value="{{ $p }}" @selected(request('priority')==$p)>{{ ucfirst($p) }}</option>
        @endforeach
    </select>
    <button type="submit" class="btn-secondary text-[12px]">Filter</button>
    <a href="{{ route('admin.feedback.index') }}" class="btn-secondary text-[12px]">Clear</a>
</form>

<div class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm">
    <table class="data-table">
        <thead><tr>
            <th>Title</th><th>Type</th><th>Status</th><th>Priority</th><th>By</th><th>Submitted</th><th>Files</th><th></th>
        </tr></thead>
        <tbody>
        @forelse($items as $item)
        <tr>
            <td><div class="font-medium text-[13px] text-gray-900">{{ $item->title }}</div></td>
            <td><span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $typeColor($item->type) }}">{{ $item->type === 'bug' ? 'Bug' : 'Feature' }}</span></td>
            <td><span class="inline-flex px-2 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $statusColor($item->status) }}">{{ str_replace('_',' ',$item->status) }}</span></td>
            <td class="text-[12px] text-gray-500">{{ ucfirst($item->priority) }}</td>
            <td class="text-[12px] text-gray-400">{{ optional($item->submittedBy)->name ?? '—' }}</td>
            <td class="text-[12px] text-gray-500">{{ $item->created_at->format('d/m/Y') }}</td>
            <td class="text-[12px] text-gray-400">{{ $item->attachments_count ?: '—' }}</td>
            <td><a href="{{ route('admin.feedback.show', $item) }}" class="text-teal text-[12px] font-medium hover:text-teal-700">Triage →</a></td>
        </tr>
        @empty
        <tr><td colspan="8" class="text-center py-12 text-gray-300">No feedback submitted yet.</td></tr>
        @endforelse
        </tbody>
    </table>
    <div class="p-4">{{ $items->links() }}</div>
</div>
@endsection

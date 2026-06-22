@extends('layouts.app')
@section('page-title', 'Triage — ' . Str::limit($item->title, 40))

@section('topbar-actions')
    <a href="{{ route('admin.feedback.index') }}" class="btn-secondary">← Triage</a>
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

<div class="max-w-2xl space-y-5">
{{-- Submission --}}
<div class="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm">
    <div class="flex items-start justify-between mb-4">
        <div>
            <h2 class="text-[18px] font-bold text-gray-900">{{ $item->title }}</h2>
            <div class="flex items-center gap-2 mt-1.5">
                <span class="inline-flex px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $typeColor }}">{{ $item->type === 'bug' ? 'Bug' : 'Feature' }}</span>
                <span class="inline-flex px-2.5 py-0.5 rounded-full text-[10px] font-bold uppercase {{ $statusColor }}">{{ str_replace('_', ' ', $item->status) }}</span>
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

    @if($item->page_url || $item->user_agent)
    <div class="mt-3 space-y-0.5 text-[11px] text-gray-400">
        @if($item->page_url)<div>Page: <span class="text-gray-500 break-all">{{ $item->page_url }}</span></div>@endif
        @if($item->user_agent)<div>Browser: <span class="text-gray-500 break-all">{{ $item->user_agent }}</span></div>@endif
    </div>
    @endif

    @include('feedback._attachments', ['item' => $item])
</div>

{{-- Triage form --}}
<div class="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm">
    <h3 class="text-[14px] font-semibold text-gray-900 mb-4">Triage</h3>
    <form method="POST" action="{{ route('admin.feedback.update', $item) }}" class="space-y-4">
        @csrf @method('PUT')
        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                    @foreach(\App\Models\FeedbackItem::STATUSES as $s)
                    <option value="{{ $s }}" @selected($item->status==$s)>{{ ucwords(str_replace('_',' ',$s)) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">Priority</label>
                <select name="priority" class="form-select">
                    @foreach(\App\Models\FeedbackItem::PRIORITIES as $p)
                    <option value="{{ $p }}" @selected($item->priority==$p)>{{ ucfirst($p) }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div>
            <label class="form-label">Response to submitter</label>
            <textarea name="admin_response" rows="3" class="form-textarea" placeholder="Optional — the submitter sees this and is notified.">{{ old('admin_response', $item->admin_response) }}</textarea>
        </div>
        <button type="submit" class="btn-primary">Save</button>
    </form>
</div>

{{-- Danger zone --}}
<div class="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm">
    <form method="POST" action="{{ route('admin.feedback.destroy', $item) }}"
          onsubmit="return confirm('Delete this feedback and its attachments? This cannot be undone.')">
        @csrf @method('DELETE')
        <button type="submit" class="btn-danger text-[12px]">Delete feedback</button>
    </form>
</div>
</div>
@endsection

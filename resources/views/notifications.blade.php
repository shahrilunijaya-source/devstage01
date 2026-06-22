@extends('layouts.app')
@section('page-title', 'Notifications')

@section('topbar-actions')
    <form method="POST" action="{{ route('notifications.mark-all-read') }}">
        @csrf
        <button type="submit" class="btn-secondary text-[12px]">Mark all read</button>
    </form>
@endsection

@section('content')
<div class="max-w-2xl">
<div class="bg-white border border-gray-200 rounded-2xl overflow-hidden shadow-sm divide-y divide-gray-50">
@forelse($notifications as $n)
<div class="flex items-start gap-4 px-5 py-4 {{ !$n->read ? 'bg-teal/5' : '' }}">
    <div class="w-2 h-2 rounded-full mt-2 flex-shrink-0 {{ !$n->read ? 'bg-teal' : 'bg-gray-200' }}"></div>
    <div class="flex-1 min-w-0">
        <div class="text-[13px] text-gray-800 leading-relaxed">{{ $n->message }}</div>
        <div class="flex items-center gap-3 mt-1">
            @if($n->project)
            <span class="text-[11px] font-medium text-gray-400">{{ $n->project->name }}</span>
            @endif
            <span class="text-[11px] text-gray-400">{{ $n->created_at->diffForHumans() }}</span>
        </div>
    </div>
    @if(!$n->read)
    <button onclick="markRead({{ $n->id }}, this)" class="text-[11px] text-teal font-medium hover:text-teal-700 whitespace-nowrap flex-shrink-0">
        Mark read
    </button>
    @endif
</div>
@empty
<div class="px-5 py-16 text-center text-gray-300">
    <div class="text-3xl mb-3">🔔</div>
    <p class="text-[14px]">No notifications yet.</p>
</div>
@endforelse
</div>
<div class="mt-4">{{ $notifications->links() }}</div>
</div>
@endsection

@push('scripts')
<script>
function markRead(id, btn) {
    fetch(`/notifications/${id}/read`, {
        method: 'PATCH',
        headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Content-Type': 'application/json' }
    }).then(() => {
        btn.closest('div[class*="bg-teal"]')?.classList.remove('bg-teal/5');
        btn.closest('.flex').querySelector('.rounded-full')?.classList.replace('bg-teal','bg-gray-200');
        btn.remove();
    });
}
</script>
@endpush

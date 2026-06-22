@extends('layouts.app')
@section('page-title', 'Calendar')
@section('page-sub', 'Tasks, milestones, claims and meetings')

@section('topbar-actions')
    <button onclick="document.getElementById('add-event-modal').classList.remove('hidden')" class="btn-primary">
        + Add Event
    </button>
@endsection


@section('content')
<div class="bg-white border border-gray-200 rounded-2xl p-5 shadow-sm mb-5">
    <div id="legend" class="flex flex-wrap gap-x-3 gap-y-1.5 text-[12px] mb-2 select-none">
        <button type="button" class="legend-chip" data-type="task"        ><span class="w-3 h-3 rounded-sm bg-[#00B8A9] inline-block"></span> WBS Tasks (plan)</button>
        <button type="button" class="legend-chip" data-type="milestone"   ><span class="w-3 h-3 rounded-sm bg-[#F97316] inline-block"></span> Milestones</button>
        <button type="button" class="legend-chip" data-type="claim"       ><span class="w-3 h-3 rounded-sm bg-[#8B5CF6] inline-block"></span> Claim Deadlines</button>
        <button type="button" class="legend-chip" data-type="week_plan"   ><span class="w-3 h-3 rounded-sm bg-[#6366F1] inline-block"></span> Weekly Plan</button>
        <button type="button" class="legend-chip" data-type="week_actual" ><span class="w-3 h-3 rounded-sm bg-[#F59E0B] inline-block"></span> Weekly Actual</button>
        <button type="button" class="legend-chip" data-type="week_income" ><span class="w-3 h-3 rounded-sm bg-[#10B981] inline-block"></span> Income</button>
        <button type="button" class="legend-chip" data-type="week_expense"><span class="w-3 h-3 rounded-sm bg-[#F43F5E] inline-block"></span> Expense</button>
        <button type="button" class="legend-chip" data-type="week_staff"  ><span class="w-3 h-3 rounded-sm bg-[#64748B] inline-block"></span> Staff Cost</button>
        <button type="button" class="legend-chip" data-type="meeting"     ><span class="w-3 h-3 rounded-sm bg-[#003D3A] inline-block"></span> Meetings</button>
        <button type="button" class="legend-chip" data-type="holiday"     ><span class="w-3 h-3 rounded-sm bg-[#DC2626] inline-block"></span> Public Holidays</button>
    </div>
    <div id="calendar"></div>
</div>

{{-- Add event modal --}}
<div id="add-event-modal" class="hidden fixed inset-0 bg-black/40 flex items-center justify-center z-50">
    <div class="bg-white rounded-2xl p-6 w-96 shadow-xl">
        <h3 class="text-[15px] font-semibold text-gray-900 mb-4">Add Calendar Event</h3>
        <form method="POST" action="{{ route('projects.calendar.events.store', $project) }}" class="space-y-3">
            @csrf
            <div>
                <label class="form-label">Title *</label>
                <input type="text" name="title" class="form-input" required>
            </div>
            <div>
                <label class="form-label">Type</label>
                <select name="event_type" class="form-select">
                    <option value="meeting">Meeting</option>
                    <option value="holiday">Holiday / Non-Working Day</option>
                    <option value="task">Task</option>
                </select>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="form-label">Start *</label>
                    <input type="datetime-local" name="start_at" class="form-input" required>
                </div>
                <div>
                    <label class="form-label">End</label>
                    <input type="datetime-local" name="end_at" class="form-input">
                </div>
            </div>
            <div>
                <label class="form-label">Notes</label>
                <textarea name="notes" rows="2" class="form-textarea"></textarea>
            </div>
            <div class="flex gap-2">
                <button type="submit" class="btn-primary flex-1 justify-center">Add</button>
                <button type="button" onclick="document.getElementById('add-event-modal').classList.add('hidden')" class="btn-secondary flex-1 justify-center">Cancel</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
@php
    $calendarConfig = [
        'container' => '#calendar',
        'eventsUrl' => route('projects.calendar.events', $project),
        'projectId' => $project->id,
    ];
@endphp
<script type="module">
    window.initializeCalendar({
        container: document.getElementById('calendar'),
        eventsUrl: @js(route('projects.calendar.events', $project)),
        projectId: @js($project->id)
    });
</script>
@endpush

@extends('layouts.ursb')
@section('title', $project->name.' — assumptions & lessons')
@section('content')
    <div class="mb-3">
        <a href="{{ route('portfolio.show', $project) }}" class="text-[13px] text-gray-500 hover:text-teal transition-colors">← {{ $project->name }}</a>
    </div>

    <div class="mb-6">
        <h1 class="text-xl font-bold text-gray-900 tracking-tight">Assumptions &amp; lessons</h1>
        <p class="text-[13px] text-gray-500 mt-0.5">Project-specific knowledge (PRD §7.3). Recorded items are searchable by the AI assistant.</p>
    </div>

    @if ($canEdit)
        <form method="POST" action="{{ route('project-knowledge.store', $project) }}" class="card card-pad mb-6">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
                <div>
                    <label class="form-label">Type</label>
                    <select name="item_type" class="form-select">
                        <option value="approved_assumption">Assumption</option>
                        <option value="lesson_learned">Lesson learned</option>
                    </select>
                </div>
                <div class="md:col-span-3">
                    <label class="form-label">Title</label>
                    <input type="text" name="title" required maxlength="255" class="form-input" placeholder="Short statement…">
                </div>
                <div class="md:col-span-4">
                    <label class="form-label">Detail (optional)</label>
                    <textarea name="body" rows="2" class="form-textarea" placeholder="Context, rationale, or impact…"></textarea>
                </div>
                <div><button type="submit" class="btn-primary">Record</button></div>
            </div>
        </form>
    @endif

    <h2 class="section-title mt-6 mb-2">Approved assumptions <span class="badge badge-gray ml-1">{{ $assumptions->count() }}</span></h2>
    @if ($assumptions->isEmpty())
        <div class="card card-pad"><p class="text-sm text-gray-400 italic">No assumptions recorded.</p></div>
    @else
        <div class="card overflow-hidden">
            <table class="data-table">
                <thead><tr><th>Assumption</th><th class="w-[150px]">Recorded by</th><th class="w-[160px]">When</th></tr></thead>
                <tbody>
                @foreach ($assumptions as $a)
                    <tr>
                        <td class="text-gray-900">{{ $a->title }}@if($a->body)<div class="text-[13px] text-gray-500 mt-1">{{ $a->body }}</div>@endif</td>
                        <td class="text-gray-500 text-[13px]">{{ $a->approver?->name ?? '—' }}</td>
                        <td class="text-gray-500 text-[13px]">{{ optional($a->created_at)->toDayDateTimeString() }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <h2 class="section-title mt-6 mb-2">Lessons learned <span class="badge badge-gray ml-1">{{ $lessons->count() }}</span></h2>
    @if ($lessons->isEmpty())
        <div class="card card-pad"><p class="text-sm text-gray-400 italic">No lessons recorded.</p></div>
    @else
        <div class="card overflow-hidden">
            <table class="data-table">
                <thead><tr><th>Lesson</th><th class="w-[150px]">Recorded by</th><th class="w-[160px]">When</th></tr></thead>
                <tbody>
                @foreach ($lessons as $l)
                    <tr>
                        <td class="text-gray-900">{{ $l->title }}@if($l->body)<div class="text-[13px] text-gray-500 mt-1">{{ $l->body }}</div>@endif</td>
                        <td class="text-gray-500 text-[13px]">{{ $l->approver?->name ?? '—' }}</td>
                        <td class="text-gray-500 text-[13px]">{{ optional($l->created_at)->toDayDateTimeString() }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection

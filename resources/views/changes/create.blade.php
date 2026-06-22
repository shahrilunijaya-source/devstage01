@extends('layouts.ursb')
@section('title', 'Raise change request')
@section('content')
    <div class="mb-3">
        <a href="{{ route('portfolio.show', $object->project) }}" class="text-[13px] text-gray-500 hover:text-teal transition-colors">← {{ $object->project->name }}</a>
    </div>

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-gray-900 tracking-tight">Raise change request</h1>
            <p class="text-[13px] text-gray-500 mt-0.5">Target: <code class="text-[12px] text-pine">{{ $object->ref }}</code> — {{ $object->title }}</p>
        </div>
    </div>

    @if ($errors->any())
        <div class="mb-5 rounded-[10px] bg-red-50 border border-red-200 px-4 py-2.5 text-[13px] text-red-700">{{ $errors->first() }}</div>
    @endif

    <div class="card card-pad mb-5 max-w-2xl">
        <h2 class="section-title mb-2">Impact analysis (auto-trace)</h2>
        <p class="text-[13px] text-gray-600">
            {{ $impact['total'] }} object(s) affected · {{ $impact['downstream'] }} downstream · {{ $impact['upstream'] }} upstream
        </p>
        @if (! empty($impact['affected_refs']))
            <div class="mt-3 flex flex-wrap gap-1.5">
                @foreach ($impact['affected_refs'] as $ref)
                    <span class="badge badge-gray">{{ $ref }}</span>
                @endforeach
            </div>
        @endif
    </div>

    <form class="card card-pad max-w-2xl" method="POST" action="{{ route('changes.store', $object) }}">
        @csrf
        <div class="mb-4">
            <label class="form-label">Change title</label>
            <input class="form-input" name="title" value="{{ old('title') }}" required>
        </div>
        <div class="mb-4">
            <label class="form-label">Description / rationale</label>
            <textarea class="form-textarea" name="description" rows="3">{{ old('description') }}</textarea>
        </div>

        <h2 class="section-title mt-6 mb-3 pt-4 border-t border-gray-100">Proposed new values (optional)</h2>
        <div class="mb-4">
            <label class="form-label">New title</label>
            <input class="form-input" name="new_title" value="{{ old('new_title', $object->title) }}">
        </div>
        <div class="mb-4">
            <label class="form-label">New body</label>
            <textarea class="form-textarea" name="new_body" rows="3">{{ old('new_body', $object->body) }}</textarea>
        </div>

        <div class="mt-6">
            <button class="btn-primary" type="submit">Open change request</button>
        </div>
    </form>
@endsection

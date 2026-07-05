@extends('layouts.ursb')
@section('title', 'Capture objective — '.$project->name)
@section('content')
    <div class="max-w-3xl">
        <div class="mb-3">
            <a href="{{ route('objective.show', $project) }}" class="text-[13px] text-gray-500 hover:text-teal transition-colors">← Objective</a>
        </div>

        @if ($wizard)
            <div class="card card-pad mb-5 border-l-4 border-teal">
                <p class="text-[14px] font-semibold text-gray-900">Guided setup — step 2 of 3</p>
                <p class="text-[13px] text-gray-500 mt-0.5">Project created. Now capture <em>why</em> it exists — this objective anchors every requirement that follows. Step 3: grant your team access on the Team page.</p>
            </div>
        @endif

        <h1 class="text-xl font-bold text-gray-900 tracking-tight mb-1">{{ $objective ? 'Revise' : 'Capture' }} the project objective</h1>
        <p class="text-[13px] text-gray-500 mb-6">Only the statement and business problem are mandatory — fill the rest as it becomes known. Revising an approved objective sends it back for re-approval.</p>

        @php
            $attrs = $objective?->getAttribute('attributes') ?? [];
            $fields = [
                'sponsor' => ['Project sponsor', 'input', 'Who owns this initiative…'],
                'current_situation' => ['Current situation', 'textarea', 'How things work today, and what hurts…'],
                'desired_outcome' => ['Desired outcome', 'textarea', 'What must be true when this succeeds…'],
                'target_users' => ['Target users', 'input', 'Who will use the result…'],
                'scope' => ['Project scope', 'textarea', 'What is inside this project…'],
                'out_of_scope' => ['Out of scope', 'textarea', 'What is explicitly excluded…'],
                'success_measures' => ['Success measurements', 'textarea', 'How success will be measured…'],
                'business_constraints' => ['Business constraints', 'textarea', 'Budget, policy, timing constraints…'],
                'technical_constraints' => ['Technical constraints', 'textarea', 'Platforms, integrations, standards…'],
                'regulatory' => ['Regulatory requirements', 'input', 'Compliance obligations, if any…'],
                'budget_assumption' => ['Budget assumptions', 'input', ''],
                'timeline_assumption' => ['Timeline assumptions', 'input', ''],
                'known_risks' => ['Known risks', 'textarea', 'Risks already visible at the start…'],
                'stakeholders' => ['Stakeholder expectations', 'textarea', 'Key people and what they expect…'],
            ];
        @endphp

        <form method="POST" action="{{ route('objective.store', $project) }}">
            @csrf
            <div class="card card-pad mb-4">
                <label class="form-label">Objective statement <span class="text-flag">*</span></label>
                <input type="text" name="title" maxlength="255" required class="form-input"
                       value="{{ old('title', $objective?->title) }}" placeholder="One sentence: what this project must achieve…">
                @error('title')<p class="text-[12px] text-flag mt-1">{{ $message }}</p>@enderror

                <label class="form-label mt-4">Business problem <span class="text-flag">*</span></label>
                <textarea name="business_problem" rows="4" required class="form-textarea"
                          placeholder="The problem that makes this project worth funding…">{{ old('business_problem', $objective?->body) }}</textarea>
                @error('business_problem')<p class="text-[12px] text-flag mt-1">{{ $message }}</p>@enderror
            </div>

            <div class="grid md:grid-cols-2 gap-4 mb-5">
                @foreach ($fields as $key => [$label, $kind, $placeholder])
                    <div class="card card-pad">
                        <label class="form-label">{{ $label }}</label>
                        @if ($kind === 'textarea')
                            <textarea name="{{ $key }}" rows="3" class="form-textarea" placeholder="{{ $placeholder }}">{{ old($key, $attrs[$key] ?? '') }}</textarea>
                        @else
                            <input type="text" name="{{ $key }}" maxlength="5000" class="form-input" placeholder="{{ $placeholder }}" value="{{ old($key, $attrs[$key] ?? '') }}">
                        @endif
                    </div>
                @endforeach
            </div>

            <div class="flex items-center gap-3">
                <button type="submit" class="btn-primary">{{ $objective ? 'Save revision' : 'Save objective' }}</button>
                <span class="text-[12px] text-gray-400">Saved as {{ $objective ? 'a new immutable version of '.$objective->ref : 'OBJ-… with full version history' }}.</span>
            </div>
        </form>
    </div>
@endsection

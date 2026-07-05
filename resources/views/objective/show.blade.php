@extends('layouts.ursb')
@section('title', 'Objective — '.$project->name)
@section('content')
    <div class="max-w-3xl">
        <div class="mb-3">
            <a href="{{ route('portfolio.show', $project) }}" class="text-[13px] text-gray-500 hover:text-teal transition-colors">← {{ $project->name }}</a>
        </div>

        <div class="flex items-start justify-between gap-4 mb-1">
            <h1 class="text-xl font-bold text-gray-900 tracking-tight">Project Objective</h1>
            @if ($objective && $canEdit)
                <a href="{{ route('objective.edit', $project) }}" class="btn-secondary">Revise objective</a>
            @endif
        </div>
        <p class="text-[13px] text-gray-500 mb-6">The anchor of the requirement chain — every requirement, suggestion and change is evaluated against it (spec §6).</p>

        @if (! $objective)
            <div class="card card-pad border-l-4 border-flag">
                <p class="text-[15px] font-semibold text-gray-900">No objective captured yet.</p>
                <p class="text-[13px] text-gray-500 mt-0.5">The BRS stage gate checks for an approved objective. Capture it to root the traceability chain.</p>
                @if ($canEdit)
                    <a href="{{ route('objective.edit', $project) }}" class="btn-primary inline-block mt-3">Capture objective</a>
                @endif
            </div>
        @else
            <div class="card card-pad mb-5 {{ $objective->status->value === 'confirmed_by_evidence' ? 'border-l-4 border-teal' : 'border-l-4 border-flag' }}">
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-[12px] font-mono text-pine mb-1">
                            <a href="{{ route('objects.show', $objective) }}" class="hover:underline">{{ $objective->ref }}</a>
                            · v{{ $objective->current_version }}
                        </p>
                        <p class="text-[16px] font-semibold text-gray-900">{{ $objective->title }}</p>
                    </div>
                    @if ($objective->status->value === 'confirmed_by_evidence')
                        <span class="badge badge-teal">approved</span>
                    @else
                        <span class="badge badge-flag">awaiting approval</span>
                    @endif
                </div>
                @if ($objective->body)
                    <p class="text-[14px] text-gray-700 mt-3 whitespace-pre-line">{{ $objective->body }}</p>
                @endif
                @if ($canApprove && $objective->status->value !== 'confirmed_by_evidence')
                    <form method="POST" action="{{ route('objective.approve', $project) }}" class="mt-4">@csrf
                        <button type="submit" class="btn-primary">Approve objective</button>
                        <span class="text-[12px] text-gray-400 ml-2">Records a sign-off ({{ $objective->ref }} v{{ $objective->current_version }}) and unlocks the BRS gate check.</span>
                    </form>
                @endif
            </div>

            @php
                $sections = [
                    'sponsor' => 'Project sponsor', 'current_situation' => 'Current situation',
                    'desired_outcome' => 'Desired outcome', 'target_users' => 'Target users',
                    'scope' => 'Project scope', 'out_of_scope' => 'Out of scope',
                    'success_measures' => 'Success measurements', 'business_constraints' => 'Business constraints',
                    'technical_constraints' => 'Technical constraints', 'regulatory' => 'Regulatory requirements',
                    'budget_assumption' => 'Budget assumptions', 'timeline_assumption' => 'Timeline assumptions',
                    'known_risks' => 'Known risks', 'stakeholders' => 'Stakeholder expectations',
                ];
                $attrs = $objective->getAttribute('attributes') ?? [];
            @endphp
            <div class="grid md:grid-cols-2 gap-4">
                @foreach ($sections as $key => $label)
                    @if (filled($attrs[$key] ?? null))
                        <div class="card card-pad">
                            <p class="text-[12px] font-medium uppercase tracking-wide text-gray-400 mb-1">{{ $label }}</p>
                            <p class="text-[14px] text-gray-700 whitespace-pre-line">{{ $attrs[$key] }}</p>
                        </div>
                    @endif
                @endforeach
            </div>
        @endif
    </div>
@endsection

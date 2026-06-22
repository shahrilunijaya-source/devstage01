@extends('layouts.ursb')
@section('title', $project->name.' — knowledge book')
@section('content')
    <div class="mb-3">
        <a href="{{ route('portfolio.show', $project) }}" class="text-[13px] text-gray-500 hover:text-teal transition-colors">← {{ $project->name }}</a>
    </div>

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-gray-900 tracking-tight">Knowledge Book</h1>
            <p class="text-[13px] text-gray-500 mt-0.5">
                The curated methodology driving this project's sessions (PRD §7).
                @if ($book)
                    Pinned to <span class="badge badge-pine">{{ $book->code ?? $book->version }} {{ $book->version }}</span>
                @else
                    <span class="text-flag-500">No Knowledge Book pinned.</span>
                @endif
            </p>
        </div>
    </div>

    @unless ($book)
        <div class="card card-pad"><p class="text-sm text-gray-400 italic">This project is not pinned to a Knowledge Book version, so no methodology is available. Pin one to drive its sessions.</p></div>
    @else
        {{-- Stage filter --}}
        <div class="card card-pad mb-6">
            <form method="GET" action="{{ route('knowledge.show', $project) }}" class="flex flex-wrap items-end gap-3">
                <div class="min-w-[220px]">
                    <label class="form-label">Filter by stage</label>
                    <select name="stage" class="form-select" onchange="this.form.submit()">
                        <option value="">All stages</option>
                        @foreach ($stages as $s)
                            <option value="{{ $s->value }}" @selected($stage?->value === $s->value)>{{ $s->label() }}</option>
                        @endforeach
                    </select>
                </div>
                @if ($stage)
                    <a class="btn-secondary" href="{{ route('knowledge.show', $project) }}">Clear</a>
                @endif
                <span class="text-[12px] text-gray-400 ml-auto">{{ $total }} item{{ $total === 1 ? '' : 's' }} in scope</span>
            </form>
        </div>

        {{-- Deliverables --}}
        <section class="mb-8">
            <h2 class="section-title mb-3">Deliverables ({{ $deliverables->count() }})</h2>
            @if ($deliverables->isEmpty())
                <div class="card card-pad"><p class="text-sm text-gray-400 italic">No deliverables for this scope.</p></div>
            @else
                <div class="card overflow-hidden">
                    <table class="data-table">
                        <thead><tr><th class="w-[90px]">Code</th><th>Deliverable</th><th class="w-[120px]">Stage</th></tr></thead>
                        <tbody>
                        @foreach ($deliverables as $d)
                            <tr>
                                <td><code class="text-[12px] text-pine">{{ $d->code }}</code></td>
                                <td class="text-gray-900">{{ $d->title }}@if($d->body)<div class="text-[13px] text-gray-500 mt-1">{{ $d->body }}</div>@endif</td>
                                <td class="text-gray-500">{{ $d->stage?->label() ?? '—' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        {{-- Question banks grouped by stage --}}
        <section class="mb-8">
            <h2 class="section-title mb-3">Question banks</h2>
            @forelse ($questions as $stageKey => $group)
                <div class="card card-pad mb-3">
                    <div class="flex items-center gap-2 mb-2">
                        <span class="badge badge-teal">{{ $stageKey === 'general' ? 'General' : \App\Enums\LifecycleStage::tryFrom($stageKey)?->label() ?? $stageKey }}</span>
                        <span class="text-[12px] text-gray-400">{{ $group->count() }} question{{ $group->count() === 1 ? '' : 's' }}</span>
                    </div>
                    <ol class="list-decimal pl-5 space-y-1">
                        @foreach ($group as $q)
                            <li class="text-sm text-gray-800">{{ $q->title }}</li>
                        @endforeach
                    </ol>
                </div>
            @empty
                <div class="card card-pad"><p class="text-sm text-gray-400 italic">No question bank for this scope.</p></div>
            @endforelse
        </section>

        {{-- Stage gates --}}
        @if ($gates->isNotEmpty())
            <section class="mb-8">
                <h2 class="section-title mb-3">Stage gates ({{ $gates->count() }})</h2>
                <div class="card overflow-hidden">
                    <table class="data-table">
                        <thead><tr><th class="w-[90px]">Code</th><th>Gate</th><th class="w-[120px]">Stage</th></tr></thead>
                        <tbody>
                        @foreach ($gates as $g)
                            <tr>
                                <td><code class="text-[12px] text-pine">{{ $g->code }}</code></td>
                                <td class="text-gray-900">{{ $g->title }}@if($g->body)<div class="text-[13px] text-gray-500 mt-1">{{ $g->body }}</div>@endif</td>
                                <td class="text-gray-500">{{ $g->stage?->label() ?? '—' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        @endif

        {{-- Glossary --}}
        @if ($glossary->isNotEmpty())
            <section>
                <h2 class="section-title mb-3">Glossary ({{ $glossary->count() }})</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    @foreach ($glossary as $term)
                        <div class="card card-pad">
                            <div class="text-[13px] font-semibold text-gray-900">{{ $term->title }}</div>
                            @if ($term->body)<div class="text-[13px] text-gray-500 mt-1">{{ $term->body }}</div>@endif
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    @endunless
@endsection

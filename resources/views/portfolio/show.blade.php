@extends('layouts.ursb')
@section('title', $project->name)
@section('content')
    <a class="text-[13px] text-gray-500 hover:text-teal transition-colors inline-flex items-center gap-1 mb-3" href="{{ route('portfolio.index') }}">← Portfolio</a>

    <div class="flex items-start justify-between mb-1">
        <div>
            <h1 class="text-xl font-bold text-gray-900 tracking-tight inline-flex items-center gap-2">
                {{ $project->name }}
                <span class="badge badge-gray align-middle">{{ $project->code }}</span>
            </h1>
            <p class="text-[13px] text-gray-500 mt-0.5">
                {{ $project->tenant->name }} ·
                <a class="hover:text-teal transition-colors" href="{{ route('objects.index', $project) }}">{{ $objectCount }} graph objects</a>
                · status {{ $project->status }}
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a class="btn-secondary" href="{{ route('objects.index', $project) }}">Browse objects</a>
            <a class="btn-secondary" href="{{ route('knowledge.show', $project) }}">Knowledge</a>
            <a class="btn-secondary" href="{{ route('metrics.show', $project) }}">Metrics</a>
            <a class="btn-secondary" href="{{ route('metrics.coverage', $project) }}">Coverage</a>
            <a class="btn-secondary" href="{{ route('changes.index', $project) }}">Change requests</a>
        </div>
    </div>

    <h2 class="section-title mt-8 mb-3">Modules &amp; lifecycle stages</h2>
    @forelse ($project->modules as $module)
        <div class="card card-pad mb-4">
            <div class="flex items-center gap-2 mb-3">
                <strong class="text-[15px] font-semibold text-gray-900">{{ $module->name }}</strong>
                <span class="badge badge-gray">{{ $module->code }}</span>
            </div>
            <div class="flex flex-wrap gap-2 mb-2">
                @foreach ($module->stages->sortBy(fn ($s) => $s->stage->order()) as $stage)
                    <span class="badge {{ $stage->status === 'baselined' ? 'badge-teal' : ($stage->status === 'in_progress' ? 'badge-flag' : 'badge-gray') }}"
                          title="{{ $stage->status }}{{ $stage->currentBaseline ? ' · '.$stage->currentBaseline->version_label : '' }}">
                        {{ $stage->stage->label() }}@if($stage->sessions->count()) ({{ $stage->sessions->count() }})@endif
                    </span>
                @endforeach
            </div>

            @php($sessions = $module->stages->flatMap->sessions)
            @if ($sessions->isNotEmpty())
                <div class="border border-gray-100 rounded-xl overflow-hidden mt-3">
                    <table class="data-table">
                        <thead><tr><th>Session</th><th>Stage</th><th>Dimension</th><th>Status</th></tr></thead>
                        <tbody>
                        @foreach ($sessions as $s)
                            <tr>
                                <td><a class="font-medium text-gray-900 hover:text-teal transition-colors" href="{{ route('sessions.show', $s) }}">{{ $s->title }}</a></td>
                                <td>{{ $s->stage->stage->label() }}</td>
                                <td class="text-gray-500">{{ collect([$s->process, $s->domain, $s->location])->filter()->implode(' · ') ?: '—' }}</td>
                                <td><span class="badge {{ $s->status === 'approved' ? 'badge-teal' : 'badge-gray' }}">{{ $s->status }}</span></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            @php($baselinable = $module->stages->filter(fn ($s) => $s->currentBaseline || $s->sessions->where('status', 'approved')->isNotEmpty()))
            @if ($baselinable->isNotEmpty())
                <div class="mt-3 flex flex-wrap gap-2 items-center">
                    @foreach ($baselinable as $stage)
                        @if ($stage->currentBaseline)
                            <a class="btn-secondary" href="{{ route('baselines.show', $stage->currentBaseline) }}">
                                {{ $stage->stage->label() }}: {{ $stage->currentBaseline->version_label }} ↗</a>
                        @elseif ($canBaseline)
                            <form method="POST" action="{{ route('stages.baseline', $stage) }}">@csrf
                                <button class="btn-primary" type="submit">Baseline {{ $stage->stage->label() }}</button>
                            </form>
                        @endif
                    @endforeach
                </div>
            @endif

            @if ($canEdit)
                @php($brs = $module->stages->firstWhere('stage', \App\Enums\LifecycleStage::BRS))
                <form method="POST" action="{{ route('portfolio.sessions.store', $brs) }}" class="mt-4 pt-4 border-t border-gray-100">
                    @csrf
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="form-label">New session (BRS) — title</label>
                            <input class="form-input" name="title" required>
                        </div>
                        <div>
                            <label class="form-label">Domain</label>
                            <input class="form-input" name="domain">
                        </div>
                    </div>
                    <div class="mt-3"><button class="btn-primary" type="submit">Add session</button></div>
                </form>
            @endif
        </div>
    @empty
        <p class="text-sm text-gray-400 italic">No modules yet.</p>
    @endforelse

    @if ($canEdit)
        <form class="card card-pad" method="POST" action="{{ route('portfolio.modules.store', $project) }}">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">New module — name</label>
                    <input class="form-input" name="name" required>
                </div>
                <div>
                    <label class="form-label">Code</label>
                    <input class="form-input" name="code" required placeholder="e.g. PAY">
                </div>
            </div>
            <div class="mt-4"><button class="btn-pine" type="submit">Add module (seeds 9 stages)</button></div>
        </form>
    @endif

    <h2 class="section-title mt-8 mb-3">Traceability chain</h2>
    @if ($chain->isEmpty())
        <p class="text-sm text-gray-400 italic">No objects yet.</p>
    @else
        <div class="flex flex-wrap items-center gap-2">
            @foreach ($chain as $obj)
                <div class="card card-pad" style="max-width:240px;">
                    <div class="text-teal font-semibold font-mono text-[13px]">{{ $obj->ref }}</div>
                    <div class="text-[12.5px] text-gray-800 mt-1">{{ $obj->title }}</div>
                    <div class="section-title mt-1.5">{{ $obj->status->label() }}</div>
                </div>
                @if (! $loop->last)<span class="text-teal text-lg">→</span>@endif
            @endforeach
        </div>
    @endif
@endsection

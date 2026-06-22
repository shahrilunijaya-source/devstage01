@extends('layouts.ursb')
@section('title', $project->name)
@section('content')
    <p class="sub" style="margin-bottom:6px;"><a href="{{ route('portfolio.index') }}">← Portfolio</a></p>
    <div style="display:flex; align-items:center; justify-content:space-between;">
        <h1 class="page">{{ $project->name }} <span class="pill">{{ $project->code }}</span></h1>
        <div style="display:flex; gap:8px;">
            <a class="btn ghost sm" href="{{ route('objects.index', $project) }}">Browse objects</a>
            <a class="btn ghost sm" href="{{ route('metrics.show', $project) }}">Metrics</a>
            <a class="btn ghost sm" href="{{ route('metrics.coverage', $project) }}">Coverage</a>
            <a class="btn ghost sm" href="{{ route('changes.index', $project) }}">Change requests</a>
        </div>
    </div>
    <p class="sub">{{ $project->tenant->name }} · <a href="{{ route('objects.index', $project) }}">{{ $objectCount }} graph objects</a> · status {{ $project->status }}</p>

    <section>
        <h2 class="sec"><span>Modules &amp; lifecycle stages</span></h2>
        @forelse ($project->modules as $module)
            <div class="panel" style="margin-bottom:14px;">
                <div style="display:flex; align-items:center; gap:10px; margin-bottom:10px;">
                    <strong>{{ $module->name }}</strong> <span class="pill">{{ $module->code }}</span>
                </div>
                <div style="display:flex; flex-wrap:wrap; gap:6px; margin-bottom:6px;">
                    @foreach ($module->stages->sortBy(fn ($s) => $s->stage->order()) as $stage)
                        <span class="pill {{ $stage->status === 'baselined' ? 'on' : ($stage->status === 'in_progress' ? 'warn' : '') }}"
                              title="{{ $stage->status }}{{ $stage->currentBaseline ? ' · '.$stage->currentBaseline->version_label : '' }}">
                            {{ $stage->stage->label() }}@if($stage->sessions->count()) ({{ $stage->sessions->count() }})@endif
                        </span>
                    @endforeach
                </div>

                @php($sessions = $module->stages->flatMap->sessions)
                @if ($sessions->isNotEmpty())
                    <table style="margin-top:8px;">
                        <thead><tr><th>Session</th><th>Stage</th><th>Dimension</th><th>Status</th></tr></thead>
                        <tbody>
                        @foreach ($sessions as $s)
                            <tr>
                                <td><a href="{{ route('sessions.show', $s) }}">{{ $s->title }}</a></td>
                                <td>{{ $s->stage->stage->label() }}</td>
                                <td class="sub" style="margin:0;">{{ collect([$s->process, $s->domain, $s->location])->filter()->implode(' · ') ?: '—' }}</td>
                                <td><span class="pill {{ $s->status === 'approved' ? 'on' : '' }}">{{ $s->status }}</span></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                @endif

                @php($baselinable = $module->stages->filter(fn ($s) => $s->currentBaseline || $s->sessions->where('status', 'approved')->isNotEmpty()))
                @if ($baselinable->isNotEmpty())
                    <div style="margin-top:12px; display:flex; flex-wrap:wrap; gap:8px; align-items:center;">
                        @foreach ($baselinable as $stage)
                            @if ($stage->currentBaseline)
                                <a class="btn ghost sm" href="{{ route('baselines.show', $stage->currentBaseline) }}">
                                    {{ $stage->stage->label() }}: {{ $stage->currentBaseline->version_label }} ↗</a>
                            @elseif ($canBaseline)
                                <form class="inline" method="POST" action="{{ route('stages.baseline', $stage) }}">@csrf
                                    <button class="btn sm" type="submit">Baseline {{ $stage->stage->label() }}</button>
                                </form>
                            @endif
                        @endforeach
                    </div>
                @endif

                @if ($canEdit)
                    @php($brs = $module->stages->firstWhere('stage', \App\Enums\LifecycleStage::BRS))
                    <form method="POST" action="{{ route('portfolio.sessions.store', $brs) }}" style="margin-top:12px;">
                        @csrf
                        <div class="row">
                            <div><label>New session (BRS) — title</label><input name="title" required></div>
                            <div><label>Domain</label><input name="domain"></div>
                        </div>
                        <div style="margin-top:10px;"><button class="btn sm" type="submit">Add session</button></div>
                    </form>
                @endif
            </div>
        @empty
            <p class="empty">No modules yet.</p>
        @endforelse

        @if ($canEdit)
            <form class="panel" method="POST" action="{{ route('portfolio.modules.store', $project) }}">
                @csrf
                <div class="row">
                    <div><label>New module — name</label><input name="name" required></div>
                    <div><label>Code</label><input name="code" required placeholder="e.g. PAY"></div>
                </div>
                <div style="margin-top:12px;"><button class="btn sm" type="submit">Add module (seeds 9 stages)</button></div>
            </form>
        @endif
    </section>

    <section>
        <h2 class="sec"><span>Traceability chain</span></h2>
        @if ($chain->isEmpty())
            <p class="empty">No objects yet.</p>
        @else
            <div style="display:flex; flex-wrap:wrap; align-items:center; gap:8px;">
                @foreach ($chain as $obj)
                    <div class="panel" style="max-width:240px; padding:11px;">
                        <div style="color:var(--gold); font-weight:650; font-family:ui-monospace,monospace; font-size:13px;">{{ $obj->ref }}</div>
                        <div style="font-size:12.5px; margin-top:3px;">{{ $obj->title }}</div>
                        <div class="sub" style="margin:4px 0 0; font-size:10.5px; text-transform:uppercase;">{{ $obj->status->label() }}</div>
                    </div>
                    @if (! $loop->last)<span style="color:var(--accent); font-size:18px;">→</span>@endif
                @endforeach
            </div>
        @endif
    </section>
@endsection

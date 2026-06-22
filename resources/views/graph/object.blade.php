@extends('layouts.ursb')
@section('title', $object->ref)
@section('content')
    @php
        $titleRedacted = in_array('title', $redacted, true);
        $bodyRedacted = in_array('body', $redacted, true);
    @endphp

    <p class="sub" style="margin-bottom:6px;">
        <a href="{{ route('portfolio.show', $object->project) }}">← {{ $object->project->name }}</a>
    </p>
    <div style="display:flex; align-items:center; justify-content:space-between; gap:12px;">
        <h1 class="page"><code>{{ $object->ref }}</code> {{ $titleRedacted ? '[redacted]' : $object->title }}</h1>
        @if ($canEdit && $object->baseline_id)
            <a class="btn ghost sm" href="{{ route('changes.create', $object) }}">Request change</a>
        @endif
    </div>
    <p class="sub">
        {{ $object->type->label() }} ·
        <span class="pill {{ $object->status->value === 'confirmed_by_evidence' ? 'on' : '' }}">{{ $object->status->label() }}</span> ·
        v{{ $object->current_version }} ·
        classification {{ $object->classification }}
        @if ($object->confidence) · confidence {{ $object->confidence->value }} @endif
        @if ($object->impact) · impact {{ $object->impact }} @endif
    </p>

    <section>
        <h2 class="sec"><span>Detail</span></h2>
        <div class="panel">
            @if ($bodyRedacted)
                <p class="sub" style="margin:0;">Body redacted for your clearance level (PRD §6.3).</p>
            @elseif ($object->body)
                <p style="margin:0; white-space:pre-wrap;">{{ $object->body }}</p>
            @else
                <p class="sub" style="margin:0;">No body.</p>
            @endif
            <div class="sub" style="margin-top:12px;">
                {{ $object->module?->name ?? '—' }}@if($object->stage) · {{ $object->stage->stage->label() }}@endif
                @if($object->session) · <a href="{{ route('sessions.show', $object->session) }}">{{ $object->session->title }}</a>@endif
                · owner {{ $object->owner?->name ?? '—' }} · source {{ $object->source ?? '—' }}
                @if($object->baseline_id) · <span class="pill">baselined</span>@endif
            </div>
        </div>
    </section>

    <div class="row" style="gap:14px; align-items:flex-start;">
        <section style="flex:1;">
            <h2 class="sec"><span>Derived from / inputs ({{ $incoming->count() }})</span></h2>
            @forelse ($incoming as $row)
                <div class="panel" style="margin-bottom:6px;">
                    <span class="sub" style="margin:0;">{{ $row['relation'] }}</span><br>
                    <a href="{{ route('objects.show', $row['object']) }}"><code>{{ $row['object']->ref }}</code> {{ $row['object']->title }}</a>
                </div>
            @empty
                <p class="empty">No inbound trace.</p>
            @endforelse
        </section>
        <section style="flex:1;">
            <h2 class="sec"><span>Leads to / outputs ({{ $outgoing->count() }})</span></h2>
            @forelse ($outgoing as $row)
                <div class="panel" style="margin-bottom:6px;">
                    <span class="sub" style="margin:0;">{{ $row['relation'] }}</span><br>
                    <a href="{{ route('objects.show', $row['object']) }}"><code>{{ $row['object']->ref }}</code> {{ $row['object']->title }}</a>
                </div>
            @empty
                <p class="empty">No outbound trace.</p>
            @endforelse
        </section>
    </div>

    <section>
        <h2 class="sec"><span>Full traceability reach</span></h2>
        <div class="panel">
            <div class="sub" style="margin:0 0 4px;">Reverse (where it comes from): {{ $reverse->count() }}</div>
            <div style="margin-bottom:12px;">
                @forelse ($reverse as $o)
                    <a href="{{ route('objects.show', $o) }}" style="margin-right:8px;"><code>{{ $o->ref }}</code></a>
                @empty
                    <span class="sub">—</span>
                @endforelse
            </div>
            <div class="sub" style="margin:0 0 4px;">Forward (where it leads): {{ $forward->count() }}</div>
            <div>
                @forelse ($forward as $o)
                    <a href="{{ route('objects.show', $o) }}" style="margin-right:8px;"><code>{{ $o->ref }}</code></a>
                @empty
                    <span class="sub">—</span>
                @endforelse
            </div>
        </div>
    </section>

    <section>
        <h2 class="sec"><span>Version history ({{ $versions->count() }})</span></h2>
        <table>
            <thead><tr><th style="width:60px;">Ver</th><th>Change</th><th>By</th><th>When</th></tr></thead>
            <tbody>
            @foreach ($versions as $v)
                <tr>
                    <td>v{{ $v->version }}</td>
                    <td>{{ $v->change_summary }}</td>
                    <td class="sub" style="margin:0;">{{ $v->changedBy?->name ?? '—' }}</td>
                    <td class="sub" style="margin:0;">{{ $v->created_at?->toDayDateTimeString() }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </section>
@endsection

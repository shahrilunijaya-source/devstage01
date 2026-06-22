@extends('layouts.ursb')
@section('title', $object->ref)
@section('content')
    @php
        $titleRedacted = in_array('title', $redacted, true);
        $bodyRedacted = in_array('body', $redacted, true);
        $typeBadge = match (true) {
            str_contains($object->type->value, 'evidence') => 'badge-teal',
            str_contains($object->type->value, 'requirement') => 'badge-pine',
            str_contains($object->type->value, 'finding') => 'badge-flag',
            default => 'badge-gray',
        };
    @endphp

    <p class="text-[13px] text-gray-500 mb-2">
        <a href="{{ route('portfolio.show', $object->project) }}" class="hover:text-teal">← {{ $object->project->name }}</a>
    </p>

    <div class="flex items-start justify-between gap-3 mb-1">
        <h1 class="text-xl font-bold text-gray-900 tracking-tight">
            <span class="font-mono text-teal">{{ $object->ref }}</span> {{ $titleRedacted ? '[redacted]' : $object->title }}
        </h1>
        @if ($canEdit && $object->baseline_id)
            <a class="btn-secondary shrink-0" href="{{ route('changes.create', $object) }}">Request change</a>
        @endif
    </div>
    <p class="text-[13px] text-gray-500 mb-6 flex flex-wrap items-center gap-2">
        <span class="badge {{ $typeBadge }}">{{ $object->type->label() }}</span>
        <span class="badge {{ $object->status->value === 'confirmed_by_evidence' ? 'badge-teal' : 'badge-gray' }}">{{ $object->status->label() }}</span>
        <span>v{{ $object->current_version }}</span>
        <span>· classification {{ $object->classification }}</span>
        @if ($object->confidence) <span>· confidence {{ $object->confidence->value }}</span> @endif
        @if ($object->impact) <span>· impact {{ $object->impact }}</span> @endif
    </p>

    <div class="mb-6">
        <h2 class="section-title mb-2">Detail</h2>
        <div class="card card-pad">
            @if ($bodyRedacted)
                <p class="text-sm text-gray-400 italic">Body redacted for your clearance level (PRD §6.3).</p>
            @elseif ($object->body)
                <p class="text-sm text-gray-800 whitespace-pre-wrap">{{ $object->body }}</p>
            @else
                <p class="text-sm text-gray-400 italic">No body.</p>
            @endif
            <div class="text-[13px] text-gray-500 mt-3 flex flex-wrap items-center gap-1">
                <span>{{ $object->module?->name ?? '—' }}</span>@if($object->stage) <span>· {{ $object->stage->stage->label() }}</span>@endif
                @if($object->session) <span>· <a href="{{ route('sessions.show', $object->session) }}" class="text-teal hover:text-teal-700">{{ $object->session->title }}</a></span>@endif
                <span>· owner {{ $object->owner?->name ?? '—' }}</span>
                <span>· source {{ $object->source ?? '—' }}</span>
                @if($object->baseline_id) <span class="badge badge-pine">baselined</span>@endif
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-5 mb-6 items-start">
        <div>
            <h2 class="section-title mb-2">Derived from / inputs ({{ $incoming->count() }})</h2>
            @forelse ($incoming as $row)
                <div class="card card-pad mb-2">
                    <span class="text-[11px] uppercase tracking-wider text-gray-400">{{ $row['relation'] }}</span>
                    <div class="mt-1">
                        <a href="{{ route('objects.show', $row['object']) }}" class="text-sm hover:text-teal"><span class="font-mono text-teal">{{ $row['object']->ref }}</span> {{ $row['object']->title }}</a>
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-400 italic">No inbound trace.</p>
            @endforelse
        </div>
        <div>
            <h2 class="section-title mb-2">Leads to / outputs ({{ $outgoing->count() }})</h2>
            @forelse ($outgoing as $row)
                <div class="card card-pad mb-2">
                    <span class="text-[11px] uppercase tracking-wider text-gray-400">{{ $row['relation'] }}</span>
                    <div class="mt-1">
                        <a href="{{ route('objects.show', $row['object']) }}" class="text-sm hover:text-teal"><span class="font-mono text-teal">{{ $row['object']->ref }}</span> {{ $row['object']->title }}</a>
                    </div>
                </div>
            @empty
                <p class="text-sm text-gray-400 italic">No outbound trace.</p>
            @endforelse
        </div>
    </div>

    <div class="mb-6">
        <h2 class="section-title mb-2">Full traceability reach</h2>
        <div class="card card-pad">
            <div class="text-[13px] text-gray-500 mb-1.5">Reverse (where it comes from): {{ $reverse->count() }}</div>
            <div class="mb-4 flex flex-wrap gap-2">
                @forelse ($reverse as $o)
                    <a href="{{ route('objects.show', $o) }}" class="badge badge-gray font-mono hover:bg-teal/10 hover:text-teal">{{ $o->ref }}</a>
                @empty
                    <span class="text-sm text-gray-400 italic">—</span>
                @endforelse
            </div>
            <div class="text-[13px] text-gray-500 mb-1.5">Forward (where it leads): {{ $forward->count() }}</div>
            <div class="flex flex-wrap gap-2">
                @forelse ($forward as $o)
                    <a href="{{ route('objects.show', $o) }}" class="badge badge-gray font-mono hover:bg-teal/10 hover:text-teal">{{ $o->ref }}</a>
                @empty
                    <span class="text-sm text-gray-400 italic">—</span>
                @endforelse
            </div>
        </div>
    </div>

    <div class="mb-6">
        <h2 class="section-title mb-2">Version history ({{ $versions->count() }})</h2>
        <div class="card overflow-hidden">
            <table class="data-table">
                <thead><tr><th class="w-[60px]">Ver</th><th>Change</th><th>By</th><th>When</th></tr></thead>
                <tbody>
                @foreach ($versions as $v)
                    <tr>
                        <td class="font-mono text-gray-900">v{{ $v->version }}</td>
                        <td class="text-gray-800">{{ $v->change_summary }}</td>
                        <td class="text-gray-500">{{ $v->changedBy?->name ?? '—' }}</td>
                        <td class="text-gray-500">{{ $v->created_at?->toDayDateTimeString() }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endsection

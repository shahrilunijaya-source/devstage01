@extends('layouts.ursb')
@section('title', $baseline->version_label)
@section('content')
    <p class="sub" style="margin-bottom:6px;"><a href="{{ route('portfolio.show', $project) }}">← {{ $project->name }}</a></p>
    <div style="display:flex; align-items:center; justify-content:space-between;">
        <h1 class="page">{{ $baseline->version_label }}</h1>
        <div style="display:flex; gap:8px;">
            <a class="btn ghost" href="{{ route('baselines.deck', $baseline) }}" target="_blank">Review deck</a>
            <a class="btn" href="{{ route('baselines.pdf', $baseline) }}">Download PDF</a>
        </div>
    </div>
    <p class="sub">
        {{ $project->tenant->name }} · {{ $project->name }} · {{ $baseline->stage->module->name }} ·
        {{ $baseline->stage->stage->label() }} · Knowledge Book {{ $baseline->knowledge_book_version ?? '—' }} ·
        frozen {{ optional($baseline->approved_at)->toDayDateTimeString() }}
    </p>

    @forelse ($groups as $type => $items)
        <section>
            <h2 class="sec"><span>{{ ucwords(str_replace('_', ' ', $type)) }} ({{ $items->count() }})</span></h2>
            <table>
                <thead><tr><th style="width:120px;">Ref</th><th>Title</th><th style="width:60px;">Ver</th>@if($canEdit)<th style="width:120px;"></th>@endif</tr></thead>
                <tbody>
                @foreach ($items as $item)
                    <tr>
                        <td><a href="{{ route('objects.show', $item['object_id']) }}"><code>{{ $item['ref'] }}</code></a></td>
                        <td>{{ $item['title'] }}@if($item['body'])<div class="sub" style="margin:3px 0 0;">{{ $item['body'] }}</div>@endif</td>
                        <td>v{{ $item['version'] }}</td>
                        @if($canEdit)<td style="text-align:right;"><a class="btn ghost sm" href="{{ route('changes.create', $item['object_id']) }}">Request change</a></td>@endif
                    </tr>
                @endforeach
                </tbody>
            </table>
        </section>
    @empty
        <p class="empty">This baseline froze no objects.</p>
    @endforelse
@endsection

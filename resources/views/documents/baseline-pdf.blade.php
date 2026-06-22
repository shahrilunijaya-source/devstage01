<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page { margin: 130px 50px 70px; }
    * { font-family: DejaVu Sans, sans-serif; }
    body { color: #1b2430; font-size: 11px; }
    header { position: fixed; top: -95px; left: 0; right: 0; height: 80px;
        border-bottom: 2px solid #17365D; padding-bottom: 8px; }
    header .title { color: #17365D; font-size: 18px; font-weight: bold; }
    header .meta { color: #6c7d7d; font-size: 10px; margin-top: 3px; }
    footer { position: fixed; bottom: -50px; left: 0; right: 0; height: 30px;
        border-top: 1px solid #cdd5df; color: #6c7d7d; font-size: 9px; padding-top: 6px; }
    .pagenum:before { content: counter(page); }
    h2 { color: #17365D; font-size: 13px; border-bottom: 1px solid #cdd5df;
        padding-bottom: 4px; margin: 18px 0 8px; }
    table { width: 100%; border-collapse: collapse; }
    th { text-align: left; color: #6c7d7d; font-size: 9px; text-transform: uppercase;
        border-bottom: 1px solid #2F75B5; padding: 5px 6px; }
    td { padding: 6px; border-bottom: 1px solid #e6e9f0; vertical-align: top; }
    .ref { font-family: DejaVu Sans Mono, monospace; color: #00807f; font-weight: bold; }
    .body { color: #56627a; font-size: 10px; margin-top: 2px; }
    .cover { margin-bottom: 14px; }
    .cover .badge { display: inline-block; background: #00A6A6; color: #fff; padding: 2px 8px;
        border-radius: 3px; font-size: 9px; }
</style>
</head>
<body>
<header>
    <div class="title">{{ $baseline->version_label }}</div>
    <div class="meta">{{ $project->tenant->name }} · {{ $project->name }} ·
        {{ $baseline->stage->module->name }} · {{ $baseline->stage->stage->label() }} stage baseline</div>
</header>
<footer>
    URSB Platform · Generated view of the canonical model · Knowledge Book {{ $baseline->knowledge_book_version ?? '—' }}
    · Page <span class="pagenum"></span>
</footer>

<div class="cover">
    <span class="badge">APPROVED BASELINE</span>
    <p class="body">This document is a generated view of immutable baseline objects frozen on
        {{ optional($baseline->approved_at)->toDayDateTimeString() }}. Each entry is pinned to its approved version.</p>
</div>

@forelse ($groups as $type => $items)
    <h2>{{ ucwords(str_replace('_', ' ', $type)) }} ({{ $items->count() }})</h2>
    <table>
        <thead><tr><th style="width:110px;">Ref</th><th>Title</th><th style="width:40px;">Ver</th></tr></thead>
        <tbody>
        @foreach ($items as $item)
            <tr>
                <td class="ref">{{ $item['ref'] }}</td>
                <td>{{ $item['title'] }}@if($item['body'])<div class="body">{{ $item['body'] }}</div>@endif</td>
                <td>v{{ $item['version'] }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@empty
    <p>This baseline froze no objects.</p>
@endforelse
</body>
</html>

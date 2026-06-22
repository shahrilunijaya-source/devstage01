@extends('layouts.ursb')
@section('title', $baseline->version_label)
@section('content')
    <p class="text-[13px] text-gray-500 mb-2"><a href="{{ route('portfolio.show', $project) }}" class="hover:text-teal">← {{ $project->name }}</a></p>

    <div class="flex items-center justify-between mb-1">
        <h1 class="text-xl font-bold text-gray-900 tracking-tight">{{ $baseline->version_label }}</h1>
        <div class="flex gap-2">
            <a class="btn-secondary" href="{{ route('baselines.deck', $baseline) }}" target="_blank">Review deck</a>
            <a class="btn-secondary" href="{{ route('baselines.deck.pptx', $baseline) }}">Deck (.pptx)</a>
            <a class="btn-primary" href="{{ route('baselines.pdf', $baseline) }}">Download PDF</a>
        </div>
    </div>
    <p class="text-[13px] text-gray-500 mb-6">
        {{ $project->tenant->name }} · {{ $project->name }} · {{ $baseline->stage->module->name }} ·
        {{ $baseline->stage->stage->label() }} · Knowledge Book {{ $baseline->knowledge_book_version ?? '—' }} ·
        frozen {{ optional($baseline->approved_at)->toDayDateTimeString() }}@if($baseline->approver) · signed off by {{ $baseline->approver->name }}@endif
    </p>

    @forelse ($groups as $type => $items)
        <div class="mb-6">
            <h2 class="section-title mb-2">{{ ucwords(str_replace('_', ' ', $type)) }} ({{ $items->count() }})</h2>
            <div class="card overflow-hidden">
                <table class="data-table">
                    <thead><tr><th class="w-[120px]">Ref</th><th>Title</th><th class="w-[60px]">Ver</th>@if($canEdit)<th class="w-[120px]"></th>@endif</tr></thead>
                    <tbody>
                    @foreach ($items as $item)
                        <tr>
                            <td><a href="{{ route('objects.show', $item['object_id']) }}" class="font-mono text-teal hover:text-teal-700">{{ $item['ref'] }}</a></td>
                            <td class="text-gray-900">{{ $item['title'] }}@if($item['body'])<div class="text-[13px] text-gray-500 mt-1">{{ $item['body'] }}</div>@endif</td>
                            <td class="text-gray-500">v{{ $item['version'] }}</td>
                            @if($canEdit)<td class="text-right"><a class="btn-secondary !py-1 !px-2.5 !text-[12px]" href="{{ route('changes.create', $item['object_id']) }}">Request change</a></td>@endif
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @empty
        <p class="text-sm text-gray-400 italic">This baseline froze no objects.</p>
    @endforelse
@endsection

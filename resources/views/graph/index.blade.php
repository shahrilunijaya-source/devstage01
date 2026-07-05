@extends('layouts.ursb')
@section('title', $project->name.' — objects')
@section('content')
    <p class="text-[13px] text-gray-500 mb-2">
        <a href="{{ route('portfolio.show', $project) }}" class="hover:text-teal">← {{ $project->name }}</a>
    </p>

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-gray-900 tracking-tight">Object graph</h1>
            <p class="text-[13px] text-gray-500 mt-0.5">{{ $total }} object{{ $total === 1 ? '' : 's' }} you can see in {{ $project->name }} (PRD §12). Filter, then click any ref for its lineage.</p>
        </div>
    </div>

    <div class="card card-pad mb-6">
        <form method="GET" action="{{ route('objects.index', $project) }}">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Type</label>
                    <select name="type" class="form-select">
                        <option value="">All types</option>
                        @foreach ($types as $type)
                            <option value="{{ $type->value }}" @selected($filters['type'] === $type->value)>
                                {{ $type->label() }}@if($typeCounts->has($type->value)) ({{ $typeCounts->get($type->value) }})@endif
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">Any status</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="mt-4">
                <label class="form-label">Search ref, title or body</label>
                <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="keyword or reference…" class="form-input">
            </div>
            <div class="mt-4 flex gap-2">
                <button class="btn-primary" type="submit">Filter</button>
                @if ($filters['type'] || $filters['status'] || $filters['q'])
                    <a class="btn-secondary" href="{{ route('objects.index', $project) }}">Clear</a>
                @endif
            </div>
        </form>
    </div>

    <div class="card overflow-hidden">
        <table class="data-table">
            <thead><tr><th class="w-[130px]">Ref</th><th>Type</th><th>Title</th><th>Status</th><th>Class.</th><th class="w-[50px]">Ver</th></tr></thead>
            <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td><a href="{{ route('objects.show', $row['object']) }}" class="font-mono text-teal hover:text-teal-700">{{ $row['object']->ref }}</a></td>
                    <td class="text-gray-500">{{ $row['object']->type->label() }}</td>
                    <td class="text-gray-900">{{ $row['title'] }}</td>
                    <td><span class="badge {{ $row['object']->status->value === 'confirmed_by_evidence' ? 'badge-teal' : 'badge-gray' }}">{{ $row['object']->status->label() }}</span></td>
                    <td class="text-gray-500">{{ $row['object']->classification }}</td>
                    <td class="text-gray-500">v{{ $row['object']->current_version }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-center py-10"><span class="text-sm text-gray-400 italic">No objects match.</span></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $rows->links() }}</div>
@endsection

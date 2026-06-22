@extends('layouts.ursb')
@section('title', $project->name.' — objects')
@section('content')
    <p class="sub" style="margin-bottom:6px;">
        <a href="{{ route('portfolio.show', $project) }}">← {{ $project->name }}</a>
    </p>
    <h1 class="page">Object graph</h1>
    <p class="sub">{{ $total }} object{{ $total === 1 ? '' : 's' }} you can see in {{ $project->name }} (PRD §12). Filter, then click any ref for its lineage.</p>

    <section>
        <form method="GET" action="{{ route('objects.index', $project) }}" class="panel">
            <div class="row">
                <div>
                    <label>Type</label>
                    <select name="type">
                        <option value="">All types</option>
                        @foreach ($types as $type)
                            <option value="{{ $type->value }}" @selected($filters['type'] === $type->value)>
                                {{ $type->label() }}@if($typeCounts->has($type->value)) ({{ $typeCounts->get($type->value) }})@endif
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label>Status</label>
                    <select name="status">
                        <option value="">Any status</option>
                        @foreach ($statuses as $status)
                            <option value="{{ $status->value }}" @selected($filters['status'] === $status->value)>{{ $status->label() }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <label>Search ref, title or body</label>
            <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="keyword or reference…">
            <div style="margin-top:16px; display:flex; gap:8px;">
                <button class="btn" type="submit">Filter</button>
                @if ($filters['type'] || $filters['status'] || $filters['q'])
                    <a class="btn ghost" href="{{ route('objects.index', $project) }}">Clear</a>
                @endif
            </div>
        </form>
    </section>

    <section>
        <table>
            <thead><tr><th style="width:130px;">Ref</th><th>Type</th><th>Title</th><th>Status</th><th>Class.</th><th style="width:50px;">Ver</th></tr></thead>
            <tbody>
            @forelse ($rows as $row)
                <tr>
                    <td><a href="{{ route('objects.show', $row['object']) }}"><code>{{ $row['object']->ref }}</code></a></td>
                    <td class="sub" style="margin:0;">{{ $row['object']->type->label() }}</td>
                    <td>{{ $row['title'] }}</td>
                    <td><span class="pill {{ $row['object']->status->value === 'confirmed_by_evidence' ? 'on' : '' }}">{{ $row['object']->status->label() }}</span></td>
                    <td class="sub" style="margin:0;">{{ $row['object']->classification }}</td>
                    <td>v{{ $row['object']->current_version }}</td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">No objects match.</td></tr>
            @endforelse
            </tbody>
        </table>
        <div style="margin-top:14px;">{{ $rows->links() }}</div>
    </section>
@endsection

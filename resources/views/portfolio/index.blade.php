@extends('layouts.ursb')
@section('title', 'Portfolio')
@section('content')
    <h1 class="page">Portfolio</h1>
    <p class="sub">Projects you have access to, grouped by client tenant.</p>

    <div class="cards">
        <div class="card"><div class="n">{{ $counts['projects'] }}</div><div class="l">Projects</div></div>
        <div class="card"><div class="n">{{ $counts['objects'] }}</div><div class="l">Graph objects</div></div>
        <div class="card"><div class="n">{{ $counts['traces'] }}</div><div class="l">Trace edges</div></div>
    </div>

    <section>
        <h2 class="sec">
            <span>Tenants &amp; projects</span>
            @if ($canCreateProject)<a class="btn sm" href="{{ route('portfolio.projects.create') }}">New project</a>@endif
        </h2>

        @forelse ($tenants as $tenant)
            <div class="panel" style="margin-bottom:14px;">
                <div style="font-weight:650; margin-bottom:8px;">{{ $tenant->name }}
                    <span class="pill">{{ $tenant->slug }}</span></div>
                <table>
                    <thead><tr><th>Project</th><th>Code</th><th>Modules</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    @foreach ($tenant->projects as $project)
                        <tr>
                            <td><a href="{{ route('portfolio.show', $project) }}">{{ $project->name }}</a></td>
                            <td><code>{{ $project->code }}</code></td>
                            <td>{{ $project->modules->count() }}</td>
                            <td><span class="pill">{{ $project->status }}</span></td>
                            <td style="text-align:right;"><a class="btn ghost sm" href="{{ route('portfolio.show', $project) }}">Open</a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @empty
            <p class="empty">No accessible projects. Ask an administrator for a scope binding.</p>
        @endforelse
    </section>
@endsection

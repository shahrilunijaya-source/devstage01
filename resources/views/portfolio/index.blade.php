@extends('layouts.ursb')
@section('title', 'Portfolio')
@section('content')
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-gray-900 tracking-tight">Portfolio</h1>
            <p class="text-[13px] text-gray-500 mt-0.5">Projects you have access to, grouped by client tenant.</p>
        </div>
        <div class="flex gap-2">
            <a class="btn-secondary" href="{{ route('portfolio.dashboard') }}">Dashboard</a>
            @if ($canCreateProject)<a class="btn-primary" href="{{ route('portfolio.projects.create') }}">New project</a>@endif
        </div>
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
        <div class="stat-card">
            <div class="stat-value">{{ $counts['projects'] }}</div>
            <div class="stat-label">Projects</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">{{ $counts['objects'] }}</div>
            <div class="stat-label">Graph objects</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">{{ $counts['traces'] }}</div>
            <div class="stat-label">Trace edges</div>
        </div>
    </div>

    <h2 class="section-title mb-3">Tenants &amp; projects</h2>

    @forelse ($tenants as $tenant)
        <div class="card mb-4">
            <div class="card-pad pb-3 flex items-center gap-2 border-b border-gray-100">
                <span class="font-semibold text-gray-900">{{ $tenant->name }}</span>
                <span class="badge badge-gray">{{ $tenant->slug }}</span>
            </div>
            <table class="data-table">
                <thead>
                    <tr><th>Project</th><th>Code</th><th>Modules</th><th>Status</th><th></th></tr>
                </thead>
                <tbody>
                @foreach ($tenant->projects as $project)
                    <tr>
                        <td>
                            <a class="font-medium text-gray-900 hover:text-teal transition-colors" href="{{ route('portfolio.show', $project) }}">{{ $project->name }}</a>
                        </td>
                        <td><code class="text-[12px] text-gray-500 font-mono">{{ $project->code }}</code></td>
                        <td>{{ $project->modules->count() }}</td>
                        <td><span class="badge badge-teal">{{ $project->status }}</span></td>
                        <td class="text-right">
                            <a class="btn-secondary" href="{{ route('portfolio.show', $project) }}">Open</a>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @empty
        <p class="text-sm text-gray-400 italic">No accessible projects. Ask an administrator for a scope binding.</p>
    @endforelse
@endsection

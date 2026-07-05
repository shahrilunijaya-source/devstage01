@extends('layouts.ursb')
@section('title', $project->name.' — coverage')
@section('content')
    <div class="mb-3">
        <a href="{{ route('portfolio.show', $project) }}" class="text-[13px] text-gray-500 hover:text-teal transition-colors">← {{ $project->name }}</a>
    </div>

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-gray-900 tracking-tight">Coverage &amp; completeness</h1>
            <p class="text-[13px] text-gray-500 mt-0.5">Item-level traceability gaps (PRD §7). Every requirement should trace back to evidence; every piece of evidence should lead somewhere.</p>
        </div>
        <div class="flex gap-2">
            <a class="btn-secondary" href="{{ route('metrics.show', $project) }}">Metrics</a>
        </div>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 mb-8">
        <div class="stat-card">
            <div class="stat-value">{{ $summary['coverage_pct'] }}%</div>
            <div class="stat-label">Requirement coverage</div>
            <x-meter :value="$summary['coverage_pct']" :color="$summary['coverage_pct'] >= 100 ? 'pine' : 'teal'" class="mt-3" />
        </div>
        <div class="stat-card">
            <div class="stat-value {{ $summary['uncovered'] > 0 ? 'text-flag-500' : '' }}">{{ $summary['uncovered'] }}</div>
            <div class="stat-label">Uncovered reqs</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">{{ $summary['covered'] }}/{{ $summary['requirements'] }}</div>
            <div class="stat-label">Reqs traced</div>
        </div>
        <div class="stat-card">
            <div class="stat-value {{ $summary['orphan'] > 0 ? 'text-flag-500' : '' }}">{{ $summary['orphan'] }}</div>
            <div class="stat-label">Orphan evidence</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">{{ $summary['used'] }}/{{ $summary['evidence'] }}</div>
            <div class="stat-label">Evidence used</div>
        </div>
    </div>

    <section class="mb-8">
        <h2 class="section-title mb-3">Requirement → evidence</h2>
        <div class="card overflow-hidden">
            <table class="data-table">
                <thead>
                    <tr>
                        <th class="w-36">Requirement</th>
                        <th>Title</th>
                        <th>Traces to</th>
                        <th class="w-28">Coverage</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($requirements as $row)
                    <tr>
                        <td><a class="text-teal hover:text-teal-700 transition-colors" href="{{ route('objects.show', $row['object']) }}"><code class="text-[12px]">{{ $row['object']->ref }}</code></a></td>
                        <td class="text-gray-900">{{ $row['object']->title }}</td>
                        <td class="text-gray-500">{{ $row['sources']->isNotEmpty() ? $row['sources']->implode(', ') : '—' }}</td>
                        <td>
                            @if ($row['covered'])
                                <span class="badge badge-teal">covered</span>
                            @else
                                <span class="badge badge-flag">no evidence</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4"><p class="text-sm text-gray-400 italic">No requirements captured yet.</p></td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="mb-8">
        <h2 class="section-title mb-3">Evidence → usage</h2>
        <div class="card overflow-hidden">
            <table class="data-table">
                <thead>
                    <tr>
                        <th class="w-36">Evidence</th>
                        <th>Title</th>
                        <th>Leads to</th>
                        <th class="w-28">Status</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($evidence as $row)
                    <tr>
                        <td><a class="text-teal hover:text-teal-700 transition-colors" href="{{ route('objects.show', $row['object']) }}"><code class="text-[12px]">{{ $row['object']->ref }}</code></a></td>
                        <td class="text-gray-900">{{ $row['object']->title }}</td>
                        <td class="text-gray-500">{{ $row['downstream']->isNotEmpty() ? $row['downstream']->implode(', ') : '—' }}</td>
                        <td>
                            @if ($row['used'])
                                <span class="badge badge-teal">used</span>
                            @else
                                <span class="badge badge-flag">orphan</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="4"><p class="text-sm text-gray-400 italic">No evidence captured yet.</p></td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section>
        <h2 class="section-title mb-3">Question-bank coverage by stage</h2>
        @if ($stages->isEmpty())
            <div class="card card-pad"><p class="text-sm text-gray-400 italic">No stage with a question bank or captured requirements yet.</p></div>
        @else
            <div class="card overflow-hidden">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Stage</th>
                            <th>Module</th>
                            <th class="w-24">Questions</th>
                            <th class="w-28">Requirements</th>
                            <th class="w-28">Coverage</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach ($stages as $row)
                        <tr>
                            <td class="text-gray-900">{{ $row['label'] }}</td>
                            <td class="text-gray-500">{{ $row['module'] ?? '—' }}</td>
                            <td>{{ $row['questions'] }}</td>
                            <td>{{ $row['requirements'] }}</td>
                            <td>{{ $row['coverage_pct'] !== null ? $row['coverage_pct'].'%' : '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
@endsection

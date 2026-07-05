@extends('layouts.ursb')
@section('title', 'Metrics')
@section('content')
    <div class="mb-3">
        <a href="{{ route('portfolio.show', $project) }}" class="text-[13px] text-gray-500 hover:text-teal transition-colors">← {{ $project->name }}</a>
    </div>

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-gray-900 tracking-tight">Quality metrics</h1>
            <p class="text-[13px] text-gray-500 mt-0.5">Advisory signals over the canonical model (PRD §17) — not judgments.</p>
        </div>
    </div>

    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-7 gap-3 mb-8">
        <div class="stat-card">
            <div class="stat-value">{{ $metrics['traceability_completeness'] }}%</div>
            <div class="stat-label">Traceability</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">{{ $metrics['confirmed_pct'] }}%</div>
            <div class="stat-label">Confirmed</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">{{ $metrics['requirements'] }}</div>
            <div class="stat-label">Requirements</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">{{ $metrics['requirements_without_evidence'] }}</div>
            <div class="stat-label">No evidence</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">{{ $metrics['objects'] }}</div>
            <div class="stat-label">Objects</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">{{ $metrics['baseline_churn'] }}</div>
            <div class="stat-label">Baseline churn</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">{{ $metrics['change_requests_applied'] }}/{{ $metrics['change_requests'] }}</div>
            <div class="stat-label">CRs applied</div>
        </div>
    </div>

    <section class="mb-8">
        <h2 class="section-title mb-3">Red flags ({{ count($metrics['red_flags']) }})</h2>
        @forelse ($metrics['red_flags'] as $flag)
            <div class="mb-2.5 rounded-[10px] bg-flag/10 border border-flag/30 px-4 py-3 text-[13px] text-gray-700">
                <strong class="text-flag-500">{{ $flag['label'] }}</strong> — {{ $flag['detail'] }}
            </div>
        @empty
            <div class="card card-pad"><span class="text-sm text-gray-400 italic">No anomalies detected.</span></div>
        @endforelse
    </section>

    <section>
        <h2 class="section-title mb-3">Session metrics</h2>
        <div class="card overflow-hidden">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Session</th>
                        <th>Stage</th>
                        <th>Items</th>
                        <th>Captures</th>
                        <th>Confirm</th>
                        <th>Correct</th>
                        <th>Unresolved</th>
                        <th>Signal</th>
                    </tr>
                </thead>
                <tbody>
                @forelse ($sessions as $row)
                    <tr>
                        <td><a class="text-teal hover:text-teal-700 transition-colors" href="{{ route('sessions.show', $row['session']) }}">{{ $row['session']->title }}</a></td>
                        <td>{{ $row['session']->stage->stage->label() }}</td>
                        <td>{{ $row['metrics']['items'] }}</td>
                        <td>{{ $row['metrics']['captures'] }}</td>
                        <td>{{ $row['metrics']['confirmation_rate'] }}%</td>
                        <td>{{ $row['metrics']['correction_rate'] }}%</td>
                        <td>{{ $row['metrics']['unresolved'] }}</td>
                        <td>
                            @if ($row['metrics']['rubber_stamp'])
                                <span class="badge badge-flag">rubber-stamp?</span>
                            @else
                                <span class="badge badge-teal">ok</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8"><p class="text-sm text-gray-400 italic">No sessions.</p></td>
                    </tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection

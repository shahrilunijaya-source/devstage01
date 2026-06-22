@extends('layouts.ursb')
@section('title', $project->name.' — coverage')
@section('content')
    <p class="sub" style="margin-bottom:6px;">
        <a href="{{ route('portfolio.show', $project) }}">← {{ $project->name }}</a>
    </p>
    <div style="display:flex; align-items:center; justify-content:space-between;">
        <h1 class="page">Coverage &amp; completeness</h1>
        <a class="btn ghost sm" href="{{ route('metrics.show', $project) }}">Metrics</a>
    </div>
    <p class="sub">Item-level traceability gaps (PRD §7). Every requirement should trace back to evidence; every piece of evidence should lead somewhere.</p>

    <div class="cards">
        <div class="card"><div class="n">{{ $summary['coverage_pct'] }}%</div><div class="l">Requirement coverage</div></div>
        <div class="card"><div class="n" style="color:{{ $summary['uncovered'] > 0 ? 'var(--gold)' : 'inherit' }};">{{ $summary['uncovered'] }}</div><div class="l">Uncovered reqs</div></div>
        <div class="card"><div class="n">{{ $summary['covered'] }}/{{ $summary['requirements'] }}</div><div class="l">Reqs traced</div></div>
        <div class="card"><div class="n" style="color:{{ $summary['orphan'] > 0 ? 'var(--gold)' : 'inherit' }};">{{ $summary['orphan'] }}</div><div class="l">Orphan evidence</div></div>
        <div class="card"><div class="n">{{ $summary['used'] }}/{{ $summary['evidence'] }}</div><div class="l">Evidence used</div></div>
    </div>

    <section>
        <h2 class="sec"><span>Requirement → evidence</span></h2>
        <table>
            <thead><tr><th style="width:140px;">Requirement</th><th>Title</th><th>Traces to</th><th style="width:110px;">Coverage</th></tr></thead>
            <tbody>
            @forelse ($requirements as $row)
                <tr>
                    <td><a href="{{ route('objects.show', $row['object']) }}"><code>{{ $row['object']->ref }}</code></a></td>
                    <td>{{ $row['object']->title }}</td>
                    <td class="sub" style="margin:0;">{{ $row['sources']->isNotEmpty() ? $row['sources']->implode(', ') : '—' }}</td>
                    <td>
                        @if ($row['covered'])
                            <span class="pill on">covered</span>
                        @else
                            <span class="pill warn">no evidence</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="empty">No requirements captured yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>

    <section>
        <h2 class="sec"><span>Evidence → usage</span></h2>
        <table>
            <thead><tr><th style="width:140px;">Evidence</th><th>Title</th><th>Leads to</th><th style="width:110px;">Status</th></tr></thead>
            <tbody>
            @forelse ($evidence as $row)
                <tr>
                    <td><a href="{{ route('objects.show', $row['object']) }}"><code>{{ $row['object']->ref }}</code></a></td>
                    <td>{{ $row['object']->title }}</td>
                    <td class="sub" style="margin:0;">{{ $row['downstream']->isNotEmpty() ? $row['downstream']->implode(', ') : '—' }}</td>
                    <td>
                        @if ($row['used'])
                            <span class="pill on">used</span>
                        @else
                            <span class="pill warn">orphan</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="4" class="empty">No evidence captured yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>

    <section>
        <h2 class="sec"><span>Question-bank coverage by stage</span></h2>
        @if ($stages->isEmpty())
            <p class="empty">No stage with a question bank or captured requirements yet.</p>
        @else
            <table>
                <thead><tr><th>Stage</th><th>Module</th><th style="width:90px;">Questions</th><th style="width:110px;">Requirements</th><th style="width:110px;">Coverage</th></tr></thead>
                <tbody>
                @foreach ($stages as $row)
                    <tr>
                        <td>{{ $row['label'] }}</td>
                        <td class="sub" style="margin:0;">{{ $row['module'] ?? '—' }}</td>
                        <td>{{ $row['questions'] }}</td>
                        <td>{{ $row['requirements'] }}</td>
                        <td>{{ $row['coverage_pct'] !== null ? $row['coverage_pct'].'%' : '—' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </section>
@endsection

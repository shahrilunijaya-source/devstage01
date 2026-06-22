@extends('layouts.ursb')
@section('title', $project->name.' — verification & validation')
@section('content')
    <div class="mb-3">
        <a href="{{ route('portfolio.show', $project) }}" class="text-[13px] text-gray-500 hover:text-teal transition-colors">← {{ $project->name }}</a>
    </div>

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-gray-900 tracking-tight">Verification &amp; validation</h1>
            <p class="text-[13px] text-gray-500 mt-0.5">Every requirement proven by the test cases that verify it (PRD §17). Each test case and result is a traceable object.</p>
        </div>
        <div class="flex gap-2">
            <a class="btn-secondary" href="{{ route('verification.csv', $project) }}">Export CSV</a>
            <a class="btn-secondary" href="{{ route('metrics.coverage', $project) }}">Coverage</a>
        </div>
    </div>

    @php
        $statusBadge = fn (string $s) => match ($s) {
            'verified' => 'badge-pine',
            'failing' => 'badge-danger',
            'blocked' => 'badge-flag',
            'pending' => 'badge-teal',
            default => 'badge-gray',
        };
        $outcomeBadge = fn (?string $o) => match ($o) {
            'pass' => 'badge-pine',
            'fail' => 'badge-danger',
            'blocked' => 'badge-flag',
            default => 'badge-gray',
        };
    @endphp

    @if (session('status'))
        <div class="mb-4 rounded-lg bg-pine/10 text-pine text-[13px] px-4 py-2.5">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="mb-4 rounded-lg bg-red-50 text-red-600 text-[13px] px-4 py-2.5">{{ session('error') }}</div>
    @endif

    <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-3 mb-5">
        <div class="stat-card"><div class="stat-value">{{ $summary['verified_pct'] }}%</div><div class="stat-label">Verified</div></div>
        <div class="stat-card"><div class="stat-value">{{ $summary['failing'] }}</div><div class="stat-label">Failing</div></div>
        <div class="stat-card"><div class="stat-value">{{ $summary['unverified'] }}</div><div class="stat-label">Unverified</div></div>
        <div class="stat-card"><div class="stat-value">{{ $summary['cases'] }}</div><div class="stat-label">Test cases</div></div>
        <div class="stat-card"><div class="stat-value">{{ $summary['executed'] }}</div><div class="stat-label">Executed</div></div>
        <div class="stat-card"><div class="stat-value">{{ $summary['pass_rate'] }}%</div><div class="stat-label">Pass rate</div></div>
    </div>

    @if ($rows->isEmpty())
        <div class="card card-pad"><p class="text-sm text-gray-400 italic">No requirements captured yet. Requirements are drafted during sessions, then verified here.</p></div>
    @else
        <div class="space-y-3">
            @foreach ($rows as $row)
                @php $req = $row['requirement']; @endphp
                <div class="card card-pad">
                    <div class="flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <a href="{{ route('objects.show', $req) }}" class="font-mono text-[13px] text-teal hover:text-teal-700">{{ $req->ref }}</a>
                                <span class="badge {{ $statusBadge($row['status']) }}">{{ $row['status'] }}</span>
                            </div>
                            <div class="text-gray-900 text-sm mt-1">{{ $req->title }}</div>
                        </div>
                    </div>

                    @if ($row['cases']->isNotEmpty())
                        <table class="data-table mt-3">
                            <thead>
                                <tr>
                                    <th class="w-[110px]">Test</th>
                                    <th>Case</th>
                                    <th class="w-[90px]">Result</th>
                                    @if ($canValidate)<th class="w-[260px]">Record</th>@endif
                                </tr>
                            </thead>
                            <tbody>
                            @foreach ($row['cases'] as $c)
                                <tr>
                                    <td><a href="{{ route('objects.show', $c['case']) }}" class="font-mono text-[12px] text-teal hover:text-teal-700">{{ $c['case']->ref }}</a></td>
                                    <td class="text-gray-900 text-[13px]">{{ $c['case']->title }}</td>
                                    <td><span class="badge {{ $outcomeBadge($c['outcome']) }}">{{ $c['outcome'] ?? 'not run' }}</span></td>
                                    @if ($canValidate)
                                        <td>
                                            <form method="POST" action="{{ route('verification.results.store', $c['case']) }}" class="flex items-center gap-1.5">
                                                @csrf
                                                <select name="outcome" class="form-select text-[12px] py-1 w-[88px]">
                                                    <option value="pass">pass</option>
                                                    <option value="fail">fail</option>
                                                    <option value="blocked">blocked</option>
                                                </select>
                                                <input type="text" name="note" placeholder="note" class="form-input text-[12px] py-1 flex-1" maxlength="2000">
                                                <button class="btn-pine text-[12px] py-1 px-2.5">Save</button>
                                            </form>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    @else
                        <p class="text-[13px] text-gray-400 italic mt-2">No test cases — this requirement is unverified.</p>
                    @endif

                    @if ($canValidate)
                        <details class="mt-3 group">
                            <summary class="text-[12px] text-teal hover:text-teal-700 cursor-pointer select-none">+ Add test case</summary>
                            <form method="POST" action="{{ route('verification.test-cases.store', $req) }}" class="mt-2 space-y-2">
                                @csrf
                                <input type="text" name="title" placeholder="Test case title" required maxlength="255" class="form-input text-[13px] w-full">
                                <textarea name="steps" placeholder="Steps / expected result (optional)" maxlength="10000" rows="2" class="form-input text-[13px] w-full"></textarea>
                                <button class="btn-primary text-[13px]">Add test case</button>
                            </form>
                        </details>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
@endsection

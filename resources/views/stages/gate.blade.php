@extends('layouts.ursb')
@section('title', $stage->stage->label().' — stage gate')
@section('content')
    <div class="max-w-2xl">
        <div class="mb-3">
            <a href="{{ route('portfolio.show', $project) }}" class="text-[13px] text-gray-500 hover:text-teal transition-colors">← {{ $project->name }}</a>
        </div>

        <h1 class="text-xl font-bold text-gray-900 tracking-tight mb-1">{{ $stage->stage->label() }} stage gate</h1>
        <p class="text-[13px] text-gray-500 mb-6">Readiness check before baselining (PRD §9.5).</p>

        @php
            $check = fn (bool $ok) => $ok
                ? '<span class="badge badge-teal">pass</span>'
                : '<span class="badge badge-danger">not met</span>';
        @endphp

        @if ($baselined)
            <div class="card card-pad mb-5 border-l-4 border-teal">
                <p class="text-[15px] font-semibold text-gray-900">Already baselined.</p>
                <p class="text-[13px] text-gray-500 mt-0.5">This stage is frozen. Further changes require an approved change request.</p>
            </div>
        @elseif ($ready)
            <div class="card card-pad mb-5 border-l-4 border-teal">
                <p class="text-[15px] font-semibold text-gray-900">Ready to baseline.</p>
                <p class="text-[13px] text-gray-500 mt-0.5">All hard gate conditions are met.</p>
                @if ($canBaseline)
                    <form method="POST" action="{{ route('stages.baseline', $stage) }}" class="mt-3">@csrf
                        <button type="submit" class="btn-primary">Baseline {{ $stage->stage->label() }}</button>
                    </form>
                @endif
            </div>
        @else
            <div class="card card-pad mb-5 border-l-4 border-flag">
                <p class="text-[15px] font-semibold text-gray-900">Not ready to baseline.</p>
                <p class="text-[13px] text-gray-500 mt-0.5">Resolve the conditions below first.</p>
            </div>
        @endif

        @if ($canEdit && ! $baselined)
            <form method="POST" action="{{ route('stages.status', $stage) }}" class="card card-pad mb-6">
                @csrf
                <div class="flex flex-wrap items-end gap-3">
                    <div>
                        <label class="form-label">Set status</label>
                        <select name="status" class="form-select">
                            <option value="not_started" @selected($stage->status === 'not_started')>Not started</option>
                            <option value="in_progress" @selected($stage->status === 'in_progress')>In progress</option>
                            <option value="blocked" @selected($stage->status === 'blocked')>Blocked</option>
                        </select>
                    </div>
                    <div class="flex-1 min-w-[200px]">
                        <label class="form-label">Reason (for blocking)</label>
                        <input type="text" name="reason" maxlength="255" class="form-input" placeholder="Why is it blocked…">
                    </div>
                    <button type="submit" class="btn-secondary">Update status</button>
                </div>
                <p class="text-[12px] text-gray-400 mt-2">Setting a stage to <strong>blocked</strong> immediately notifies directors and the project team.</p>
            </form>
        @endif

        <h2 class="section-title mb-2">Hard conditions</h2>
        <div class="card overflow-hidden mb-6">
            <table class="data-table">
                <tbody>
                    <tr>
                        <td class="text-gray-900">Has an approved session</td>
                        <td class="w-[100px] text-right">{!! $check($hasApproved) !!}</td>
                    </tr>
                    <tr>
                        <td class="text-gray-900">All items resolved
                            @if ($unresolved > 0)<span class="text-gray-500 text-[13px]">— {{ $unresolved }} unresolved</span>@endif
                        </td>
                        <td class="text-right">{!! $check($unresolved === 0) !!}</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <h2 class="section-title mb-2">Knowledge Book gate criteria</h2>
        @if ($criteria->isEmpty())
            <div class="card card-pad"><p class="text-sm text-gray-400 italic">No gate criteria defined for this stage in the pinned Knowledge Book.</p></div>
        @else
            <div class="card overflow-hidden">
                <table class="data-table">
                    <tbody>
                    @foreach ($criteria as $c)
                        <tr>
                            <td class="text-gray-900">{{ $c->title }}@if($c->body)<div class="text-[13px] text-gray-500 mt-1">{{ $c->body }}</div>@endif</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <p class="text-[12px] text-gray-400 mt-2">Criteria are advisory — confirm them with the team before baselining.</p>
        @endif
    </div>
@endsection

@extends('layouts.ursb')
@section('title', $stage->stage->label().' — stage gate')
@section('content')
    <div class="max-w-2xl">
        <div class="mb-3">
            <a href="{{ route('portfolio.show', $project) }}" class="text-[13px] text-gray-500 hover:text-teal transition-colors">← {{ $project->name }}</a>
        </div>

        <h1 class="text-xl font-bold text-gray-900 tracking-tight mb-1">{{ $stage->stage->label() }} stage gate</h1>
        <p class="text-[13px] text-gray-500 mb-4">Readiness check before baselining (PRD §9.5). The baseline endpoint enforces these same conditions.</p>

        @if ($guidance = config('guidance.'.$stage->stage->value))
            <div class="card card-pad mb-6" x-data="{ open: false }">
                <button type="button" class="w-full text-left flex items-center justify-between" @click="open = !open">
                    <span class="text-[13px] font-medium text-gray-800">What is the {{ $stage->stage->label() }} stage?</span>
                    <span class="text-[12px] text-teal" x-text="open ? 'hide' : 'show guidance'"></span>
                </button>
                <div class="mt-3 grid gap-2 text-[13px] text-gray-600" x-show="open" x-cloak>
                    <p><span class="font-medium text-gray-800">What it means:</span> {{ $guidance['what'] }}</p>
                    <p><span class="font-medium text-gray-800">Why it matters:</span> {{ $guidance['why'] }}</p>
                    <p><span class="font-medium text-gray-800">Who provides it:</span> {{ $guidance['who'] }}</p>
                    <p><span class="font-medium text-gray-800">A good example:</span> {{ $guidance['good'] }}</p>
                    <p><span class="font-medium text-gray-800">Common mistakes:</span> {{ $guidance['mistakes'] }}</p>
                    <p><span class="font-medium text-gray-800">What happens next:</span> {{ $guidance['next'] }}</p>
                </div>
            </div>
        @endif

        @if ($baselined)
            <div class="card card-pad mb-5 border-l-4 border-teal">
                <p class="text-[15px] font-semibold text-gray-900">Already baselined.</p>
                <p class="text-[13px] text-gray-500 mt-0.5">This stage is frozen. Raise a change request for targeted edits — or reopen the baseline to put the whole stage back into refinement.</p>
                @if ($canBaseline && $stage->currentBaseline)
                    <form method="POST" action="{{ route('baselines.reopen', $stage->currentBaseline) }}" class="mt-3 flex flex-wrap items-end gap-3" x-data="{ open: false }">
                        @csrf
                        <button type="button" class="btn-secondary" x-show="!open" @click="open = true">Reopen {{ $stage->currentBaseline->version_label }}…</button>
                        <template x-if="open">
                            <div class="flex flex-wrap items-end gap-3 w-full">
                                <div class="flex-1 min-w-[240px]">
                                    <label class="form-label">Reason for reopening (recorded)</label>
                                    <input type="text" name="reason" maxlength="500" required class="form-input" placeholder="Why must this baseline be reopened…">
                                </div>
                                <button type="submit" class="btn-danger">Reopen baseline</button>
                            </div>
                        </template>
                    </form>
                @endif
            </div>
        @elseif ($gate['ready'])
            <div class="card card-pad mb-5 border-l-4 border-teal">
                <p class="text-[15px] font-semibold text-gray-900">Ready to baseline.</p>
                <p class="text-[13px] text-gray-500 mt-0.5">All gate conditions are met.</p>
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

                @if ($canBaseline && $gate['blockers'] === [] && $gate['failing_overridable'] !== [])
                    <form method="POST" action="{{ route('stages.baseline', $stage) }}" class="mt-4 pt-3 border-t border-gray-100" x-data="{ ack: false }">
                        @csrf
                        <p class="text-[13px] font-medium text-gray-700 mb-2">Baseline with exceptions</p>
                        <label class="flex items-start gap-2 text-[13px] text-gray-600">
                            <input type="checkbox" name="override" value="1" x-model="ack" class="mt-0.5">
                            <span>I accept baselining past: {{ implode(' · ', $gate['failing_overridable']) }}. The exception and my reason will be recorded on the baseline.</span>
                        </label>
                        <div class="mt-2 flex flex-wrap items-end gap-3" x-show="ack" x-cloak>
                            <div class="flex-1 min-w-[240px]">
                                <label class="form-label">Reason (recorded)</label>
                                <input type="text" name="override_reason" maxlength="500" class="form-input" placeholder="Why the exception is acceptable…">
                            </div>
                            <button type="submit" class="btn-danger">Baseline with exceptions</button>
                        </div>
                    </form>
                @endif
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

        <h2 class="section-title mb-2">Gate conditions</h2>
        <div class="card overflow-hidden mb-6">
            <table class="data-table">
                <tbody>
                @foreach ($gate['checks'] as $check)
                    <tr>
                        <td class="text-gray-900">
                            {{ $check['label'] }}
                            @if (! $check['pass'] && $check['detail'])
                                <span class="text-gray-500 text-[13px]">— {{ $check['detail'] }}</span>
                            @endif
                            @if (! $check['pass'] && $check['overridable'])
                                <span class="badge badge-gray ml-1">overridable</span>
                            @endif
                        </td>
                        <td class="w-[100px] text-right">
                            @if ($check['pass'])
                                <span class="badge badge-teal">pass</span>
                            @else
                                <span class="badge badge-danger">not met</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
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

        @include('partials.discussions', [
            'discussions' => $discussions,
            'discussableKind' => 'stage',
            'discussableId' => $stage->id,
            'canEdit' => $canEdit,
        ])
    </div>
@endsection

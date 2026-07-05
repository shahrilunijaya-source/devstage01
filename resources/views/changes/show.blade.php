@extends('layouts.ursb')
@section('title', $change->ref)
@section('content')
    <div class="mb-3">
        <a href="{{ route('changes.index', $change->project) }}" class="text-[13px] text-gray-500 hover:text-teal transition-colors">← Change requests</a>
    </div>

    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-gray-900 tracking-tight flex items-center gap-2.5">
                {{ $change->ref }}
                <span class="badge {{ $change->status === 'applied' ? 'badge-pine' : ($change->status === 'rejected' ? 'badge-danger' : ($change->status === 'approved' ? 'badge-teal' : 'badge-flag')) }}">{{ $change->status }}</span>
            </h1>
            <p class="text-[13px] text-gray-500 mt-0.5">{{ $change->title }} · target <code class="text-[12px] text-pine">{{ $change->target->ref }}</code> · raised by {{ $change->raisedBy?->name ?? '—' }}</p>
        </div>
    </div>

    @if ($change->description)
        <div class="card card-pad mb-5 text-[13px] text-gray-700">{{ $change->description }}</div>
    @endif

    @if ($guardian = $change->guardian_assessment)
        <section class="mb-6">
            <h2 class="section-title mb-2">Objective Guardian</h2>
            <div class="card card-pad {{ in_array($guardian['classification'], ['possible_conflict', 'direct_conflict']) ? 'border-l-4 border-flag' : '' }}">
                <p class="mb-2">
                    <span class="badge {{ in_array($guardian['classification'], ['aligned', 'potential_improvement']) ? 'badge-teal' : ($guardian['classification'] === 'insufficient_information' ? 'badge-gray' : 'badge-danger') }}">
                        {{ str_replace('_', ' ', $guardian['classification']) }}
                    </span>
                    @if (! empty($guardian['objective_ref']))<span class="text-[12px] text-gray-400 ml-1">vs objective {{ $guardian['objective_ref'] }}</span>@endif
                    @if (! empty($guardian['confidence']))<span class="badge badge-gray ml-1">{{ $guardian['confidence'] }} confidence</span>@endif
                </p>
                <p class="text-[13px] text-gray-700">{{ $guardian['rationale'] ?? '' }}</p>
                @foreach (['benefits' => 'Benefits', 'risks' => 'Risks', 'questions' => 'Questions to answer'] as $key => $label)
                    @if (! empty($guardian[$key]))
                        <p class="text-[13px] text-gray-800 font-medium mt-2">{{ $label }}:</p>
                        <ul class="list-disc ml-5 text-[13px] text-gray-600">
                            @foreach ($guardian[$key] as $item)<li>{{ $item }}</li>@endforeach
                        </ul>
                    @endif
                @endforeach
                @if ($change->override_reason)
                    <p class="text-[13px] text-gray-700 mt-3 bg-flag/10 border border-flag/30 rounded px-3 py-2">
                        <span class="font-medium">Guardian overridden:</span> {{ $change->override_reason }}
                    </p>
                @endif
                <p class="text-[11px] text-gray-400 mt-2">Advisory only — humans decide. Assessed {{ $guardian['assessed_at'] ?? '' }} (prompt {{ $guardian['prompt_version'] ?? '—' }}).</p>
            </div>
        </section>
    @endif

    <section class="mb-6">
        <h2 class="section-title mb-2">Impact analysis</h2>
        <div class="card card-pad">
            <p class="text-[13px] text-gray-600 mb-2">
                {{ $change->impact['total'] ?? 0 }} affected · {{ $change->impact['downstream'] ?? 0 }} downstream · {{ $change->impact['upstream'] ?? 0 }} upstream
            </p>
            <div class="flex flex-wrap gap-1.5">
                @foreach (($change->impact['affected_refs'] ?? []) as $ref)
                    <span class="badge badge-gray">{{ $ref }}</span>
                @endforeach
                @if (empty($change->impact['affected_refs']))
                    <span class="text-sm text-gray-400 italic">No linked objects.</span>
                @endif
            </div>
        </div>
    </section>

    @if (! empty($change->proposed_changes))
        <section class="mb-6">
            <h2 class="section-title mb-2">Before / after</h2>
            <div class="grid md:grid-cols-2 gap-3 text-[13px]">
                <div class="card card-pad">
                    <p class="text-[11px] uppercase tracking-wide text-gray-400 mb-1">Current ({{ $change->target->ref }} v{{ $change->target->current_version }})</p>
                    <p class="font-medium text-gray-900">{{ $change->target->title }}</p>
                    <p class="text-gray-600 mt-1 whitespace-pre-line">{{ $change->target->body }}</p>
                </div>
                <div class="card card-pad bg-teal/5 border-teal/20">
                    <p class="text-[11px] uppercase tracking-wide text-teal mb-1">Proposed</p>
                    <p class="font-medium text-gray-900">{{ $change->proposed_changes['title'] ?? $change->target->title }}</p>
                    <p class="text-gray-600 mt-1 whitespace-pre-line">{{ $change->proposed_changes['body'] ?? $change->target->body }}</p>
                    @foreach (collect($change->proposed_changes)->except(['title', 'body']) as $field => $value)
                        <p class="text-gray-600 mt-1"><span class="font-medium">{{ $field }}:</span> {{ $value }}</p>
                    @endforeach
                </div>
            </div>
            @if ($change->status === 'applied')
                <p class="text-[12px] text-gray-400 mt-1">Applied — "current" now reflects the change; see the target's version history for the prior state.</p>
            @endif
        </section>
    @endif

    @if ($flagged->isNotEmpty())
        <section class="mb-6">
            <h2 class="section-title mb-2">Downstream impact disposition</h2>
            <div class="card overflow-hidden">
                <table class="data-table">
                    <thead><tr><th class="w-[120px]">Ref</th><th>Title</th><th class="w-[150px]">Impact</th><th class="w-[240px]">Disposition</th></tr></thead>
                    <tbody>
                    @foreach ($flagged as $obj)
                        @php $impact = $obj->getAttribute('attributes')['impact'] ?? 'review_required'; @endphp
                        <tr>
                            <td><a href="{{ route('objects.show', $obj) }}" class="font-mono text-[13px] text-pine hover:underline">{{ $obj->ref }}</a></td>
                            <td class="text-gray-900">{{ $obj->title }}</td>
                            <td><span class="badge {{ $impact === 'no_impact' ? 'badge-teal' : ($impact === 'review_required' ? 'badge-flag' : ($impact === 'invalidated' ? 'badge-danger' : 'badge-gray')) }}">{{ str_replace('_', ' ', $impact) }}</span></td>
                            <td>
                                @if ($impact === 'review_required')
                                    <form method="POST" action="{{ route('changes.disposition', [$change, $obj]) }}" class="flex items-center gap-1.5">
                                        @csrf
                                        <select name="impact" class="form-select text-[12px] py-1">
                                            <option value="no_impact">No impact</option>
                                            <option value="update_required">Update required</option>
                                            <option value="invalidated">Invalidated</option>
                                        </select>
                                        <button type="submit" class="btn-secondary">Set</button>
                                    </form>
                                @else
                                    <span class="text-[12px] text-gray-400">dispositioned</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </section>
    @endif

    <section class="mb-6">
        <h2 class="section-title mb-2">Decision</h2>
        <div class="card card-pad">
            @if ($sodEnabled && $isRaiser && in_array($change->status, ['draft', 'approved']))
                <p class="text-[13px] text-gray-600">Separation of duties: you raised this change, so another authorised user must approve and apply it.</p>
            @elseif ($change->status === 'draft' && $canApprove)
                @php $needsOverride = in_array($change->guardian_assessment['classification'] ?? null, ['direct_conflict'], true); @endphp
                <div class="flex flex-wrap items-end gap-2">
                    <form method="POST" action="{{ route('changes.approve', $change) }}" class="flex flex-wrap items-end gap-2">@csrf
                        @if ($needsOverride)
                            <div class="min-w-[280px]">
                                <label class="form-label">Override reason (required — Guardian flagged a direct conflict)</label>
                                <input type="text" name="override_reason" maxlength="500" required class="form-input">
                            </div>
                        @endif
                        <button class="btn-primary" type="submit">Approve</button>
                    </form>
                    <form method="POST" action="{{ route('changes.reject', $change) }}">@csrf
                        <button class="btn-secondary" type="submit">Reject</button>
                    </form>
                </div>
            @elseif ($change->status === 'approved' && $canApply)
                <form method="POST" action="{{ route('changes.apply', $change) }}" class="flex items-center gap-3">@csrf
                    <button class="btn-pine" type="submit">Apply change</button>
                    <span class="text-[13px] text-gray-500">Versions the target &amp; flags downstream objects for re-confirmation.</span>
                </form>
            @else
                <p class="text-[13px] text-gray-600">Status: <strong class="text-gray-900">{{ $change->status }}</strong>
                    @if ($change->decidedBy) · decided by {{ $change->decidedBy->name }}@endif
                    @if ($change->applied_at) · applied {{ $change->applied_at->toDayDateTimeString() }}@endif
                </p>
            @endif
        </div>
    </section>
@endsection

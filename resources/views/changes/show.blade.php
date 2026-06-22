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
            <h2 class="section-title mb-2">Proposed changes</h2>
            <div class="card overflow-hidden">
                <table class="data-table">
                    <tbody>
                    @foreach ($change->proposed_changes as $field => $value)
                        <tr>
                            <th class="w-32">{{ $field }}</th>
                            <td>{{ $value }}</td>
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
                <div class="flex items-center gap-2">
                    <form method="POST" action="{{ route('changes.approve', $change) }}">@csrf
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

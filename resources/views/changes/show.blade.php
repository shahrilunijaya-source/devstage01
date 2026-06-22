@extends('layouts.ursb')
@section('title', $change->ref)
@section('content')
    <p class="sub" style="margin-bottom:6px;"><a href="{{ route('changes.index', $change->project) }}">← Change requests</a></p>
    <h1 class="page">{{ $change->ref }} <span class="pill {{ $change->status === 'applied' ? 'on' : ($change->status === 'rejected' ? 'warn' : '') }}">{{ $change->status }}</span></h1>
    <p class="sub">{{ $change->title }} · target <code>{{ $change->target->ref }}</code> · raised by {{ $change->raisedBy?->name ?? '—' }}</p>

    @if ($change->description)
        <div class="panel" style="margin-bottom:18px;">{{ $change->description }}</div>
    @endif

    <section>
        <h2 class="sec"><span>Impact analysis</span></h2>
        <div class="panel">
            <p class="sub" style="margin:0 0 8px;">
                {{ $change->impact['total'] ?? 0 }} affected · {{ $change->impact['downstream'] ?? 0 }} downstream · {{ $change->impact['upstream'] ?? 0 }} upstream
            </p>
            <div style="display:flex; flex-wrap:wrap; gap:6px;">
                @foreach (($change->impact['affected_refs'] ?? []) as $ref)<span class="pill">{{ $ref }}</span>@endforeach
                @if (empty($change->impact['affected_refs']))<span class="empty">No linked objects.</span>@endif
            </div>
        </div>
    </section>

    @if (! empty($change->proposed_changes))
        <section>
            <h2 class="sec"><span>Proposed changes</span></h2>
            <table>
                @foreach ($change->proposed_changes as $field => $value)
                    <tr><th style="width:120px;">{{ $field }}</th><td>{{ $value }}</td></tr>
                @endforeach
            </table>
        </section>
    @endif

    <section>
        <h2 class="sec"><span>Decision</span></h2>
        <div class="panel">
            @if ($sodEnabled && $isRaiser && in_array($change->status, ['draft', 'approved']))
                <p class="sub" style="margin:0;">Separation of duties: you raised this change, so another authorised user must approve and apply it.</p>
            @elseif ($change->status === 'draft' && $canApprove)
                <form class="inline" method="POST" action="{{ route('changes.approve', $change) }}">@csrf
                    <button class="btn" type="submit">Approve</button></form>
                <form class="inline" method="POST" action="{{ route('changes.reject', $change) }}" style="margin-left:8px;">@csrf
                    <button class="btn ghost" type="submit">Reject</button></form>
            @elseif ($change->status === 'approved' && $canApply)
                <form method="POST" action="{{ route('changes.apply', $change) }}">@csrf
                    <button class="btn" type="submit">Apply change</button>
                    <span class="sub" style="display:inline; margin-left:10px;">Versions the target &amp; flags downstream objects for re-confirmation.</span>
                </form>
            @else
                <p class="sub" style="margin:0;">Status: <strong>{{ $change->status }}</strong>
                    @if ($change->decidedBy) · decided by {{ $change->decidedBy->name }}@endif
                    @if ($change->applied_at) · applied {{ $change->applied_at->toDayDateTimeString() }}@endif
                </p>
            @endif
        </div>
    </section>
@endsection

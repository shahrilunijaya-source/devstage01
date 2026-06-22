@extends('layouts.ursb')
@section('title', 'Change requests')
@section('content')
    <p class="sub" style="margin-bottom:6px;"><a href="{{ route('portfolio.show', $project) }}">← {{ $project->name }}</a></p>
    <h1 class="page">Change requests</h1>
    <p class="sub">Controlled changes to the canonical model (PRD §9.3.3).</p>

    <section>
        <table>
            <thead><tr><th>Ref</th><th>Target</th><th>Title</th><th>Status</th><th>Raised by</th><th></th></tr></thead>
            <tbody>
            @forelse ($changes as $cr)
                <tr>
                    <td><code>{{ $cr->ref }}</code></td>
                    <td><code>{{ $cr->target->ref }}</code></td>
                    <td>{{ $cr->title }}</td>
                    <td><span class="pill {{ $cr->status === 'applied' ? 'on' : ($cr->status === 'rejected' ? 'warn' : '') }}">{{ $cr->status }}</span></td>
                    <td class="sub" style="margin:0;">{{ $cr->raisedBy?->name ?? '—' }}</td>
                    <td style="text-align:right;"><a class="btn ghost sm" href="{{ route('changes.show', $cr) }}">Open</a></td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">No change requests.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
@endsection

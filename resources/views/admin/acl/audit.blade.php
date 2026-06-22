@extends('layouts.ursb')
@section('title', 'Access audit')
@section('content')
    <p class="sub" style="margin-bottom:6px;"><a href="{{ route('admin.acl.index') }}">← Access Control</a></p>
    <h1 class="page">Access audit</h1>
    <p class="sub">Immutable record of access decisions (PRD §6.4). Latest 200.</p>

    <section>
        <table>
            <thead><tr><th>When</th><th>User</th><th>Decision</th><th>Action</th><th>Object</th><th>Scope</th><th>Reason</th><th>PEP</th></tr></thead>
            <tbody>
            @forelse ($entries as $e)
                <tr>
                    <td class="sub" style="margin:0;">{{ $e->created_at?->format('Y-m-d H:i:s') }}</td>
                    <td>{{ $e->user?->name ?? '#'.$e->user_id }}</td>
                    <td><span class="pill {{ $e->decision === 'permit' ? 'on' : 'warn' }}">{{ $e->decision }}</span></td>
                    <td>{{ $e->action }}</td>
                    <td class="sub" style="margin:0;">{{ $e->object_type }}@if($e->object_id) #{{ $e->object_id }}@endif</td>
                    <td class="sub" style="margin:0;">{{ $e->scope_type }}</td>
                    <td class="sub" style="margin:0;">{{ $e->reason }}</td>
                    <td class="sub" style="margin:0;">{{ $e->pep }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="empty">No access decisions recorded yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
@endsection

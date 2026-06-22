@extends('layouts.ursb')
@section('title', 'Access audit')
@section('content')
    <div class="flex items-center justify-between mb-6">
        <div>
            <p class="text-[13px] mb-1"><a class="text-teal hover:text-teal-700" href="{{ route('admin.acl.index') }}">← Access Control</a></p>
            <h1 class="text-xl font-bold text-gray-900 tracking-tight">Access audit</h1>
            <p class="text-[13px] text-gray-500 mt-0.5">Immutable record of access decisions (PRD §6.4). Latest 200.</p>
        </div>
    </div>

    <div class="card overflow-hidden">
        <table class="data-table">
            <thead><tr><th>When</th><th>User</th><th>Decision</th><th>Action</th><th>Object</th><th>Scope</th><th>Reason</th><th>PEP</th></tr></thead>
            <tbody>
            @forelse ($entries as $e)
                <tr>
                    <td class="text-gray-400 whitespace-nowrap">{{ $e->created_at?->format('Y-m-d H:i:s') }}</td>
                    <td class="font-medium text-gray-900">{{ $e->user?->name ?? '#'.$e->user_id }}</td>
                    <td><span class="badge {{ $e->decision === 'permit' ? 'badge-teal' : 'badge-danger' }}">{{ $e->decision }}</span></td>
                    <td>{{ $e->action }}</td>
                    <td class="text-gray-500">{{ $e->object_type }}@if($e->object_id) #{{ $e->object_id }}@endif</td>
                    <td class="text-gray-500">{{ $e->scope_type }}</td>
                    <td class="text-gray-500">{{ $e->reason }}</td>
                    <td class="text-gray-500 font-mono text-[12px]">{{ $e->pep }}</td>
                </tr>
            @empty
                <tr><td colspan="8"><p class="text-sm text-gray-400 italic py-4">No access decisions recorded yet.</p></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
@endsection

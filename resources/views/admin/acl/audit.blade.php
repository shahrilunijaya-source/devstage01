@extends('layouts.ursb')
@section('title', 'Access audit')
@section('content')
    <div class="flex items-center justify-between mb-6">
        <div>
            <p class="text-[13px] mb-1"><a class="text-teal hover:text-teal-700" href="{{ route('admin.acl.index') }}">← Access Control</a></p>
            <h1 class="text-xl font-bold text-gray-900 tracking-tight">Access audit</h1>
            <p class="text-[13px] text-gray-500 mt-0.5">Immutable record of access decisions (PRD §6.4). {{ $entries->total() }} matching.</p>
        </div>
    </div>

    <form method="GET" action="{{ route('admin.acl.audit') }}" class="card card-pad mb-5">
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3 items-end">
            <div>
                <label class="form-label">Decision</label>
                <select name="decision" class="form-select">
                    <option value="">Any</option>
                    <option value="permit" @selected($filters['decision'] === 'permit')>permit</option>
                    <option value="deny" @selected($filters['decision'] === 'deny')>deny</option>
                </select>
            </div>
            <div>
                <label class="form-label">Action</label>
                <select name="action" class="form-select">
                    <option value="">Any</option>
                    @foreach ($actions as $a)
                        <option value="{{ $a }}" @selected($filters['action'] === $a)>{{ $a }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">User</label>
                <select name="user_id" class="form-select">
                    <option value="">Any</option>
                    @foreach ($users as $u)
                        <option value="{{ $u->id }}" @selected((string) $filters['user_id'] === (string) $u->id)>{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">PEP</label>
                <select name="pep" class="form-select">
                    <option value="">Any</option>
                    @foreach ($peps as $p)
                        <option value="{{ $p }}" @selected($filters['pep'] === $p)>{{ $p }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="form-label">From</label>
                <input type="date" name="from" value="{{ $filters['from'] }}" class="form-input">
            </div>
            <div>
                <label class="form-label">To</label>
                <input type="date" name="to" value="{{ $filters['to'] }}" class="form-input">
            </div>
            <div class="col-span-2 md:col-span-2 lg:col-span-4">
                <label class="form-label">Search reason / object type</label>
                <input type="text" name="q" value="{{ $filters['q'] }}" placeholder="keyword…" class="form-input">
            </div>
            <div class="flex gap-2 col-span-2">
                <button type="submit" class="btn-primary">Filter</button>
                <a href="{{ route('admin.acl.audit') }}" class="btn-secondary">Reset</a>
            </div>
        </div>
    </form>

    <div class="card overflow-hidden">
        <table class="data-table">
            <thead><tr><th>When</th><th>User</th><th>Decision</th><th>Action</th><th>Object</th><th>Scope</th><th>Reason</th><th>IP</th><th>PEP</th></tr></thead>
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
                    <td class="text-gray-400 font-mono text-[12px]">{{ $e->ip }}</td>
                    <td class="text-gray-500 font-mono text-[12px]">{{ $e->pep }}</td>
                </tr>
            @empty
                <tr><td colspan="9"><p class="text-sm text-gray-400 italic py-4">No access decisions match these filters.</p></td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $entries->links() }}</div>
@endsection

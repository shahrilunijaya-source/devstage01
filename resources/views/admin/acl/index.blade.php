@extends('layouts.ursb')
@section('title', 'ACL administration')
@section('content')
    <div class="flex items-center justify-between mb-6">
        <div>
            <h1 class="text-xl font-bold text-gray-900 tracking-tight">Access Control</h1>
            <p class="text-[13px] text-gray-500 mt-0.5">Grant and revoke scope bindings (PRD §6).</p>
        </div>
        <div class="flex gap-2">
            <a class="btn-secondary" href="{{ route('admin.acl.audit') }}">Access audit</a>
        </div>
    </div>

    @if ($errors->any())
        <div class="mb-6 rounded-[10px] bg-red-50 border border-red-200 px-4 py-2.5 text-[13px] text-red-700">{{ $errors->first() }}</div>
    @endif

    <section class="mb-8">
        <h2 class="section-title mb-3">Grant scope binding</h2>
        <form class="card card-pad space-y-4" method="POST" action="{{ route('admin.acl.grant') }}">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">User</label>
                    <select class="form-select" name="user_id" required>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->role }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Role</label>
                    <select class="form-select" name="role_id" required>
                        @foreach ($roles as $role)<option value="{{ $role->id }}">{{ $role->name }}</option>@endforeach
                    </select>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Scope type</label>
                    <select class="form-select" name="scope_type"><option value="project">project</option><option value="tenant">tenant</option></select>
                </div>
                <div>
                    <label class="form-label">Project (for project scope)</label>
                    <select class="form-select" name="project_id">
                        <option value="">—</option>
                        @foreach ($projects as $project)
                            <option value="{{ $project->id }}">{{ $project->tenant->name }} / {{ $project->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div>
                <label class="form-label">Tenant (for tenant scope)</label>
                <select class="form-select" name="tenant_id">
                    <option value="">—</option>
                    @foreach ($tenants as $tenant)<option value="{{ $tenant->id }}">{{ $tenant->name }}</option>@endforeach
                </select>
            </div>
            <div><button class="btn-primary" type="submit">Grant</button></div>
        </form>
    </section>

    <section class="mb-8">
        <h2 class="section-title mb-3">Scope bindings ({{ $bindings->count() }})</h2>
        <div class="card overflow-hidden">
            <table class="data-table">
                <thead><tr><th>User</th><th>Role</th><th>Scope</th><th>Tenant</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse ($bindings as $binding)
                    @php($revoked = $binding->revoked_at !== null)
                    <tr>
                        <td class="font-medium text-gray-900">{{ $binding->user?->name ?? '—' }}</td>
                        <td>{{ $binding->role?->name ?? '—' }}</td>
                        <td class="text-gray-500">
                            {{ $binding->scope_type }}@if($binding->scope_type === 'project') · {{ $projects->firstWhere('id', $binding->scope_id)?->name ?? ('#'.$binding->scope_id) }}@endif
                        </td>
                        <td class="text-gray-500">{{ $binding->tenant?->name ?? '—' }}</td>
                        <td><span class="badge {{ $revoked ? 'badge-gray' : 'badge-teal' }}">{{ $revoked ? 'revoked' : 'active' }}</span></td>
                        <td class="text-right">
                            @unless ($revoked)
                                <form class="inline" method="POST" action="{{ route('admin.acl.revoke', $binding) }}">@csrf
                                    <button class="btn-danger" type="submit">Revoke</button></form>
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6"><p class="text-sm text-gray-400 italic py-4">No scope bindings.</p></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <section class="mb-8">
        <h2 class="section-title mb-3">Delegate access (time-bound)</h2>
        <p class="text-[13px] text-gray-500 mb-3">A delegator temporarily grants their access to a delegate. Auto-expires at the end time; <code class="font-mono text-pine">acl:expire</code> runs hourly (PRD §6.3).</p>
        <form class="card card-pad space-y-4" method="POST" action="{{ route('admin.acl.delegate') }}">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Delegator (gives access)</label>
                    <select class="form-select" name="delegator_id" required>
                        @foreach ($users as $user)<option value="{{ $user->id }}">{{ $user->name }} ({{ $user->role }})</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Delegate (receives access)</label>
                    <select class="form-select" name="delegate_id" required>
                        @foreach ($users as $user)<option value="{{ $user->id }}">{{ $user->name }} ({{ $user->role }})</option>@endforeach
                    </select>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Role</label>
                    <select class="form-select" name="role_id" required>
                        @foreach ($roles as $role)<option value="{{ $role->id }}">{{ $role->name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Ends at</label>
                    <input class="form-input" type="datetime-local" name="ends_at" required>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Scope type</label>
                    <select class="form-select" name="scope_type"><option value="project">project</option><option value="tenant">tenant</option></select>
                </div>
                <div>
                    <label class="form-label">Project (for project scope)</label>
                    <select class="form-select" name="project_id">
                        <option value="">—</option>
                        @foreach ($projects as $project)<option value="{{ $project->id }}">{{ $project->tenant->name }} / {{ $project->name }}</option>@endforeach
                    </select>
                </div>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="form-label">Tenant (for tenant scope)</label>
                    <select class="form-select" name="tenant_id">
                        <option value="">—</option>
                        @foreach ($tenants as $tenant)<option value="{{ $tenant->id }}">{{ $tenant->name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label">Reason (optional)</label>
                    <input class="form-input" type="text" name="reason" maxlength="255" placeholder="e.g. covering leave">
                </div>
            </div>
            <div><button class="btn-pine" type="submit">Delegate</button></div>
        </form>
    </section>

    <section class="mb-8">
        <h2 class="section-title mb-3">Delegations ({{ $delegations->count() }})</h2>
        <div class="card overflow-hidden">
            <table class="data-table">
                <thead><tr><th>Delegator → Delegate</th><th>Role</th><th>Scope</th><th>Window</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @forelse ($delegations as $d)
                    @php($revoked = $d->revoked_at !== null)
                    @php($expired = ! $revoked && $d->ends_at !== null && $d->ends_at->isPast())
                    <tr>
                        <td class="font-medium text-gray-900">{{ $d->delegator?->name ?? '—' }} → {{ $d->delegate?->name ?? '—' }}</td>
                        <td>{{ $d->role?->name ?? '—' }}</td>
                        <td class="text-gray-500">
                            {{ $d->scope_type }}@if($d->scope_type === 'project') · {{ $projects->firstWhere('id', $d->scope_id)?->name ?? ('#'.$d->scope_id) }}@endif
                        </td>
                        <td class="text-gray-500">{{ $d->ends_at?->format('Y-m-d H:i') ?? '—' }}</td>
                        <td>
                            @if ($revoked)<span class="badge badge-gray">revoked</span>
                            @elseif ($expired)<span class="badge badge-flag">expired</span>
                            @else<span class="badge badge-teal">active</span>@endif
                        </td>
                        <td class="text-right">
                            @unless ($revoked)
                                <form class="inline" method="POST" action="{{ route('admin.acl.delegation.revoke', $d) }}">@csrf
                                    <button class="btn-danger" type="submit">Revoke</button></form>
                            @endunless
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6"><p class="text-sm text-gray-400 italic py-4">No delegations.</p></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </section>
@endsection

@extends('layouts.ursb')
@section('title', 'ACL administration')
@section('content')
    <div style="display:flex; align-items:center; justify-content:space-between;">
        <h1 class="page">Access Control</h1>
        <a class="btn ghost sm" href="{{ route('admin.acl.audit') }}">Access audit</a>
    </div>
    <p class="sub">Grant and revoke scope bindings (PRD §6).</p>

    @if ($errors->any())<div class="flash err">{{ $errors->first() }}</div>@endif

    <section>
        <h2 class="sec"><span>Grant scope binding</span></h2>
        <form class="panel" method="POST" action="{{ route('admin.acl.grant') }}">
            @csrf
            <div class="row">
                <div>
                    <label>User</label>
                    <select name="user_id" required>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}">{{ $user->name }} ({{ $user->role }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label>Role</label>
                    <select name="role_id" required>
                        @foreach ($roles as $role)<option value="{{ $role->id }}">{{ $role->name }}</option>@endforeach
                    </select>
                </div>
            </div>
            <div class="row">
                <div>
                    <label>Scope type</label>
                    <select name="scope_type"><option value="project">project</option><option value="tenant">tenant</option></select>
                </div>
                <div>
                    <label>Project (for project scope)</label>
                    <select name="project_id">
                        <option value="">—</option>
                        @foreach ($projects as $project)
                            <option value="{{ $project->id }}">{{ $project->tenant->name }} / {{ $project->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <label>Tenant (for tenant scope)</label>
            <select name="tenant_id">
                <option value="">—</option>
                @foreach ($tenants as $tenant)<option value="{{ $tenant->id }}">{{ $tenant->name }}</option>@endforeach
            </select>
            <div style="margin-top:16px;"><button class="btn" type="submit">Grant</button></div>
        </form>
    </section>

    <section>
        <h2 class="sec"><span>Scope bindings ({{ $bindings->count() }})</span></h2>
        <table>
            <thead><tr><th>User</th><th>Role</th><th>Scope</th><th>Tenant</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse ($bindings as $binding)
                @php($revoked = $binding->revoked_at !== null)
                <tr>
                    <td>{{ $binding->user?->name ?? '—' }}</td>
                    <td>{{ $binding->role?->name ?? '—' }}</td>
                    <td class="sub" style="margin:0;">
                        {{ $binding->scope_type }}@if($binding->scope_type === 'project') · {{ $projects->firstWhere('id', $binding->scope_id)?->name ?? ('#'.$binding->scope_id) }}@endif
                    </td>
                    <td class="sub" style="margin:0;">{{ $binding->tenant?->name ?? '—' }}</td>
                    <td><span class="pill {{ $revoked ? 'warn' : 'on' }}">{{ $revoked ? 'revoked' : 'active' }}</span></td>
                    <td style="text-align:right;">
                        @unless ($revoked)
                            <form class="inline" method="POST" action="{{ route('admin.acl.revoke', $binding) }}">@csrf
                                <button class="btn ghost sm" type="submit">Revoke</button></form>
                        @endunless
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">No scope bindings.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>

    <section>
        <h2 class="sec"><span>Delegate access (time-bound)</span></h2>
        <p class="sub">A delegator temporarily grants their access to a delegate. Auto-expires at the end time; <code>acl:expire</code> runs hourly (PRD §6.3).</p>
        <form class="panel" method="POST" action="{{ route('admin.acl.delegate') }}">
            @csrf
            <div class="row">
                <div>
                    <label>Delegator (gives access)</label>
                    <select name="delegator_id" required>
                        @foreach ($users as $user)<option value="{{ $user->id }}">{{ $user->name }} ({{ $user->role }})</option>@endforeach
                    </select>
                </div>
                <div>
                    <label>Delegate (receives access)</label>
                    <select name="delegate_id" required>
                        @foreach ($users as $user)<option value="{{ $user->id }}">{{ $user->name }} ({{ $user->role }})</option>@endforeach
                    </select>
                </div>
            </div>
            <div class="row">
                <div>
                    <label>Role</label>
                    <select name="role_id" required>
                        @foreach ($roles as $role)<option value="{{ $role->id }}">{{ $role->name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label>Ends at</label>
                    <input type="datetime-local" name="ends_at" required>
                </div>
            </div>
            <div class="row">
                <div>
                    <label>Scope type</label>
                    <select name="scope_type"><option value="project">project</option><option value="tenant">tenant</option></select>
                </div>
                <div>
                    <label>Project (for project scope)</label>
                    <select name="project_id">
                        <option value="">—</option>
                        @foreach ($projects as $project)<option value="{{ $project->id }}">{{ $project->tenant->name }} / {{ $project->name }}</option>@endforeach
                    </select>
                </div>
            </div>
            <div class="row">
                <div>
                    <label>Tenant (for tenant scope)</label>
                    <select name="tenant_id">
                        <option value="">—</option>
                        @foreach ($tenants as $tenant)<option value="{{ $tenant->id }}">{{ $tenant->name }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label>Reason (optional)</label>
                    <input type="text" name="reason" maxlength="255" placeholder="e.g. covering leave">
                </div>
            </div>
            <div style="margin-top:16px;"><button class="btn" type="submit">Delegate</button></div>
        </form>
    </section>

    <section>
        <h2 class="sec"><span>Delegations ({{ $delegations->count() }})</span></h2>
        <table>
            <thead><tr><th>Delegator → Delegate</th><th>Role</th><th>Scope</th><th>Window</th><th>Status</th><th></th></tr></thead>
            <tbody>
            @forelse ($delegations as $d)
                @php($revoked = $d->revoked_at !== null)
                @php($expired = ! $revoked && $d->ends_at !== null && $d->ends_at->isPast())
                <tr>
                    <td>{{ $d->delegator?->name ?? '—' }} → {{ $d->delegate?->name ?? '—' }}</td>
                    <td>{{ $d->role?->name ?? '—' }}</td>
                    <td class="sub" style="margin:0;">
                        {{ $d->scope_type }}@if($d->scope_type === 'project') · {{ $projects->firstWhere('id', $d->scope_id)?->name ?? ('#'.$d->scope_id) }}@endif
                    </td>
                    <td class="sub" style="margin:0;">{{ $d->ends_at?->format('Y-m-d H:i') ?? '—' }}</td>
                    <td>
                        @if ($revoked)<span class="pill warn">revoked</span>
                        @elseif ($expired)<span class="pill warn">expired</span>
                        @else<span class="pill on">active</span>@endif
                    </td>
                    <td style="text-align:right;">
                        @unless ($revoked)
                            <form class="inline" method="POST" action="{{ route('admin.acl.delegation.revoke', $d) }}">@csrf
                                <button class="btn ghost sm" type="submit">Revoke</button></form>
                        @endunless
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="empty">No delegations.</td></tr>
            @endforelse
            </tbody>
        </table>
    </section>
@endsection

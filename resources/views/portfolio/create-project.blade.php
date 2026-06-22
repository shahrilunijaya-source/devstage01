@extends('layouts.ursb')
@section('title', 'New project')
@section('content')
    <p class="sub" style="margin-bottom:6px;"><a href="{{ route('portfolio.index') }}">← Portfolio</a></p>
    <h1 class="page">New project</h1>
    <p class="sub">One project = one system (PRD §5).</p>

    @if ($errors->any())<div class="flash err">{{ $errors->first() }}</div>@endif

    <form class="panel" method="POST" action="{{ route('portfolio.projects.store') }}" style="max-width:560px;">
        @csrf
        <label>Tenant</label>
        <select name="tenant_id" required>
            @foreach ($tenants as $tenant)
                <option value="{{ $tenant->id }}">{{ $tenant->name }} ({{ $tenant->slug }})</option>
            @endforeach
        </select>

        <div class="row">
            <div><label>Project name</label><input name="name" value="{{ old('name') }}" required></div>
            <div><label>Code</label><input name="code" value="{{ old('code') }}" required placeholder="e.g. ERP"></div>
        </div>

        <label>Status</label>
        <select name="status"><option value="active">active</option><option value="archived">archived</option></select>

        <div style="margin-top:18px;"><button class="btn" type="submit">Create project</button></div>
    </form>
@endsection

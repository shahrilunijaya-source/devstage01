@extends('layouts.ursb')
@section('title', 'New project')
@section('content')
    <a class="text-[13px] text-gray-500 hover:text-teal transition-colors inline-flex items-center gap-1 mb-3" href="{{ route('portfolio.index') }}">← Portfolio</a>

    <div class="mb-6">
        <h1 class="text-xl font-bold text-gray-900 tracking-tight">New project</h1>
        <p class="text-[13px] text-gray-500 mt-0.5">One project = one system (PRD §5).</p>
    </div>

    @if ($errors->any())
        <div class="mb-5 rounded-[10px] bg-red-50 border border-red-200 px-4 py-2.5 text-[13px] text-red-700">{{ $errors->first() }}</div>
    @endif

    <form class="card card-pad max-w-xl" method="POST" action="{{ route('portfolio.projects.store') }}">
        @csrf
        <div class="mb-4">
            <label class="form-label">Tenant</label>
            <select class="form-select" name="tenant_id" required>
                @foreach ($tenants as $tenant)
                    <option value="{{ $tenant->id }}">{{ $tenant->name }} ({{ $tenant->slug }})</option>
                @endforeach
            </select>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
            <div>
                <label class="form-label">Project name</label>
                <input class="form-input" name="name" value="{{ old('name') }}" required>
            </div>
            <div>
                <label class="form-label">Code</label>
                <input class="form-input" name="code" value="{{ old('code') }}" required placeholder="e.g. ERP">
            </div>
        </div>

        <div class="mb-6">
            <label class="form-label">Status</label>
            <select class="form-select" name="status">
                <option value="active">active</option>
                <option value="archived">archived</option>
            </select>
        </div>

        <button class="btn-primary" type="submit">Create project</button>
    </form>
@endsection

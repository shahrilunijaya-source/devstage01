@extends('layouts.ursb')
@section('title', 'Raise change request')
@section('content')
    <p class="sub" style="margin-bottom:6px;"><a href="{{ route('portfolio.show', $object->project) }}">← {{ $object->project->name }}</a></p>
    <h1 class="page">Raise change request</h1>
    <p class="sub">Target: <code>{{ $object->ref }}</code> — {{ $object->title }}</p>

    @if ($errors->any())<div class="flash err">{{ $errors->first() }}</div>@endif

    <div class="panel" style="margin-bottom:18px;">
        <strong>Impact analysis (auto-trace)</strong>
        <p class="sub" style="margin:6px 0 0;">
            {{ $impact['total'] }} object(s) affected · {{ $impact['downstream'] }} downstream · {{ $impact['upstream'] }} upstream
        </p>
        @if (! empty($impact['affected_refs']))
            <div style="margin-top:8px; display:flex; flex-wrap:wrap; gap:6px;">
                @foreach ($impact['affected_refs'] as $ref)<span class="pill">{{ $ref }}</span>@endforeach
            </div>
        @endif
    </div>

    <form class="panel" method="POST" action="{{ route('changes.store', $object) }}" style="max-width:640px;">
        @csrf
        <label>Change title</label>
        <input name="title" value="{{ old('title') }}" required>
        <label>Description / rationale</label>
        <textarea name="description" rows="3">{{ old('description') }}</textarea>

        <h2 class="sec" style="margin-top:18px;"><span>Proposed new values (optional)</span></h2>
        <label>New title</label>
        <input name="new_title" value="{{ old('new_title', $object->title) }}">
        <label>New body</label>
        <textarea name="new_body" rows="3">{{ old('new_body', $object->body) }}</textarea>

        <div style="margin-top:18px;"><button class="btn" type="submit">Open change request</button></div>
    </form>
@endsection

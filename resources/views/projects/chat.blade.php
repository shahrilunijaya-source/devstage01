@extends('layouts.app')
@section('page-title', 'Track AI')
@section('page-sub', 'Grounded answers from this project’s documents and data — no guessing')

@php
$initial = $messages->map(fn($m) => [
    'role'      => $m->role,
    'content'   => $m->content,
    'citations' => $m->citations ?? [],
    'grounded'  => (bool) $m->grounded,
    'model'     => $m->model,
])->values();
@endphp

@section('content')
    @include('partials.rag-chat', [
        'title'     => 'Ask about ' . $project->name,
        'askUrl'    => route('projects.chat.ask', $project),
        'canSync'   => $canSync,
        'syncUrl'   => route('projects.chat.sync', $project),
        'statusUrl' => route('projects.chat.sync-status', $project),
        'initial'   => $initial,
    ])
@endsection

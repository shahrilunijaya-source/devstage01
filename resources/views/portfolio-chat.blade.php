@extends('layouts.app')
@section('page-title', 'Track AI — All Projects')
@section('page-sub', 'Grounded answers across every project you can access')

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
        'title'   => 'Ask across your portfolio',
        'sub'     => 'Sources are tagged with their project code. If it’s not in the data, the assistant says so.',
        'askUrl'  => route('portfolio.chat.ask'),
        'canSync' => false,
        'initial' => $initial,
    ])
@endsection

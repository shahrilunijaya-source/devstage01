@extends('layouts.ursb')
@section('title', 'Sign in')
@section('content')
<div style="max-width:380px; margin:8vh auto 0;">
    <h1 class="page" style="text-align:center;">URS<span style="color:var(--accent)">B</span> Platform</h1>
    <p class="sub" style="text-align:center;">Requirement-to-Prototype · sign in</p>

    @if ($errors->any())
        <div class="flash err">{{ $errors->first() }}</div>
    @endif

    <form class="panel" method="POST" action="{{ route('login') }}">
        @csrf
        <label for="email">Email</label>
        <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus>

        <label for="password">Password</label>
        <input id="password" name="password" type="password" required>

        <div style="margin-top:18px;">
            <button class="btn" type="submit" style="width:100%;">Sign in</button>
        </div>
    </form>
    <p class="sub" style="text-align:center; margin-top:14px;">Demo: <code>admin@ursb.test</code> / <code>password</code></p>
</div>
@endsection

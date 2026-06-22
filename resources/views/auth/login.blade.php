@extends('layouts.guest')
@section('title', 'Sign in')
@section('content')
    <div class="mb-6">
        <h2 class="text-2xl font-bold text-gray-900 tracking-tight">Sign in</h2>
        <p class="text-[13px] text-gray-500 mt-1">Enter your credentials to access the URSB Platform</p>
    </div>

    @if (session('status'))
        <div class="mb-4 rounded-[10px] bg-teal/10 border border-teal/30 px-4 py-2.5 text-[13px] text-pine">{{ session('status') }}</div>
    @endif

    <!-- Quick Login -->
    <div class="mb-6">
        <p class="text-[11px] font-semibold text-gray-400 uppercase tracking-widest mb-2">Quick login (demo)</p>
        <div class="grid grid-cols-3 gap-2">
            @foreach ([
                ['label' => 'Admin',    'email' => 'admin@ursb.test',    'badge' => 'bg-pine/10 text-pine'],
                ['label' => 'Director', 'email' => 'director@ursb.test', 'badge' => 'bg-teal/10 text-teal'],
                ['label' => 'PM',       'email' => 'pm@ursb.test',       'badge' => 'bg-gray-100 text-gray-600'],
            ] as $demo)
                <button type="button"
                    onclick="document.getElementById('email').value='{{ $demo['email'] }}'; document.getElementById('password').value='password';"
                    class="flex flex-col items-center py-2.5 px-2 rounded-[10px] border border-gray-200 hover:border-teal hover:bg-teal/5 transition-colors cursor-pointer group">
                    <span class="text-[11px] font-semibold {{ $demo['badge'] }} px-2 py-0.5 rounded-full mb-1 group-hover:ring-1 group-hover:ring-teal/30">{{ $demo['label'] }}</span>
                    <span class="text-[10px] text-gray-400 truncate w-full text-center">{{ $demo['email'] }}</span>
                </button>
            @endforeach
        </div>
    </div>

    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf

        <div>
            <label for="email" class="form-label">Email</label>
            <input id="email" type="email" name="email" value="{{ old('email') }}"
                   class="form-input" required autofocus autocomplete="username" />
            @error('email')
                <p class="mt-1.5 text-[12px] text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="password" class="form-label">Password</label>
            <input id="password" type="password" name="password"
                   class="form-input" required autocomplete="current-password" />
            @error('password')
                <p class="mt-1.5 text-[12px] text-red-600">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex items-center justify-between">
            <label for="remember_me" class="inline-flex items-center gap-2 cursor-pointer">
                <input id="remember_me" type="checkbox"
                       class="rounded border-gray-300 text-teal focus:ring-teal/20" name="remember">
                <span class="text-[13px] text-gray-600">Remember me</span>
            </label>
        </div>

        <button type="submit"
                class="w-full bg-teal hover:bg-teal-700 text-white font-semibold text-sm py-2.5 rounded-[10px] transition-colors mt-2">
            Sign in
        </button>
    </form>

    <p class="text-[12px] text-gray-400 text-center mt-6">Demo password: <code class="text-gray-500">password</code></p>
@endsection

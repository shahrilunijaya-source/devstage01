<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('page-title', 'URSB') — URSB Platform</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        aside.is-collapsed nav a span.label { display: none; }
        aside.is-collapsed nav a { justify-content: center; }
    </style>
    @stack('styles')
</head>
<body class="bg-paper font-sans antialiased text-gray-800"
      x-data="{ navCollapsed: localStorage.getItem('navCollapsed') === '1' }"
      x-init="$watch('navCollapsed', v => localStorage.setItem('navCollapsed', v ? '1' : '0'))">

@auth
    @php($u = auth()->user())
    @include('partials.sidebar')

    <main :class="navCollapsed ? 'ml-16' : 'ml-60'" class="transition-[margin] duration-200 ease-out min-h-screen">
        <header class="bg-white border-b border-gray-100 px-6 lg:px-8 py-5">
            <h1 class="text-xl font-bold text-gray-900 tracking-tight">@yield('page-title', 'URSB')</h1>
            @hasSection('page-sub')
                <p class="text-[13px] text-gray-500 mt-0.5">@yield('page-sub')</p>
            @endif
        </header>
        <div class="max-w-7xl mx-auto px-6 lg:px-8 py-8">
            @if (session('status'))
                <div class="mb-5 rounded-[10px] bg-teal/10 border border-teal/30 px-4 py-2.5 text-[13px] text-pine">{{ session('status') }}</div>
            @endif
            @if (session('error'))
                <div class="mb-5 rounded-[10px] bg-red-50 border border-red-200 px-4 py-2.5 text-[13px] text-red-700">{{ session('error') }}</div>
            @endif
            @yield('content')
        </div>
    </main>
@else
    <main class="min-h-screen max-w-7xl mx-auto px-6 py-8">
        @yield('content')
    </main>
@endauth

</body>
</html>

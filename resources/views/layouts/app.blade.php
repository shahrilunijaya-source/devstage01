<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@unless(app()->environment('production'))[{{ strtoupper(app()->environment()) }}] @endunless@yield('page-title', 'DevStage01') — DevStage01</title>
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

    <div :class="navCollapsed ? 'ml-16' : 'ml-60'" class="flex flex-col min-h-screen transition-[margin] duration-200 ease-out">
        @include('partials.topbar')
        <main class="flex-1">
            <div class="max-w-7xl mx-auto px-6 lg:px-8 py-8">
                @if (session('status'))
                    <div class="mb-5 rounded-[10px] bg-teal/10 border border-teal/30 px-4 py-2.5 text-[13px] text-pine">{{ session('status') }}</div>
                @endif
                @if (session('success'))
                    <div class="mb-5 rounded-[10px] bg-teal/10 border border-teal/30 px-4 py-2.5 text-[13px] text-pine">{{ session('success') }}</div>
                @endif
                @if (session('error'))
                    <div class="mb-5 rounded-[10px] bg-red-50 border border-red-200 px-4 py-2.5 text-[13px] text-red-700">{{ session('error') }}</div>
                @endif
                @yield('content')
            </div>
        </main>
    </div>

    @include('feedback._hub-modal')
@else
    <main class="min-h-screen max-w-7xl mx-auto px-6 py-8">
        @yield('content')
    </main>
@endauth

</body>
</html>

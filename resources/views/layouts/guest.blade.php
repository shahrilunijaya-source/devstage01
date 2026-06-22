<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Sign in') — URSB Platform</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased">
<div class="min-h-screen grid lg:grid-cols-2">
    <!-- Left: pine dark panel -->
    <div class="bg-pine hidden lg:flex flex-col justify-between p-16">
        <div>
            <div class="text-white font-bold text-2xl tracking-tight">
                URS<span class="text-teal">B</span>
            </div>
            <div class="text-white/40 text-xs font-semibold uppercase tracking-widest mt-1">
                Requirement-to-Prototype Platform
            </div>
            @unless(app()->environment('production'))
                <span class="inline-block mt-2 px-1.5 py-0.5 bg-red-500 text-white text-[9px] font-bold uppercase tracking-widest rounded">
                    {{ app()->environment() }}
                </span>
            @endunless
        </div>
        <div>
            <p class="text-white/70 text-sm leading-relaxed max-w-xs">
                Evidence to findings to baselined requirements — one traceable object graph from BRS to working prototype.
            </p>
            <div class="flex gap-2 mt-6">
                <span class="w-2 h-2 rounded-full bg-teal"></span>
                <span class="w-2 h-2 rounded-full bg-white/30"></span>
                <span class="w-2 h-2 rounded-full bg-white/30"></span>
            </div>
        </div>
    </div>
    <!-- Right: light panel -->
    <div class="bg-paper flex items-center justify-center p-8 sm:p-16">
        <div class="w-full max-w-sm">
            @yield('content')
        </div>
    </div>
</div>
</body>
</html>

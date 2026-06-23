<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@unless(app()->environment('production'))[{{ strtoupper(app()->environment()) }}] @endunless@yield('title', 'Sign in') — DevStage01</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased">
<div class="min-h-screen grid lg:grid-cols-2">
    <!-- Left: atmospheric pine panel -->
    <div class="relative hidden lg:flex flex-col justify-between p-16 overflow-hidden"
         style="background:linear-gradient(160deg,#06504b 0%,#003d3a 55%,#01302d 100%);">
        <!-- aurora glow -->
        <div class="absolute inset-0 pointer-events-none"
             style="background:radial-gradient(40rem 28rem at 15% 0%, rgba(0,184,169,0.28), transparent 60%),radial-gradient(36rem 26rem at 90% 100%, rgba(167,139,250,0.16), transparent 55%);"></div>
        <!-- subtle grid texture -->
        <div class="absolute inset-0 opacity-[0.06] pointer-events-none"
             style="background-image:linear-gradient(#fff 1px,transparent 1px),linear-gradient(90deg,#fff 1px,transparent 1px);background-size:42px 42px;"></div>

        <div class="relative">
            <div class="text-white font-bold text-2xl tracking-tight">
                DevStage<span class="text-teal">01</span>
            </div>
            <div class="text-white/40 text-xs font-semibold uppercase tracking-widest mt-1">
                Requirement-to-Prototype Platform
            </div>
            @unless(app()->environment('production'))
                <span class="inline-block mt-3 px-2 py-0.5 bg-red-500 text-white text-[9px] font-bold uppercase tracking-widest rounded">
                    {{ app()->environment() }}
                </span>
            @endunless
        </div>

        <div class="relative">
            <h2 class="text-white text-[28px] font-bold leading-tight tracking-tight max-w-md">
                One traceable object graph,<br><span class="text-teal">evidence to prototype.</span>
            </h2>
            <p class="text-white/55 text-sm leading-relaxed max-w-sm mt-4">
                Capture evidence, draft requirements with AI, validate, baseline, and prove every requirement end-to-end — with deny-by-default access at every seam.
            </p>
            <div class="flex flex-wrap gap-x-5 gap-y-2 mt-7">
                @foreach (['Object graph', 'AI pre-analysis', 'Traceability matrix', 'Verification & defects'] as $feat)
                    <div class="flex items-center gap-2 text-white/70 text-[13px]">
                        <span class="w-1.5 h-1.5 rounded-full bg-teal"></span>{{ $feat }}
                    </div>
                @endforeach
            </div>
        </div>

        <div class="relative text-white/30 text-[11px] tracking-wide">© {{ date('Y') }} DevStage01 · Unijaya</div>
    </div>

    <!-- Right: light panel -->
    <div class="flex items-center justify-center p-8 sm:p-16">
        <div class="w-full max-w-sm animate-rise">
            @yield('content')
        </div>
    </div>
</div>
</body>
</html>

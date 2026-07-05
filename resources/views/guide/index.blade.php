@extends('layouts.app')
@section('page-title', 'How to Use')
@section('page-sub', 'A guided tour of the URSB workflow, end to end')

@section('content')
@php
    // Single source of truth for both the diagram and the detail cards below,
    // so the numbered steps and the pipeline nodes never drift apart.
    $steps = [
        [
            'n' => 1,
            'title' => 'Portfolio & Projects',
            'route' => 'portfolio.index',
            'cta' => 'Open Portfolio',
            'desc' => 'Start here. Create a project, add its modules, and assign the team. Each project is broken into lifecycle stages (Requirements, Design, Prototype, …).',
            'does' => ['Create a project', 'Add modules & team', 'Open a stage'],
            'icon' => 'M3 7h18M3 12h18M3 17h18',
        ],
        [
            'n' => 2,
            'title' => 'Requirement Sessions',
            'route' => null,
            'cta' => 'Inside a stage',
            'desc' => 'A session is where requirements get captured as objects. Run pre-analyze, pass the firewall, start the session, capture each object, scan for conflicts, consolidate, then approve.',
            'does' => ['Pre-analyze → Firewall', 'Capture objects', 'Scan conflicts → Approve'],
            'icon' => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01',
        ],
        [
            'n' => 3,
            'title' => 'Baseline & Documents',
            'route' => null,
            'cta' => 'At the stage gate',
            'desc' => 'When a stage is complete, baseline it. The baseline freezes the approved objects and auto-generates the deliverables — PDF specification and a presentation deck.',
            'does' => ['Pass the stage gate', 'Freeze the baseline', 'Export PDF / deck'],
            'icon' => 'M12 4v16m8-8H4',
        ],
        [
            'n' => 4,
            'title' => 'Verification & RTM',
            'route' => null,
            'cta' => 'Per project',
            'desc' => 'Prove the build matches the spec. Add test cases to requirements, record results, raise and resolve defects, and read the Requirements Traceability Matrix.',
            'does' => ['Add test cases', 'Record results / defects', 'Read the RTM'],
            'icon' => 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z',
        ],
        [
            'n' => 5,
            'title' => 'Change Management',
            'route' => null,
            'cta' => 'Anytime',
            'desc' => 'Requirements evolve. Raise a change request against any object; it routes for approval, then applies — keeping every object version-controlled and audited.',
            'does' => ['Raise a change', 'Approve / reject', 'Apply the change'],
            'icon' => 'M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15',
        ],
    ];
@endphp

<div class="max-w-5xl space-y-8">

    {{-- Intro --}}
    <section class="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm">
        <h2 class="text-[18px] font-bold text-gray-900">What is DevStage01?</h2>
        <p class="mt-2 text-[13.5px] text-gray-600 leading-relaxed">
            DevStage01 is the <strong>Unified Requirements &amp; Specification Baseline (URSB)</strong> platform.
            It captures requirements as structured <em>objects</em>, traces them through the project lifecycle,
            and turns approved work into signed-off baselines and documents. The flow below moves left to right —
            from a new project all the way to a controlled, auditable change.
        </p>
    </section>

    {{-- Pipeline diagram --}}
    <section class="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm">
        <h3 class="text-[12px] font-semibold uppercase tracking-widest text-gray-400 mb-4">The URSB pipeline</h3>

        <div class="overflow-x-auto">
            <svg viewBox="0 0 980 240" class="w-full min-w-[760px]" role="img"
                 aria-label="URSB workflow: Portfolio and Projects, then Requirement Sessions, then Baseline and Documents, then Verification and RTM, with Change Management looping back.">
                <defs>
                    <marker id="arrow" viewBox="0 0 10 10" refX="8" refY="5" markerWidth="7" markerHeight="7" orient="auto-start-reverse">
                        <path d="M0 0 L10 5 L0 10 z" fill="#06504b"/>
                    </marker>
                    <linearGradient id="node" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stop-color="#0a605a"/>
                        <stop offset="100%" stop-color="#033f3b"/>
                    </linearGradient>
                </defs>

                @php
                    $nodes = [
                        ['x' => 20,  'label' => 'Portfolio',   'sub' => '& Projects'],
                        ['x' => 215, 'label' => 'Sessions',    'sub' => 'capture objects'],
                        ['x' => 410, 'label' => 'Baseline',    'sub' => '& documents'],
                        ['x' => 605, 'label' => 'Verification','sub' => '& RTM'],
                    ];
                @endphp

                {{-- Connectors between the four forward nodes --}}
                @foreach ([170, 365, 560] as $cx)
                    <line x1="{{ $cx }}" y1="80" x2="{{ $cx + 47 }}" y2="80" stroke="#06504b" stroke-width="2.5" marker-end="url(#arrow)"/>
                @endforeach

                {{-- Forward nodes --}}
                @foreach ($nodes as $i => $node)
                    <g>
                        <rect x="{{ $node['x'] }}" y="40" width="150" height="80" rx="14" fill="url(#node)"/>
                        <circle cx="{{ $node['x'] + 24 }}" cy="64" r="13" fill="#f5b700"/>
                        <text x="{{ $node['x'] + 24 }}" y="69" text-anchor="middle" font-size="14" font-weight="700" fill="#033f3b">{{ $i + 1 }}</text>
                        <text x="{{ $node['x'] + 46 }}" y="69" font-size="15" font-weight="700" fill="#ffffff">{{ $node['label'] }}</text>
                        <text x="{{ $node['x'] + 16 }}" y="98" font-size="11.5" fill="#a7d8d2">{{ $node['sub'] }}</text>
                    </g>
                @endforeach

                {{-- Change Management node (step 5) sits below, looping back across the whole pipeline --}}
                <g>
                    <rect x="215" y="170" width="345" height="56" rx="14" fill="#fff" stroke="#f5b700" stroke-width="2"/>
                    <circle cx="244" cy="198" r="13" fill="#f5b700"/>
                    <text x="244" y="203" text-anchor="middle" font-size="14" font-weight="700" fill="#033f3b">5</text>
                    <text x="266" y="194" font-size="14" font-weight="700" fill="#033f3b">Change Management</text>
                    <text x="266" y="212" font-size="11.5" fill="#6b7280">raise → approve → apply, fully audited</text>
                </g>

                {{-- Loop arrow: Verification down into Change, Change back up to Sessions --}}
                <path d="M680 120 L680 150 L560 150 L560 168" fill="none" stroke="#9ca3af" stroke-width="2" stroke-dasharray="5 4" marker-end="url(#arrow)"/>
                <path d="M215 198 L150 198 L150 80 L67 80" fill="none" stroke="#9ca3af" stroke-width="2" stroke-dasharray="5 4" marker-end="url(#arrow)"/>
                <text x="120" y="150" font-size="10.5" fill="#9ca3af" transform="rotate(-90 120 150)">re-baseline</text>
            </svg>
        </div>

        <p class="mt-3 text-[12px] text-gray-400">
            Solid arrows = forward flow. Dashed arrows = the change loop: a verified requirement can spawn a change that
            feeds back into a new session and re-baseline.
        </p>
    </section>

    {{-- Step-by-step detail cards --}}
    <section class="space-y-4">
        <h3 class="text-[12px] font-semibold uppercase tracking-widest text-gray-400">Step by step</h3>

        @foreach ($steps as $step)
            <div class="bg-white border border-gray-200 rounded-2xl p-6 shadow-sm flex gap-5">
                <div class="shrink-0 w-11 h-11 rounded-xl bg-pine text-white flex items-center justify-center">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="{{ $step['icon'] }}"/>
                    </svg>
                </div>
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-flag text-pine text-[11px] font-bold">{{ $step['n'] }}</span>
                        <h4 class="text-[15px] font-bold text-gray-900">{{ $step['title'] }}</h4>
                    </div>
                    <p class="mt-1.5 text-[13px] text-gray-600 leading-relaxed">{{ $step['desc'] }}</p>
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        @foreach ($step['does'] as $do)
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-teal/10 text-pine text-[11.5px] font-medium">
                                <span class="w-1.5 h-1.5 rounded-full bg-teal"></span>{{ $do }}
                            </span>
                        @endforeach
                        @if ($step['route'])
                            <a href="{{ route($step['route']) }}"
                               class="ml-auto inline-flex items-center gap-1 text-[12px] font-semibold text-teal-700 hover:text-pine transition-colors">
                                {{ $step['cta'] }}
                                <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                            </a>
                        @else
                            <span class="ml-auto text-[11px] text-gray-400 uppercase tracking-wide">{{ $step['cta'] }}</span>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </section>

    {{-- Footer tip --}}
    <section class="bg-pine/5 border border-pine/15 rounded-2xl p-5">
        <p class="text-[13px] text-pine leading-relaxed">
            <strong>Tip:</strong> stuck on a screen? The
            <span class="font-semibold">Feedback</span> button at the bottom of the sidebar opens the help hub,
            and <span class="font-semibold">Search</span> in the top bar jumps straight to any object by id or title.
        </p>
    </section>

</div>
@endsection

@php
    $u = auth()->user();
@endphp
<aside :class="navCollapsed ? 'w-16 is-collapsed' : 'w-60'"
       class="fixed inset-y-0 left-0 bg-pine flex flex-col z-50 transition-[width] duration-200 ease-out">
    <!-- Brand -->
    <div class="border-b border-white/10 flex items-center justify-between gap-2 px-4 pt-5 pb-4">
        <a href="{{ route('portfolio.index') }}" class="text-white font-bold text-lg tracking-tight" x-show="!navCollapsed" x-cloak>
            URS<span class="text-teal">B</span>
            <span class="block text-white/40 text-[10px] font-semibold uppercase tracking-widest mt-0.5">Platform</span>
        </a>
        <a href="{{ route('portfolio.index') }}" class="text-white font-bold text-lg mx-auto" x-show="navCollapsed" x-cloak>U<span class="text-teal">B</span></a>
        <button type="button" @click="navCollapsed = !navCollapsed"
                class="text-white/50 hover:text-white p-1 rounded hover:bg-white/10 transition-colors shrink-0">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path x-show="!navCollapsed" stroke-linecap="round" stroke-linejoin="round" d="M11 19l-7-7 7-7m8 14l-7-7 7-7"/>
                <path x-show="navCollapsed" stroke-linecap="round" stroke-linejoin="round" d="M13 5l7 7-7 7M5 5l7 7-7 7"/>
            </svg>
        </button>
    </div>

    <!-- Nav links -->
    <nav class="flex-1 overflow-y-auto py-3">
        @php
            $links = [
                ['route' => 'inbox', 'active' => request()->routeIs('inbox'), 'label' => 'Inbox', 'icon' => 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'],
                ['route' => 'portfolio.index', 'active' => request()->routeIs('portfolio.index') || request()->routeIs('portfolio.show'), 'label' => 'Portfolio', 'icon' => 'M3 7h18M3 12h18M3 17h18'],
                ['route' => 'portfolio.dashboard', 'active' => request()->routeIs('portfolio.dashboard'), 'label' => 'Dashboard', 'icon' => 'M4 5h6v6H4zM14 5h6v4h-6zM14 13h6v6h-6zM4 15h6v4H4z'],
                ['route' => 'ursb.dashboard', 'active' => request()->routeIs('ursb.*'), 'label' => 'Object Graph', 'icon' => 'M5 7a2 2 0 100-4 2 2 0 000 4zM19 21a2 2 0 100-4 2 2 0 000 4zM6 6l12 12'],
            ];
        @endphp
        @foreach ($links as $link)
            <a href="{{ route($link['route']) }}"
               @class([
                   'flex items-center gap-3 px-5 py-2.5 text-[13px] font-medium transition-colors',
                   'nav-active' => $link['active'],
                   'text-white/65 hover:bg-white/5 hover:text-white/90' => ! $link['active'],
               ])>
                <svg class="shrink-0 opacity-80" style="width:18px;height:18px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="{{ $link['icon'] }}"/></svg>
                <span class="label">{{ $link['label'] }}</span>
                @if ($link['route'] === 'inbox' && ($inboxCount ?? 0) > 0)
                    <span class="label ml-auto inline-flex items-center justify-center min-w-[18px] h-[18px] px-1 rounded-full bg-flag text-pine text-[10px] font-bold">{{ $inboxCount > 99 ? '99+' : $inboxCount }}</span>
                @endif
            </a>
        @endforeach

        @if ($u->isAdmin())
            <div class="px-5 pt-4 pb-1 text-[10px] font-semibold uppercase tracking-widest text-white/35 nav-group-head">Admin</div>
            <a href="{{ route('admin.acl.index') }}"
               @class([
                   'flex items-center gap-3 px-5 py-2.5 text-[13px] font-medium transition-colors',
                   'nav-active' => request()->routeIs('admin.acl.*'),
                   'text-white/65 hover:bg-white/5 hover:text-white/90' => ! request()->routeIs('admin.acl.*'),
               ])>
                <svg class="shrink-0 opacity-80" style="width:18px;height:18px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 15a3 3 0 100-6 3 3 0 000 6z"/><path stroke-linecap="round" stroke-linejoin="round" d="M19.4 15a1.65 1.65 0 00.33 1.82l.06.06a2 2 0 11-2.83 2.83l-.06-.06a1.65 1.65 0 00-1.82-.33 1.65 1.65 0 00-1 1.51V21a2 2 0 11-4 0v-.09A1.65 1.65 0 008 19.4a1.65 1.65 0 00-1.82.33l-.06.06a2 2 0 11-2.83-2.83l.06-.06a1.65 1.65 0 00.33-1.82 1.65 1.65 0 00-1.51-1H2a2 2 0 110-4h.09A1.65 1.65 0 004.6 8a1.65 1.65 0 00-.33-1.82l-.06-.06a2 2 0 112.83-2.83l.06.06a1.65 1.65 0 001.82.33H9a1.65 1.65 0 001-1.51V2a2 2 0 114 0v.09a1.65 1.65 0 001 1.51 1.65 1.65 0 001.82-.33l.06-.06a2 2 0 112.83 2.83l-.06.06a1.65 1.65 0 00-.33 1.82V9a1.65 1.65 0 001.51 1H22a2 2 0 110 4h-.09a1.65 1.65 0 00-1.51 1z"/></svg>
                <span class="label">Access Control</span>
            </a>
            <a href="{{ route('admin.settings.index') }}"
               @class([
                   'flex items-center gap-3 px-5 py-2.5 text-[13px] font-medium transition-colors',
                   'nav-active' => request()->routeIs('admin.settings.*'),
                   'text-white/65 hover:bg-white/5 hover:text-white/90' => ! request()->routeIs('admin.settings.*'),
               ])>
                <svg class="shrink-0 opacity-80" style="width:18px;height:18px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M10 3v2m0 14v2m7-9h2M3 12h2m12.07-5.07l-1.42 1.42M6.34 17.66l-1.41 1.41m12.73 0l-1.42-1.42M6.34 6.34L4.93 4.93M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                <span class="label">Settings</span>
            </a>
        @endif
    </nav>

    {{-- Feedback — pinned to the bottom of the sidebar (Track-style yellow pill). --}}
    @php($feedbackActive = request()->routeIs('feedback.*') || request()->routeIs('admin.feedback.*'))
    <div class="shrink-0 border-t border-white/10" :class="navCollapsed ? 'p-2' : 'p-3'">
        <button type="button" @click="$dispatch('open-modal', 'feedback-hub')"
                @class([
                    'w-full flex items-center gap-2 rounded-full font-semibold text-[13px] transition-colors ring-1',
                    'bg-flag text-pine ring-flag shadow-sm' => $feedbackActive,
                    'bg-flag/15 text-flag ring-flag/30 hover:bg-flag/25' => ! $feedbackActive,
                ])
                :class="navCollapsed ? 'justify-center px-0 py-2.5' : 'px-4 py-2.5'">
            <svg class="shrink-0" style="width:16px;height:16px" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M8 10h.01M12 10h.01M16 10h.01M21 12a9 9 0 11-3.6-7.2L21 3l-1.2 4.2A8.96 8.96 0 0121 12z"/></svg>
            <span x-show="!navCollapsed" x-cloak>Feedback</span>
        </button>
    </div>
</aside>

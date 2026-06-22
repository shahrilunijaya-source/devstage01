{{-- Sticky top bar with the user menu at the top-right (Track-style). --}}
<header class="bg-white border-b border-gray-200 h-14 flex items-center px-6 lg:px-8 sticky top-0 z-40">
    <div class="flex-1 min-w-0">
        @hasSection('page-title')
            <h1 class="text-[15px] font-semibold text-gray-900 truncate">@yield('page-title')</h1>
            @hasSection('page-sub')
                <p class="text-[12px] text-gray-500 mt-0.5 truncate">@yield('page-sub')</p>
            @endif
        @else
            <h1 class="text-[15px] font-semibold text-gray-900 truncate">@yield('title', 'URSB')</h1>
        @endif
    </div>

    @yield('topbar-actions')

    <div class="flex items-center gap-2 ml-auto">
        @auth
            @php($tbUser = auth()->user())
            {{-- Global feedback trigger — opens the feedback hub modal from any page. --}}
            <button type="button" @click="$dispatch('open-modal', 'feedback-hub')"
                class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-[12px] font-medium text-gray-600 hover:text-gray-900 hover:bg-gray-100 transition-colors">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M7 8h10M7 12h6m-6 8l-3-3H5a2 2 0 01-2-2V5a2 2 0 012-2h14a2 2 0 012 2v10a2 2 0 01-2 2h-7l-3 3z"/></svg>
                <span class="hidden sm:inline">Feedback</span>
            </button>
            <div x-data="{ open: false }" class="relative ml-1">
                <button @click="open = !open"
                    class="flex items-center gap-2 pl-2 pr-2.5 py-1.5 rounded-lg hover:bg-gray-100 transition-colors">
                    <div class="w-7 h-7 rounded-full bg-pine text-white text-[11px] font-semibold flex items-center justify-center">
                        {{ strtoupper(substr($tbUser->name, 0, 1)) }}
                    </div>
                    <div class="hidden sm:block text-left leading-tight">
                        <div class="text-[12px] font-semibold text-gray-900 truncate max-w-[140px]">{{ $tbUser->name }}</div>
                        <div class="text-[10px] text-gray-500 uppercase tracking-wide">{{ $tbUser->system_role ?? $tbUser->role }}</div>
                    </div>
                    <svg class="w-3.5 h-3.5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="open" @click.away="open = false" x-cloak
                    class="absolute right-0 top-11 w-56 bg-white border border-gray-200 rounded-xl shadow-xl z-50 overflow-hidden">
                    <div class="px-4 py-3 border-b border-gray-100">
                        <div class="text-[13px] font-semibold text-gray-900 truncate">{{ $tbUser->name }}</div>
                        <div class="text-[11px] text-gray-500 truncate mt-0.5">{{ $tbUser->email }}</div>
                        <div class="text-[10px] text-gray-400 uppercase tracking-wide mt-1">{{ $tbUser->system_role ?? $tbUser->role }}</div>
                    </div>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit"
                            class="w-full text-left px-4 py-2.5 text-[12px] font-medium text-gray-700 hover:bg-gray-50 hover:text-red-600 transition-colors">
                            Sign out
                        </button>
                    </form>
                </div>
            </div>
        @endauth
    </div>
</header>

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

    @auth
        <form method="GET" action="{{ route('search') }}" class="hidden md:block mr-2">
            <div class="relative">
                <svg class="w-4 h-4 text-gray-400 absolute left-2.5 top-1/2 -translate-y-1/2" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 11a6 6 0 11-12 0 6 6 0 0112 0z"/></svg>
                <input type="search" name="q" placeholder="Search objects…" class="w-56 pl-8 pr-3 py-1.5 text-[13px] bg-gray-50 border border-gray-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-teal/30 focus:border-teal">
            </div>
        </form>
    @endauth

    <div class="flex items-center gap-2 ml-auto">
        @auth
            @php($tbUser = auth()->user())
            @php($tbUnread = $tbUser->notifications()->where('read', false)->count())
            <a href="{{ route('notifications.index') }}" class="relative p-2 rounded-lg hover:bg-gray-100 transition-colors" title="Notifications">
                <svg class="w-5 h-5 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/>
                </svg>
                @if ($tbUnread > 0)
                    <span class="absolute -top-0.5 -right-0.5 min-w-[16px] h-4 px-1 rounded-full bg-flag text-pine text-[10px] font-bold flex items-center justify-center">{{ $tbUnread > 99 ? '99+' : $tbUnread }}</span>
                @endif
            </a>
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

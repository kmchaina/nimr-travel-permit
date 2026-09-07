<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@isset($pageTitle){{ $pageTitle }} · @endisset{{ config('app.name', 'NIMR Travel Permit') }}</title>
    <link rel="icon" type="image/png" href="{{ asset('NIMR.png') }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet"/>
    <link href="https://cdn.jsdelivr.net/npm/flag-icons@7.2.3/css/flag-icons.min.css" rel="stylesheet"/>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased bg-slate-50 text-slate-900">

<div x-data="{
        sideOpen: false,
        collapsed: localStorage.getItem('sidebar-collapsed') === '1',
        toggleCollapse() { this.collapsed = !this.collapsed; localStorage.setItem('sidebar-collapsed', this.collapsed ? '1' : '0'); }
     }"
     class="flex h-screen overflow-hidden">

    {{-- ================================================================== --}}
    {{-- SIDEBAR                                                             --}}
    {{-- ================================================================== --}}
    <aside
        :class="sideOpen ? 'translate-x-0' : '-translate-x-full md:translate-x-0'"
        :style="collapsed ? 'width:64px' : 'width:240px'"
        class="fixed inset-y-0 left-0 z-50 flex flex-col bg-slate-900 transition-all duration-200 ease-in-out md:static md:inset-auto shrink-0"
        style="width:240px">

        {{-- Logo --}}
        <div class="flex items-center gap-4 h-20 px-5 border-b border-slate-800 shrink-0">
            <img src="{{ asset('NIMR.png') }}" alt="NIMR" class="h-11 w-11 object-contain shrink-0">
            <div x-show="!collapsed" class="min-w-0">
                <div class="text-sm font-semibold text-white leading-snug">{{ __('nav.system_name') }}</div>
            </div>
        </div>

        {{-- Navigation --}}
        <nav class="flex-1 overflow-y-auto py-5 px-3 space-y-1.5">
            @php
                $user = auth()->user();

                $pendingCount = 0;
                if ($user->isApprover()) {
                    $pendingCount = \App\Models\TravelRequest::where('current_approver_id', $user->id)
                        ->where('status', 'pending')
                        ->where('requester_id', '!=', $user->id)
                        ->count();
                }

                $navItems = [];
                $navItems[] = ['route' => 'dashboard', 'label' => __('nav.dashboard'), 'icon' => 'home', 'badge' => null, 'pattern' => 'dashboard'];

                if ($user->isHr() || $user->isDirectorGeneral()) {
                    $navItems[] = ['route' => 'travel-requests.index', 'label' => __('nav.all_requests'), 'icon' => 'document-list', 'badge' => null, 'pattern' => 'travel-requests.*'];
                    if ($user->isHr()) {
                        // An HR officer oversees everyone's requests and also travels
                        // themselves. Grouping them with the DG took away the only way
                        // to start their own request — the DG has no approval chain to
                        // submit through, but an HR officer does.
                        $navItems[] = ['route' => 'travel-requests.create', 'label' => __('nav.new_request'), 'icon' => 'plus-circle', 'badge' => null, 'pattern' => 'travel-requests.create'];
                        $navItems[] = ['route' => 'hr.reports.index', 'label' => __('nav.hr_reports'), 'icon' => 'chart-bar', 'badge' => null, 'pattern' => 'hr.reports.*'];
                    }
                    if ($user->isDirectorGeneral()) {
                        $navItems[] = ['route' => 'travel-reports.index', 'label' => __('nav.travel_reports'), 'icon' => 'chart-bar', 'badge' => null, 'pattern' => 'travel-reports.*'];
                        $navItems[] = ['route' => 'approvals.index', 'label' => __('nav.approvals'), 'icon' => 'check-circle', 'badge' => $pendingCount ?: null, 'pattern' => 'approvals.*'];
                    }
                } else {
                    $navItems[] = ['route' => 'travel-requests.index', 'label' => __('nav.my_requests'), 'icon' => 'document-list', 'badge' => null, 'pattern' => 'travel-requests.index'];
                    $navItems[] = ['route' => 'travel-requests.create', 'label' => __('nav.new_request'), 'icon' => 'plus-circle', 'badge' => null, 'pattern' => 'travel-requests.create'];
                    if ($user->isApprover()) {
                        $navItems[] = ['route' => 'approvals.index', 'label' => __('nav.approvals'), 'icon' => 'check-circle', 'badge' => $pendingCount ?: null, 'pattern' => 'approvals.*'];
                    }
                    if ($user->isSystemAdmin()) {
                        $navItems[] = ['route' => 'travel-reports.index', 'label' => __('nav.travel_reports'), 'icon' => 'chart-bar', 'badge' => null, 'pattern' => 'travel-reports.*'];
                    }
                }
            @endphp

            @foreach ($navItems as $item)
            @php $active = request()->routeIs($item['pattern']); @endphp
            <a href="{{ route($item['route']) }}"
               title="{{ $item['label'] }}"
               class="group flex items-center gap-3 px-3 py-3 rounded-lg text-sm transition-all
                      {{ $active ? 'text-white' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}"
               style="{{ $active ? 'background-color:#05499c;' : '' }}"">
                <span class="shrink-0 w-5 h-5">
                    @if ($item['icon'] === 'home')
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg>
                    @elseif ($item['icon'] === 'document-list')
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    @elseif ($item['icon'] === 'plus-circle')
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    @elseif ($item['icon'] === 'check-circle')
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    @elseif ($item['icon'] === 'chart-bar')
                        <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg>
                    @endif
                </span>
                <span class="flex-1 truncate" x-show="!collapsed">{{ $item['label'] }}</span>
                @if (!empty($item['badge']))
                <span x-show="!collapsed"
                      class="ml-auto px-1.5 py-0.5 rounded-full text-[10px] font-bold {{ $active ? 'bg-white' : 'bg-amber-500 text-white' }}"
                      style="{{ $active ? 'color:#05499c;' : '' }}">
                    {{ $item['badge'] }}
                </span>
                @endif
            </a>
            @endforeach

            {{-- Admin section --}}
            @if ($user->isSystemAdmin())
            <div class="pt-4 pb-1">
                <p x-show="!collapsed" class="px-3 text-[10px] font-semibold text-slate-600 uppercase tracking-widest mb-1">{{ __('nav.administration') }}</p>
            </div>
            @php $uActive = request()->routeIs('users.*'); @endphp
            <a href="{{ route('users.index') }}" title="{{ __('nav.users') }}"
               class="group flex items-center gap-3 px-3 py-3 rounded-lg text-sm transition-all
                      {{ $uActive ? 'text-white' : 'text-slate-400 hover:text-white hover:bg-slate-800' }}"
               style="{{ $uActive ? 'background-color:#05499c;' : '' }}">
                <span class="shrink-0 w-5 h-5">
                    <svg fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                </span>
                <span x-show="!collapsed">{{ __('nav.users') }}</span>
            </a>
            @endif
        </nav>

    </aside>

    {{-- Mobile overlay --}}
    <div x-show="sideOpen" @click="sideOpen = false" x-cloak
         class="fixed inset-0 z-40 bg-black/50 backdrop-blur-sm md:hidden"></div>

    {{-- ================================================================== --}}
    {{-- MAIN CONTENT                                                        --}}
    {{-- ================================================================== --}}
    <div class="flex-1 flex flex-col min-w-0 overflow-hidden">

        {{-- Top bar --}}
        <header class="h-14 bg-white border-b border-slate-200 flex items-center px-4 gap-2 shrink-0 z-30">
            {{-- Mobile hamburger --}}
            <button @click="sideOpen = !sideOpen"
                    class="md:hidden p-2 rounded-lg text-slate-500 hover:bg-slate-100 hover:text-slate-700 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
            </button>
            {{-- Desktop collapse --}}
            <button @click="toggleCollapse()"
                    class="hidden md:flex p-2 rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h8M4 18h16"/></svg>
            </button>

            {{-- Page title --}}
            <div class="flex-1 min-w-0 px-2">
                @php
                    $topbarUser = auth()->user();
                    $topbarTitle = match(true) {
                        request()->routeIs('dashboard')               => __('nav.dashboard'),
                        request()->routeIs('travel-requests.create')  => __('nav.new_request'),
                        request()->routeIs('travel-requests.edit')    => __('nav.edit_request'),
                        request()->routeIs('travel-requests.show')    => __('nav.view_request'),
                        request()->routeIs('travel-requests.*')       => ($topbarUser->isHr() || $topbarUser->isDirectorGeneral()) ? __('nav.all_requests') : __('nav.my_requests'),
                        request()->routeIs('approvals.*')             => __('nav.approvals'),
                        request()->routeIs('hr.reports.*')            => __('nav.hr_reports'),
                        request()->routeIs('travel-reports.*')        => __('nav.travel_reports'),
                        request()->routeIs('users.*')                 => __('nav.users'),
                        request()->routeIs('profile.*')               => __('nav.profile'),
                        default                                       => config('app.name'),
                    };
                @endphp
                <h1 class="text-sm font-semibold text-slate-700 truncate">{{ $topbarTitle }}</h1>
            </div>

            {{-- Right actions --}}
            @php
                $headerUser = auth()->user();
                $headerRoleColors = [
                    'staff'            => ['#f1f5f9','#475569'],
                    'head'             => ['#eff6ff','#1d4ed8'],
                    'manager'          => ['#f5f3ff','#6d28d9'],
                    'supervisor'       => ['#f5f3ff','#7e22ce'],
                    'director'         => ['#eef2ff','#4338ca'],
                    'centre_manager'   => ['#ecfeff','#0891b2'],
                    'director_general' => ['#fffbeb','#b45309'],
                    'hr'               => ['#f0fdf4','#15803d'],
                    'system_admin'     => ['#0f172a','#ffffff'],
                ];
                $hrc = $headerRoleColors[$headerUser->role] ?? $headerRoleColors['staff'];
            @endphp
            <div class="flex items-center gap-2 shrink-0">

                {{-- Language toggle — segmented control --}}
                <div class="flex items-center gap-0.5 rounded-lg border border-slate-200 bg-slate-100 p-0.5">
                    <a href="{{ route('locale.switch', 'sw') }}" title="Kiswahili"
                       class="flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-semibold transition"
                       style="{{ app()->getLocale() === 'sw' ? 'background:#0f8a4b;color:#ffffff;' : 'color:#64748b;' }}">
                        <span class="fi fi-tz fis rounded-sm" style="font-size:11px;"></span>
                        SW
                    </a>
                    <a href="{{ route('locale.switch', 'en') }}" title="English"
                       class="flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-semibold transition"
                       style="{{ app()->getLocale() === 'en' ? 'background:#0f8a4b;color:#ffffff;' : 'color:#64748b;' }}">
                        <span class="fi fi-gb fis rounded-sm" style="font-size:11px;"></span>
                        EN
                    </a>
                </div>

                {{-- Notification bell (pending approvals) --}}
                @if ($headerUser->isApprover() && isset($pendingCount) && $pendingCount > 0)
                <a href="{{ route('approvals.index') }}"
                   class="relative p-2 rounded-lg text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"/></svg>
                    <span class="absolute top-1 right-1 h-2 w-2 rounded-full bg-red-500"></span>
                </a>
                @endif

                {{-- User profile dropdown --}}
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open"
                            class="group flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-1.5 shadow-sm transition hover:border-slate-300">
                        <span class="hidden sm:block max-w-[150px] truncate text-sm font-semibold text-slate-700">{{ $headerUser->name }}</span>
                        <svg class="h-3.5 w-3.5 shrink-0 text-slate-400 transition group-hover:text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                    </button>

                    <div x-show="open" @click.outside="open = false" x-cloak
                         class="absolute right-0 mt-1.5 w-56 rounded-xl bg-white border border-slate-200 shadow-lg overflow-hidden z-50">
                        {{-- Dropdown header: name + role badge + email --}}
                        <div class="px-4 py-3 border-b border-slate-100">
                            <div class="flex items-center justify-between gap-2 mb-1">
                                <p class="text-sm font-bold text-slate-800 truncate">{{ $headerUser->name }}</p>
                                <span class="shrink-0 inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold"
                                      style="background:{{ $hrc[0] }};color:{{ $hrc[1] }};">
                                    {{ __('common.role_' . $headerUser->role) }}
                                </span>
                            </div>
                            <p class="text-xs text-slate-400 truncate">{{ $headerUser->email }}</p>
                        </div>
                        <a href="{{ route('profile.edit') }}"
                           class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 transition">
                            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/></svg>
                            {{ __('nav.profile') }}
                        </a>
                        <div class="border-t border-slate-100">
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button class="w-full flex items-center gap-2.5 px-4 py-2.5 text-sm text-red-500 hover:bg-red-50 transition">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                                    {{ __('nav.logout') }}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>

            </div>
        </header>

        {{-- Page content --}}
        <main class="flex-1 overflow-y-auto">
            {{ $slot }}
        </main>

        {{-- Global error toast --}}
        @if (session('error'))
        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 5000)"
             x-transition:leave="transition ease-in duration-300"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 translate-y-2"
             class="fixed bottom-5 right-5 z-50 flex items-center gap-3 px-4 py-3.5 bg-red-600 text-white rounded-xl shadow-xl text-sm max-w-sm">
            <svg class="w-5 h-5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
            <span class="flex-1">{{ session('error') }}</span>
            <button @click="show = false" class="opacity-70 hover:opacity-100">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
        @endif
    </div>
</div>
</body>
</html>

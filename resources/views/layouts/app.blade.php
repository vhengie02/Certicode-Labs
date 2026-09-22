<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="Certicode Labs - Interactive coding challenges, virtual laboratory environments, and automated competency verification.">
    <meta name="theme-color" content="#0f0f0f">
    <meta http-equiv="Content-Security-Policy" content="default-src 'self' https: data: blob: 'unsafe-inline' 'unsafe-eval'; script-src 'self' https: 'unsafe-inline' 'unsafe-eval' blob: data:; style-src 'self' https: 'unsafe-inline'; font-src 'self' https: data:; img-src 'self' https: data: blob:; connect-src 'self' https: ws: wss:;">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="alternate icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <title>@yield('title', 'Certicode Labs') - Certicode Labs</title>

    <!-- Immediate Theme Initialization (No-FOUC) -->
    <script>
        (function() {
            try {
                const stored = localStorage.getItem('theme') || 'system';
                const systemDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                const isDark = stored === 'dark' || (stored === 'system' && systemDark);
                if (isDark) {
                    document.documentElement.classList.add('dark');
                    document.documentElement.classList.remove('light');
                    document.documentElement.style.colorScheme = 'dark';
                } else {
                    document.documentElement.classList.remove('dark');
                    document.documentElement.classList.add('light');
                    document.documentElement.style.colorScheme = 'light';
                }
            } catch (e) {}
        })();
    </script>

    <!-- Vite Compiled Assets -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <!-- Google Fonts: Inter (Circular-like geometric sans) & Source Code Pro -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Source+Code+Pro:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">
    <style>
        @font-face {
            font-family: 'Brick Sans';
            src: url('/fonts/BrickSans-Bold.otf') format('opentype');
            font-weight: bold;
            font-style: normal;
            font-display: swap;
        }
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #0f0f0f;
            color: #ededed;
        }
        .glass-panel, .surface-panel {
            background-color: #171717;
            border: 1px solid #2e2e2e;
            border-radius: 8px;
        }
        .glass-card, .surface-card {
            background-color: #171717;
            border: 1px solid #2e2e2e;
            border-radius: 8px;
            transition: border-color 150ms ease, background-color 150ms ease;
        }
        .glass-card:hover, .surface-card:hover {
            border-color: rgba(62, 207, 142, 0.35);
            background-color: #1c1c1c;
            box-shadow: none !important;
        }
        /* Supabase Pill CTA */
        .btn-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 9999px;
            background-color: #3ecf8e;
            color: #0f0f0f;
            font-weight: 600;
            font-size: 0.8125rem;
            padding: 0.5rem 1.25rem;
            transition: background-color 150ms ease;
        }
        .btn-pill:hover {
            background-color: #00c573;
        }
        /* Supabase 6px Secondary Button */
        .btn-secondary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            background-color: #171717;
            border: 1px solid #2e2e2e;
            color: #ededed;
            font-weight: 500;
            font-size: 0.8125rem;
            padding: 0.4rem 0.875rem;
            transition: background-color 150ms ease, border-color 150ms ease;
        }
        .btn-secondary:hover {
            background-color: #222222;
            border-color: #3a3a3a;
        }
        /* Uppercase technical tags */
        .tech-tag {
            font-family: 'Source Code Pro', monospace;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-size: 0.6875rem;
            font-weight: 600;
        }
        /* Custom sidebar active state style matching Supabase */
        .sidebar-active-item {
            background-color: #1c1c1c !important;
            border-left: 3px solid #3ecf8e !important;
            border-top-left-radius: 0px !important;
            border-bottom-left-radius: 0px !important;
            color: #ffffff !important;
        }
        /* Custom scrollbar matching Supabase Dark */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        ::-webkit-scrollbar-track {
            background: #0f0f0f;
        }
        ::-webkit-scrollbar-thumb {
            background: #2e2e2e;
            border-radius: 3px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #404040;
        }

        /* Loading Skeletons */
        .skeleton-pulse {
            background: linear-gradient(90deg, #171717 25%, #222222 50%, #171717 75%);
            background-size: 200% 100%;
            animation: skeleton-pulse-anim 1.5s infinite ease-in-out;
            border-radius: 6px;
        }
        @keyframes skeleton-pulse-anim {
            0% { background-position: 200% 0; }
            100% { background-position: -200% 0; }
        }
        html:not(.dark) .skeleton-pulse {
            background: linear-gradient(90deg, #f3f4f6 25%, #e5e7eb 50%, #f3f4f6 75%);
            background-size: 200% 100%;
        }

        /* ==========================================================================
           SUPABASE LIGHT MODE SYSTEM (UI-UX PRO MAX SPECIFICATION)
           Applied dynamically when html does not have .dark or has .light
           ========================================================================== */
        html:not(.dark) body {
            background-color: #f8f9fa !important;
            color: #111827 !important;
        }

        /* 1. Canvas & Surface Backgrounds */
        html:not(.dark) .bg-\[\#0f0f0f\],
        html:not(.dark) .bg-slate-950 {
            background-color: #f8f9fa !important;
        }
        html:not(.dark) .bg-\[\#171717\],
        html:not(.dark) .bg-slate-900 {
            background-color: #ffffff !important;
        }
        html:not(.dark) .bg-\[\#141414\] {
            background-color: #f9fafb !important;
        }
        html:not(.dark) .bg-\[\#1c1c1c\],
        html:not(.dark) .bg-slate-850 {
            background-color: #f3f4f6 !important;
        }
        html:not(.dark) .bg-\[\#101010\],
        html:not(.dark) .bg-\[\#0d0d0d\] {
            background-color: #f8fafc !important;
        }
        html:not(.dark) .bg-\[\#222222\],
        html:not(.dark) .bg-slate-800 {
            background-color: #e5e7eb !important;
        }

        /* 2. Crisp 1px Hairline Borders */
        html:not(.dark) .border-\[\#2e2e2e\],
        html:not(.dark) .border-\[\#2e2e2e\]\/30,
        html:not(.dark) .border-slate-800,
        html:not(.dark) .border-slate-850,
        html:not(.dark) .border-slate-700 {
            border-color: #e5e7eb !important;
        }
        html:not(.dark) .border-\[\#232323\],
        html:not(.dark) .border-\[\#262626\],
        html:not(.dark) .divide-slate-800,
        html:not(.dark) .divide-slate-800\/60 {
            border-color: #f0f2f5 !important;
        }
        html:not(.dark) .border-\[\#383838\] {
            border-color: #d1d5db !important;
        }

        /* 3. Typography & High-Contrast Readability (WCAG 4.5:1+) */
        html:not(.dark) .text-\[\#ededed\],
        html:not(.dark) .text-slate-200 {
            color: #111827 !important;
        }
        html:not(.dark) .text-\[\#a3a3a3\],
        html:not(.dark) .text-slate-300 {
            color: #374151 !important;
        }
        html:not(.dark) .text-\[\#888888\],
        html:not(.dark) .text-slate-400 {
            color: #4b5563 !important;
        }
        html:not(.dark) .text-\[\#666666\],
        html:not(.dark) .text-slate-500 {
            color: #6b7280 !important;
        }
        html:not(.dark) h1.text-white,
        html:not(.dark) h2.text-white,
        html:not(.dark) h3.text-white,
        html:not(.dark) h4.text-white,
        html:not(.dark) p.text-white,
        html:not(.dark) header span.text-white {
            color: #111827 !important;
        }

        /* 4. Signature Emerald Accent (Deepened for WCAG contrast on Light Canvas) */
        html:not(.dark) .text-\[\#3ecf8e\] {
            color: #059669 !important;
        }
        html:not(.dark) .btn-pill,
        html:not(.dark) .bg-\[\#3ecf8e\] {
            background-color: #059669 !important;
            color: #ffffff !important;
        }
        html:not(.dark) .btn-pill:hover,
        html:not(.dark) .hover\:bg-\[\#00c573\]:hover {
            background-color: #047857 !important;
            color: #ffffff !important;
        }
        html:not(.dark) .hover\:border-\[\#3ecf8e\]\/35:hover,
        html:not(.dark) .hover\:border-\[\#3ecf8e\]\/50:hover {
            border-color: rgba(5, 150, 105, 0.4) !important;
        }

        /* 5. Panels, Cards & Interactive Surfaces */
        html:not(.dark) .glass-panel,
        html:not(.dark) .surface-panel,
        html:not(.dark) .glass-card,
        html:not(.dark) .surface-card {
            background-color: #ffffff !important;
            border: 1px solid #e5e7eb !important;
            box-shadow: 0 1px 3px 0 rgba(0, 0, 0, 0.04) !important;
        }
        html:not(.dark) .glass-card:hover,
        html:not(.dark) .surface-card:hover {
            border-color: rgba(5, 150, 105, 0.4) !important;
            background-color: #ffffff !important;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05) !important;
        }

        /* 6. Form Controls & Dropdown Inputs */
        html:not(.dark) input[type="text"],
        html:not(.dark) input[type="email"],
        html:not(.dark) input[type="password"],
        html:not(.dark) input[type="number"],
        html:not(.dark) select,
        html:not(.dark) textarea {
            background-color: #ffffff !important;
            border: 1px solid #d1d5db !important;
            color: #111827 !important;
        }
        html:not(.dark) input::placeholder,
        html:not(.dark) textarea::placeholder {
            color: #9ca3af !important;
        }
        html:not(.dark) select option {
            background-color: #ffffff !important;
            color: #111827 !important;
        }
        html:not(.dark) input:focus,
        html:not(.dark) select:focus,
        html:not(.dark) textarea:focus {
            border-color: #059669 !important;
            box-shadow: 0 0 0 1px #059669 !important;
        }

        /* 7. Switch Toggles */
        html:not(.dark) .sr-only.peer + div {
            background-color: #e5e7eb !important;
            border-color: #d1d5db !important;
        }
        html:not(.dark) .sr-only.peer + div:after {
            background-color: #ffffff !important;
            box-shadow: 0 1px 3px rgba(0,0,0,0.15) !important;
        }
        html:not(.dark) .sr-only.peer:checked + div {
            background-color: #059669 !important;
            border-color: #059669 !important;
        }
        html:not(.dark) .sr-only.peer:checked + div:after {
            background-color: #ffffff !important;
        }

        /* 8. Top Navigation & Dropdown Panels */
        html:not(.dark) header.h-15 {
            background-color: #ffffff !important;
            border-bottom: 1px solid #e5e7eb !important;
        }
        html:not(.dark) header input[placeholder="Search..."] {
            background-color: #f8f9fa !important;
            border-color: #e5e7eb !important;
            color: #111827 !important;
        }
        html:not(.dark) #notifications-dropdown,
        html:not(.dark) #profile-dropdown {
            background-color: #ffffff !important;
            border-color: #e5e7eb !important;
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.05) !important;
        }
        html:not(.dark) #notifications-dropdown .bg-slate-950,
        html:not(.dark) #profile-dropdown .bg-slate-950\/40 {
            background-color: #f9fafb !important;
            border-color: #e5e7eb !important;
        }
        html:not(.dark) #notifications-list a:hover,
        html:not(.dark) #profile-dropdown a:hover,
        html:not(.dark) #profile-dropdown button:hover {
            background-color: #f3f4f6 !important;
            color: #111827 !important;
        }

        /* 9. Search Command Palette Modal */
        html:not(.dark) #search-modal > div:last-child {
            background-color: #ffffff !important;
            border-color: #e5e7eb !important;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15) !important;
        }
        html:not(.dark) #search-modal input {
            color: #111827 !important;
        }
        html:not(.dark) #search-quick-links a:hover,
        html:not(.dark) #search-results a:hover {
            background-color: #f3f4f6 !important;
            color: #111827 !important;
        }
        html:not(.dark) #search-results a.bg-slate-800\/80 {
            background-color: #f3f4f6 !important;
            color: #111827 !important;
        }

        /* 10. Secondary Button & Sidebar Active */
        html:not(.dark) .btn-secondary {
            background-color: #ffffff !important;
            border: 1px solid #e5e7eb !important;
            color: #1f2937 !important;
        }
        html:not(.dark) .btn-secondary:hover {
            background-color: #f9fafb !important;
            border-color: #d1d5db !important;
        }
        html:not(.dark) .sidebar-active-item {
            background-color: #f3f4f6 !important;
            border-left: 3px solid #059669 !important;
            color: #111827 !important;
        }

        /* 11. Light Scrollbars */
        html:not(.dark) ::-webkit-scrollbar-track {
            background: #f8f9fa;
        }
        html:not(.dark) ::-webkit-scrollbar-thumb {
            background: #d1d5db;
            border-radius: 3px;
        }
        html:not(.dark) ::-webkit-scrollbar-thumb:hover {
            background: #9ca3af;
        }

        /* 12. Active Theme Selection Cards */
        html:not(.dark) .active-theme-card {
            border-color: #059669 !important;
            background-color: #ffffff !important;
            box-shadow: 0 0 0 1px #059669 !important;
        }
        html:not(.dark) .theme-check-badge {
            background-color: #059669 !important;
            color: #ffffff !important;
        }
        html:not(.dark) #theme-status-indicator {
            background-color: #ecfdf5 !important;
            border-color: #a7f3d0 !important;
            color: #047857 !important;
        }
        html:not(.dark) #theme-status-indicator span.rounded-full {
            background-color: #059669 !important;
        }
    </style>
</head>
<body class="h-full text-[#ededed] bg-[#0f0f0f] flex flex-col overflow-hidden" data-is-instructor-or-admin="{{ auth()->user() && (auth()->user()->role === 'instructor' || auth()->user()->role === 'admin') ? 'true' : 'false' }}">

    <!-- Main Content Shell -->
    <div class="flex flex-col flex-1 overflow-hidden">
        <!-- Top bar (60px tall with hairline border) -->
        <header class="h-15 bg-[#171717] border-b border-[#2e2e2e] flex items-center justify-between px-6 z-50 flex-shrink-0">
            <div class="flex-1 flex items-center justify-between">
                <!-- Left Header: Logo & Branding + Breadcrumbs -->
                <div class="flex items-center space-x-4">
                    <!-- Logo / Link to Dashboard -->
                    <a href="{{ route('dashboard') }}" class="flex items-center space-x-2.5 hover:opacity-95 transition-opacity">
                        <div class="w-7 h-7 rounded-md bg-[#0f0f0f] border border-[#2e2e2e] flex items-center justify-center">
                            <!-- Supabase-inspired emerald icon -->
                            <svg class="w-4 h-4 text-[#3ecf8e]" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M21.362 9.354H12V.396a.396.396 0 0 0-.716-.233L.103 13.916a.396.396 0 0 0 .307.632H9.6v9.056a.396.396 0 0 0 .716.233l11.181-13.753a.396.396 0 0 0-.307-.632z"/>
                            </svg>
                        </div>
                        <span class="text-base font-bold tracking-tight text-white flex items-center gap-1 font-sans">
                            Certicode<span class="text-[#3ecf8e]">Labs</span>
                        </span>
                    </a>
                    
                    <span class="text-[#404040]">/</span>
                    
                    <h2 class="text-xs font-medium text-[#a3a3a3] font-mono tracking-wide uppercase">@yield('page_header', 'Workspace')</h2>
                </div>

                <!-- Center Search Input (GitHub Style) -->
                <div class="hidden lg:block w-80 relative mx-4">
                    <input type="text" id="global-search-bar-input" name="global_search_header" placeholder="Search..." onclick="openSearchModal()" readonly class="w-full h-8 pl-8 pr-12 rounded-md bg-slate-900 border border-slate-800 text-xs text-slate-300 placeholder-slate-500 cursor-pointer hover:border-slate-700 transition-colors">
                    <div class="absolute left-2.5 top-2 text-slate-500 pointer-events-none">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>
                    <div class="absolute right-2 top-1.5 bg-slate-950 border border-slate-800 px-1 py-0.5 rounded text-[9px] text-slate-500 font-mono pointer-events-none">
                        Ctrl+K
                    </div>
                </div>

                <!-- Right Actions & Profile Dropdown -->
                <div class="flex items-center space-x-3">
                    <!-- Quick Theme Toggle Button -->
                    <button id="quick-theme-toggle" onclick="toggleQuickTheme()" title="Toggle theme (Light / Dark)" aria-label="Toggle theme" class="p-1.5 text-slate-400 hover:text-white rounded-lg hover:bg-slate-900 border border-slate-850 transition focus:outline-none flex items-center justify-center">
                        <!-- Sun icon (shown when dark, click to switch to light) -->
                        <svg id="theme-toggle-sun" class="w-4 h-4 hidden text-[#3ecf8e]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                        <!-- Moon icon (shown when light, click to switch to dark) -->
                        <svg id="theme-toggle-moon" class="w-4 h-4 hidden text-[#059669]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                        </svg>
                    </button>

                    <!-- Notification Bell and Dropdown -->
                    <div class="relative" id="notification-bell-container">
                        @php
                            $currentUserId = auth()->id();
                            $cachedNotificationsData = \Illuminate\Support\Facades\Cache::store('file')->remember("user_notifs_summary_{$currentUserId}", 30, function () {
                                /** @var \App\Models\User|null $u */
                                $u = auth()->user();
                                return [
                                    'count' => $u ? $u->unreadNotifications()->count() : 0,
                                    'items' => $u ? $u->notifications()->take(5)->get() : collect(),
                                ];
                            });
                            $unreadCount = $cachedNotificationsData['count'] ?? 0;
                            $notifications = $cachedNotificationsData['items'] ?? collect();
                        @endphp
                        <button onclick="toggleNotifications()" aria-label="View notifications" class="relative p-1.5 text-slate-400 hover:text-white rounded-lg hover:bg-slate-900 border border-slate-850 transition focus:outline-none">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                            @if($unreadCount > 0)
                                <span class="absolute top-0.5 right-0.5 block h-2 w-2 rounded-full bg-rose-500 ring-2 ring-slate-950"></span>
                            @endif
                        </button>

                        <!-- Dropdown Panel -->
                        <div id="notifications-dropdown" class="hidden absolute right-0 mt-2 w-80 bg-slate-900 border border-slate-800 rounded-xl overflow-hidden shadow-2xl z-50 text-left">
                            <div class="px-4 py-2.5 border-b border-slate-800 bg-slate-950 flex items-center justify-between text-[11px] font-bold uppercase tracking-wider text-slate-400">
                                <span class="text-white">Notifications</span>
                                @if($unreadCount > 0)
                                    <button onclick="markAllAsRead()" class="text-indigo-400 hover:underline normal-case">Mark all read</button>
                                @endif
                            </div>
                            <div class="max-h-64 overflow-y-auto divide-y divide-slate-800/60" id="notifications-list">
                                @forelse($notifications as $notif)
                                    <a href="{{ $notif->data['url'] ?? '#' }}" class="block px-4 py-3 hover:bg-slate-850/40 transition {{ $notif->unread() ? 'bg-slate-900/40 border-l-2 border-indigo-500' : '' }}">
                                        <div class="flex items-start space-x-2.5">
                                            <span class="mt-1 flex h-1.5 w-1.5 shrink-0 rounded-full {{ ($notif->data['type'] ?? 'info') === 'class' ? 'bg-indigo-400' : (($notif->data['type'] ?? 'info') === 'module' ? 'bg-blue-400' : (($notif->data['type'] ?? 'info') === 'certificate' ? 'bg-amber-400' : 'bg-emerald-400')) }}"></span>
                                            <div class="overflow-hidden">
                                                <p class="text-xs font-semibold text-white truncate">{{ $notif->data['title'] }}</p>
                                                <p class="text-[10px] text-slate-400 mt-0.5 leading-normal line-clamp-2">{{ $notif->data['message'] }}</p>
                                                <span class="text-[9px] text-slate-500 font-mono block mt-1">{{ $notif->created_at->diffForHumans() }}</span>
                                            </div>
                                        </div>
                                    </a>
                                @empty
                                    <div class="px-4 py-6 text-center text-xs text-slate-500">
                                        No new notifications.
                                    </div>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    <!-- Profile Dropdown (Replaces Left Sidebar) -->
                    <div class="relative" id="profile-dropdown-container">
                        <button onclick="toggleProfileDropdown()" class="flex items-center space-x-2 p-1 text-slate-400 hover:text-white rounded-lg hover:bg-slate-900 border border-transparent hover:border-slate-850 transition focus:outline-none">
                            <div class="h-7 w-7 rounded-full bg-slate-800 border border-slate-700 flex items-center justify-center text-indigo-400 font-bold text-xs">
                                {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                            </div>
                            <span class="text-xs font-semibold text-slate-300 hidden sm:block">{{ auth()->user()->name }}</span>
                            <svg class="w-3.5 h-3.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </button>

                        <div id="profile-dropdown" class="hidden absolute right-0 mt-2 w-48 bg-slate-900 border border-slate-800 rounded-xl overflow-hidden shadow-2xl z-50 text-left">
                            <div class="px-4 py-2.5 border-b border-slate-800 bg-slate-950/40">
                                <p class="text-xs font-semibold text-white truncate">{{ auth()->user()->name }}</p>
                                <p class="text-[10px] font-bold text-indigo-400 uppercase tracking-wider mt-0.5">{{ auth()->user()->role }}</p>
                            </div>
                            <div class="py-1">
                                <a href="{{ route('dashboard') }}" class="block px-4 py-2 text-xs text-slate-300 hover:bg-slate-850 hover:text-white transition">Dashboard</a>
                                <a href="{{ route('classes.index') }}" class="block px-4 py-2 text-xs text-slate-300 hover:bg-slate-850 hover:text-white transition">Classes</a>
                                @if(auth()->user()->role === 'admin')
                                    <a href="{{ route('students.index') }}" class="block px-4 py-2 text-xs text-slate-300 hover:bg-slate-850 hover:text-white transition">Student Directory</a>
                                @endif
                                <a href="{{ route('settings.show') }}" class="block px-4 py-2 text-xs text-slate-300 hover:bg-slate-850 hover:text-white transition">Account Settings</a>
                            </div>
                            <div class="border-t border-slate-800 py-1">
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="w-full text-left px-4 py-2 text-xs text-rose-400 hover:bg-slate-850 transition">
                                        Logout
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </header>


        <!-- Main Viewport -->
        <main class="flex-1 relative overflow-y-auto focus:outline-none py-8 px-6">
            @if(session('success'))
                <div class="mb-6 p-3.5 rounded-md border border-[#16a34a]/30 bg-[#16a34a]/5 text-[#16a34a] flex items-center justify-between">
                    <div class="flex items-center space-x-2.5">
                        <svg class="w-4 h-4 text-[#16a34a]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <span class="text-xs font-semibold">{{ session('success') }}</span>
                    </div>
                </div>
            @endif

            @if(session('error'))
                <div class="mb-6 p-3.5 rounded-md border border-[#dc2626]/30 bg-[#dc2626]/5 text-[#dc2626] flex items-center justify-between">
                    <div class="flex items-center space-x-2.5">
                        <svg class="w-4 h-4 text-[#dc2626]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <span class="text-xs font-semibold">{{ session('error') }}</span>
                    </div>
                </div>
            @endif

            @if(session('warning'))
                <div class="mb-6 p-3.5 rounded-md border border-[#eab308]/30 bg-[#eab308]/5 text-[#eab308] flex items-center justify-between">
                    <div class="flex items-center space-x-2.5">
                        <svg class="w-4 h-4 text-[#eab308]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                        <span class="text-xs font-semibold">{{ session('warning') }}</span>
                    </div>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
    
    @yield('scripts')

    <script>
        function toggleNotifications() {
            const dropdown = document.getElementById('notifications-dropdown');
            dropdown.classList.toggle('hidden');
        }

        function toggleProfileDropdown() {
            const dropdown = document.getElementById('profile-dropdown');
            dropdown.classList.toggle('hidden');
        }

        document.addEventListener('click', function(e) {
            // Notification close
            const container = document.getElementById('notification-bell-container');
            const dropdown = document.getElementById('notifications-dropdown');
            if (container && !container.contains(e.target) && dropdown) {
                dropdown.classList.add('hidden');
            }

            // Profile close
            const profileContainer = document.getElementById('profile-dropdown-container');
            const profileDropdown = document.getElementById('profile-dropdown');
            if (profileContainer && !profileContainer.contains(e.target) && profileDropdown) {
                profileDropdown.classList.add('hidden');
            }
        });

        function markAllAsRead() {
            fetch("{{ route('notifications.mark-as-read') }}", {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}'
                }
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    const dot = document.querySelector('#notification-bell-container span.bg-rose-500');
                    if (dot) dot.remove();

                    const items = document.querySelectorAll('#notifications-list a.bg-slate-900\\/40');
                    items.forEach(item => {
                        item.classList.remove('bg-slate-900/40', 'border-l-2', 'border-indigo-500');
                    });

                    const btn = document.querySelector('#notifications-dropdown button');
                    if (btn) btn.remove();
                }
            });
        }

        let lastNotifState = null;
        let notifPollTimer = null;

        function pollNotifications() {
            if (document.hidden) return; // Save bandwidth and avoid re-renders when tab is hidden

            fetch("{{ route('notifications.fetch') }}")
                .then(res => res.json())
                .then(data => {
                    const stateKey = JSON.stringify({ count: data.unreadCount, ids: data.notifications.map(n => n.id + ':' + n.unread) });
                    if (stateKey === lastNotifState) return; // Prevent unnecessary DOM re-renders if no notification changes
                    lastNotifState = stateKey;

                    // Update unread count badge
                    const container = document.getElementById('notification-bell-container');
                    if (!container) return;
                    
                    let dot = container.querySelector('span.bg-rose-500');
                    if (data.unreadCount > 0) {
                        if (!dot) {
                            const btn = container.querySelector('button');
                            dot = document.createElement('span');
                            dot.className = 'absolute top-0.5 right-0.5 block h-2 w-2 rounded-full bg-rose-500 ring-2 ring-slate-950';
                            btn.appendChild(dot);
                        }
                    } else {
                        if (dot) dot.remove();
                    }

                    // Update "Mark all read" button in dropdown
                    const header = document.querySelector('#notifications-dropdown div.px-4.py-2\\.5');
                    if (header) {
                        let markReadBtn = header.querySelector('button');
                        if (data.unreadCount > 0) {
                            if (!markReadBtn) {
                                markReadBtn = document.createElement('button');
                                markReadBtn.onclick = markAllAsRead;
                                markReadBtn.className = 'text-indigo-400 hover:underline normal-case';
                                markReadBtn.innerText = 'Mark all read';
                                header.appendChild(markReadBtn);
                            }
                        } else {
                            if (markReadBtn) markReadBtn.remove();
                        }
                    }

                    // Update list items
                    const list = document.getElementById('notifications-list');
                    if (list) {
                        if (data.notifications.length === 0) {
                            list.innerHTML = `
                                <div class="px-4 py-6 text-center text-xs text-slate-500">
                                    No new notifications.
                                </div>
                            `;
                        } else {
                            list.innerHTML = data.notifications.map(notif => {
                                const typeColor = notif.type === 'class' ? 'bg-indigo-400' : (notif.type === 'module' ? 'bg-blue-400' : (notif.type === 'certificate' ? 'bg-amber-400' : 'bg-emerald-400'));
                                const unreadStyle = notif.unread ? 'bg-slate-900/40 border-l-2 border-indigo-500' : '';
                                return `
                                    <a href="${notif.url}" class="block px-4 py-3 hover:bg-slate-850/40 transition ${unreadStyle}">
                                        <div class="flex items-start space-x-2.5">
                                            <span class="mt-1 flex h-1.5 w-1.5 shrink-0 rounded-full ${typeColor}"></span>
                                            <div class="overflow-hidden">
                                                <p class="text-xs font-semibold text-white truncate">${notif.title}</p>
                                                <p class="text-[10px] text-slate-400 mt-0.5 leading-normal line-clamp-2">${notif.message}</p>
                                                <span class="text-[9px] text-slate-500 font-mono block mt-1">${notif.time}</span>
                                            </div>
                                        </div>
                                    </a>
                                `;
                            }).join('');
                        }
                    }
                })
                .catch(() => {});
        }

        // Start polling every 25 seconds; wake up immediately when tab gains focus
        notifPollTimer = setInterval(pollNotifications, 25000);
        document.addEventListener('visibilitychange', () => {
            if (!document.hidden) {
                pollNotifications();
            }
        });

        // --- Global Search Command Palette Logic ---
        let searchTimeout = null;
        let searchAbortController = null;

        function openSearchModal() {
            const modal = document.getElementById('search-modal');
            const input = document.getElementById('search-modal-input');
            if (modal && input) {
                modal.classList.remove('hidden');
                input.value = '';
                document.getElementById('search-quick-links').classList.remove('hidden');
                document.getElementById('search-results').classList.add('hidden');
                
                // Clear highlighted states
                const items = Array.from(modal.querySelectorAll('a'));
                items.forEach(item => {
                    item.classList.remove('bg-slate-800/80', 'text-white');
                    item.classList.add('text-slate-300');
                });
                
                setTimeout(() => input.focus(), 50);
            }
        }

        function closeSearchModal() {
            const modal = document.getElementById('search-modal');
            if (modal) {
                modal.classList.add('hidden');
            }
        }

        function performSearch(query) {
            clearTimeout(searchTimeout);
            if (searchAbortController) {
                searchAbortController.abort();
            }
            
            const quickLinks = document.getElementById('search-quick-links');
            const results = document.getElementById('search-results');
            
            if (!query || query.trim().length < 2) {
                quickLinks.classList.remove('hidden');
                results.classList.add('hidden');
                return;
            }

            const isInstructorOrAdmin = document.body.getAttribute('data-is-instructor-or-admin') === 'true';
            
            const navLinks = [
                { label: 'Dashboard', url: "{{ route('dashboard') }}", type: 'Navigation', keywords: ['dashboard', 'home', 'dash'] },
                { label: 'All Classes', url: "{{ route('classes.index') }}", type: 'Navigation', keywords: ['classes', 'all classes', 'course', 'courses'] },
                { label: 'Account Settings', url: "{{ route('settings.show') }}", type: 'Navigation', keywords: ['settings', 'account', 'profile', 'password', 'gmail', 'github'] }
            ];

            if (isInstructorOrAdmin) {
                navLinks.push({ label: 'Create a Class', url: "{{ route('classes.create') }}", type: 'Navigation', keywords: ['create a class', 'create class', 'new class', 'add class'] });
                navLinks.push({ label: 'Student Directory', url: "{{ route('students.index') }}", type: 'Navigation', keywords: ['students', 'directory', 'users', 'profiles', 'student directory'] });
            }

            const queryLower = query.toLowerCase().trim();
            const matchingNavs = navLinks.filter(link => {
                return link.label.toLowerCase().includes(queryLower) || 
                       link.keywords.some(kw => kw.includes(queryLower));
            });

            searchTimeout = setTimeout(() => {
                searchAbortController = new AbortController();
                fetch(`/search?q=${encodeURIComponent(query)}`, { signal: searchAbortController.signal })
                    .then(res => res.json())
                    .then(data => {
                        quickLinks.classList.add('hidden');
                        results.classList.remove('hidden');

                        data.navigation = matchingNavs;

                        const sections = {
                            navigation: document.getElementById('search-section-navigation'),
                            classes: document.getElementById('search-section-classes'),
                            modules: document.getElementById('search-section-modules'),
                            laboratories: document.getElementById('search-section-laboratories')
                        };

                        let hasAnyResults = false;

                        for (const [key, section] of Object.entries(sections)) {
                            if (!section) continue;
                            const items = data[key] || [];
                            const container = section.querySelector('.search-items-container');
                            container.innerHTML = '';

                            if (items.length > 0) {
                                hasAnyResults = true;
                                section.classList.remove('hidden');
                                items.forEach(item => {
                                    const a = document.createElement('a');
                                    a.href = item.url;
                                    a.className = 'flex items-center space-x-3 px-3 py-2 text-xs font-semibold rounded-lg text-slate-300 hover:text-white hover:bg-slate-800/60 transition';
                                    a.innerHTML = `
                                        <svg class="h-3.5 w-3.5 text-slate-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            ${getIconSvg(item.type)}
                                        </svg>
                                        <span class="truncate">${item.label}</span>
                                    `;
                                    container.appendChild(a);
                                });
                            } else {
                                section.classList.add('hidden');
                            }
                        }

                        const noResults = document.getElementById('search-no-results');
                        if (hasAnyResults) {
                            noResults.classList.add('hidden');
                        } else {
                            noResults.classList.remove('hidden');
                        }
                    })
                    .catch(err => {
                        if (err.name !== 'AbortError') {
                            console.error('Search failed:', err);
                        }
                    });
            }, 250);
        }

        function getIconSvg(type) {
            if (type === 'Navigation') {
                return '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1" />';
            } else if (type === 'Class') {
                return '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />';
            } else if (type === 'Module') {
                return '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />';
            } else { // Laboratory
                return '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />';
            }
        }

        document.addEventListener('keydown', function(e) {
            // Ctrl+K or Cmd+K to toggle search modal
            if ((e.ctrlKey || e.metaKey) && e.key === 'k') {
                e.preventDefault();
                const modal = document.getElementById('search-modal');
                if (modal && modal.classList.contains('hidden')) {
                    openSearchModal();
                } else {
                    closeSearchModal();
                }
            }

            // Close with Escape
            if (e.key === 'Escape') {
                closeSearchModal();
            }

            // Keyboard navigation inside search modal
            const modal = document.getElementById('search-modal');
            if (modal && !modal.classList.contains('hidden')) {
                if (e.key === 'ArrowDown' || e.key === 'ArrowUp' || e.key === 'Enter') {
                    const activeContainer = document.getElementById('search-results').classList.contains('hidden') 
                        ? document.getElementById('search-quick-links') 
                        : document.getElementById('search-results');
                    
                    const items = Array.from(activeContainer.querySelectorAll('a:not(.hidden)'));
                    if (items.length === 0) return;

                    e.preventDefault();

                    let activeIndex = items.findIndex(item => item.classList.contains('bg-slate-800/80'));

                    if (e.key === 'Enter') {
                        if (activeIndex >= 0) {
                            items[activeIndex].click();
                        }
                        return;
                    }

                    if (activeIndex >= 0) {
                        items[activeIndex].classList.remove('bg-slate-800/80', 'text-white');
                        items[activeIndex].classList.add('text-slate-300');
                    }

                    if (e.key === 'ArrowDown') {
                        activeIndex = (activeIndex + 1) % items.length;
                    } else if (e.key === 'ArrowUp') {
                        activeIndex = (activeIndex - 1 + items.length) % items.length;
                    }

                    items[activeIndex].classList.remove('text-slate-300');
                    items[activeIndex].classList.add('bg-slate-800/80', 'text-white');
                    items[activeIndex].scrollIntoView({ block: 'nearest' });
                }
            }
        });

        // Global Theme Management
        window.applyTheme = function(theme) {
            try {
                localStorage.setItem('theme', theme);
                const systemDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
                const isDark = theme === 'dark' || (theme === 'system' && systemDark);
                
                if (isDark) {
                    document.documentElement.classList.add('dark');
                    document.documentElement.classList.remove('light');
                    document.documentElement.style.colorScheme = 'dark';
                } else {
                    document.documentElement.classList.remove('dark');
                    document.documentElement.classList.add('light');
                    document.documentElement.style.colorScheme = 'light';
                }
                
                // Update Quick Theme Toggle Icons
                const sunIcon = document.getElementById('theme-toggle-sun');
                const moonIcon = document.getElementById('theme-toggle-moon');
                if (sunIcon && moonIcon) {
                    if (isDark) {
                        sunIcon.classList.remove('hidden');
                        moonIcon.classList.add('hidden');
                    } else {
                        sunIcon.classList.add('hidden');
                        moonIcon.classList.remove('hidden');
                    }
                }
                
                window.dispatchEvent(new CustomEvent('certicode:themechange', { detail: { theme, isDark } }));
            } catch (e) {
                console.error('Theme switch error:', e);
            }
        };

        window.toggleQuickTheme = function() {
            const isDark = document.documentElement.classList.contains('dark');
            const newTheme = isDark ? 'light' : 'dark';
            window.applyTheme(newTheme);
        };

        // Initialize theme UI state
        (function initThemeIcons() {
            const stored = localStorage.getItem('theme') || 'system';
            const systemDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            const isDark = stored === 'dark' || (stored === 'system' && systemDark);
            
            const sunIcon = document.getElementById('theme-toggle-sun');
            const moonIcon = document.getElementById('theme-toggle-moon');
            if (sunIcon && moonIcon) {
                if (isDark) {
                    sunIcon.classList.remove('hidden');
                    moonIcon.classList.add('hidden');
                } else {
                    sunIcon.classList.add('hidden');
                    moonIcon.classList.remove('hidden');
                }
            }
        })();

        // Listen for OS color scheme changes when system mode is selected
        window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function(e) {
            const stored = localStorage.getItem('theme') || 'system';
            if (stored === 'system') {
                window.applyTheme('system');
            }
        });
    </script>

    <!-- Search Command Palette Modal -->
    <div id="search-modal" class="fixed inset-0 z-50 hidden overflow-y-auto p-4 sm:p-6 md:p-20" role="dialog" aria-modal="true">
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-slate-950/80 backdrop-blur-sm transition-opacity" onclick="closeSearchModal()"></div>

        <!-- Modal Box -->
        <div class="mx-auto max-w-xl transform divide-y divide-slate-800 rounded-xl bg-slate-900 border border-slate-800 shadow-2xl transition-all ring-1 ring-black ring-opacity-5 relative z-10">
            <div class="relative">
                <!-- Search Icon -->
                <div class="pointer-events-none absolute left-4 top-3.5 text-slate-400">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
                <input type="text" id="search-modal-input" name="search_modal_query" oninput="performSearch(this.value)" placeholder="Search classes, modules, challenges..."
                       class="h-12 w-full border-0 bg-transparent pl-11 pr-4 text-sm text-slate-200 placeholder-slate-500 focus:ring-0 focus:outline-none" role="combobox" aria-expanded="false" aria-controls="options">
            </div>

            <!-- Default Quick Links (when input is empty) -->
            <div id="search-quick-links" class="p-2 space-y-1">
                <span class="block px-3 py-2 text-[10px] font-bold text-slate-500 uppercase tracking-wider">Quick Links</span>
                <a href="{{ route('dashboard') }}" class="flex items-center space-x-3 px-3 py-2 text-xs font-semibold rounded-lg text-slate-300 hover:text-white hover:bg-slate-800/60 transition">
                    <span>Dashboard</span>
                </a>
                <a href="{{ route('classes.index') }}" class="flex items-center space-x-3 px-3 py-2 text-xs font-semibold rounded-lg text-slate-300 hover:text-white hover:bg-slate-800/60 transition">
                    <span>All Classes</span>
                </a>
                @if(auth()->user()->role === 'instructor' || auth()->user()->role === 'admin')
                    <a href="{{ route('classes.create') }}" class="flex items-center space-x-3 px-3 py-2 text-xs font-semibold rounded-lg text-slate-300 hover:text-white hover:bg-slate-800/60 transition">
                        <span>Create a Class</span>
                    </a>
                @endif
                <a href="{{ route('settings.show') }}" class="flex items-center space-x-3 px-3 py-2 text-xs font-semibold rounded-lg text-slate-300 hover:text-white hover:bg-slate-800/60 transition">
                    <span>Account Settings</span>
                </a>
            </div>

            <!-- Search Results -->
            <div id="search-results" class="hidden max-h-96 overflow-y-auto p-2 space-y-4">
                <!-- Navigation section -->
                <div id="search-section-navigation" class="hidden space-y-1">
                    <span class="block px-3 py-1 text-[10px] font-bold text-slate-500 uppercase tracking-wider">Navigation</span>
                    <div class="search-items-container space-y-0.5"></div>
                </div>
                <!-- Classes section -->
                <div id="search-section-classes" class="hidden space-y-1">
                    <span class="block px-3 py-1 text-[10px] font-bold text-slate-500 uppercase tracking-wider">Classes</span>
                    <div class="search-items-container space-y-0.5"></div>
                </div>
                <!-- Modules section -->
                <div id="search-section-modules" class="hidden space-y-1">
                    <span class="block px-3 py-1 text-[10px] font-bold text-slate-500 uppercase tracking-wider">Modules</span>
                    <div class="search-items-container space-y-0.5"></div>
                </div>
                <!-- Laboratories section -->
                <div id="search-section-laboratories" class="hidden space-y-1">
                    <span class="block px-3 py-1 text-[10px] font-bold text-slate-500 uppercase tracking-wider">Lab Challenges</span>
                    <div class="search-items-container space-y-0.5"></div>
                </div>
                <!-- No results -->
                <div id="search-no-results" class="hidden text-center py-6 text-xs text-slate-500">
                    No results found. Try a different query.
                </div>
            </div>
        </div>
    </div>
</body>
</html>

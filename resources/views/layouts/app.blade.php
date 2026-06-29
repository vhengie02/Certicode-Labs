<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Certicode Labs') - AI Competency Platform</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Inter & JetBrains Mono Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Design System Theme Overrides -->
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        blue: {
                            50: '#f0f6fc',
                            100: '#c9d1d9',
                            200: '#b1bac4',
                            300: '#8b949e',
                            400: '#58A6FF', // GitHub Light Accent Blue
                            500: '#388BFD', // GitHub Active/Hover Accent Blue
                            600: '#1F6FEB', // GitHub Primary Accent Blue
                            700: '#1158c7',
                        },
                        green: {
                            50: '#f0fdf4',
                            100: '#dcfce7',
                            200: '#bbf7d0',
                            300: '#86efac',
                            400: '#4ade80',
                            500: '#2EA043', // GitHub Hover Green
                            600: '#238636', // GitHub Primary Green
                            700: '#1a6528',
                        },
                        indigo: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            200: '#bfdbfe',
                            300: '#93c5fd',
                            400: '#60a5fa',
                            500: '#3b82f6',
                            600: '#2563EB',
                            700: '#1d4ed8',
                            800: '#1e40af',
                            900: '#1e3a8a',
                            950: '#172554',
                        },
                        slate: {
                            50: '#f8fafc',
                            100: '#f1f5f9',
                            200: '#e2e8f0',
                            300: '#cbd5e1',
                            400: '#8B949E', // Text Secondary
                            500: '#64748b',
                            600: '#475569',
                            700: '#334155',
                            800: '#30363D', // Border/Divider (GitHub Border)
                            900: '#161B22', // Secondary Background / Card (GitHub Surface)
                            950: '#0D1117', // Primary Background (GitHub Canvas)
                        }
                    },
                    fontFamily: {
                        sans: ['Inter', 'sans-serif'],
                        mono: ['JetBrains Mono', 'monospace']
                    }
                }
            }
        }
    </script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #0D1117;
            color: #E6EDF3;
        }
        .glass-panel {
            background-color: #161B22;
            border: 1px solid #30363D;
        }
        .glass-card {
            background-color: #161B22;
            border: 1px solid #30363D;
            transition: all 150ms ease;
        }
        .glass-card:hover {
            border-color: #388BFD;
        }
        /* Custom sidebar active state style matching GitHub */
        .sidebar-active-item {
            background-color: #21262D !important;
            border-left: 4px solid #f78166 !important; /* GitHub's active tab coral border */
            border-top-left-radius: 0px !important;
            border-bottom-left-radius: 0px !important;
            color: #ffffff !important;
        }
        /* Custom scrollbar matching GitHub Dark */
        ::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }
        ::-webkit-scrollbar-track {
            background: #0D1117;
        }
        ::-webkit-scrollbar-thumb {
            background: #30363D;
            border-radius: 4px;
        }
        ::-webkit-scrollbar-thumb:hover {
            background: #8B949E;
        }
    </style>
</head>
<body class="h-full text-slate-100 bg-slate-950 flex flex-col overflow-hidden">

    <!-- Main Content Shell -->
    <div class="flex flex-col flex-1 overflow-hidden">
        <!-- Top bar (56px tall) -->
        <header class="h-14 bg-slate-950 border-b border-slate-800 flex items-center justify-between px-6 z-50 flex-shrink-0">
            <div class="flex-1 flex items-center justify-between">
                <!-- Left Header: Logo & Branding + Breadcrumbs -->
                <div class="flex items-center space-x-4">
                    <!-- Logo / Link to Dashboard -->
                    <a href="{{ route('dashboard') }}" class="flex items-center space-x-2.5 hover:opacity-90 transition-opacity">
                        <div class="h-8 w-8 rounded-lg bg-indigo-600 flex items-center justify-center shadow-md shadow-indigo-600/20">
                            <!-- Shield SVG logo -->
                            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                            </svg>
                        </div>
                        <span class="text-sm font-bold text-white tracking-tight">Certicode Labs</span>
                    </a>
                    
                    <span class="text-slate-700">/</span>
                    
                    <h2 class="text-xs font-semibold text-slate-400">@yield('page_header', 'Workspace')</h2>
                </div>

                <!-- Center Search Input (GitHub Style) -->
                <div class="hidden lg:block w-80 relative mx-4">
                    <input type="text" placeholder="Search..." onclick="openSearchModal()" readonly class="w-full h-8 pl-8 pr-12 rounded-md bg-slate-900 border border-slate-800 text-xs text-slate-300 placeholder-slate-500 cursor-pointer hover:border-slate-700 transition-colors">
                    <div class="absolute left-2.5 top-2 text-slate-500">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>
                    <div class="absolute right-2 top-1.5 bg-slate-950 border border-slate-800 px-1 py-0.5 rounded text-[9px] text-slate-500 font-mono">
                        Ctrl+K
                    </div>
                </div>

                <!-- Right Actions & Profile Dropdown -->
                <div class="flex items-center space-x-4">
                    <!-- Notification Bell and Dropdown -->
                    <div class="relative" id="notification-bell-container">
                        @php
                            $unreadCount = auth()->user()->unreadNotifications->count();
                            $notifications = auth()->user()->notifications()->take(5)->get();
                        @endphp
                        <button onclick="toggleNotifications()" class="relative p-1.5 text-slate-400 hover:text-white rounded-lg hover:bg-slate-900 border border-slate-850 transition focus:outline-none">
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
                <div class="mb-6 p-4 rounded-xl border border-emerald-500/20 bg-emerald-500/5 text-emerald-400 flex items-center justify-between shadow-lg">
                    <div class="flex items-center space-x-2">
                        <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <span class="text-sm font-semibold">{{ session('success') }}</span>
                    </div>
                </div>
            @endif

            @if(session('error'))
                <div class="mb-6 p-4 rounded-xl border border-rose-500/20 bg-rose-500/5 text-rose-400 flex items-center justify-between shadow-lg">
                    <div class="flex items-center space-x-2">
                        <svg class="w-5 h-5 text-rose-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <span class="text-sm font-semibold">{{ session('error') }}</span>
                    </div>
                </div>
            @endif

            @if(session('warning'))
                <div class="mb-6 p-4 rounded-xl border border-amber-500/20 bg-amber-500/5 text-amber-400 flex items-center justify-between shadow-lg">
                    <div class="flex items-center space-x-2">
                        <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                        <span class="text-sm font-semibold">{{ session('warning') }}</span>
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

        // --- Global Search Command Palette Logic ---
        let searchTimeout = null;

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
            
            const quickLinks = document.getElementById('search-quick-links');
            const results = document.getElementById('search-results');
            
            if (!query || query.trim().length < 2) {
                quickLinks.classList.remove('hidden');
                results.classList.add('hidden');
                return;
            }

            searchTimeout = setTimeout(() => {
                fetch(`/search?q=${encodeURIComponent(query)}`)
                    .then(res => res.json())
                    .then(data => {
                        quickLinks.classList.add('hidden');
                        results.classList.remove('hidden');

                        const sections = {
                            classes: document.getElementById('search-section-classes'),
                            modules: document.getElementById('search-section-modules'),
                            laboratories: document.getElementById('search-section-laboratories')
                        };

                        let hasAnyResults = false;

                        for (const [key, section] of Object.entries(sections)) {
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
                        console.error('Search failed:', err);
                    });
            }, 200);
        }

        function getIconSvg(type) {
            if (type === 'Class') {
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
    </script>

    <!-- Search Command Palette Modal -->
    <div id="search-modal" class="fixed inset-0 z-50 hidden overflow-y-auto p-4 sm:p-6 md:p-20" role="dialog" aria-modal="true">
        <!-- Backdrop -->
        <div class="fixed inset-0 bg-slate-955/80 backdrop-blur-sm transition-opacity" onclick="closeSearchModal()"></div>

        <!-- Modal Box -->
        <div class="mx-auto max-w-xl transform divide-y divide-slate-800 rounded-xl bg-slate-900 border border-slate-800 shadow-2xl transition-all ring-1 ring-black ring-opacity-5 relative z-10">
            <div class="relative">
                <!-- Search Icon -->
                <div class="pointer-events-none absolute left-4 top-3.5 text-slate-400">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
                <input type="text" id="search-modal-input" oninput="performSearch(this.value)" placeholder="Search classes, modules, challenges..."
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

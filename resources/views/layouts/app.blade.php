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
                        indigo: {
                            50: '#eff6ff',
                            100: '#dbeafe',
                            200: '#bfdbfe',
                            300: '#93c5fd',
                            400: '#60a5fa',
                            500: '#3b82f6',
                            600: '#2563EB', // Primary Accent (electric blue)
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
                            800: '#30363D', // Border/Divider
                            900: '#161B22', // Secondary Background / Card
                            950: '#0D1117', // Primary Background
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
        /* Custom sidebar active state style */
        .sidebar-active-item {
            background-color: rgba(37, 99, 235, 0.15) !important;
            border-left: 3px solid #2563EB !important;
            border-top-left-radius: 0px !important;
            border-bottom-left-radius: 0px !important;
            color: #E6EDF3 !important;
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
<body class="h-full text-slate-100 flex overflow-hidden">

    <!-- Left Sidebar Panel (NetAcad Inspired Layout) -->
    <div class="hidden md:flex md:flex-shrink-0">
        <div class="flex flex-col w-64 bg-[#1C2333] border-r border-slate-800">
            <div class="flex flex-col flex-grow pt-5 pb-4 overflow-y-auto">
                <!-- Branding Header -->
                <div class="flex items-center flex-shrink-0 px-6 space-x-2">
                    <div class="h-9 w-9 rounded-lg bg-indigo-600 flex items-center justify-center shadow-lg shadow-indigo-600/30">
                        <!-- Shield SVG logo -->
                        <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                        </svg>
                    </div>
                    <span class="text-lg font-bold text-white tracking-tight">Certicode Labs</span>
                </div>
                
                <!-- Sidebar Menu Items -->
                <div class="mt-8 flex-grow flex flex-col">
                    <nav class="flex-1 px-3 space-y-1">
                        <a href="{{ route('dashboard') }}" class="group flex items-center px-4 py-3 text-sm font-semibold rounded-lg transition-all duration-150 {{ request()->routeIs('dashboard') ? 'sidebar-active-item' : 'text-slate-400 hover:bg-slate-800/40 hover:text-white' }}">
                            <svg class="mr-3 h-5 w-5 {{ request()->routeIs('dashboard') ? 'text-white' : 'text-slate-400 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2v-4zM14 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2v-4z"></path>
                            </svg>
                            Dashboard
                        </a>

                        <a href="{{ route('laboratories.index') }}" class="group flex items-center px-4 py-3 text-sm font-semibold rounded-lg transition-all duration-150 {{ request()->routeIs('laboratories.*') ? 'sidebar-active-item' : 'text-slate-400 hover:bg-slate-800/40 hover:text-white' }}">
                            <svg class="mr-3 h-5 w-5 {{ request()->routeIs('laboratories.*') ? 'text-white' : 'text-slate-400 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19.428 15.428a2 2 0 00-1.022-.547l-2.387-.477a6 6 0 00-3.86.517l-.318.158a6 6 0 01-3.86.517L6.05 15.21a2 2 0 00-1.806.547M8 4h8l-1 1v5.172a2 2 0 00.586 1.414l5 5c1.26 1.26.367 3.414-1.415 3.414H4.828c-1.782 0-2.674-2.154-1.414-3.414l5-5A2 2 0 009 10.172V5L8 4z"></path>
                            </svg>
                            Laboratories
                        </a>

                        @if(auth()->user()->role === 'admin' || auth()->user()->role === 'instructor')
                        <a href="{{ route('students.index') }}" class="group flex items-center px-4 py-3 text-sm font-semibold rounded-lg transition-all duration-150 {{ request()->routeIs('students.*') ? 'sidebar-active-item' : 'text-slate-400 hover:bg-slate-800/40 hover:text-white' }}">
                            <svg class="mr-3 h-5 w-5 {{ request()->routeIs('students.*') ? 'text-white' : 'text-slate-400 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>
                            </svg>
                            Student Directory
                        </a>
                        @endif

                        <a href="{{ route('profiles.edit', auth()->id()) }}" class="group flex items-center px-4 py-3 text-sm font-semibold rounded-lg transition-all duration-150 {{ request()->is('profiles/' . auth()->id() . '/edit') ? 'sidebar-active-item' : 'text-slate-400 hover:bg-slate-800/40 hover:text-white' }}">
                            <svg class="mr-3 h-5 w-5 {{ request()->is('profiles/' . auth()->id() . '/edit') ? 'text-white' : 'text-slate-400 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                            </svg>
                            My Profile
                        </a>
                    </nav>
                </div>

                <!-- Footer User Badge -->
                <div class="flex-shrink-0 flex border-t border-slate-800/80 p-4">
                    <div class="flex-shrink-0 w-full group block">
                        <div class="flex items-center">
                            <div class="inline-block h-9 w-9 rounded-full bg-slate-800 border border-slate-700 flex items-center justify-center text-indigo-400 font-bold">
                                {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
                            </div>
                            <div class="ml-3 overflow-hidden">
                                <p class="text-sm font-medium text-white truncate">{{ auth()->user()->name }}</p>
                                <p class="text-xs font-semibold text-indigo-400 uppercase tracking-wider">{{ auth()->user()->role }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Shell -->
    <div class="flex flex-col w-0 flex-1 overflow-hidden">
        <!-- Top bar (56px tall) -->
        <header class="h-14 bg-slate-950 border-b border-slate-800 flex items-center justify-between px-6 z-10 flex-shrink-0">
            <div class="flex-1 flex items-center justify-between">
                <!-- Left Header Title / Breadcrumbs -->
                <div class="flex items-center space-x-2">
                    <h2 class="text-lg font-bold tracking-tight text-white">@yield('page_header', 'Workspace')</h2>
                </div>

                <!-- Center Search Input (GitHub Style) -->
                <div class="hidden lg:block w-80 relative mx-4">
                    <input type="text" placeholder="Search..." disabled class="w-full h-8 pl-8 pr-12 rounded-md bg-slate-900 border border-slate-800 text-xs text-slate-300 placeholder-slate-500 cursor-not-allowed">
                    <div class="absolute left-2.5 top-2 text-slate-500">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    </div>
                    <div class="absolute right-2 top-1.5 bg-slate-950 border border-slate-800 px-1 py-0.5 rounded text-[9px] text-slate-500 font-mono">
                        Ctrl+K
                    </div>
                </div>

                <!-- Right Actions & Logout -->
                <div class="flex items-center space-x-4">
                    <!-- Status Badge -->
                    <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                        <span class="w-1.5 h-1.5 mr-1.5 bg-emerald-400 rounded-full animate-ping"></span>
                        AI Monitor Connected
                    </span>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center px-3 py-1.5 border border-slate-800 text-xs font-semibold rounded-lg text-rose-400 bg-rose-500/5 hover:bg-rose-500/10 transition-colors">
                            Logout
                        </button>
                    </form>
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

            @yield('content')
        </main>
    </div>
    
    @yield('scripts')
</body>
</html>

<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Certicode Labs - AI-Powered Coding Education & Telemetry Platform</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Google Fonts: Inter -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #0D1117;
            color: #E6EDF3;
        }
    </style>
</head>
<body class="h-full flex flex-col justify-between overflow-y-auto">

    <!-- Top Navigation -->
    <header class="w-full max-w-7xl mx-auto px-6 h-16 flex items-center justify-between border-b border-slate-800">
        <div class="flex items-center space-x-2.5">
            <div class="h-9 w-9 rounded-lg bg-blue-600 flex items-center justify-center shadow-lg shadow-blue-600/30">
                <!-- Logo Shield SVG -->
                <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                </svg>
            </div>
            <span class="text-lg font-bold text-white tracking-tight">Certicode Labs</span>
        </div>

        <nav class="flex items-center space-x-4">
            @auth
                <a href="{{ route('dashboard') }}" class="inline-flex items-center px-4 py-2 border border-slate-800 text-xs font-semibold rounded-lg text-white bg-slate-900 hover:bg-slate-800 transition-colors">
                    Dashboard
                </a>
            @else
                <a href="{{ route('login.show') }}" class="text-xs font-semibold text-slate-400 hover:text-white transition-colors">
                    Log in
                </a>
                @if (Route::has('register.show'))
                    <a href="{{ route('register.show') }}" class="inline-flex items-center px-4 py-2 border border-transparent text-xs font-semibold rounded-lg text-white bg-blue-600 hover:bg-blue-500 transition-colors shadow-lg shadow-blue-500/20">
                        Get Started
                    </a>
                @endif
            @endauth
        </nav>
    </header>

    <!-- Main Content Area -->
    <main class="flex-1 max-w-7xl mx-auto px-6 flex flex-col justify-center py-16">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">
            <!-- Hero Content -->
            <div class="space-y-6">
                <div class="inline-flex items-center space-x-2 px-3 py-1 rounded-full text-xs font-medium bg-blue-500/10 text-blue-400 border border-blue-500/20">
                    <span class="w-1.5 h-1.5 bg-blue-400 rounded-full animate-pulse"></span>
                    <span>Version 1.0 Live</span>
                </div>
                <h1 class="text-4xl md:text-5xl font-extrabold text-white leading-tight tracking-tight">
                    Verify Your Code Skills with Real-time AI Telemetry.
                </h1>
                <p class="text-slate-400 text-base md:text-lg leading-relaxed max-w-lg">
                    Certicode Labs blends GitHub repository integration and NetAcad-inspired learning paths. Write, compile, and run code while maintaining high compliance standards.
                </p>
                <div class="flex items-center space-x-4">
                    <a href="{{ route('register.show') }}" class="inline-flex items-center px-6 py-3 border border-transparent text-sm font-semibold rounded-lg text-white bg-blue-600 hover:bg-blue-500 transition-all duration-150 shadow-lg shadow-blue-500/30">
                        Create Free Account
                    </a>
                    <a href="{{ route('login.show') }}" class="inline-flex items-center px-6 py-3 border border-slate-800 text-sm font-semibold rounded-lg text-slate-300 bg-slate-900 hover:bg-slate-800 transition-colors">
                        Sign In
                    </a>
                </div>
            </div>

            <!-- Hero Feature Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <!-- Feature 1 -->
                <div class="p-6 bg-slate-900 border border-slate-800 rounded-xl space-y-3">
                    <div class="w-9 h-9 bg-blue-500/10 border border-blue-500/20 rounded-lg flex items-center justify-center text-blue-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path></svg>
                    </div>
                    <h3 class="font-bold text-white text-sm">Sandbox Workspace</h3>
                    <p class="text-xs text-slate-400 leading-relaxed">
                        Interactive terminals with full execution telemetry built for Bash, Python, and SQL scripts.
                    </p>
                </div>

                <!-- Feature 2 -->
                <div class="p-6 bg-slate-900 border border-slate-800 rounded-xl space-y-3">
                    <div class="w-9 h-9 bg-emerald-500/10 border border-emerald-500/20 rounded-lg flex items-center justify-center text-emerald-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                    </div>
                    <h3 class="font-bold text-white text-sm">Integrity Shield</h3>
                    <p class="text-xs text-slate-400 leading-relaxed">
                        Real-time presence metrics and tab-switching monitoring to verify work authenticity.
                    </p>
                </div>

                <!-- Feature 3 -->
                <div class="p-6 bg-slate-900 border border-slate-800 rounded-xl space-y-3">
                    <div class="w-9 h-9 bg-yellow-500/10 border border-yellow-500/20 rounded-lg flex items-center justify-center text-yellow-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    </div>
                    <h3 class="font-bold text-white text-sm">GitHub Integration</h3>
                    <p class="text-xs text-slate-400 leading-relaxed">
                        Sync your code pushes directly to generate clean performance benchmarks and graphs.
                    </p>
                </div>

                <!-- Feature 4 -->
                <div class="p-6 bg-slate-900 border border-slate-800 rounded-xl space-y-3">
                    <div class="w-9 h-9 bg-purple-500/10 border border-purple-500/20 rounded-lg flex items-center justify-center text-purple-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                    </div>
                    <h3 class="font-bold text-white text-sm">Learning Paths</h3>
                    <p class="text-xs text-slate-400 leading-relaxed">
                        NetAcad-inspired structure guides learners through step-by-step module trees.
                    </p>
                </div>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="w-full border-t border-slate-800 bg-slate-950 py-6 text-center text-xs text-slate-500">
        <div class="max-w-7xl mx-auto px-6 flex flex-col sm:flex-row items-center justify-between">
            <p>&copy; 2026 Certicode Labs. All rights reserved.</p>
            <p class="mt-2 sm:mt-0">Built for educators and developers.</p>
        </div>
    </footer>

</body>
</html>

<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Certicode Labs - AI-Powered Coding Education & Telemetry Platform</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['"IBM Plex Sans"', 'sans-serif'],
                        mono: ['"JetBrains Mono"', 'monospace'],
                        brick: ['"Brick Sans"', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <!-- Google Fonts: IBM Plex Sans & JetBrains Mono -->
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:wght@300;400;500;600;700&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        @font-face {
            font-family: 'Brick Sans';
            src: url('/fonts/BrickSans-Bold.otf') format('opentype');
            font-weight: bold;
            font-style: normal;
            font-display: swap;
        }
        body {
            font-family: 'IBM Plex Sans', sans-serif;
            background-color: #0F172A;
            color: #F8FAFC;
        }
    </style>
</head>
<body class="h-full flex flex-col justify-between overflow-y-auto">

    <!-- Top Navigation -->
    <header class="w-full max-w-7xl mx-auto px-6 h-20 flex items-center justify-between border-b border-slate-800">
        <div class="flex items-center">
            <span class="text-3xl font-bold tracking-wider leading-[1.1] uppercase text-white" style="font-family: 'Brick Sans', sans-serif;">
                Certicode<br><span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-400 to-green-500">Labs</span>
            </span>
        </div>

        <nav class="flex items-center space-x-4">
            @auth
                <a href="{{ route('dashboard') }}" class="inline-flex items-center px-4 py-2 border border-slate-800 text-xs font-semibold rounded-lg text-white bg-slate-900 hover:bg-slate-800 transition-colors cursor-pointer">
                    Dashboard
                </a>
            @else
                <a href="{{ route('login') }}" class="text-xs font-semibold text-slate-400 hover:text-white transition-colors cursor-pointer">
                    Log in
                </a>
                @if (Route::has('register.show'))
                    <a href="{{ route('register.show') }}" class="inline-flex items-center px-4 py-2 border border-transparent text-xs font-semibold rounded-lg text-white bg-green-600 hover:bg-green-500 transition-colors shadow-lg shadow-green-500/20 cursor-pointer">
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
                <h1 class="text-4xl md:text-5xl font-extrabold text-white leading-tight tracking-tight font-mono">
                    Verify Your Coding Skills with Real-Time Sandbox Telemetry.
                </h1>
                <p class="text-slate-400 text-base md:text-lg leading-relaxed max-w-lg">
                    Certicode Labs is an interactive learning playground where developer training meets automated skill verification. Connect your GitHub repositories, complete hands-on lab assignments in live sandboxes, and build a verified portfolio of your coding competence.
                </p>
                <div class="flex items-center space-x-4">
                    <a href="{{ route('register.show') }}" class="inline-flex items-center px-6 py-3 border border-transparent text-sm font-semibold rounded-lg text-white bg-green-600 hover:bg-green-500 transition-all duration-200 shadow-lg shadow-green-500/30 cursor-pointer">
                        Create Account
                    </a>
                    <a href="{{ route('login') }}" class="inline-flex items-center px-6 py-3 border border-slate-800 text-sm font-semibold rounded-lg text-slate-300 bg-slate-900 hover:bg-slate-800 transition-colors cursor-pointer">
                        Sign In
                    </a>
                </div>
            </div>

            <!-- Hero Feature Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <!-- Feature 1 -->
                <div class="p-6 bg-slate-900/60 border border-slate-800 rounded-xl space-y-3 transition-all duration-300 hover:border-slate-700 hover:bg-slate-900 hover:shadow-lg hover:shadow-emerald-500/5 hover:-translate-y-0.5 cursor-pointer">
                    <div class="w-9 h-9 bg-blue-500/10 border border-blue-500/20 rounded-lg flex items-center justify-center text-blue-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path></svg>
                    </div>
                    <h3 class="font-bold text-white text-sm font-mono">Sandbox Workspace</h3>
                    <p class="text-xs text-slate-400 leading-relaxed">
                        Interactive terminals with full execution telemetry built for Bash, Python, and SQL scripts.
                    </p>
                </div>

                <!-- Feature 2 -->
                <div class="p-6 bg-slate-900/60 border border-slate-800 rounded-xl space-y-3 transition-all duration-300 hover:border-slate-700 hover:bg-slate-900 hover:shadow-lg hover:shadow-emerald-500/5 hover:-translate-y-0.5 cursor-pointer">
                    <div class="w-9 h-9 bg-emerald-500/10 border border-emerald-500/20 rounded-lg flex items-center justify-center text-emerald-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                    </div>
                    <h3 class="font-bold text-white text-sm font-mono">Integrity Shield</h3>
                    <p class="text-xs text-slate-400 leading-relaxed">
                        Real-time presence metrics and tab-switching monitoring to verify work authenticity.
                    </p>
                </div>

                <!-- Feature 3 -->
                <div class="p-6 bg-slate-900/60 border border-slate-800 rounded-xl space-y-3 transition-all duration-300 hover:border-slate-700 hover:bg-slate-900 hover:shadow-lg hover:shadow-emerald-500/5 hover:-translate-y-0.5 cursor-pointer">
                    <div class="w-9 h-9 bg-yellow-500/10 border border-yellow-500/20 rounded-lg flex items-center justify-center text-yellow-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    </div>
                    <h3 class="font-bold text-white text-sm font-mono">GitHub Integration</h3>
                    <p class="text-xs text-slate-400 leading-relaxed">
                        Sync your code pushes directly to generate clean performance benchmarks and graphs.
                    </p>
                </div>

                <!-- Feature 4 -->
                <div class="p-6 bg-slate-900/60 border border-slate-800 rounded-xl space-y-3 transition-all duration-300 hover:border-slate-700 hover:bg-slate-900 hover:shadow-lg hover:shadow-emerald-500/5 hover:-translate-y-0.5 cursor-pointer">
                    <div class="w-9 h-9 bg-purple-500/10 border border-purple-500/20 rounded-lg flex items-center justify-center text-purple-400">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                    </div>
                    <h3 class="font-bold text-white text-sm font-mono">Learning Paths</h3>
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
            <p class="mt-2 sm:mt-0 font-mono">Built for educators and developers.</p>
        </div>
    </footer>

</body>
</html>

</body>
</html>

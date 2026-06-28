<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Certicode Labs</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
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
                            400: '#58A6FF',
                            500: '#388BFD',
                            600: '#1F6FEB',
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
                        slate: {
                            50: '#f8fafc',
                            100: '#f1f5f9',
                            200: '#e2e8f0',
                            300: '#cbd5e1',
                            400: '#8B949E',
                            500: '#64748b',
                            600: '#475569',
                            700: '#334155',
                            800: '#30363D',
                            900: '#161B22',
                            950: '#0D1117',
                        }
                    }
                }
            }
        }
    </script>
    <!-- Inter Font -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #0D1117;
            color: #E6EDF3;
        }
    </style>
</head>
<body class="h-full flex overflow-hidden">
    <!-- LEFT SIDE: NetAcad Branding & Presentation Panel (hidden on small screens) -->
    <div class="hidden md:flex md:w-1/2 lg:w-3/5 bg-gradient-to-tr from-slate-950 via-[#111827] to-[#1e3a8a]/20 flex-col justify-between p-16 border-r border-slate-800 relative">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_bottom_left,rgba(37,99,235,0.06),transparent)] pointer-events-none"></div>
        
        <!-- Logo and Brand Name -->
        <div class="flex items-center space-x-3 z-10">
            <div class="h-10 w-10 rounded-lg bg-blue-600 flex items-center justify-center shadow-lg shadow-blue-600/30">
                <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                </svg>
            </div>
            <span class="text-xl font-bold text-white tracking-tight">Certicode Labs</span>
        </div>

        <!-- Middle Pitch Presentation -->
        <div class="max-w-xl space-y-6 z-10">
            <h1 class="text-3xl lg:text-4xl font-extrabold text-white leading-tight tracking-tight">
                Create a student or instructor account.
            </h1>
            <p class="text-slate-400 text-sm leading-relaxed">
                Unlock sandboxed coding challenge environments, track your progress through structured courses, and earn verified industry competencies.
            </p>
            <!-- Feature list with SVGs -->
            <ul class="space-y-4 pt-4">
                <li class="flex items-start space-x-3 text-xs">
                    <div class="h-5 w-5 rounded-full bg-blue-500/10 border border-blue-500/20 flex items-center justify-center text-blue-400 shrink-0 mt-0.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <div>
                        <span class="font-bold text-white block">Interactive Code Sandbox</span>
                        <span class="text-slate-400">Implement and execute coding scripts with terminal diagnostic tools.</span>
                    </div>
                </li>
                <li class="flex items-start space-x-3 text-xs">
                    <div class="h-5 w-5 rounded-full bg-emerald-500/10 border border-emerald-500/20 flex items-center justify-center text-emerald-400 shrink-0 mt-0.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                    </div>
                    <div>
                        <span class="font-bold text-white block">Academic Course Progress</span>
                        <span class="text-slate-400">Take lesson modules sequentially and unlock advanced validation benchmarks.</span>
                    </div>
                </li>
            </ul>
        </div>

        <!-- Footer -->
        <div class="text-xs text-slate-600 z-10">
            &copy; 2026 Certicode Labs. All rights reserved.
        </div>
    </div>

    <!-- RIGHT SIDE: Split Register Form Panel -->
    <div class="w-full md:w-1/2 lg:w-2/5 flex flex-col justify-center bg-[#0D1117] px-8 sm:px-16 md:px-12 lg:px-16 xl:px-20 overflow-y-auto">
        <div class="max-w-md w-full mx-auto space-y-6 py-12">
            <!-- Mobile Brand Header -->
            <div class="flex items-center space-x-2 md:hidden mb-8">
                <div class="h-8 w-8 rounded bg-blue-600 flex items-center justify-center text-white font-bold text-sm">
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                </div>
                <span class="text-lg font-bold text-white">Certicode Labs</span>
            </div>

            <!-- Header Titles -->
            <div>
                <h2 class="text-2xl font-extrabold text-white tracking-tight">
                    Create your account
                </h2>
                <p class="text-sm text-slate-400 mt-2">
                    Enter details to get started or
                    <a href="{{ route('login') }}" class="font-semibold text-blue-500 hover:underline">
                        sign in to your account
                    </a>
                </p>
            </div>

            <!-- Register Form -->
            <form class="space-y-4 pt-2" method="POST" action="{{ route('register.store') }}">
                @csrf

                <!-- Errors Handler Alert -->
                @if ($errors->any())
                    <div class="rounded-lg bg-red-500/10 border border-red-500/20 p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <svg class="h-5 w-5 text-rose-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <h3 class="text-xs font-bold text-rose-400 uppercase tracking-wider">
                                    Registration Error
                                </h3>
                                <ul class="mt-1 list-disc list-inside text-xs text-rose-300 space-y-0.5">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Full Name Input -->
                <div>
                    <label for="name" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Full Name</label>
                    <input id="name" name="name" type="text" value="{{ old('name') }}" required 
                           class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white placeholder-slate-600 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-colors" 
                           placeholder="Full Name">
                </div>

                <!-- Email Input -->
                <div>
                    <label for="email" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Email Address</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required 
                           class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white placeholder-slate-600 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-colors" 
                           placeholder="you@email.com">
                </div>

                <!-- Choose Role Input -->
                <div>
                    <label for="role" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Choose Role</label>
                    <select id="role" name="role" required 
                            class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-lg text-sm text-slate-300 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-colors">
                        <option value="" disabled selected>Choose role...</option>
                        <option value="student">Student</option>
                        <option value="instructor">Instructor / Teacher</option>
                    </select>
                </div>

                <!-- Password Input -->
                <div>
                    <label for="password" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Password</label>
                    <div class="relative">
                        <input id="password" name="password" type="password" required 
                               class="w-full pl-3.5 pr-10 py-2.5 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white placeholder-slate-600 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-colors" 
                               placeholder="••••••••">
                        <button type="button" onclick="togglePasswordVisibility('password', 'password-eye-icon')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-white">
                            <svg id="password-eye-icon" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Confirm Password Input -->
                <div>
                    <label for="password_confirmation" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Confirm Password</label>
                    <div class="relative">
                        <input id="password_confirmation" name="password_confirmation" type="password" required 
                               class="w-full pl-3.5 pr-10 py-2.5 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white placeholder-slate-600 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-colors" 
                               placeholder="••••••••">
                        <button type="button" onclick="togglePasswordVisibility('password_confirmation', 'password-confirm-eye-icon')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-white">
                            <svg id="password-confirm-eye-icon" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Action Button -->
                <div class="pt-4">
                    <button type="submit" class="w-full py-3 px-4 border border-transparent text-sm font-semibold rounded-lg text-white bg-green-600 hover:bg-green-500 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 transition-colors shadow-lg shadow-green-500/20 active:scale-[0.98]">
                        Create Account
                    </button>
                </div>
            </form>

            <div class="relative flex py-2 items-center">
                <div class="flex-grow border-t border-slate-800"></div>
                <span class="flex-shrink mx-4 text-slate-500 text-xs font-semibold uppercase tracking-wider">or</span>
                <div class="flex-grow border-t border-slate-800"></div>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <a href="{{ route('auth.provider.redirect', 'google') }}" class="flex items-center justify-center py-2.5 px-4 border border-slate-800 rounded-lg text-xs font-semibold text-white bg-slate-900 hover:bg-slate-850 transition-colors shadow-md">
                    <!-- Google logo SVG -->
                    <svg class="h-4 w-4 mr-2" viewBox="0 0 24 24" width="24" height="24">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                    </svg>
                    Google
                </a>
                <a href="{{ route('auth.provider.redirect', 'github') }}" class="flex items-center justify-center py-2.5 px-4 border border-slate-800 rounded-lg text-xs font-semibold text-white bg-slate-900 hover:bg-slate-850 transition-colors shadow-md">
                    <!-- GitHub logo SVG -->
                    <svg class="h-4 w-4 mr-2" fill="currentColor" viewBox="0 0 24 24">
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.477 2 2 6.477 2 12c0 4.42 2.865 8.166 6.839 9.489.5.092.682-.217.682-.482 0-.237-.008-.866-.013-1.7-2.782.603-3.369-1.34-3.369-1.34-.454-1.156-1.11-1.464-1.11-1.464-.908-.62.069-.608.069-.608 1.003.07 1.531 1.03 1.531 1.03.892 1.529 2.341 1.087 2.91.831.092-.646.35-1.086.636-1.336-2.22-.253-4.555-1.11-4.555-4.943 0-1.091.39-1.984 1.029-2.683-.103-.253-.446-1.27.098-2.647 0 0 .84-.269 2.75 1.025A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.294 2.747-1.025 2.747-1.025.546 1.377.203 2.394.1 2.647.64.699 1.028 1.592 1.028 2.683 0 3.842-2.339 4.687-4.566 4.935.359.309.678.919.678 1.852 0 1.336-.012 2.415-.012 2.743 0 .267.18.579.688.481C19.137 20.162 22 16.418 22 12c0-5.523-4.477-10-10-10z"/>
                    </svg>
                    GitHub
                </a>
            </div>

            <!-- Back to landing link -->
            <div class="text-center pt-4 border-t border-slate-800/60 mt-6">
                <a href="/" class="text-xs text-slate-500 hover:text-white transition-colors">
                    ← Back to Home Page
                </a>
            </div>
        </div>
    </div>
    <script>
        function togglePasswordVisibility(inputId, iconId) {
            const input = document.getElementById(inputId);
            const icon = document.getElementById(iconId);
            if (input.type === 'password') {
                input.type = 'text';
                icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.542-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.542 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21" />';
            } else {
                input.type = 'password';
                icon.innerHTML = '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />';
            }
        }
    </script>
</body>
</html>

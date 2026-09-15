<!DOCTYPE html>
<html lang="en" class="h-full bg-[#0f0f0f] text-[#ededed]">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - Certicode Labs</title>
    <!-- Google Fonts: Inter & Source Code Pro -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Source+Code+Pro:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Vite Compiled Assets -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #0f0f0f;
            color: #ededed;
        }
    </style>
</head>
<body class="h-full flex overflow-hidden selection:bg-[#3ecf8e]/20 selection:text-[#3ecf8e]">
    <!-- LEFT SIDE: Supabase-Style Developer Presentation Panel -->
    <div class="hidden md:flex md:w-1/2 lg:w-3/5 bg-[#121212] flex-col justify-between p-16 border-r border-[#232323] relative">
        <!-- Logo and Brand Name -->
        <div class="flex items-center gap-3 z-10">
            <a href="/" class="flex items-center gap-2.5">
                <div class="w-8 h-8 rounded-[6px] bg-[#171717] border border-[#2e2e2e] flex items-center justify-center">
                    <svg class="w-4 h-4 text-[#3ecf8e]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                    </svg>
                </div>
                <span class="text-xl font-bold tracking-tight text-[#ededed]">
                    Certicode <span class="text-[#3ecf8e]">Labs</span>
                </span>
            </a>
            <span class="px-2 py-0.5 rounded-[4px] bg-[#1a1a1a] border border-[#2e2e2e] text-[10px] font-mono text-[#a3a3a3] uppercase tracking-wider">
                Console
            </span>
        </div>

        <!-- Middle Pitch Presentation -->
        <div class="max-w-xl space-y-6 z-10">
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full border border-[#2e2e2e] bg-[#171717]">
                <span class="w-1.5 h-1.5 rounded-full bg-[#3ecf8e]"></span>
                <span class="text-[11px] font-mono uppercase tracking-wider text-[#a3a3a3]">Verified Developer Telemetry</span>
            </div>

            <h1 class="text-3xl lg:text-4xl font-extrabold text-[#ededed] leading-tight tracking-tight">
                Authentic coding skills. <span class="text-[#3ecf8e]">Verified by execution telemetry.</span>
            </h1>
            <p class="text-[#888888] text-sm leading-relaxed">
                Connect your IDE sandboxes, complete structured module trees, and build a verified portfolio of production-grade engineering competence.
            </p>

            <!-- Feature list -->
            <ul class="space-y-4 pt-4 border-t border-[#232323]">
                <li class="flex items-start space-x-3 text-xs">
                    <div class="h-5 w-5 rounded-[4px] bg-[#171717] border border-[#2e2e2e] flex items-center justify-center text-[#3ecf8e] shrink-0 mt-0.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    </div>
                    <div>
                        <span class="font-semibold text-[#ededed] block">Live Sandbox Telemetry</span>
                        <span class="text-[#888888]">Monitor real-time compilation, memory benchmarks, and test assertions.</span>
                    </div>
                </li>
                <li class="flex items-start space-x-3 text-xs">
                    <div class="h-5 w-5 rounded-[4px] bg-[#171717] border border-[#2e2e2e] flex items-center justify-center text-[#3ecf8e] shrink-0 mt-0.5">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                    </div>
                    <div>
                        <span class="font-semibold text-[#ededed] block">Cryptographic Credentials</span>
                        <span class="text-[#888888]">Earn public SHA-256 verified digital certificates upon course completion.</span>
                    </div>
                </li>
            </ul>
        </div>

        <!-- Footer -->
        <div class="text-xs text-[#666666] font-mono z-10">
            &copy; 2026 Certicode Labs. Built for engineers.
        </div>
    </div>

    <!-- RIGHT SIDE: Split Login Form Panel -->
    <div class="w-full md:w-1/2 lg:w-2/5 flex flex-col bg-[#0f0f0f] px-8 sm:px-14 md:px-10 lg:px-14 xl:px-16 overflow-y-auto justify-center">
        <div class="max-w-md w-full mx-auto py-12 space-y-6">
            <!-- Mobile Brand Header -->
            <div class="flex items-center gap-2.5 md:hidden mb-6">
                <div class="w-8 h-8 rounded-[6px] bg-[#171717] border border-[#2e2e2e] flex items-center justify-center">
                    <svg class="w-4 h-4 text-[#3ecf8e]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                    </svg>
                </div>
                <span class="text-xl font-bold tracking-tight text-[#ededed]">
                    Certicode <span class="text-[#3ecf8e]">Labs</span>
                </span>
            </div>

            <!-- Header Titles -->
            <div>
                <h2 class="text-2xl font-bold text-[#ededed] tracking-tight">
                    Sign in to your account
                </h2>
                <p class="text-xs text-[#888888] mt-1.5">
                    Don't have an account yet?
                    <a href="{{ route('register.show') }}" class="font-medium text-[#3ecf8e] hover:text-[#00c573] transition-colors">
                        Create an account
                    </a>
                </p>
            </div>

            <!-- Login Form -->
            <form class="space-y-4 pt-1" method="POST" action="{{ route('login.store') }}">
                @csrf

                <!-- Session status message -->
                @if (session('status'))
                    <div class="rounded-[6px] bg-[#171717] border border-[#3ecf8e]/30 p-3.5">
                        <div class="flex items-center">
                            <svg class="h-4 w-4 text-[#3ecf8e] mr-2 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            <p class="text-xs text-[#3ecf8e] font-medium">{{ session('status') }}</p>
                        </div>
                    </div>
                @endif

                <!-- Errors Handler Alert -->
                @if ($errors->any())
                    <div class="rounded-[6px] bg-[#171717] border border-red-500/30 p-3.5">
                        <div class="flex">
                            <svg class="h-4 w-4 text-red-400 mr-2 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                            </svg>
                            <div>
                                <h3 class="text-xs font-mono font-bold text-red-400 uppercase tracking-wider">
                                    Authentication Error
                                </h3>
                                <ul class="mt-1 list-disc list-inside text-xs text-red-300 space-y-0.5">
                                    @foreach ($errors->all() as $error)
                                        <li>{{ $error }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Email Input -->
                <div>
                    <label for="email" class="block text-xs font-mono uppercase tracking-wider text-[#a3a3a3] mb-1.5">Email Address</label>
                    <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus
                           class="w-full px-3 py-2.5 bg-[#141414] border border-[#2e2e2e] rounded-[6px] text-sm text-[#ededed] placeholder-[#666666] focus:outline-none focus:border-[#3ecf8e] focus:ring-1 focus:ring-[#3ecf8e] transition-colors" 
                           placeholder="developer@example.com">
                </div>

                <!-- Password Input -->
                <div>
                    <div class="flex justify-between items-center mb-1.5">
                        <label for="password" class="block text-xs font-mono uppercase tracking-wider text-[#a3a3a3]">Password</label>
                        <a href="{{ route('password.request') }}" class="text-xs text-[#888888] hover:text-[#3ecf8e] transition-colors">Forgot Password?</a>
                    </div>
                    <div class="relative">
                        <input id="password" name="password" type="password" required 
                               class="w-full pl-3 pr-10 py-2.5 bg-[#141414] border border-[#2e2e2e] rounded-[6px] text-sm text-[#ededed] placeholder-[#666666] focus:outline-none focus:border-[#3ecf8e] focus:ring-1 focus:ring-[#3ecf8e] transition-colors" 
                               placeholder="••••••••">
                        <button type="button" onclick="togglePasswordVisibility('password', 'password-eye-icon')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-[#666666] hover:text-[#ededed] transition-colors">
                            <svg id="password-eye-icon" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Remember Me checkbox -->
                <div class="flex items-center justify-between pt-1">
                    <label class="flex items-center text-xs text-[#888888] cursor-pointer select-none">
                        <input id="remember" name="remember" type="checkbox" class="h-4 w-4 rounded-[4px] bg-[#141414] border-[#2e2e2e] text-[#3ecf8e] focus:ring-[#3ecf8e] focus:ring-offset-0">
                        <span class="ml-2">Keep me signed in</span>
                    </label>
                </div>

                <!-- Action Button: Pill CTA -->
                <div class="pt-2">
                    <button type="submit" class="w-full py-2.5 px-4 rounded-full bg-[#3ecf8e] text-sm font-semibold text-[#0f0f0f] hover:bg-[#00c573] focus:outline-none transition-colors">
                        Sign In
                    </button>
                </div>
            </form>

            <!-- Hairline Divider -->
            <div class="relative flex py-2 items-center">
                <div class="flex-grow border-t border-[#232323]"></div>
                <span class="flex-shrink mx-3 text-[#666666] text-[10px] font-mono uppercase tracking-wider">or continue with</span>
                <div class="flex-grow border-t border-[#232323]"></div>
            </div>

            <!-- Social Providers: 6px controls -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <a href="{{ route('auth.provider.redirect', 'google') }}" class="flex items-center justify-center py-2 px-4 rounded-[6px] border border-[#2e2e2e] bg-[#171717] text-xs font-semibold text-[#ededed] hover:bg-[#222222] hover:border-[#383838] transition">
                    <svg class="h-4 w-4 mr-2" viewBox="0 0 24 24">
                        <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                        <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                        <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                        <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                    </svg>
                    Google
                </a>
                <a href="{{ route('auth.provider.redirect', 'github') }}" class="flex items-center justify-center py-2 px-4 rounded-[6px] border border-[#2e2e2e] bg-[#171717] text-xs font-semibold text-[#ededed] hover:bg-[#222222] hover:border-[#383838] transition">
                    <svg class="h-4 w-4 mr-2" fill="currentColor" viewBox="0 0 24 24">
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.477 2 2 6.477 2 12c0 4.42 2.865 8.166 6.839 9.489.5.092.682-.217.682-.482 0-.237-.008-.866-.013-1.7-2.782.603-3.369-1.34-3.369-1.34-.454-1.156-1.11-1.464-1.11-1.464-.908-.62.069-.608.069-.608 1.003.07 1.531 1.03 1.531 1.03.892 1.529 2.341 1.087 2.91.831.092-.646.35-1.086.636-1.336-2.22-.253-4.555-1.11-4.555-4.943 0-1.091.39-1.984 1.029-2.683-.103-.253-.446-1.27.098-2.647 0 0 .84-.269 2.75 1.025A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.294 2.747-1.025 2.747-1.025.546 1.377.203 2.394.1 2.647.64.699 1.028 1.592 1.028 2.683 0 3.842-2.339 4.687-4.566 4.935.359.309.678.919.678 1.852 0 1.336-.012 2.415-.012 2.743 0 .267.18.579.688.481C19.137 20.162 22 16.418 22 12c0-5.523-4.477-10-10-10z"/>
                    </svg>
                    GitHub
                </a>
            </div>

            <!-- Back to landing link -->
            <div class="text-center pt-4 border-t border-[#232323]">
                <a href="/" class="text-xs font-mono text-[#666666] hover:text-[#ededed] transition-colors">
                    &larr; Return to Home
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

<!DOCTYPE html>
<html lang="en" class="h-full bg-[#0f0f0f] text-[#ededed]">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="alternate icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <title>Reset Password - Certicode Labs</title>
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
<body class="h-full flex items-center justify-center p-4 selection:bg-[#3ecf8e]/20 selection:text-[#3ecf8e]">
    <div class="max-w-md w-full bg-[#171717] border border-[#2e2e2e] rounded-xl p-8 space-y-6">
        <!-- Logo and Header -->
        <div class="flex flex-col items-center space-y-3">
            <div class="w-10 h-10 rounded-[6px] bg-[#141414] border border-[#2e2e2e] flex items-center justify-center text-[#3ecf8e]">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                    <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
                </svg>
            </div>
            <div class="text-center space-y-1">
                <h1 class="text-xl font-bold text-[#ededed] tracking-tight">Reset Password</h1>
                <p class="text-xs text-[#888888]">Enter a secure new password for your account</p>
            </div>
        </div>

        <!-- Form -->
        <form action="{{ route('password.update') }}" method="POST" class="space-y-4">
            @csrf
            
            <input type="hidden" name="token" value="{{ $token }}">

            <!-- Success Alert -->
            @if (session('status'))
                <div class="rounded-[6px] bg-[#141414] border border-[#3ecf8e]/30 p-3.5">
                    <p class="text-xs text-[#3ecf8e] font-medium leading-relaxed">{{ session('status') }}</p>
                </div>
            @endif

            <!-- Errors Alert -->
            @if ($errors->any())
                <div class="rounded-[6px] bg-[#141414] border border-red-500/30 p-3.5">
                    <ul class="list-disc list-inside text-xs text-red-300 space-y-0.5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- Email Input -->
            <div>
                <label for="email" class="block text-xs font-mono uppercase tracking-wider text-[#a3a3a3] mb-1.5">Email Address</label>
                <input id="email" name="email" type="email" required value="{{ old('email', $email) }}"
                       class="w-full px-3.5 py-2.5 bg-[#141414] border border-[#2e2e2e] rounded-[6px] text-sm text-[#ededed] placeholder-[#666666] focus:outline-none focus:border-[#3ecf8e] focus:ring-1 focus:ring-[#3ecf8e] transition-colors"
                       placeholder="developer@example.com">
            </div>

            <!-- Password Input -->
            <div>
                <label for="password" class="block text-xs font-mono uppercase tracking-wider text-[#a3a3a3] mb-1.5">New Password</label>
                <div class="relative">
                    <input id="password" name="password" type="password" required autofocus
                           class="w-full pl-3.5 pr-10 py-2.5 bg-[#141414] border border-[#2e2e2e] rounded-[6px] text-sm text-[#ededed] placeholder-[#666666] focus:outline-none focus:border-[#3ecf8e] focus:ring-1 focus:ring-[#3ecf8e] transition-colors"
                           placeholder="••••••••">
                    <button type="button" onclick="togglePasswordVisibility('password', 'password-eye-icon')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-[#666666] hover:text-[#ededed] transition-colors">
                        <svg id="password-eye-icon" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Confirm Password Input -->
            <div>
                <label for="password_confirmation" class="block text-xs font-mono uppercase tracking-wider text-[#a3a3a3] mb-1.5">Confirm Password</label>
                <div class="relative">
                    <input id="password_confirmation" name="password_confirmation" type="password" required
                           class="w-full pl-3.5 pr-10 py-2.5 bg-[#141414] border border-[#2e2e2e] rounded-[6px] text-sm text-[#ededed] placeholder-[#666666] focus:outline-none focus:border-[#3ecf8e] focus:ring-1 focus:ring-[#3ecf8e] transition-colors"
                           placeholder="••••••••">
                    <button type="button" onclick="togglePasswordVisibility('password_confirmation', 'password-confirm-eye-icon')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-[#666666] hover:text-[#ededed] transition-colors">
                        <svg id="password-confirm-eye-icon" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Action Button: Pill CTA -->
            <button type="submit" class="w-full py-2.5 px-4 rounded-full bg-[#3ecf8e] text-sm font-semibold text-[#0f0f0f] hover:bg-[#00c573] focus:outline-none transition-colors">
                Reset Password
            </button>

            <!-- Back link -->
            <div class="text-center pt-2 border-t border-[#232323]">
                <a href="{{ route('login') }}" class="text-xs font-mono text-[#666666] hover:text-[#ededed] transition-colors">
                    &larr; Back to Login
                </a>
            </div>
        </form>
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

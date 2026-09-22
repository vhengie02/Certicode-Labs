<!DOCTYPE html>
<html lang="en" class="h-full bg-[#0f0f0f] text-[#ededed]">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
    <link rel="alternate icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    <title>Forgot Password - Certicode Labs</title>
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
                <h1 class="text-xl font-bold text-[#ededed] tracking-tight">Forgot Password</h1>
                <p class="text-xs text-[#888888]">Enter your email to receive a password reset link</p>
            </div>
        </div>

        <!-- Form -->
        <form action="{{ route('password.email') }}" method="POST" class="space-y-4">
            @csrf

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
                <input id="email" name="email" type="email" required autofocus value="{{ old('email') }}"
                       class="w-full px-3.5 py-2.5 bg-[#141414] border border-[#2e2e2e] rounded-[6px] text-sm text-[#ededed] placeholder-[#666666] focus:outline-none focus:border-[#3ecf8e] focus:ring-1 focus:ring-[#3ecf8e] transition-colors"
                       placeholder="developer@example.com">
            </div>

            <!-- Action Button: Pill CTA -->
            <button type="submit" class="w-full py-2.5 px-4 rounded-full bg-[#3ecf8e] text-sm font-semibold text-[#0f0f0f] hover:bg-[#00c573] focus:outline-none transition-colors">
                Send Reset Link
            </button>

            <!-- Back link -->
            <div class="text-center pt-2 border-t border-[#232323]">
                <a href="{{ route('login') }}" class="text-xs font-mono text-[#666666] hover:text-[#ededed] transition-colors">
                    &larr; Back to Login
                </a>
            </div>
        </form>
    </div>
</body>
</html>

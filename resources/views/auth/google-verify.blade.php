<!DOCTYPE html>
<html lang="en" class="h-full bg-[#FFFFFF]">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign in with Google - Verify</title>
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
                @if(request('needs_role'))
                    <h1 class="text-2xl font-normal text-[#202124]">Create account</h1>
                    <p class="text-sm text-[#5f6368]">Choose your role and enter verification code</p>
                @else
                    <h1 class="text-2xl font-normal text-[#202124]">Verification Code</h1>
                    <p class="text-sm text-[#5f6368]">Confirm ownership of your Google account</p>
                @endif
            </div>
        </div>

        <!-- Google OAuth Form -->
        <form action="{{ route('auth.google.callback') }}" method="POST" class="space-y-6">
            @csrf

            <!-- Success/Debug alert -->
            @if(session('success'))
                <div class="bg-blue-50 border border-blue-200 text-blue-800 p-3 rounded text-xs leading-normal">
                    {{ session('success') }}
                </div>
            @endif

            <!-- Warning alert -->
            @if(session('warning'))
                <div class="bg-amber-50 border border-amber-200 text-amber-800 p-3 rounded text-xs leading-normal">
                    {{ session('warning') }}
                </div>
            @endif

            <!-- Errors Handler Alert -->
            @if ($errors->any())
                <div class="rounded-lg bg-red-50 border border-red-200 p-3 text-xs text-red-650 space-y-0.5">
                    @foreach ($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            <div class="bg-[#f8f9fa] border border-[#dadce0] rounded p-3 text-xs text-[#202124] font-medium truncate">
                Connecting: <span class="font-mono text-[#1a73e8]">{{ session('google_auth_gmail') }}</span>
            </div>

            <!-- 1. Role select for registration -->
            @if(request('needs_role'))
                <div class="space-y-2">
                    <label class="block text-xs font-medium text-[#5f6368] uppercase tracking-wider">Account Role</label>
                    <select name="role" required class="w-full px-3 py-2 border border-[#dadce0] rounded text-sm text-[#202124] bg-white focus:outline-none focus:border-[#1a73e8]">
                        <option value="student">Student</option>
                        <option value="instructor">Instructor</option>
                    </select>
                </div>
            @endif

            <!-- 2. Code select for all -->
            <div>
                <label for="code" class="block text-xs font-medium text-[#5f6368] uppercase tracking-wider mb-2">Verification Code (6 digits)</label>
                <input type="text" name="code" id="code" required maxlength="6" autofocus placeholder="######"
                       class="w-full text-center tracking-[0.25em] font-mono px-4 py-3 border border-[#dadce0] rounded text-sm text-[#202124] focus:outline-none focus:border-[#1a73e8] focus:ring-1 focus:ring-[#1a73e8] transition-all">
            </div>

            <!-- Footer Buttons -->
            <div class="flex items-center justify-between pt-4">
                <a href="{{ route('auth.google') }}" class="text-sm font-medium text-[#1a73e8] hover:text-[#174ea6] transition-colors">
                    Back
                </a>
                <button type="submit" class="bg-[#1a73e8] hover:bg-[#1b66ca] text-white text-sm font-medium px-6 py-2.5 rounded transition shadow-sm">
                    Complete
                </button>
            </div>
        </form>
    </div>
</body>
</html>

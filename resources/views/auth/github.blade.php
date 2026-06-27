<!DOCTYPE html>
<html lang="en" class="h-full bg-[#0d1117]">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Authorize Certicode Labs with GitHub</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #0d1117;
        }
    </style>
</head>
<body class="min-h-full flex flex-col items-center justify-center p-4">
    <!-- Main Authorization Container -->
    <div class="max-w-[480px] w-full bg-[#161b22] border border-[#30363d] rounded-xl p-8 shadow-2xl text-left space-y-6">
        <!-- Brand Header Section -->
        <div class="flex items-center justify-center space-x-6 pb-6 border-b border-[#30363d]">
            <!-- GitHub Logo -->
            <svg class="h-12 w-12 text-[#f0f6fc]" fill="currentColor" viewBox="0 0 16 16" version="1.1" aria-hidden="true">
                <path fill-rule="evenodd" d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0016 8c0-4.42-3.58-8-8-8z"></path>
            </svg>
            <div class="text-[#8b949e] font-light text-2xl">&harr;</div>
            <!-- Certicode Shield Logo -->
            <div class="h-12 w-12 rounded-xl bg-indigo-600 flex items-center justify-center shadow-lg shadow-indigo-600/30">
                <svg class="w-7 h-7 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                </svg>
            </div>
        </div>

        <div class="space-y-2">
            <h1 class="text-xl font-semibold text-[#f0f6fc]">Authorize Certicode Labs</h1>
            <p class="text-sm text-[#8b949e] leading-relaxed">
                Certicode Labs is requesting permission to access your public profile and read your connected email addresses.
            </p>
        </div>

        <!-- Warning block informing user this is a simulated local environment -->
        <div class="bg-blue-500/10 border border-blue-500/25 p-4 rounded-lg flex items-start space-x-3 text-xs text-blue-450">
            <svg class="w-5 h-5 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <div>
                <span class="font-bold text-white block">Simulated Local Environment</span>
                Since GitHub OAuth keys are not configured in your <code class="bg-[#1f242c] px-1 py-0.5 rounded font-mono">.env</code>, you are using Mock GitHub Sign-In. You can type any username and email address to verify functionality.
            </div>
        </div>

        <!-- Mock Form -->
        <form action="{{ route('auth.github.callback') }}" method="POST" class="space-y-4">
            @csrf

            <!-- Errors Handler Alert -->
            @if ($errors->any())
                <div class="rounded-lg bg-red-900/20 border border-red-500/30 p-4 text-xs text-red-400 space-y-1">
                    @foreach ($errors->all() as $error)
                        <p class="flex items-center">
                            <span class="inline-block w-1 h-1 rounded-full bg-red-400 mr-2"></span>
                            {{ $error }}
                        </p>
                    @endforeach
                </div>
            @endif

            <!-- GitHub Username -->
            <div>
                <label for="github_username" class="block text-xs font-semibold text-[#8b949e] uppercase tracking-wider mb-2">GitHub Username</label>
                <input type="text" name="github_username" id="github_username" required autofocus value="{{ old('github_username', 'octocat') }}"
                       class="w-full px-4 py-2.5 bg-[#0d1117] border border-[#30363d] rounded-lg text-sm text-[#f0f6fc] placeholder-[#484f58] focus:outline-none focus:border-[#58a6ff] focus:ring-1 focus:ring-[#58a6ff] transition-all font-mono"
                       placeholder="e.g. octocat">
            </div>

            <!-- GitHub Email Address (Only shown/required if user is NOT logged in) -->
            @if (!auth()->check())
                <div>
                    <label for="github_email" class="block text-xs font-semibold text-[#8b949e] uppercase tracking-wider mb-2">GitHub Primary Email</label>
                    <input type="email" name="github_email" id="github_email" required value="{{ old('github_email', 'octocat@github.com') }}"
                           class="w-full px-4 py-2.5 bg-[#0d1117] border border-[#30363d] rounded-lg text-sm text-[#f0f6fc] placeholder-[#484f58] focus:outline-none focus:border-[#58a6ff] focus:ring-1 focus:ring-[#58a6ff] transition-all font-mono"
                           placeholder="e.g. user@example.com">
                </div>

                <!-- GitHub Name -->
                <div>
                    <label for="github_name" class="block text-xs font-semibold text-[#8b949e] uppercase tracking-wider mb-2">GitHub Profile Name</label>
                    <input type="text" name="github_name" id="github_name" value="{{ old('github_name', 'The Octocat') }}"
                           class="w-full px-4 py-2.5 bg-[#0d1117] border border-[#30363d] rounded-lg text-sm text-[#f0f6fc] placeholder-[#484f58] focus:outline-none focus:border-[#58a6ff] focus:ring-1 focus:ring-[#58a6ff] transition-all"
                           placeholder="e.g. Jane Doe">
                </div>
            @endif

            <!-- Form Actions -->
            <div class="flex items-center justify-between pt-4 border-t border-[#30363d] mt-6">
                @if (auth()->check())
                    <a href="{{ route('settings.show') }}" class="text-sm font-medium text-[#58a6ff] hover:underline transition-colors">
                        Cancel
                    </a>
                @else
                    <a href="{{ route('login') }}" class="text-sm font-medium text-[#58a6ff] hover:underline transition-colors">
                        Back to Login
                    </a>
                @endif
                <button type="submit" class="bg-[#238636] hover:bg-[#2ea043] text-white text-sm font-semibold px-6 py-2.5 rounded-lg transition shadow-lg shadow-[#238636]/15 hover:shadow-[#2ea043]/20">
                    Authorize Certicode-Labs
                </button>
            </div>
        </form>
    </div>
</body>
</html>

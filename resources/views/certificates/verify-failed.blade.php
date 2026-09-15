<!DOCTYPE html>
<html lang="en" class="h-full bg-[#0f0f0f] text-[#ededed]">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Certificate - Failed</title>
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
<body class="min-h-full flex items-center justify-center p-6 bg-[#0f0f0f] selection:bg-[#3ecf8e]/20 selection:text-[#3ecf8e]">
    <div class="max-w-md w-full bg-[#171717] border border-[#2e2e2e] rounded-xl p-8 relative overflow-hidden space-y-6">
        <!-- Failure Emblem -->
        <div class="flex flex-col items-center text-center space-y-3">
            <div class="h-14 w-14 rounded-full bg-[#141414] border border-red-500/30 flex items-center justify-center text-red-400">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
            </div>
            <div>
                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-mono tracking-wider bg-[#141414] text-red-400 border border-red-500/30 uppercase">
                    Verification Failed
                </span>
                <h1 class="text-xl font-bold text-[#ededed] mt-2">Invalid Certificate Hash</h1>
            </div>
        </div>

        <!-- Details -->
        <div class="border-t border-[#232323] pt-5 text-center text-xs space-y-3">
            <p class="text-[#a3a3a3] leading-relaxed">
                The verification code provided (<span class="font-mono text-red-400 font-bold uppercase">{{ $code }}</span>) is invalid or has not been logged in the Certicode ledger.
            </p>
            <p class="text-[#666666] font-mono">
                Please check for typing errors or request a direct verification link from the student.
            </p>
        </div>

        <div class="pt-4 text-center border-t border-[#232323]">
            <a href="/" class="inline-flex items-center text-xs font-mono text-[#666666] hover:text-[#ededed] transition-colors">
                &larr; Return to Certicode Labs
            </a>
        </div>
    </div>
</body>
</html>

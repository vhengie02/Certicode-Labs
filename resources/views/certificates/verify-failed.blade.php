<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Certificate - Failed</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #0D1117;
            color: #E6EDF3;
        }
    </style>
</head>
<body class="min-h-full flex items-center justify-center p-6 bg-slate-950">
    <div class="max-w-md w-full bg-[#161B22] border border-slate-800 rounded-2xl p-8 relative overflow-hidden shadow-2xl space-y-6">
        <!-- Background accents -->
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(239,68,68,0.06),transparent)] pointer-events-none"></div>

        <!-- Failure Emblem -->
        <div class="flex flex-col items-center text-center space-y-3">
            <div class="h-16 w-16 rounded-full bg-rose-500/10 border border-rose-500/30 flex items-center justify-center text-rose-500 shadow-lg shadow-rose-500/5">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path>
                </svg>
            </div>
            <div>
                <span class="px-2.5 py-0.5 rounded text-[10px] font-bold font-mono tracking-wider bg-rose-500/10 text-rose-450 border border-rose-500/20 uppercase">
                    Verification Failed
                </span>
                <h1 class="text-xl font-bold text-white mt-2">Invalid Certificate Hash</h1>
            </div>
        </div>

        <!-- Details -->
        <div class="border-t border-slate-800/80 pt-5 text-center text-sm space-y-4">
            <p class="text-slate-400 leading-relaxed">
                The verification code you provided (<span class="font-mono text-rose-400 font-bold uppercase">{{ $code }}</span>) is invalid or has not been registered inside the Certicode telemetry ledger database.
            </p>
            <p class="text-xs text-slate-500">
                Please verify the code format, check for typing errors, or request the issuer to send a corrected link.
            </p>
        </div>

        <div class="pt-4 text-center">
            <a href="/" class="inline-flex items-center text-xs font-bold text-slate-500 hover:text-slate-300 transition-colors">
                Return to Certicode Labs Homepage
            </a>
        </div>
    </div>
</body>
</html>

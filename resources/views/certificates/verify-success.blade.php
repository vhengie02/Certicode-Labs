<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-950">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Certificate - Certicode Labs</title>
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
    <div class="max-w-xl w-full bg-[#161B22] border border-slate-800 rounded-2xl p-8 relative overflow-hidden shadow-2xl space-y-6">
        <!-- Background accents -->
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(34,197,94,0.06),transparent)] pointer-events-none"></div>

        <!-- Success Seal -->
        <div class="flex flex-col items-center text-center space-y-3">
            <div class="h-16 w-16 rounded-full bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-center text-emerald-400 shadow-lg shadow-emerald-500/5">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                </svg>
            </div>
            <div>
                <span class="px-2.5 py-0.5 rounded text-[10px] font-bold font-mono tracking-wider bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 uppercase">
                    Authentic Credential Verified
                </span>
                <h1 class="text-xl font-bold text-white mt-2">Certicode Verification Success</h1>
            </div>
        </div>

        <!-- Core Details -->
        <div class="border-t border-slate-800/80 pt-5 space-y-4 text-sm">
            <div class="flex justify-between">
                <span class="text-slate-450">Student Name:</span>
                <span class="text-white font-semibold">{{ $certificate->user->name }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-slate-450">Curriculum Completed:</span>
                <span class="text-indigo-400 font-semibold">{{ $certificate->schoolClass->name }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-slate-450">Verification Code:</span>
                <span class="text-amber-500 font-mono font-bold uppercase">{{ $certificate->verification_code }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-slate-450">Issued Date:</span>
                <span class="text-slate-300 font-mono">{{ $certificate->issued_at->format('M d, Y') }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-slate-450">Instructor:</span>
                <span class="text-slate-350">{{ $certificate->schoolClass->instructor->name }}</span>
            </div>
        </div>

        <!-- Completed Modules Section -->
        <div class="bg-slate-950/40 rounded-xl border border-slate-850 p-4 space-y-3">
            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Curriculum Accomplishments Checkpoints</h3>
            <div class="space-y-2 max-h-40 overflow-y-auto pr-1">
                @foreach($certificate->schoolClass->modules as $module)
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-slate-300 truncate mr-2">{{ $module->title }}</span>
                        <span class="text-emerald-400 font-semibold flex items-center shrink-0">
                            <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            Competencies Met
                        </span>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="pt-4 text-center">
            <a href="/" class="inline-flex items-center text-xs font-bold text-slate-500 hover:text-slate-300 transition-colors">
                Return to Certicode Labs Homepage
            </a>
        </div>
    </div>
</body>
</html>

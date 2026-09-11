<!DOCTYPE html>
<html lang="en" class="h-full bg-[#0f0f0f] text-[#ededed]">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verify Certificate - Certicode Labs</title>
    <!-- Google Fonts: Inter & Source Code Pro -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Source+Code+Pro:wght@400;500;600;700&display=swap" rel="stylesheet">
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    fontFamily: {
                        sans: ['Inter', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'sans-serif'],
                        mono: ['"Source Code Pro"', 'ui-monospace', 'monospace'],
                    },
                    colors: {
                        canvas: '#0f0f0f',
                        surface: '#171717',
                        brand: {
                            DEFAULT: '#3ecf8e',
                            hover: '#00c573',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background-color: #0f0f0f;
            color: #ededed;
        }
    </style>
</head>
<body class="min-h-full flex items-center justify-center p-6 bg-[#0f0f0f] selection:bg-[#3ecf8e]/20 selection:text-[#3ecf8e]">
    <div class="max-w-xl w-full bg-[#171717] border border-[#2e2e2e] rounded-xl p-8 relative overflow-hidden space-y-6">
        <!-- Success Seal -->
        <div class="flex flex-col items-center text-center space-y-3">
            <div class="h-14 w-14 rounded-full bg-[#141414] border border-[#3ecf8e]/40 flex items-center justify-center text-[#3ecf8e]">
                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path>
                </svg>
            </div>
            <div>
                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-mono tracking-wider bg-[#141414] text-[#3ecf8e] border border-[#3ecf8e]/30 uppercase">
                    Authentic Credential Verified
                </span>
                <h1 class="text-xl font-bold text-[#ededed] mt-2">Certicode Verification Success</h1>
            </div>
        </div>

        <!-- Core Details -->
        <div class="border-t border-[#232323] pt-5 space-y-3 text-xs font-mono">
            <div class="flex justify-between">
                <span class="text-[#888888]">Student Name:</span>
                <span class="text-[#ededed] font-sans font-semibold text-sm">{{ $certificate->user->name }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-[#888888]">Curriculum Completed:</span>
                <span class="text-[#3ecf8e] font-semibold">{{ $certificate->schoolClass->name }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-[#888888]">Verification Code:</span>
                <span class="text-[#3ecf8e] font-bold uppercase">{{ $certificate->verification_code }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-[#888888]">Issued Date:</span>
                <span class="text-[#a3a3a3]">{{ $certificate->issued_at->format('M d, Y') }}</span>
            </div>
            <div class="flex justify-between">
                <span class="text-[#888888]">Instructor:</span>
                <span class="text-[#a3a3a3]">{{ $certificate->schoolClass->instructor->name }}</span>
            </div>
        </div>

        <!-- Completed Modules Section -->
        <div class="bg-[#141414] rounded-[6px] border border-[#2e2e2e] p-4 space-y-3">
            <h3 class="text-[11px] font-mono uppercase font-bold tracking-wider text-[#a3a3a3]">Curriculum Accomplishments Checkpoints</h3>
            <div class="space-y-2 max-h-40 overflow-y-auto pr-1">
                @foreach($certificate->schoolClass->modules as $module)
                    <div class="flex items-center justify-between text-xs">
                        <span class="text-[#ededed] truncate mr-2">{{ $module->title }}</span>
                        <span class="text-[#3ecf8e] font-mono font-semibold flex items-center shrink-0">
                            <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                            Verified
                        </span>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="pt-4 text-center border-t border-[#232323]">
            <a href="/" class="inline-flex items-center text-xs font-mono text-[#666666] hover:text-[#ededed] transition-colors">
                &larr; Return to Certicode Labs
            </a>
        </div>
    </div>
</body>
</html>

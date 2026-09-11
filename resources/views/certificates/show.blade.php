@extends('layouts.app')

@section('title', 'Your Competency Certificate')
@section('page_header', 'Student Certificate View')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <!-- Back Navigation and Actions Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <a href="{{ route('classes.show', $certificate->schoolClass->id) }}" class="inline-flex items-center text-xs font-mono text-[#888888] hover:text-[#ededed] transition-colors">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            &larr; Back to Class View
        </a>
        <div class="flex items-center space-x-3">
            <button onclick="window.print()" class="inline-flex items-center px-4 py-2 border border-[#2e2e2e] text-xs font-mono font-medium rounded-[6px] text-[#ededed] bg-[#171717] hover:bg-[#222222] hover:border-[#383838] transition">
                <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                Print / Save PDF
            </button>
            <button onclick="copyVerificationLink()" class="inline-flex items-center px-5 py-2 text-xs font-semibold rounded-full text-[#0f0f0f] bg-[#3ecf8e] hover:bg-[#00c573] transition-colors shadow-none">
                <svg class="w-3.5 h-3.5 mr-1.5 text-[#0f0f0f]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"></path></svg>
                Copy Public Link
            </button>
        </div>
    </div>

    <!-- Certificate Card -->
    <div id="certificate-print-area" class="bg-[#141414] rounded-2xl border border-[#2e2e2e] p-8 sm:p-12 relative overflow-hidden">
        <!-- Certificate Subtle Emerald Border Accent -->
        <div class="absolute inset-0 border-[4px] border-double border-[#3ecf8e]/20 rounded-2xl pointer-events-none m-3"></div>

        <div class="relative z-10 space-y-8 text-center">
            <!-- Header Seal / Logo -->
            <div class="flex justify-center">
                <div class="h-16 w-16 rounded-full bg-[#171717] border border-[#3ecf8e]/40 flex items-center justify-center">
                    <svg class="w-8 h-8 text-[#3ecf8e]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path>
                    </svg>
                </div>
            </div>

            <!-- Title -->
            <div class="space-y-2">
                <span class="text-xs font-bold uppercase tracking-[0.25em] text-[#3ecf8e] font-mono">Verified Competency Credential</span>
                <h1 class="text-3xl sm:text-4xl font-extrabold text-[#ededed] font-serif">Certificate of Completion</h1>
            </div>

            <!-- Statement -->
            <div class="space-y-4 max-w-xl mx-auto">
                <p class="text-xs text-[#888888] font-mono">This document hereby certifies that</p>
                <h2 class="text-2xl sm:text-3xl font-bold text-[#ededed] tracking-wide border-b border-[#2e2e2e] pb-2 font-sans">{{ $certificate->user->name }}</h2>
                <p class="text-xs text-[#888888] leading-relaxed">
                    has successfully achieved all required competencies and completed all laboratory exercises for the curriculum course
                </p>
                <h3 class="text-lg sm:text-xl font-bold text-[#3ecf8e] font-mono">{{ $certificate->schoolClass->name }}</h3>
                <p class="text-[11px] text-[#666666] leading-normal font-mono">
                    under strict telemetry supervision logging, completing code activities, matching verification criteria, and demonstrating proficiency.
                </p>
            </div>

            <!-- Signature & QR Row -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8 pt-8 items-center max-w-3xl mx-auto border-t border-[#232323]">
                <!-- Instructor Signature -->
                <div class="space-y-2 text-center md:text-left font-mono">
                    <p class="text-sm text-[#ededed] italic">{{ $certificate->schoolClass->instructor->name }}</p>
                    <div class="h-0.5 bg-[#2e2e2e] w-32 mx-auto md:mx-0"></div>
                    <p class="text-[10px] text-[#888888] uppercase tracking-wider">Authorized Instructor</p>
                </div>

                <!-- SVG QR Code Visualizer -->
                <div class="flex justify-center">
                    <div class="p-3 bg-white rounded-xl shadow-lg border border-slate-200">
                        <svg class="w-20 h-20 text-[#0f0f0f]" viewBox="0 0 100 100" fill="currentColor">
                            <rect x="0" y="0" width="20" height="20"/>
                            <rect x="5" y="5" width="10" height="10" fill="white"/>
                            <rect x="80" y="0" width="20" height="20"/>
                            <rect x="85" y="5" width="10" height="10" fill="white"/>
                            <rect x="0" y="80" width="20" height="20"/>
                            <rect x="5" y="85" width="10" height="10" fill="white"/>
                            <rect x="40" y="40" width="20" height="20"/>
                            <rect x="30" y="10" width="5" height="10"/>
                            <rect x="50" y="15" width="10" height="5"/>
                            <rect x="15" y="30" width="5" height="15"/>
                            <rect x="65" y="50" width="5" height="15"/>
                            <rect x="35" y="65" width="15" height="5"/>
                            <rect x="80" y="45" width="10" height="10"/>
                            <rect x="55" y="80" width="15" height="5"/>
                            <rect x="80" y="70" width="5" height="10"/>
                            <rect x="10" y="55" width="10" height="5"/>
                        </svg>
                    </div>
                </div>

                <!-- Verification Stamp -->
                <div class="space-y-2 text-center md:text-right font-mono">
                    <p class="text-xs text-[#888888] tracking-wider">VERIFICATION ID</p>
                    <p class="text-xs font-bold text-[#3ecf8e] uppercase tracking-wide">{{ $certificate->verification_code }}</p>
                    <div class="h-0.5 bg-[#2e2e2e] w-32 mx-auto md:ml-auto md:mr-0"></div>
                    <p class="text-[10px] text-[#666666]">Issued On: {{ $certificate->issued_at->format('M d, Y') }}</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Print-only Style adjustments -->
<style>
    @media print {
        body * {
            visibility: hidden;
        }
        #certificate-print-area, #certificate-print-area * {
            visibility: visible;
        }
        #certificate-print-area {
            position: absolute;
            left: 0;
            top: 0;
            width: 100%;
            border: none;
            background: white !important;
            color: black !important;
        }
        #certificate-print-area h1, 
        #certificate-print-area h2, 
        #certificate-print-area h3, 
        #certificate-print-area p, 
        #certificate-print-area span {
            color: black !important;
        }
    }
</style>
@endsection

@section('scripts')
<script>
    function copyVerificationLink() {
        const link = "{{ url('/verify-certificate/' . $certificate->verification_code) }}";
        navigator.clipboard.writeText(link).then(() => {
            alert('Verification link copied to clipboard!');
        }).catch(err => {
            console.error('Failed to copy link: ', err);
        });
    }
</script>
@endsection

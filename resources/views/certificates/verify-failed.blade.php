@extends('layouts.public')

@section('title', 'Certificate not found')

@section('content')
<div class="mx-auto max-w-xl space-y-6">
    <section class="rounded-2xl border border-red-500/35 bg-red-500/[0.06] p-6" role="alert">
        <div class="flex items-start gap-4">
            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-red-500/15 text-red-400" aria-hidden="true">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
            </span>
            <div class="min-w-0">
                <h1 class="text-xl font-bold text-[#ededed]">We couldn't find this certificate</h1>
                <p class="mt-1 text-sm text-[#a3a3a3]">
                    No Certicode certificate has the code <span class="font-mono text-red-300 break-all">{{ $code }}</span>.
                    Treat a certificate with this code as not verified.
                </p>
            </div>
        </div>
    </section>

    <section class="rounded-xl border border-[#2e2e2e] bg-[#171717] p-5">
        <h2 class="font-semibold text-[#ededed]">Check the code</h2>
        <p class="mt-1 text-sm text-[#888888]">Codes look like <span class="font-mono text-[#a3a3a3]">CERT-7Q2LMX9KD3PA</span> and are printed at the top right of the certificate.</p>
        <form id="verify-form" class="mt-4 flex flex-col sm:flex-row gap-2">
            <label for="verify-code" class="sr-only">Verification code</label>
            <input id="verify-code" name="code" required placeholder="CERT-…" autocomplete="off" spellcheck="false"
                   class="flex-1 min-w-0 rounded-lg border border-[#2e2e2e] bg-[#141414] px-3 py-2.5 font-mono text-sm uppercase text-[#ededed] placeholder-[#666666] focus:outline-none focus:border-[#3ecf8e]">
            <button type="submit" class="rounded-lg bg-[#3ecf8e] px-4 py-2.5 text-sm font-semibold text-[#06150e] hover:bg-[#00c573]">Verify</button>
        </form>
    </section>
</div>
@endsection

@push('scripts')
<script>
    document.getElementById('verify-form').addEventListener('submit', (e) => {
        e.preventDefault();
        const code = document.getElementById('verify-code').value.trim().toUpperCase();
        if (code) window.location.href = @json(url('/verify-certificate')) + '/' + encodeURIComponent(code);
    });
</script>
@endpush

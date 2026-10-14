@extends('layouts.app')

@section('title', 'Certificate · ' . $certificate->schoolClass->name)

@section('content')
@include('certificates._styles')
<div class="max-w-5xl mx-auto space-y-6">
    <div class="no-print">
        <x-page-header :back="route('classes.show', $certificate->schoolClass->id)" :back-label="$certificate->schoolClass->name"
                       title="Certificate" subtitle="Anyone can confirm it's genuine by scanning the QR code or opening the verification link.">
            <button type="button" onclick="window.print()" class="ui-btn ui-btn-secondary">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v12m0 0l-4-4m4 4l4-4M4 18v1a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-1"/></svg>
                Download PDF
            </button>
            <button type="button" class="ui-btn ui-btn-secondary" data-copy-link="{{ $certificate->verificationUrl() }}">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/></svg>
                <span data-copy-label>Copy verification link</span>
            </button>
            <a href="{{ $certificate->verificationUrl() }}" target="_blank" rel="noopener" class="ui-btn ui-btn-primary">Open public page</a>
        </x-page-header>
    </div>

    <div class="cert-print space-y-8">
        @include('certificates._document')

        <div class="cert-print-transcript">
            <div class="no-print mb-4 mt-2">
                <h2 class="ui-card-title">Verified record</h2>
                <p class="ui-card-subtitle">The same details appear on the public verification page, so employers and schools can check them.</p>
            </div>
            @include('certificates._transcript')
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    document.querySelectorAll('[data-copy-link]').forEach((button) => {
        button.addEventListener('click', async () => {
            const label = button.querySelector('[data-copy-label]');
            try {
                await navigator.clipboard.writeText(button.dataset.copyLink);
                label.textContent = 'Link copied';
            } catch (e) {
                label.textContent = button.dataset.copyLink;
            }
            setTimeout(() => { label.textContent = 'Copy verification link'; }, 2500);
        });
    });
</script>
@endsection

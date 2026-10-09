@extends('layouts.auth')

@php($needsRole = (bool) request('needs_role'))
@php($oauthVerified = session('google_auth_code') === 'OAUTH_VERIFIED')

@section('title', $needsRole ? 'Finish creating your account' : 'Enter your code')

@section('topLink')
    <p class="text-sm text-[var(--cc-text-dim)] whitespace-nowrap">
        <a href="{{ route('auth.google') }}" class="cc-link font-medium">Use a different account</a>
    </p>
@endsection

@section('content')
    <div class="cc-rise" style="--d:0">
        @include('auth.partials.google-chip', ['email' => session('google_auth_gmail')])
        @if ($needsRole)
            <h2 class="mt-5 cc-display text-[32px] leading-tight font-bold">Almost <span class="cc-serif font-normal text-[#3ecf8e]">there.</span></h2>
            <p class="mt-2 text-[15px] text-[var(--cc-text-dim)] cc-pretty">
                Pick your role and set a password{{ $oauthVerified ? '' : ', then enter the code we emailed you' }}.
            </p>
        @else
            <h2 class="mt-5 cc-display text-[32px] leading-tight font-bold">Check your <span class="cc-serif font-normal text-[#3ecf8e]">inbox.</span></h2>
            <p class="mt-2 text-[15px] text-[var(--cc-text-dim)] cc-pretty">We emailed a 6-digit code to the address above. It confirms the account is yours.</p>
        @endif
    </div>

    <form class="mt-8 space-y-5 cc-rise" style="--d:1" method="POST" action="{{ route('auth.google.callback') }}">
        @csrf

        @include('auth.partials.notices')

        @if ($needsRole)
            @include('auth.partials.role-cards')

            <div>
                <label for="password" class="cc-label">Password</label>
                <div class="relative">
                    <input id="password" name="password" type="password" required minlength="8" autocomplete="new-password" aria-describedby="password-hint"
                           class="cc-field !pr-11" placeholder="At least 8 characters" @error('password') aria-invalid="true" @enderror>
                    @include('auth.partials.password-toggle', ['target' => 'password'])
                </div>
                <p id="password-hint" class="mt-2 text-[13px] text-[var(--cc-text-faint)]">Lets you sign in with your email too, not just Google.</p>
            </div>
        @endif

        @if ($oauthVerified)
            <input type="hidden" name="code" value="OAUTH_VERIFIED">
        @else
            <div>
                <div class="flex items-center justify-between mb-2">
                    <label for="code" class="cc-label !mb-0">Verification code</label>
                    <button type="submit" form="resend-code-form" id="resend-google-btn"
                            class="text-[13px] text-[var(--cc-text-dim)] hover:text-white transition-colors disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:text-[var(--cc-text-dim)]">
                        Resend code
                    </button>
                </div>
                <input id="code" name="code" type="text" required maxlength="6" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}"
                       @if (!$needsRole) autofocus @endif
                       class="cc-field cc-mono text-center text-xl !tracking-[0.5em]" placeholder="000000" @error('code') aria-invalid="true" @enderror>
            </div>
        @endif

        <button type="submit" class="cc-btn cc-btn-brand w-full">
            {{ $needsRole ? 'Create account' : 'Verify and sign in' }} <span class="cc-arrow" aria-hidden="true">&rarr;</span>
        </button>
    </form>

    <form id="resend-code-form" action="{{ route('auth.google.email') }}" method="POST" class="hidden">
        @csrf
        <input type="hidden" name="gmail" value="{{ session('google_auth_gmail') }}">
    </form>

    <p class="mt-10 text-center text-[13px] cc-rise" style="--d:2">
        <a href="{{ route('auth.google') }}" class="text-[var(--cc-text-faint)] hover:text-white transition-colors">&larr; Back</a>
    </p>
@endsection

@push('scripts')
    @include('auth.partials.password-toggle-script')
    <script>
        // After a code is sent, wait 60 seconds before allowing another (survives reloads in this tab)
        (() => {
            const button = document.getElementById('resend-google-btn');
            if (!button) return;

            const key = 'google_resend_cooldown_end';
            const justSent = @json((bool) session('success') && !$errors->any());
            let end = 0;
            try {
                end = parseInt(sessionStorage.getItem(key) || '0', 10);
                if (!end && justSent) {
                    end = Date.now() + 60000;
                    sessionStorage.setItem(key, String(end));
                }
            } catch (e) {
                if (justSent) end = Date.now() + 60000;
            }

            const tick = () => {
                const left = Math.ceil((end - Date.now()) / 1000);
                if (left > 0) {
                    button.disabled = true;
                    button.textContent = `Resend in ${left}s`;
                    setTimeout(tick, 1000);
                } else {
                    button.disabled = false;
                    button.textContent = 'Resend code';
                    try { sessionStorage.removeItem(key); } catch (e) {}
                }
            };
            if (end) tick();
        })();
    </script>
@endpush

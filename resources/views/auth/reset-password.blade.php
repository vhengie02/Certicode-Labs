@extends('layouts.auth')

@section('title', 'Choose a new password')

@section('topLink')
    <p class="text-sm text-[var(--cc-text-dim)] whitespace-nowrap">
        <a href="{{ route('login') }}" class="cc-link font-medium">Sign in</a>
    </p>
@endsection

@section('content')
    <div class="cc-rise" style="--d:0">
        <h2 class="cc-display text-[32px] leading-tight font-bold">Choose a new <span class="cc-serif font-normal text-[#3ecf8e]">password.</span></h2>
        <p class="mt-2 text-[15px] text-[var(--cc-text-dim)] cc-pretty">You'll use it to sign in from now on.</p>
    </div>

    <form class="mt-8 space-y-5 cc-rise" style="--d:1" method="POST" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">

        @include('auth.partials.notices', ['errorTitle' => "We couldn't reset your password."])

        <div>
            <label for="email" class="cc-label">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email', $email) }}" required autocomplete="email"
                   class="cc-field" placeholder="you@school.edu" @error('email') aria-invalid="true" @enderror>
        </div>

        <div>
            <label for="password" class="cc-label">New password</label>
            <div class="relative">
                <input id="password" name="password" type="password" required autofocus minlength="8" autocomplete="new-password" aria-describedby="password-hint"
                       class="cc-field !pr-11" placeholder="At least 8 characters" @error('password') aria-invalid="true" @enderror>
                @include('auth.partials.password-toggle', ['target' => 'password'])
            </div>
            <p id="password-hint" class="mt-2 text-[13px] text-[var(--cc-text-faint)]">At least 8 characters. A mix of letters, numbers and symbols is stronger.</p>
        </div>

        <div>
            <label for="password_confirmation" class="cc-label">Confirm new password</label>
            <div class="relative">
                <input id="password_confirmation" name="password_confirmation" type="password" required minlength="8" autocomplete="new-password"
                       class="cc-field !pr-11" placeholder="Type it again">
                @include('auth.partials.password-toggle', ['target' => 'password_confirmation'])
            </div>
        </div>

        <button type="submit" class="cc-btn cc-btn-brand w-full">
            Save new password <span class="cc-arrow" aria-hidden="true">&rarr;</span>
        </button>
    </form>

    <p class="mt-10 text-center text-[13px] cc-rise" style="--d:2">
        <a href="{{ route('login') }}" class="text-[var(--cc-text-faint)] hover:text-white transition-colors">&larr; Back to sign in</a>
    </p>
@endsection

@push('scripts')
    @include('auth.partials.password-toggle-script')
@endpush

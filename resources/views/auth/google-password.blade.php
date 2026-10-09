@extends('layouts.auth')

@section('title', 'Enter your password')

@section('topLink')
    <p class="text-sm text-[var(--cc-text-dim)] whitespace-nowrap">
        <a href="{{ route('auth.google') }}" class="cc-link font-medium">Use a different account</a>
    </p>
@endsection

@section('content')
    <div class="cc-rise" style="--d:0">
        @include('auth.partials.google-chip', ['email' => session('google_auth_gmail')])
        <h2 class="mt-5 cc-display text-[32px] leading-tight font-bold">Welcome <span class="cc-serif font-normal text-[#3ecf8e]">back.</span></h2>
        <p class="mt-2 text-[15px] text-[var(--cc-text-dim)] cc-pretty">Enter your Certicode password to finish signing in.</p>
    </div>

    <form class="mt-8 space-y-5 cc-rise" style="--d:1" method="POST" action="{{ route('auth.google.password.submit') }}">
        @csrf

        @include('auth.partials.notices')

        <div>
            <div class="flex items-center justify-between mb-2">
                <label for="password" class="cc-label !mb-0">Password</label>
                <button type="submit" form="forgot-form" class="text-[13px] text-[var(--cc-text-dim)] hover:text-white transition-colors">Forgot password?</button>
            </div>
            <div class="relative">
                <input id="password" name="password" type="password" required autofocus autocomplete="current-password"
                       class="cc-field !pr-11" placeholder="Your password" @error('password') aria-invalid="true" @enderror>
                @include('auth.partials.password-toggle', ['target' => 'password'])
            </div>
        </div>

        <button type="submit" class="cc-btn cc-btn-brand w-full">
            Sign in <span class="cc-arrow" aria-hidden="true">&rarr;</span>
        </button>
    </form>

    <form id="forgot-form" action="{{ route('auth.google.forgot') }}" method="POST" class="hidden">
        @csrf
    </form>

    <p class="mt-10 text-center text-[13px] cc-rise" style="--d:2">
        <a href="{{ route('auth.google') }}" class="text-[var(--cc-text-faint)] hover:text-white transition-colors">&larr; Back</a>
    </p>
@endsection

@push('scripts')
    @include('auth.partials.password-toggle-script')
@endpush

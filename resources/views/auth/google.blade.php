@extends('layouts.auth')

@section('title', 'Continue with Google')

@section('topLink')
    <p class="text-sm text-[var(--cc-text-dim)] whitespace-nowrap">
        <a href="{{ route('login') }}" class="cc-link font-medium">Use email instead</a>
    </p>
@endsection

@section('content')
    <div class="cc-rise" style="--d:0">
        @include('auth.partials.google-chip')
        <h2 class="mt-5 cc-display text-[32px] leading-tight font-bold">Which <span class="cc-serif font-normal text-[#3ecf8e]">Gmail?</span></h2>
        <p class="mt-2 text-[15px] text-[var(--cc-text-dim)] cc-pretty">Enter your Google address. If it's new to Certicode, we'll email you a code to confirm it's yours.</p>
    </div>

    <form class="mt-8 space-y-5 cc-rise" style="--d:1" method="POST" action="{{ route('auth.google.email') }}">
        @csrf

        @include('auth.partials.notices')

        <div>
            <label for="gmail" class="cc-label">Google email</label>
            <input id="gmail" name="gmail" type="email" value="{{ old('gmail') }}" required autofocus autocomplete="email"
                   class="cc-field" placeholder="you@gmail.com" @error('gmail') aria-invalid="true" @enderror>
        </div>

        <button type="submit" class="cc-btn cc-btn-brand w-full">
            Continue <span class="cc-arrow" aria-hidden="true">&rarr;</span>
        </button>
    </form>

    <p class="mt-10 text-center text-[13px] cc-rise" style="--d:2">
        <a href="{{ route('login') }}" class="text-[var(--cc-text-faint)] hover:text-white transition-colors">&larr; Back to sign in</a>
    </p>
@endsection

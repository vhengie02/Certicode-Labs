@extends('layouts.auth')

@section('title', 'Reset your password')

@section('topLink')
    <p class="text-sm text-[var(--cc-text-dim)] whitespace-nowrap">
        <span class="hidden sm:inline">Remembered it?</span> <a href="{{ route('login') }}" class="cc-link font-medium">Sign in</a>
    </p>
@endsection

@section('content')
    <div class="cc-rise" style="--d:0">
        <h2 class="cc-display text-[32px] leading-tight font-bold">Forgot your <span class="cc-serif font-normal text-[#3ecf8e]">password?</span></h2>
        <p class="mt-2 text-[15px] text-[var(--cc-text-dim)] cc-pretty">Enter the email you signed up with and we'll send you a link to choose a new one.</p>
    </div>

    <form class="mt-8 space-y-5 cc-rise" style="--d:1" method="POST" action="{{ route('password.email') }}">
        @csrf

        @include('auth.partials.notices')

        <div>
            <label for="email" class="cc-label">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email"
                   class="cc-field" placeholder="you@school.edu" @error('email') aria-invalid="true" @enderror>
        </div>

        <button type="submit" class="cc-btn cc-btn-brand w-full">
            Send reset link <span class="cc-arrow" aria-hidden="true">&rarr;</span>
        </button>
    </form>

    <p class="mt-10 text-center text-[13px] cc-rise" style="--d:2">
        <a href="{{ route('login') }}" class="text-[var(--cc-text-faint)] hover:text-white transition-colors">&larr; Back to sign in</a>
    </p>
@endsection

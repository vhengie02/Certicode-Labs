@extends('layouts.auth')

@section('title', 'Review our terms')

@section('content')
    <div class="cc-rise" style="--d:0">
        <h2 class="cc-display text-[32px] leading-tight font-bold">Before you <span class="cc-serif font-normal text-[#3ecf8e]">continue.</span></h2>
        <p class="mt-2 text-[15px] text-[var(--cc-text-dim)] cc-pretty">
            Please review how Certicode handles your data and agree to continue using it.
        </p>
    </div>

    <form class="mt-8 space-y-5 cc-rise" style="--d:1" method="POST" action="{{ route('legal.accept.store') }}">
        @csrf
        @include('auth.partials.terms-consent')
        <button type="submit" class="cc-btn cc-btn-brand w-full">
            Agree and continue <span class="cc-arrow" aria-hidden="true">&rarr;</span>
        </button>
    </form>

    <p class="mt-6 text-[13px] text-[var(--cc-text-faint)] cc-pretty cc-rise" style="--d:2">
        Don't agree? You can <a href="{{ route('settings.show') }}#privacy" class="cc-link">download your data or delete your account</a> in Settings.
    </p>

    <form method="POST" action="{{ route('logout') }}" class="mt-4 text-center cc-rise" style="--d:2">
        @csrf
        <button type="submit" class="text-[13px] text-[var(--cc-text-faint)] hover:text-white transition-colors">Sign out</button>
    </form>
@endsection

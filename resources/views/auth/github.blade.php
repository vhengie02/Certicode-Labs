@extends('layouts.auth')

@section('title', 'Connect GitHub (development)')

@section('topLink')
    <p class="text-sm text-[var(--cc-text-dim)] whitespace-nowrap">
        <a href="{{ auth()->check() ? route('settings.show') : route('login') }}" class="cc-link font-medium">Cancel</a>
    </p>
@endsection

@section('content')
    <div class="cc-rise" style="--d:0">
        <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full border border-[var(--cc-line-strong)] bg-white/[0.03] text-[13px] text-[var(--cc-text-dim)]">
            <svg class="w-3.5 h-3.5 text-white" fill="currentColor" viewBox="0 0 16 16" aria-hidden="true"><path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0016 8c0-4.42-3.58-8-8-8z"/></svg>
            GitHub
        </div>
        <h2 class="mt-5 cc-display text-[32px] leading-tight font-bold">Connect <span class="cc-serif font-normal text-[#3ecf8e]">GitHub.</span></h2>
        <p class="mt-2 text-[15px] text-[var(--cc-text-dim)] cc-pretty">Pretend to be any GitHub user to test the sign-in and linking flows.</p>
    </div>

    <div class="mt-6 cc-notice cc-notice-warning cc-rise" style="--d:1" role="note">
        <svg class="w-4 h-4 mt-0.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4m0 4h.01M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0z"/></svg>
        <p>
            <span class="font-medium">Development only.</span>
            GitHub OAuth keys aren't set in <code class="cc-mono text-[12px] px-1 py-0.5 rounded bg-white/[0.06]">.env</code>, so this stands in for GitHub.
            It is switched off in production.
        </p>
    </div>

    <form class="mt-6 space-y-5 cc-rise" style="--d:2" method="POST" action="{{ route('auth.github.callback') }}">
        @csrf

        @include('auth.partials.notices')

        <div>
            <label for="github_username" class="cc-label">GitHub username</label>
            <input id="github_username" name="github_username" type="text" value="{{ old('github_username', 'octocat') }}" required autofocus
                   class="cc-field cc-mono" placeholder="octocat" @error('github_username') aria-invalid="true" @enderror>
        </div>

        @guest
            <div>
                <label for="github_email" class="cc-label">GitHub email</label>
                <input id="github_email" name="github_email" type="email" value="{{ old('github_email', 'octocat@github.com') }}" required
                       class="cc-field" placeholder="you@example.com" @error('github_email') aria-invalid="true" @enderror>
            </div>

            <div>
                <label for="github_name" class="cc-label">Display name <span class="normal-case tracking-normal text-[var(--cc-text-faint)] font-normal">(optional)</span></label>
                <input id="github_name" name="github_name" type="text" value="{{ old('github_name', 'The Octocat') }}"
                       class="cc-field" placeholder="Jane Doe">
            </div>
        @endguest

        <button type="submit" class="cc-btn cc-btn-brand w-full">
            {{ auth()->check() ? 'Link this account' : 'Continue as this user' }} <span class="cc-arrow" aria-hidden="true">&rarr;</span>
        </button>
    </form>

    <p class="mt-10 text-center text-[13px] cc-rise" style="--d:3">
        <a href="{{ auth()->check() ? route('settings.show') : route('login') }}" class="text-[var(--cc-text-faint)] hover:text-white transition-colors">&larr; {{ auth()->check() ? 'Back to settings' : 'Back to sign in' }}</a>
    </p>
@endsection

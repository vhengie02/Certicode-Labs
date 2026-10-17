@extends('layouts.auth')

@section('title', 'Sign in')

@section('topLink')
    <p class="text-sm text-[var(--cc-text-dim)] whitespace-nowrap">
        <span class="hidden sm:inline">New here?</span> <a href="{{ route('register.show') }}" class="cc-link font-medium">Create an account</a>
    </p>
@endsection

@section('content')
    <div class="cc-rise" style="--d:0">
        <h2 class="cc-display text-[32px] leading-tight font-bold">Welcome <span class="cc-serif font-normal text-[#3ecf8e]">back.</span></h2>
        <p class="mt-2 text-[15px] text-[var(--cc-text-dim)] cc-pretty">Sign in to your classes and labs.</p>
    </div>

    <form class="mt-8 space-y-5 cc-rise" style="--d:1" method="POST" action="{{ route('login.store') }}">
        @csrf

        @if (session('status'))
            <div class="cc-notice cc-notice-success" role="status">
                <svg class="w-4 h-4 mt-0.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                <p>{{ session('status') }}</p>
            </div>
        @endif

        @if ($errors->any())
            <div class="cc-notice cc-notice-error" role="alert">
                <svg class="w-4 h-4 mt-0.5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0z"/></svg>
                <div>
                    <p class="font-medium">We couldn't sign you in.</p>
                    <ul class="mt-1 space-y-0.5 text-[#fca5a5]">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    @if ($errors->has('email'))
                        <p class="mt-2 text-[var(--cc-text-dim)]">Enrolled in a course? Use your school email, or ask your instructor to check the class roster.</p>
                    @endif
                </div>
            </div>
        @endif

        <div>
            <label for="email" class="cc-label">Email</label>
            <input id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="email"
                   class="cc-field" placeholder="you@school.edu" @error('email') aria-invalid="true" @enderror>
        </div>

        <div>
            <div class="flex items-center justify-between mb-2">
                <label for="password" class="cc-label !mb-0">Password</label>
                <a href="{{ route('password.request') }}" class="text-[13px] text-[var(--cc-text-dim)] hover:text-white transition-colors">Forgot password?</a>
            </div>
            <div class="relative">
                <input id="password" name="password" type="password" required autocomplete="current-password"
                       class="cc-field !pr-11" placeholder="Your password" @error('password') aria-invalid="true" @enderror>
                @include('auth.partials.password-toggle', ['target' => 'password'])
            </div>
        </div>

        <label class="flex items-center gap-2.5 text-sm text-[var(--cc-text-dim)] cursor-pointer select-none w-fit">
            <input id="remember" name="remember" type="checkbox" class="cc-checkbox">
            Keep me signed in
        </label>

        <button type="submit" class="cc-btn cc-btn-brand w-full">
            Sign in <span class="cc-arrow" aria-hidden="true">&rarr;</span>
        </button>
    </form>

    <div class="relative my-7 flex items-center gap-3 cc-rise" style="--d:2">
        <div class="h-px flex-1 bg-[var(--cc-line-strong)]"></div>
        <span class="text-[13px] text-[var(--cc-text-faint)]">or</span>
        <div class="h-px flex-1 bg-[var(--cc-line-strong)]"></div>
    </div>

    <div class="grid grid-cols-2 gap-3 cc-rise" style="--d:3">
        <a href="{{ route('auth.provider.redirect', 'google') }}" class="cc-btn cc-btn-quiet">
            <svg class="w-[18px] h-[18px]" viewBox="0 0 24 24" aria-hidden="true">
                <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
            </svg>
            Google
        </a>
        <a href="{{ route('auth.provider.redirect', 'github') }}" class="cc-btn cc-btn-quiet">
            <svg class="w-[18px] h-[18px]" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                <path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.477 2 2 6.477 2 12c0 4.42 2.865 8.166 6.839 9.489.5.092.682-.217.682-.482 0-.237-.008-.866-.013-1.7-2.782.603-3.369-1.34-3.369-1.34-.454-1.156-1.11-1.464-1.11-1.464-.908-.62.069-.608.069-.608 1.003.07 1.531 1.03 1.531 1.03.892 1.529 2.341 1.087 2.91.831.092-.646.35-1.086.636-1.336-2.22-.253-4.555-1.11-4.555-4.943 0-1.091.39-1.984 1.029-2.683-.103-.253-.446-1.27.098-2.647 0 0 .84-.269 2.75 1.025A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.294 2.747-1.025 2.747-1.025.546 1.377.203 2.394.1 2.647.64.699 1.028 1.592 1.028 2.683 0 3.842-2.339 4.687-4.566 4.935.359.309.678.919.678 1.852 0 1.336-.012 2.415-.012 2.743 0 .267.18.579.688.481C19.137 20.162 22 16.418 22 12c0-5.523-4.477-10-10-10z"/>
            </svg>
            GitHub
        </a>
    </div>

    <p class="mt-10 text-center text-[13px]">
        <a href="/" class="text-[var(--cc-text-faint)] hover:text-white transition-colors">&larr; Back to home</a>
    </p>
@endsection

@push('scripts')
    @include('auth.partials.password-toggle-script')
@endpush

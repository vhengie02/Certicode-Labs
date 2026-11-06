@extends('layouts.app')

@section('title', 'Settings')

@php
    $sections = [
        [
            'label' => 'Account',
            'children' => [
                ['value' => 'profile', 'label' => 'Profile', 'icon' => 'M16 7a4 4 0 1 1-8 0 4 4 0 0 1 8 0zM12 14a7 7 0 0 0-7 7h14a7 7 0 0 0-7-7z'],
                ['value' => 'password', 'label' => 'Password', 'icon' => 'M12 15v2m-6 4h12a2 2 0 0 0 2-2v-6a2 2 0 0 0-2-2H6a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2zm10-10V7a4 4 0 0 0-8 0v4h8z'],
                ['value' => 'accounts', 'label' => 'Connected accounts', 'icon' => 'M13.828 10.172a4 4 0 0 0-5.656 0l-4 4a4 4 0 1 0 5.656 5.656l1.102-1.101m-.758-4.899a4 4 0 0 0 5.656 0l4-4a4 4 0 0 0-5.656-5.656l-1.1 1.1'],
                ...($activeSessions === null ? [] : [
                    ['value' => 'sessions', 'label' => 'Active sessions', 'icon' => 'M9.75 17 9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2z'],
                ]),
            ],
        ],
        [
            'label' => 'Preferences',
            'children' => [
                ['value' => 'appearance', 'label' => 'Appearance', 'icon' => 'M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364-.707-.707M6.343 6.343l-.707-.707m12.728 0-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 1 1-8 0 4 4 0 0 1 8 0z'],
                ['value' => 'datetime', 'label' => 'Date and time', 'icon' => 'M12 8v4l3 3m6-3a9 9 0 1 1-18 0 9 9 0 0 1 18 0z'],
                ['value' => 'notifications', 'label' => 'Email notifications', 'icon' => 'M3 8l7.89 5.26a2 2 0 0 0 2.22 0L21 8M5 19h14a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v10a2 2 0 0 0 2 2z'],
            ],
        ],
        [
            'label' => 'Privacy',
            'children' => [
                ['value' => 'privacy', 'label' => 'Your data', 'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0 1 12 2.944a11.955 11.955 0 0 1-8.618 3.04A12.02 12.02 0 0 0 3 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z'],
                ['value' => 'delete-account', 'label' => 'Delete account', 'icon' => 'M19 7l-.867 12.142A2 2 0 0 1 16.138 21H7.862a2 2 0 0 1-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v3M4 7h16'],
            ],
        ],
    ];
    $themes = [
        'system' => ['System', 'Follows your device setting.'],
        'dark' => ['Dark Mode', 'Easy on the eyes at night.'],
        'light' => ['Light Mode', 'Best in bright rooms.'],
    ];
    // Inline colors, not Tailwind classes: the light theme in layouts/app.blade.php
    // remaps the dark hex classes with !important, which would turn these previews light.
    $darkPreview = ['canvas' => '#0f0f0f', 'line' => '#2e2e2e', 'field' => '#171717', 'accent' => '#3ecf8e'];
    $lightPreview = ['canvas' => '#f8f9fa', 'line' => '#e5e7eb', 'field' => '#ffffff', 'accent' => '#059669'];
    $themePreviews = [
        'system' => [$darkPreview, $lightPreview],
        'dark' => [$darkPreview],
        'light' => [$lightPreview],
    ];
@endphp

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <x-page-header title="Settings" subtitle="Your profile, sign-in options and what we email you." />

    @if ($errors->any())
        @push('toasts')
            <x-toast tone="error">
                <ul>
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </x-toast>
        @endpush
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-[220px_minmax(0,1fr)] gap-8 items-start">
        {{-- Section menu --}}
        <div class="hidden lg:block sticky top-2">
            <x-branched-menu :items="$sections" label="Settings sections" />
        </div>

        <div class="space-y-6 min-w-0">
            {{-- Profile --}}
            <form id="profile" action="{{ route('settings.profile.update') }}" method="POST" class="ui-card scroll-mt-4">
                @csrf
                @method('PUT')
                <div class="ui-card-header">
                    <h2 class="ui-card-title">Profile</h2>
                    <p class="ui-card-subtitle">Your name appears on your certificates and to your instructors.</p>
                </div>
                <div class="ui-card-body grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <label for="first_name" class="ui-label">First name</label>
                        <input type="text" name="first_name" id="first_name" required autocomplete="given-name" value="{{ old('first_name', $user->first_name) }}" class="ui-input" @error('first_name') aria-invalid="true" @enderror>
                    </div>
                    <div>
                        <label for="last_name" class="ui-label">Last name</label>
                        <input type="text" name="last_name" id="last_name" required autocomplete="family-name" value="{{ old('last_name', $user->last_name) }}" class="ui-input" @error('last_name') aria-invalid="true" @enderror>
                    </div>
                    <div>
                        <label for="username" class="ui-label">Username</label>
                        <input type="text" name="username" id="username" required autocomplete="username" value="{{ old('username', $user->username) }}" class="ui-input" @error('username') aria-invalid="true" @enderror>
                        @error('username') <p class="ui-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="gender" class="ui-label">Gender <span class="ui-optional">(optional)</span></label>
                        <select name="gender" id="gender" class="ui-input">
                            <option value="" @selected(is_null($user->gender))>Prefer not to say</option>
                            <option value="male" @selected($user->gender === 'male')>Male</option>
                            <option value="female" @selected($user->gender === 'female')>Female</option>
                            <option value="other" @selected($user->gender === 'other')>Other</option>
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <span class="ui-label">Account email</span>
                        <p class="text-sm text-[#a3a3a3]">{{ $user->email }}</p>
                    </div>
                </div>
                <div class="flex justify-end border-t border-[#232323] px-6 py-4">
                    <button type="submit" class="ui-btn ui-btn-primary">Save profile</button>
                </div>
            </form>

            {{-- Password --}}
            <form id="password" action="{{ route('settings.password.update') }}" method="POST" class="ui-card scroll-mt-4">
                @csrf
                @method('PUT')
                <div class="ui-card-header">
                    <h2 class="ui-card-title">Password</h2>
                    <p class="ui-card-subtitle">Use at least 8 characters.</p>
                </div>
                <div class="ui-card-body grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div class="sm:col-span-2 sm:max-w-sm">
                        <label for="current_password" class="ui-label">Current password</label>
                        <input type="password" name="current_password" id="current_password" required autocomplete="current-password" class="ui-input" @error('current_password') aria-invalid="true" @enderror>
                        @error('current_password') <p class="ui-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="new_password" class="ui-label">New password</label>
                        <input type="password" name="password" id="new_password" required autocomplete="new-password" minlength="8" class="ui-input" @error('password') aria-invalid="true" @enderror>
                        @error('password') <p class="ui-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="new_password_confirmation" class="ui-label">Confirm new password</label>
                        <input type="password" name="password_confirmation" id="new_password_confirmation" required autocomplete="new-password" minlength="8" class="ui-input">
                    </div>
                </div>
                <div class="flex justify-end border-t border-[#232323] px-6 py-4">
                    <button type="submit" class="ui-btn ui-btn-primary">Update password</button>
                </div>
            </form>

            {{-- Connected accounts --}}
            <section id="accounts" class="ui-card scroll-mt-4" aria-labelledby="accounts-heading">
                <div class="ui-card-header">
                    <h2 id="accounts-heading" class="ui-card-title">Connected accounts</h2>
                    <p class="ui-card-subtitle">Sign in faster, and choose where your email alerts go.</p>
                </div>
                <div class="ui-card-body space-y-3">
                    {{-- Google --}}
                    <div class="rounded-xl border border-[#2e2e2e] bg-[#141414] p-4">
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-[#2e2e2e] bg-[#171717]" aria-hidden="true">
                                <svg class="w-4 h-4" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/><path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/></svg>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-medium text-[#ededed]">Google</span>
                                <span class="block truncate text-xs text-[#888888]">
                                    @if (empty($user->gmail))
                                        Sign in with Google, and get email alerts at your Gmail.
                                    @elseif (empty($user->gmail_verified_at))
                                        Waiting for you to confirm {{ $user->gmail }}
                                    @else
                                        {{ $user->gmail }} &middot; email alerts go here
                                    @endif
                                </span>
                            </span>
                            @if (!empty($user->gmail) && !empty($user->gmail_verified_at))
                                <span class="ui-badge ui-badge-brand text-[11px]">Connected</span>
                                <form action="{{ route('settings.gmail.disconnect') }}" method="POST">
                                    @csrf
                                    <button type="submit" class="ui-btn ui-btn-ghost ui-btn-sm hover:!text-red-400">Disconnect</button>
                                </form>
                            @endif
                        </div>

                        @if (empty($user->gmail))
                            <form action="{{ route('settings.gmail.connect') }}" method="POST" class="mt-4 flex flex-col sm:flex-row gap-2">
                                @csrf
                                <label for="gmail" class="sr-only">Gmail address</label>
                                <input type="email" name="gmail" id="gmail" required placeholder="you@gmail.com" autocomplete="email" class="ui-input flex-1">
                                <button type="submit" class="ui-btn ui-btn-primary shrink-0">Send code</button>
                            </form>
                            <p class="ui-hint">We'll email a 6-digit code to confirm the address is yours.</p>
                        @elseif (empty($user->gmail_verified_at))
                            <form action="{{ route('settings.gmail.verify') }}" method="POST" class="mt-4 flex flex-col sm:flex-row gap-2">
                                @csrf
                                <label for="gmail-code" class="sr-only">Verification code</label>
                                <input type="text" name="code" id="gmail-code" required maxlength="6" inputmode="numeric" autocomplete="one-time-code" placeholder="000000"
                                       class="ui-input ui-input-mono sm:w-40 text-center tracking-[0.4em]">
                                <button type="submit" class="ui-btn ui-btn-primary shrink-0">Confirm</button>
                            </form>
                            <div class="mt-3 flex flex-wrap items-center justify-between gap-2 text-xs">
                                <form action="{{ route('settings.gmail.disconnect') }}" method="POST">
                                    @csrf
                                    <button type="submit" class="text-[#888888] hover:text-red-400 underline-offset-2 hover:underline">Use a different address</button>
                                </form>
                                <form action="{{ route('settings.gmail.connect') }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="gmail" value="{{ $user->gmail }}">
                                    <button type="submit" id="resend-gmail-btn" class="font-medium text-[#3ecf8e] hover:underline underline-offset-2 disabled:text-[#888888] disabled:no-underline">Resend code</button>
                                </form>
                            </div>
                        @endif
                    </div>

                    {{-- GitHub --}}
                    <div class="rounded-xl border border-[#2e2e2e] bg-[#141414] p-4">
                        <div class="flex flex-wrap items-center gap-3">
                            <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg border border-[#2e2e2e] bg-[#171717] text-[#ededed]" aria-hidden="true">
                                <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 16 16"><path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0016 8c0-4.42-3.58-8-8-8z"/></svg>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-medium text-[#ededed]">GitHub</span>
                                <span class="block truncate text-xs text-[#888888]">{{ $user->github_username ? '@' . $user->github_username : 'Sign in with GitHub, and link your commits in group labs.' }}</span>
                            </span>
                            @if ($user->github_username)
                                <span class="ui-badge ui-badge-brand text-[11px]">Connected</span>
                                <form action="{{ route('settings.github.disconnect') }}" method="POST">
                                    @csrf
                                    <button type="submit" class="ui-btn ui-btn-ghost ui-btn-sm hover:!text-red-400">Disconnect</button>
                                </form>
                            @else
                                <a href="{{ route('auth.provider.redirect', 'github') }}" class="ui-btn ui-btn-secondary ui-btn-sm">Connect</a>
                            @endif
                        </div>
                    </div>
                </div>
            </section>

            {{-- Active sessions --}}
            @if ($activeSessions !== null)
                <section id="sessions" class="ui-card scroll-mt-4" aria-labelledby="sessions-heading">
                    <div class="ui-card-header">
                        <h2 id="sessions-heading" class="ui-card-title">Active sessions</h2>
                        <p class="ui-card-subtitle">Browsers signed in to your account. Sign out of any you don't recognize, then change your password.</p>
                    </div>
                    <ul class="ui-card-body divide-y divide-[#232323]">
                        @foreach ($activeSessions as $session)
                            <li class="flex flex-wrap items-center justify-between gap-3 py-3.5 first:pt-0 last:pb-0">
                                <span class="min-w-0">
                                    <span class="block text-sm font-medium text-[#ededed]">{{ $session['device'] }}</span>
                                    <span class="block mt-0.5 text-[13px] text-[#888888]">{{ $session['ip_address'] ?? 'Unknown address' }} &middot; Last active <x-local-time :at="$session['last_active']" /></span>
                                </span>
                                @if ($session['is_current'])
                                    <span class="ui-badge ui-badge-brand text-[11px]">This device</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                    @if ($activeSessions->count() > 1)
                        <form action="{{ route('settings.sessions.signout') }}" method="POST" class="flex justify-end border-t border-[#232323] px-6 py-4">
                            @csrf
                            <button type="submit" class="ui-btn ui-btn-secondary">Sign out everywhere else</button>
                        </form>
                    @endif
                </section>
            @endif

            {{-- Appearance --}}
            <section id="appearance" class="ui-card scroll-mt-4" aria-labelledby="appearance-heading">
                <div class="ui-card-header flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 id="appearance-heading" class="ui-card-title">Appearance</h2>
                        <p class="ui-card-subtitle">Applies instantly and is remembered on this device.</p>
                    </div>
                    <span id="theme-status-indicator" class="ui-badge ui-badge-brand">
                        <span class="h-1.5 w-1.5 rounded-full bg-[#3ecf8e]" aria-hidden="true"></span>
                        <span id="theme-status-text">Active: System</span>
                    </span>
                </div>
                <div class="ui-card-body grid grid-cols-1 sm:grid-cols-3 gap-3" role="radiogroup" aria-label="Theme">
                    @foreach ($themes as $key => [$label, $hint])
                        <button type="button" onclick="selectTheme('{{ $key }}')" id="theme-card-{{ $key }}" role="radio" aria-checked="false"
                                class="theme-card group rounded-xl border-2 border-[#2e2e2e] bg-[#141414] p-3 text-left transition-colors hover:border-[#3ecf8e]/50">
                            <span class="flex h-20 w-full overflow-hidden rounded-lg border border-[#2e2e2e]" aria-hidden="true">
                                @foreach ($themePreviews[$key] as $palette)
                                    <span class="flex flex-1 flex-col justify-between p-2" style="background-color: {{ $palette['canvas'] }}">
                                        <span class="h-1.5 w-2/3 rounded" style="background-color: {{ $palette['line'] }}"></span>
                                        <span class="h-1.5 w-full rounded border" style="background-color: {{ $palette['field'] }}; border-color: {{ $palette['line'] }}"></span>
                                        <span class="h-1.5 w-1/2 rounded-full" style="background-color: {{ $palette['accent'] }}"></span>
                                    </span>
                                @endforeach
                            </span>
                            <span class="mt-3 flex items-center justify-between">
                                <span class="text-sm font-medium text-[#ededed]">{{ $label }}</span>
                                <span class="theme-check-badge hidden h-4 w-4 items-center justify-center rounded-full bg-[#3ecf8e] text-[#0f0f0f]" aria-hidden="true">
                                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="3.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                </span>
                            </span>
                            <span class="mt-0.5 block text-xs text-[#888888]">{{ $hint }}</span>
                        </button>
                    @endforeach
                </div>
            </section>

            {{-- Date and time --}}
            <form id="datetime" action="{{ route('settings.datetime.update') }}" method="POST" class="ui-card scroll-mt-4">
                @csrf
                @method('PUT')
                <div class="ui-card-header">
                    <h2 class="ui-card-title">Date and time</h2>
                    <p class="ui-card-subtitle">How dates and lab times appear for you. Certificates always show the same date for everyone.</p>
                </div>
                <div class="ui-card-body grid grid-cols-1 sm:grid-cols-2 gap-5">
                    <div>
                        <label for="timezone" class="ui-label">Time zone</label>
                        <select name="timezone" id="timezone" class="ui-input" @error('timezone') aria-invalid="true" @enderror>
                            <option value="">Use my device's time zone</option>
                            @foreach (timezone_identifiers_list() as $zone)
                                <option value="{{ $zone }}" @selected(old('timezone', $user->timezone) === $zone)>{{ str_replace('_', ' ', $zone) }}</option>
                            @endforeach
                        </select>
                        @error('timezone') <p class="ui-error">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label for="date_style" class="ui-label">Date format</label>
                        <select name="date_style" id="date_style" class="ui-input" @error('date_style') aria-invalid="true" @enderror>
                            <option value="">Automatic</option>
                            @foreach (\App\Support\DisplayTime::DATE_STYLES as $style => $formats)
                                <option value="{{ $style }}" @selected(old('date_style', $user->date_style) === $style)>{{ \App\Support\DisplayTime::in(now())->format($formats['datetime']) }}</option>
                            @endforeach
                        </select>
                        @error('date_style') <p class="ui-error">{{ $message }}</p> @enderror
                    </div>
                </div>
                <div class="flex justify-end border-t border-[#232323] px-6 py-4">
                    <button type="submit" class="ui-btn ui-btn-primary">Save date and time</button>
                </div>
            </form>

            {{-- Email notifications --}}
            <form id="notifications" action="{{ route('settings.notifications.update') }}" method="POST" class="ui-card scroll-mt-4">
                @csrf
                <div class="ui-card-header">
                    <h2 class="ui-card-title">Email notifications</h2>
                    <p class="ui-card-subtitle">
                        Sent to {{ !empty($user->gmail) && !empty($user->gmail_verified_at) ? $user->gmail : $user->email }}.
                        You'll always see these in the bell menu, whatever you choose here.
                    </p>
                </div>
                <div class="ui-card-body divide-y divide-[#232323]">
                    @foreach (['notify_email_channel' => ['All alert emails', 'Turn this off to stop every email below, and account updates from admins. Sign-in and password emails still arrive.']] + $toggles as $field => [$label, $hint])
                        <label class="flex cursor-pointer items-center justify-between gap-4 py-3.5 first:pt-0 last:pb-0">
                            <span>
                                <span class="block text-sm font-medium text-[#ededed]">{{ $label }}</span>
                                <span class="block mt-0.5 text-[13px] text-[#888888]">{{ $hint }}</span>
                            </span>
                            <span class="relative shrink-0">
                                <input type="checkbox" name="{{ $field }}" id="{{ $field }}" value="1" @checked($user->{$field}) class="sr-only peer">
                                <span class="block h-6 w-11 rounded-full border border-[#2e2e2e] bg-[#232323] transition-colors peer-checked:border-[#3ecf8e] peer-checked:bg-[#3ecf8e] peer-focus-visible:ring-2 peer-focus-visible:ring-[#3ecf8e]/50"></span>
                                <span class="absolute left-[3px] top-[3px] h-[18px] w-[18px] rounded-full bg-[#a3a3a3] motion-knob peer-checked:translate-x-5 peer-checked:bg-[#06150e]" aria-hidden="true"></span>
                            </span>
                        </label>
                    @endforeach
                </div>
                <div class="flex justify-end border-t border-[#232323] px-6 py-4">
                    <button type="submit" class="ui-btn ui-btn-primary">Save notification settings</button>
                </div>
            </form>

            {{-- Your data --}}
            <section id="privacy" class="ui-card scroll-mt-4" aria-labelledby="privacy-heading">
                <div class="ui-card-header">
                    <h2 id="privacy-heading" class="ui-card-title">Your data</h2>
                    <p class="ui-card-subtitle">What Certicode records about you. Your class's instructor and Certicode admins can see it.</p>
                </div>
                <dl class="ui-card-body divide-y divide-[#232323]">
                    <div class="py-3.5 first:pt-0">
                        <dt class="text-sm font-medium text-[#ededed]">During a lab</dt>
                        <dd class="mt-0.5 text-[13px] text-[#888888]">Typing pace, focus changes, idle time, and pasted text or large blocks of code that weren't typed.</dd>
                    </div>
                    <div class="py-3.5">
                        <dt class="text-sm font-medium text-[#ededed]">Camera</dt>
                        <dd class="mt-0.5 text-[13px] text-[#888888]">Checks that you're present while a lab runs. A snapshot is kept only when the camera raises a flag.</dd>
                    </div>
                    <div class="py-3.5 last:pb-0">
                        <dt class="text-sm font-medium text-[#ededed]">Your work</dt>
                        <dd class="mt-0.5 text-[13px] text-[#888888]">The code you submit, including code files you add or change in your lab folder during a lab, your grades, and your messages in lab chat.</dd>
                    </div>
                </dl>
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-[#232323] px-6 py-4">
                    <p class="text-[13px] text-[#888888]">A JSON file of your data. Camera snapshots are listed but not included.</p>
                    <a href="{{ route('settings.export') }}" class="ui-btn ui-btn-secondary">Download my data</a>
                </div>
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-[#232323] px-6 py-4">
                    <div>
                        <p class="text-sm font-medium text-[#ededed]">Lab monitoring consent</p>
                        <p class="mt-0.5 text-[13px] text-[#888888]">
                            @if ($user->hasMonitoringConsent())
                                You agreed on {{ $user->monitoring_consent_at->format('F j, Y') }}. Labs can't start if you withdraw it.
                            @else
                                Not given. You'll be asked before your next lab.
                            @endif
                        </p>
                    </div>
                    @if ($user->hasMonitoringConsent())
                        <form action="{{ route('legal.monitoring.destroy') }}" method="POST">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="ui-btn ui-btn-secondary">Withdraw consent</button>
                        </form>
                    @endif
                </div>
                <p class="border-t border-[#232323] px-6 py-4 text-[13px] text-[#888888]">
                    Read the <a href="{{ route('legal.privacy') }}" class="text-[#3ecf8e] hover:underline">Privacy Policy</a>,
                    <a href="{{ route('legal.terms') }}" class="text-[#3ecf8e] hover:underline">Terms of Service</a> and
                    <a href="{{ route('legal.cookies') }}" class="text-[#3ecf8e] hover:underline">Cookie Notice</a>.
                </p>
            </section>

            {{-- Delete account --}}
            <section id="delete-account" class="ui-card scroll-mt-4" aria-labelledby="delete-heading">
                <div class="ui-card-header">
                    <h2 id="delete-heading" class="ui-card-title">Delete account</h2>
                    <p class="ui-card-subtitle">This can't be undone.</p>
                </div>
                @if ($deletionBlocker)
                    <div class="ui-card-body text-sm text-[#a3a3a3]">{{ $deletionBlocker }}</div>
                @else
                    <form action="{{ route('settings.account.destroy') }}" method="POST">
                        @csrf
                        @method('DELETE')
                        <div class="ui-card-body space-y-4">
                            <p class="text-sm text-[#a3a3a3]">
                                @if ($deletionKeepsRecord)
                                    Your sign-in, email, Gmail, GitHub link and monitoring data are erased. Your name and grades stay, so your certificates keep verifying and teammates keep their group labs. Shared group lab records stay too, minus your camera snapshots.
                                @else
                                    Your account, lab work and monitoring data are erased.
                                @endif
                            </p>
                            <div class="sm:max-w-sm">
                                <label for="confirm_email" class="ui-label">Type {{ $user->email }} to confirm</label>
                                <input type="email" name="confirm_email" id="confirm_email" required autocomplete="off" class="ui-input" @error('confirm_email') aria-invalid="true" @enderror>
                                @error('confirm_email') <p class="ui-error">{{ $message }}</p> @enderror
                            </div>
                        </div>
                        <div class="flex justify-end border-t border-[#232323] px-6 py-4">
                            <button type="submit" class="ui-btn ui-btn-danger">Delete my account</button>
                        </div>
                    </form>
                @endif
            </section>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    // Gmail code: wait 60 seconds after sending before allowing another
    (() => {
        const button = document.getElementById('resend-gmail-btn');
        if (!button) return;
        const key = 'gmail_resend_cooldown_end';
        const justSent = @json(str_contains((string) session('success'), 'sent to'));
        let end = 0;
        try {
            end = parseInt(sessionStorage.getItem(key) || '0', 10);
            if (!end && justSent) { end = Date.now() + 60000; sessionStorage.setItem(key, String(end)); }
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

    // Theme cards
    function updateSettingsThemeCards(activeTheme) {
        ['system', 'dark', 'light'].forEach((t) => {
            const card = document.getElementById(`theme-card-${t}`);
            if (!card) return;
            const active = t === activeTheme;
            card.classList.toggle('border-[#3ecf8e]', active);
            card.classList.toggle('active-theme-card', active);
            card.classList.toggle('border-[#2e2e2e]', !active);
            card.setAttribute('aria-checked', active ? 'true' : 'false');
            const check = card.querySelector('.theme-check-badge');
            if (check) {
                check.classList.toggle('hidden', !active);
                check.classList.toggle('flex', active);
            }
        });
        const indicator = document.getElementById('theme-status-text');
        if (indicator) {
            indicator.textContent = { system: 'Active: System', dark: 'Active: Dark Mode', light: 'Active: Light Mode' }[activeTheme] || 'Active';
        }
    }

    function selectTheme(theme) {
        if (window.applyTheme) {
            window.applyTheme(theme);
        } else {
            try { localStorage.setItem('theme', theme); } catch (e) {}
        }
        updateSettingsThemeCards(theme);
    }

    let initialTheme = 'system';
    try { initialTheme = localStorage.getItem('theme') || 'system'; } catch (e) {}
    updateSettingsThemeCards(initialTheme);
    window.addEventListener('certicode:themechange', (e) => {
        if (e.detail && e.detail.theme) updateSettingsThemeCards(e.detail.theme);
    });
</script>
@endsection

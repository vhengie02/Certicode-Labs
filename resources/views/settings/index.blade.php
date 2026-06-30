@extends('layouts.app')

@section('title', 'Account Settings')
@section('page_header', 'Account & Notification Settings')

@section('content')
<div class="max-w-3xl mx-auto space-y-8">
    
    <!-- Errors Handler Alert -->
    @if ($errors->any())
        <div class="rounded-xl bg-rose-500/10 border border-rose-500/20 p-4 shadow-lg">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-rose-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-xs font-bold text-rose-450 uppercase tracking-wider">
                        Settings Error
                    </h3>
                    <ul class="mt-1 list-disc list-inside text-xs text-rose-400 space-y-0.5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <!-- Profile Information Box -->
    <form action="{{ route('settings.profile.update') }}" method="POST" class="glass-panel rounded-2xl p-8 border border-slate-800 space-y-6">
        @csrf
        @method('PUT')

        <div class="flex items-center space-x-3.5 pb-4 border-b border-slate-800/80">
            <div class="h-10 w-10 rounded-xl bg-blue-600/10 border border-blue-500/25 flex items-center justify-center text-blue-400 shrink-0">
                <!-- User Icon -->
                <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                </svg>
            </div>
            <div>
                <h2 class="text-lg font-bold text-white">Profile Information</h2>
                <p class="text-xs text-slate-400 mt-0.5">Update your name, username, gender, and email address.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- First Name -->
            <div>
                <label for="first_name" class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">First Name</label>
                <input type="text" name="first_name" id="first_name" required value="{{ old('first_name', $user->first_name) }}"
                       class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white placeholder-slate-600 focus:outline-none focus:border-indigo-500">
            </div>

            <!-- Last Name -->
            <div>
                <label for="last_name" class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Last Name</label>
                <input type="text" name="last_name" id="last_name" required value="{{ old('last_name', $user->last_name) }}"
                       class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white placeholder-slate-600 focus:outline-none focus:border-indigo-500">
            </div>

            <!-- Username -->
            <div>
                <label for="username" class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Username</label>
                <input type="text" name="username" id="username" required value="{{ old('username', $user->username) }}"
                       class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white placeholder-slate-600 focus:outline-none focus:border-indigo-500">
            </div>

            <!-- Gender -->
            <div>
                <label for="gender" class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Gender</label>
                <select name="gender" id="gender"
                        class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white focus:outline-none focus:border-indigo-500">
                    <option value="" disabled {{ is_null($user->gender) ? 'selected' : '' }}>Select Gender...</option>
                    <option value="male" {{ $user->gender === 'male' ? 'selected' : '' }}>Male</option>
                    <option value="female" {{ $user->gender === 'female' ? 'selected' : '' }}>Female</option>
                    <option value="other" {{ $user->gender === 'other' ? 'selected' : '' }}>Other / Prefer not to say</option>
                </select>
            </div>
        </div>

        <div class="flex justify-end pt-4 border-t border-slate-800/80">
            <button type="submit" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 font-semibold text-xs rounded-xl text-white transition shadow-lg shadow-indigo-600/25">
                Save Profile Information
            </button>
        </div>
    </form>

    <!-- Change Password Box -->
    <form action="{{ route('settings.password.update') }}" method="POST" class="glass-panel rounded-2xl p-8 border border-slate-800 space-y-6">
        @csrf
        @method('PUT')

        <div class="flex items-center space-x-3.5 pb-4 border-b border-slate-800/80">
            <div class="h-10 w-10 rounded-xl bg-blue-600/10 border border-blue-500/25 flex items-center justify-center text-blue-450 shrink-0">
                <!-- Key Icon -->
                <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m-2 4a5 5 0 11-7.07-7.07m7.07 7.07L17 17m0 0a2 2 0 102 2 2 2 0 00-2-2zm0 0l-3-3m0 0h.01"></path>
                </svg>
            </div>
            <div>
                <h2 class="text-lg font-bold text-white">Change Password</h2>
                <p class="text-xs text-slate-400 mt-0.5">Ensure your account is using a secure password.</p>
            </div>
        </div>

        <div class="space-y-4">
            <!-- Current Password -->
            <div>
                <label for="current_password" class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Current Password</label>
                <input type="password" name="current_password" id="current_password" required placeholder="••••••••"
                       class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white placeholder-slate-600 focus:outline-none focus:border-indigo-500">
            </div>

            <!-- New Password -->
            <div>
                <label for="new_password" class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">New Password</label>
                <input type="password" name="password" id="new_password" required placeholder="••••••••"
                       class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white placeholder-slate-600 focus:outline-none focus:border-indigo-500">
            </div>

            <!-- Confirm New Password -->
            <div>
                <label for="new_password_confirmation" class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Confirm New Password</label>
                <input type="password" name="password_confirmation" id="new_password_confirmation" required placeholder="••••••••"
                       class="w-full px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white placeholder-slate-600 focus:outline-none focus:border-indigo-500">
            </div>
        </div>

        <div class="flex justify-end pt-4 border-t border-slate-800/80">
            <button type="submit" class="px-5 py-2.5 bg-rose-600 hover:bg-rose-500 font-semibold text-xs rounded-xl text-white transition shadow-lg shadow-rose-600/25">
                Update Password
            </button>
        </div>
    </form>

    <!-- Gmail Connection Box -->
    <div class="glass-panel rounded-2xl p-8 border border-slate-800 space-y-6">
        <div class="flex items-center space-x-3.5 pb-4 border-b border-slate-800/80">
            <div class="h-10 w-10 rounded-xl bg-indigo-600/10 border border-indigo-500/25 flex items-center justify-center text-indigo-400 shrink-0">
                <!-- SVG Google/Mail Icon -->
                <svg class="w-5 h-5 text-indigo-400" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                    <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill-rule="evenodd" clip-rule="evenodd"/>
                    <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z" fill-rule="evenodd" clip-rule="evenodd"/>
                    <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z" fill-rule="evenodd" clip-rule="evenodd"/>
                </svg>
            </div>
            <div>
                <h2 class="text-lg font-bold text-white">Gmail Integration</h2>
                <p class="text-xs text-slate-400 mt-0.5">Link your Google account to authorize instant access logins and routing alerts.</p>
            </div>
        </div>

        <!-- Primary Account Email -->
        <div class="bg-slate-950/30 border border-slate-800/80 p-4 rounded-xl flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="h-5 w-5 bg-indigo-500/10 border border-indigo-500/20 text-indigo-400 rounded-full flex items-center justify-center shrink-0">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                </div>
                <div>
                    <p class="text-xs text-slate-500 font-semibold">Primary Account Email</p>
                    <p class="text-sm font-semibold text-white font-mono mt-0.5">{{ $user->email }}</p>
                </div>
            </div>
            <div class="text-[10px] text-slate-500 uppercase tracking-wider font-bold font-sans">
                Account Login
            </div>
        </div>

        @if(empty($user->gmail))
            <!-- Case 1: Not connected at all -->
            <form action="{{ route('settings.gmail.connect') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label for="gmail" class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Google Email Address</label>
                    <div class="flex gap-3">
                        <input type="email" name="gmail" id="gmail" required placeholder="example@gmail.com"
                               class="flex-1 px-4 py-2.5 bg-slate-950 border border-slate-800 rounded-xl text-xs text-white placeholder-slate-600 focus:outline-none focus:border-indigo-500">
                        <button type="submit" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 font-semibold text-xs rounded-xl text-white transition shadow-lg shadow-indigo-600/25 shrink-0">
                            Connect Gmail
                        </button>
                    </div>
                    <p class="text-[10px] text-slate-500 mt-2">Connecting your Gmail requires completing verification to receive platform notifications via email.</p>
                </div>
            </form>

        @elseif(!empty($user->gmail) && empty($user->gmail_verified_at))
            <!-- Case 2: Code verification is pending -->
            <div class="bg-amber-500/5 border border-amber-500/20 p-4 rounded-xl space-y-4">
                <div class="flex items-start space-x-3 text-xs text-amber-400 leading-normal">
                    <svg class="w-5 h-5 text-amber-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    <div>
                        <span class="font-bold text-white block">Connection Pending Verification</span>
                        We have logged a 6-digit confirmation code for connecting <span class="font-mono font-semibold">{{ $user->gmail }}</span>. Please submit it below.
                    </div>
                </div>

                <form action="{{ route('settings.gmail.verify') }}" method="POST" class="flex gap-3">
                    @csrf
                    <input type="text" name="code" required maxlength="6" placeholder="######"
                           class="w-32 text-center tracking-[0.3em] font-mono px-4 py-2 bg-slate-950 border border-slate-800 rounded-xl text-sm text-white focus:outline-none focus:border-indigo-500">
                    <button type="submit" class="px-5 py-2.5 bg-emerald-600 hover:bg-emerald-500 font-semibold text-xs rounded-xl text-white transition shadow-lg shadow-emerald-600/25 shrink-0">
                        Confirm Code
                    </button>
                </form>

                <div class="flex items-center justify-between text-[10px] pt-2 border-t border-slate-800/40">
                    <div class="flex items-center space-x-1">
                        <span class="text-slate-500">Wrong address?</span>
                        <form action="{{ route('settings.gmail.disconnect') }}" method="POST" class="m-0 inline">
                            @csrf
                            <button type="submit" class="text-rose-400 hover:underline">Cancel & Reset</button>
                        </form>
                    </div>
                    
                    <form action="{{ route('settings.gmail.connect') }}" method="POST" class="m-0 inline">
                        @csrf
                        <input type="hidden" name="gmail" value="{{ $user->gmail }}">
                        <button type="submit" id="resend-gmail-btn" class="text-indigo-400 hover:underline font-semibold bg-transparent border-0 p-0 cursor-pointer">
                            Didn't receive code? Resend Code
                        </button>
                    </form>
                </div>
            </div>

        @else
            <!-- Case 3: Fully verified and connected -->
            <div class="flex items-center justify-between bg-slate-950/30 border border-slate-850 p-4 rounded-xl">
                <div class="flex items-center space-x-3">
                    <div class="h-5 w-5 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 rounded-full flex items-center justify-center shrink-0">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500 font-semibold">Linked Google Account</p>
                        <p class="text-sm font-semibold text-white font-mono mt-0.5">{{ $user->gmail }}</p>
                    </div>
                </div>
                <form action="{{ route('settings.gmail.disconnect') }}" method="POST" class="m-0">
                    @csrf
                    <button type="submit" class="px-4 py-2 border border-slate-800 hover:bg-slate-850 hover:text-rose-400 text-xs font-semibold rounded-lg text-slate-400 transition">
                        Disconnect
                    </button>
                </form>
            </div>
        @endif
    </div>

    <!-- GitHub Connection Box -->
    <div class="glass-panel rounded-2xl p-8 border border-slate-800 space-y-6">
        <div class="flex items-center space-x-3.5 pb-4 border-b border-slate-800/80">
            <div class="h-10 w-10 rounded-xl bg-purple-650/10 border border-purple-500/25 flex items-center justify-center text-purple-400 shrink-0">
                <!-- SVG GitHub Icon -->
                <svg class="w-5 h-5 text-purple-400" fill="currentColor" viewBox="0 0 24 24">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.477 2 2 6.477 2 12c0 4.42 2.865 8.166 6.839 9.489.5.092.682-.217.682-.482 0-.237-.008-.866-.013-1.7-2.782.603-3.369-1.34-3.369-1.34-.454-1.156-1.11-1.464-1.11-1.464-.908-.62.069-.608.069-.608 1.003.07 1.531 1.03 1.531 1.03.892 1.529 2.341 1.087 2.91.831.092-.646.35-1.086.636-1.336-2.22-.253-4.555-1.11-4.555-4.943 0-1.091.39-1.984 1.029-2.683-.103-.253-.446-1.27.098-2.647 0 0 .84-.269 2.75 1.025A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.294 2.747-1.025 2.747-1.025.546 1.377.203 2.394.1 2.647.64.699 1.028 1.592 1.028 2.683 0 3.842-2.339 4.687-4.566 4.935.359.309.678.919.678 1.852 0 1.336-.012 2.415-.012 2.743 0 .267.18.579.688.481C19.137 20.162 22 16.418 22 12c0-5.523-4.477-10-10-10z"/>
                </svg>
            </div>
            <div>
                <h2 class="text-lg font-bold text-white">GitHub Connection</h2>
                <p class="text-xs text-slate-400 mt-0.5">Link your GitHub profile to synchronize repository activity and code contributions.</p>
            </div>
        </div>

        @if(empty($user->github_username))
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between bg-slate-950/20 border border-slate-850 p-4 rounded-xl gap-4">
                <div class="text-xs text-slate-400 leading-relaxed">
                    Connecting your GitHub account automatically imports commit frequency and updates your contribution grid dashboard graphics.
                </div>
                <a href="{{ route('auth.provider.redirect', 'github') }}" class="inline-flex items-center px-5 py-2.5 bg-purple-600 hover:bg-purple-500 font-semibold text-xs rounded-xl text-white transition shadow-lg shadow-purple-600/25 shrink-0 text-center justify-center">
                    Connect GitHub Account
                </a>
            </div>
        @else
            <div class="flex items-center justify-between bg-slate-950/30 border border-slate-850 p-4 rounded-xl">
                <div class="flex items-center space-x-3">
                    <div class="h-5 w-5 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 rounded-full flex items-center justify-center shrink-0">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    </div>
                    <div>
                        <p class="text-xs text-slate-500 font-semibold">Linked GitHub Account</p>
                        <p class="text-sm font-semibold text-white font-mono mt-0.5">{{ '@' . $user->github_username }}</p>
                    </div>
                </div>
                <form action="{{ route('settings.github.disconnect') }}" method="POST" class="m-0">
                    @csrf
                    <button type="submit" class="px-4 py-2 border border-slate-800 hover:bg-slate-850 hover:text-rose-400 text-xs font-semibold rounded-lg text-slate-400 transition">
                        Disconnect
                    </button>
                </form>
            </div>
        @endif
    </div>

    <!-- Notification Settings Box -->
    <form action="{{ route('settings.notifications.update') }}" method="POST" class="glass-panel rounded-2xl p-8 border border-slate-800 space-y-6">
        @csrf

        <div class="flex items-center space-x-3.5 pb-4 border-b border-slate-800/80">
            <div class="h-10 w-10 rounded-xl bg-indigo-600/10 border border-indigo-500/25 flex items-center justify-center text-indigo-400 shrink-0">
                <!-- Bell Icon -->
                <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                </svg>
            </div>
            <div>
                <h2 class="text-lg font-bold text-white">Notification Preferences</h2>
                <p class="text-xs text-slate-400 mt-0.5">Toggle exactly which updates you wish to receive.</p>
            </div>
        </div>

        <div class="space-y-5">
            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Subscribed Alert Triggers</h3>

            <!-- 1. Class -->
            <label class="flex items-start justify-between cursor-pointer select-none group py-1">
                <div class="pr-4">
                    <span class="font-semibold text-white block text-xs group-hover:text-indigo-400 transition-colors">Class Invites & Admissions</span>
                    <span class="text-[11px] text-slate-400">Notify me when an instructor invites me to a class or joins my roster.</span>
                </div>
                <div class="relative shrink-0 mt-0.5">
                    <input type="checkbox" name="notify_class" id="notify_class" value="1" {{ $user->notify_class ? 'checked' : '' }} class="sr-only peer">
                    <div class="w-10 h-6 bg-slate-850 border border-slate-800 rounded-full peer peer-checked:bg-indigo-600 peer-checked:border-indigo-500 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-slate-500 after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-4 peer-checked:after:bg-white"></div>
                </div>
            </label>

            <!-- 2. Modules -->
            <label class="flex items-start justify-between cursor-pointer select-none group py-1">
                <div class="pr-4">
                    <span class="font-semibold text-white block text-xs group-hover:text-indigo-400 transition-colors">Module Upload Updates</span>
                    <span class="text-[11px] text-slate-400">Notify me when instructors upload study lesson guides or sub-modules.</span>
                </div>
                <div class="relative shrink-0 mt-0.5">
                    <input type="checkbox" name="notify_module" id="notify_module" value="1" {{ $user->notify_module ? 'checked' : '' }} class="sr-only peer">
                    <div class="w-10 h-6 bg-slate-850 border border-slate-800 rounded-full peer peer-checked:bg-indigo-600 peer-checked:border-indigo-500 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-slate-500 after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-4 peer-checked:after:bg-white"></div>
                </div>
            </label>

            <!-- 3. Labs -->
            <label class="flex items-start justify-between cursor-pointer select-none group py-1">
                <div class="pr-4">
                    <span class="font-semibold text-white block text-xs group-hover:text-indigo-400 transition-colors">Laboratory Exercise Actions</span>
                    <span class="text-[11px] text-slate-400">Notify me when new coding challenge tasks or lab exercises are assigned.</span>
                </div>
                <div class="relative shrink-0 mt-0.5">
                    <input type="checkbox" name="notify_lab" id="notify_lab" value="1" {{ $user->notify_lab ? 'checked' : '' }} class="sr-only peer">
                    <div class="w-10 h-6 bg-slate-850 border border-slate-800 rounded-full peer peer-checked:bg-indigo-600 peer-checked:border-indigo-500 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-slate-500 after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-4 peer-checked:after:bg-white"></div>
                </div>
            </label>

            <!-- 4. Certificates -->
            <label class="flex items-start justify-between cursor-pointer select-none group py-1">
                <div class="pr-4">
                    <span class="font-semibold text-white block text-xs group-hover:text-indigo-400 transition-colors">Certificate Accomplishments</span>
                    <span class="text-[11px] text-slate-400">Notify me when I successfully achieve 100% progress and earn verified certificates.</span>
                </div>
                <div class="relative shrink-0 mt-0.5">
                    <input type="checkbox" name="notify_certificate" id="notify_certificate" value="1" {{ $user->notify_certificate ? 'checked' : '' }} class="sr-only peer">
                    <div class="w-10 h-6 bg-slate-850 border border-slate-800 rounded-full peer peer-checked:bg-indigo-600 peer-checked:border-indigo-500 after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-slate-500 after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:after:translate-x-4 peer-checked:after:bg-white"></div>
                </div>
            </label>
        </div>

        <div class="flex justify-end pt-4 border-t border-slate-800/80">
            <button type="submit" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 font-semibold text-xs rounded-xl text-white transition shadow-lg shadow-indigo-600/25">
                Save Notification Settings
            </button>
        </div>
    </form>
</div>

@section('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const resendBtn = document.getElementById('resend-gmail-btn');
        if (resendBtn) {
            let cooldownEnd = sessionStorage.getItem('gmail_resend_cooldown_end');
            let secondsLeft = 0;
            
            if (cooldownEnd) {
                secondsLeft = Math.ceil((parseInt(cooldownEnd) - Date.now()) / 1000);
            } else if ("{{ session('success') }}" && "{{ session('success') }}".includes("sent to")) {
                secondsLeft = 60;
                sessionStorage.setItem('gmail_resend_cooldown_end', Date.now() + 60000);
            }

            if (secondsLeft > 0) {
                disableResend(secondsLeft);
            }
            
            function disableResend(duration) {
                resendBtn.disabled = true;
                resendBtn.style.pointerEvents = 'none';
                resendBtn.classList.add('opacity-50', 'cursor-not-allowed');
                resendBtn.classList.remove('hover:underline');
                resendBtn.textContent = `Resend Code in ${duration}s`;
                
                let timeLeft = duration;
                const interval = setInterval(() => {
                    timeLeft--;
                    if (timeLeft <= 0) {
                        clearInterval(interval);
                        sessionStorage.removeItem('gmail_resend_cooldown_end');
                        resendBtn.disabled = false;
                        resendBtn.style.pointerEvents = 'auto';
                        resendBtn.classList.remove('opacity-50', 'cursor-not-allowed');
                        resendBtn.classList.add('hover:underline');
                        resendBtn.textContent = "Didn't receive code? Resend Code";
                    } else {
                        resendBtn.textContent = `Resend Code in ${timeLeft}s`;
                    }
                }, 1000);
            }
        }
    });
</script>
@endsection
@endsection

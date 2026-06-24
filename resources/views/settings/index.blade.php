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
                    <ul class="mt-1 list-disc list-inside text-xs text-rose-350 space-y-0.5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

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
                <p class="text-xs text-slate-450 mt-0.5">Link your Google account to authorize instant access logins and routing alerts.</p>
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
                    <svg class="w-4.5 h-4.5 text-amber-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
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
                    <span class="text-slate-500">Wrong address? Disconnect and reset.</span>
                    <form action="{{ route('settings.gmail.disconnect') }}" method="POST" class="m-0">
                        @csrf
                        <button type="submit" class="text-rose-450 hover:underline">Cancel & Reset</button>
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
                    <button type="submit" class="px-4 py-2 border border-slate-800 hover:bg-slate-850 hover:text-rose-400 text-xs font-semibold rounded-lg text-slate-450 transition">
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
                <p class="text-xs text-slate-450 mt-0.5">Toggle exactly which updates you wish to receive and configure the email delivery options.</p>
            </div>
        </div>

        <div class="space-y-4">
            
            <!-- Global Email toggle -->
            <label class="flex items-start justify-between p-4 bg-slate-900/20 border border-slate-850 rounded-xl cursor-pointer hover:border-slate-800 transition">
                <div class="space-y-0.5 pr-4">
                    <span class="text-sm font-semibold text-white block">Email Notifications Dispatcher</span>
                    <span class="text-xs text-slate-450 block">When active, all enabled alerts below will trigger an email dispatch to your verified Gmail connection.</span>
                </div>
                <input type="checkbox" name="notify_email_channel" value="1" {{ $user->notify_email_channel ? 'checked' : '' }}
                       class="h-4 w-4 text-indigo-600 bg-slate-950 border-slate-800 rounded focus:ring-indigo-500 focus:ring-offset-slate-950 shrink-0 mt-1">
            </label>

            <!-- Class Alert toggles -->
            <div class="space-y-3.5 pt-2">
                <h3 class="text-xs font-bold text-slate-400 uppercase tracking-wider">Subscribed Alert Triggers</h3>

                <!-- 1. Class -->
                <label class="flex items-start space-x-3 text-xs text-slate-350 cursor-pointer select-none">
                    <input type="checkbox" name="notify_class" value="1" {{ $user->notify_class ? 'checked' : '' }}
                           class="h-4 w-4 text-indigo-600 bg-slate-950 border-slate-800 rounded focus:ring-indigo-500 focus:ring-offset-slate-950 shrink-0 mt-0.5">
                    <div>
                        <span class="font-semibold text-white block text-xs">Class Invites & Admissions</span>
                        Notify me when an instructor invites me to a class or joins my roster.
                    </div>
                </label>

                <!-- 2. Modules -->
                <label class="flex items-start space-x-3 text-xs text-slate-350 cursor-pointer select-none">
                    <input type="checkbox" name="notify_module" value="1" {{ $user->notify_module ? 'checked' : '' }}
                           class="h-4 w-4 text-indigo-600 bg-slate-950 border-slate-800 rounded focus:ring-indigo-500 focus:ring-offset-slate-950 shrink-0 mt-0.5">
                    <div>
                        <span class="font-semibold text-white block text-xs">Module Upload Updates</span>
                        Notify me when instructors upload study lesson guides or sub-modules.
                    </div>
                </label>

                <!-- 3. Labs -->
                <label class="flex items-start space-x-3 text-xs text-slate-350 cursor-pointer select-none">
                    <input type="checkbox" name="notify_lab" value="1" {{ $user->notify_lab ? 'checked' : '' }}
                           class="h-4 w-4 text-indigo-600 bg-slate-950 border-slate-800 rounded focus:ring-indigo-500 focus:ring-offset-slate-950 shrink-0 mt-0.5">
                    <div>
                        <span class="font-semibold text-white block text-xs">Laboratory Exercise Actions</span>
                        Notify me when new coding challenge tasks or lab exercises are assigned.
                    </div>
                </label>

                <!-- 4. Certificates -->
                <label class="flex items-start space-x-3 text-xs text-slate-350 cursor-pointer select-none">
                    <input type="checkbox" name="notify_certificate" value="1" {{ $user->notify_certificate ? 'checked' : '' }}
                           class="h-4 w-4 text-indigo-600 bg-slate-950 border-slate-800 rounded focus:ring-indigo-500 focus:ring-offset-slate-950 shrink-0 mt-0.5">
                    <div>
                        <span class="font-semibold text-white block text-xs">Certificate Accomplishments</span>
                        Notify me when I successfully achieve 100% progress and earn verified certificates.
                    </div>
                </label>
            </div>
        </div>

        <div class="flex justify-end pt-4 border-t border-slate-800/80">
            <button type="submit" class="px-5 py-2.5 bg-indigo-600 hover:bg-indigo-500 font-semibold text-xs rounded-xl text-white transition shadow-lg shadow-indigo-600/25">
                Save Notification Settings
            </button>
        </div>
    </form>
</div>
@endsection

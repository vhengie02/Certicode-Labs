@extends('layouts.app')

@section('title', 'Account Settings')
@section('page_header', 'Account & Notification Settings')

@section('content')
<div class="max-w-3xl mx-auto space-y-8">
    
    <!-- Errors Handler Alert -->
    @if ($errors->any())
        <div class="rounded-[6px] bg-[#171717] border border-red-500/30 p-4">
            <div class="flex">
                <div class="flex-shrink-0">
                    <svg class="h-5 w-5 text-red-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd" />
                    </svg>
                </div>
                <div class="ml-3">
                    <h3 class="text-xs font-mono font-bold text-red-400 uppercase tracking-wider">
                        Settings Error
                    </h3>
                    <ul class="mt-1 list-disc list-inside text-xs text-red-300 space-y-0.5">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>
    @endif

    <!-- Appearance & Theme Selector -->
    <div class="rounded-xl p-8 bg-[#171717] border border-[#2e2e2e] space-y-6">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-4 border-b border-[#232323]">
            <div class="flex items-center space-x-3.5">
                <div class="h-9 w-9 rounded-[6px] bg-[#141414] border border-[#2e2e2e] flex items-center justify-center text-[#3ecf8e] shrink-0">
                    <!-- Palette / Appearance icon -->
                    <svg class="w-4 h-4 text-[#3ecf8e]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01"></path>
                    </svg>
                </div>
                <div>
                    <h2 class="text-base font-bold text-[#ededed]">Appearance & Theme</h2>
                    <p class="text-xs text-[#888888] font-mono mt-0.5">Customize your interface theme. Selected theme is applied instantly and saved to your device.</p>
                </div>
            </div>
            <div id="theme-status-indicator" class="inline-flex items-center space-x-1.5 px-3 py-1 rounded-full bg-[#141414] border border-[#2e2e2e] text-[11px] font-mono text-[#3ecf8e] transition-all self-start sm:self-auto">
                <span class="w-1.5 h-1.5 rounded-full bg-[#3ecf8e] animate-pulse"></span>
                <span id="theme-status-text">Active: System</span>
            </div>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <!-- 1. System Default Theme Option -->
            <button type="button" onclick="selectTheme('system')" id="theme-card-system"
                    class="theme-card relative group text-left rounded-xl p-4 bg-[#141414] border-2 border-[#2e2e2e] hover:border-[#3ecf8e]/50 transition-all focus:outline-none flex flex-col justify-between">
                <div>
                    <!-- Mini Preview Box -->
                    <div class="h-28 w-full rounded-lg border border-[#2e2e2e] overflow-hidden mb-3.5 relative flex">
                        <!-- Left half: Light Mode Preview -->
                        <div class="w-1/2 h-full bg-[#f8f9fa] p-2 flex flex-col justify-between border-r border-[#e5e7eb]">
                            <div class="flex items-center space-x-1 mb-1">
                                <div class="w-1.5 h-1.5 rounded-full bg-red-400"></div>
                                <div class="w-1.5 h-1.5 rounded-full bg-amber-400"></div>
                                <div class="w-1.5 h-1.5 rounded-full bg-emerald-400"></div>
                            </div>
                            <div class="space-y-1.5">
                                <div class="h-2 w-3/4 bg-[#e5e7eb] rounded"></div>
                                <div class="h-2 w-full bg-white border border-[#e5e7eb] rounded"></div>
                                <div class="h-2 w-1/2 bg-[#059669] rounded-full"></div>
                            </div>
                            <div class="h-1.5 w-1/3 bg-[#d1d5db] rounded"></div>
                        </div>
                        <!-- Right half: Dark Mode Preview -->
                        <div class="w-1/2 h-full bg-[#0f0f0f] p-2 flex flex-col justify-between">
                            <div class="flex items-center justify-end space-x-1 mb-1">
                                <div class="w-3 h-1.5 rounded-full bg-[#3ecf8e]"></div>
                            </div>
                            <div class="space-y-1.5">
                                <div class="h-2 w-3/4 bg-[#2e2e2e] rounded"></div>
                                <div class="h-2 w-full bg-[#171717] border border-[#2e2e2e] rounded"></div>
                                <div class="h-2 w-1/2 bg-[#3ecf8e] rounded-full"></div>
                            </div>
                            <div class="h-1.5 w-1/3 bg-[#404040] rounded"></div>
                        </div>
                    </div>

                    <!-- Label & Details -->
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-2">
                            <svg class="w-4 h-4 text-[#888888] group-hover:text-[#ededed] transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                            </svg>
                            <span class="text-xs font-bold text-[#ededed]">System</span>
                        </div>
                        <span class="theme-check-badge hidden h-4 w-4 rounded-full bg-[#3ecf8e] text-[#0f0f0f] items-center justify-center text-[10px] font-bold">✓</span>
                    </div>
                    <p class="text-[11px] text-[#888888] font-mono mt-1">Adapts to OS color scheme.</p>
                </div>
                <div class="mt-3 pt-2 border-t border-[#232323] flex items-center justify-between">
                    <span class="tech-tag text-[9px] text-[#888888]">AUTO-SYNC</span>
                    <span class="theme-status-pill text-[10px] font-mono text-[#888888]">Inactive</span>
                </div>
            </button>

            <!-- 2. Dark Mode Option -->
            <button type="button" onclick="selectTheme('dark')" id="theme-card-dark"
                    class="theme-card relative group text-left rounded-xl p-4 bg-[#141414] border-2 border-[#2e2e2e] hover:border-[#3ecf8e]/50 transition-all focus:outline-none flex flex-col justify-between">
                <div>
                    <!-- Mini Preview Box -->
                    <div class="h-28 w-full rounded-lg border border-[#2e2e2e] bg-[#0f0f0f] p-2.5 flex flex-col justify-between mb-3.5 relative overflow-hidden">
                        <div class="flex items-center justify-between mb-1">
                            <div class="flex items-center space-x-1">
                                <div class="w-1.5 h-1.5 rounded-full bg-red-500/60"></div>
                                <div class="w-1.5 h-1.5 rounded-full bg-amber-500/60"></div>
                                <div class="w-1.5 h-1.5 rounded-full bg-emerald-500/60"></div>
                            </div>
                            <div class="w-8 h-1.5 rounded-full bg-[#2e2e2e]"></div>
                        </div>
                        <div class="p-2 rounded bg-[#171717] border border-[#2e2e2e] space-y-1.5">
                            <div class="flex items-center justify-between">
                                <div class="h-2 w-1/3 bg-[#3ecf8e] rounded-full"></div>
                                <div class="h-1.5 w-1/4 bg-[#383838] rounded"></div>
                            </div>
                            <div class="h-2 w-full bg-[#232323] rounded"></div>
                        </div>
                        <div class="flex items-center justify-between pt-1">
                            <div class="h-1.5 w-1/4 bg-[#404040] rounded"></div>
                            <div class="h-2.5 w-10 bg-[#3ecf8e] rounded-full"></div>
                        </div>
                    </div>

                    <!-- Label & Details -->
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-2">
                            <svg class="w-4 h-4 text-[#888888] group-hover:text-[#ededed] transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"></path>
                            </svg>
                            <span class="text-xs font-bold text-[#ededed]">Dark Mode</span>
                        </div>
                        <span class="theme-check-badge hidden h-4 w-4 rounded-full bg-[#3ecf8e] text-[#0f0f0f] items-center justify-center text-[10px] font-bold">✓</span>
                    </div>
                    <p class="text-[11px] text-[#888888] font-mono mt-1">Supabase near-black #0f0f0f.</p>
                </div>
                <div class="mt-3 pt-2 border-t border-[#232323] flex items-center justify-between">
                    <span class="tech-tag text-[9px] text-[#888888]">EMERALD #3ECF8E</span>
                    <span class="theme-status-pill text-[10px] font-mono text-[#888888]">Inactive</span>
                </div>
            </button>

            <!-- 3. Light Mode Option -->
            <button type="button" onclick="selectTheme('light')" id="theme-card-light"
                    class="theme-card relative group text-left rounded-xl p-4 bg-[#141414] border-2 border-[#2e2e2e] hover:border-[#3ecf8e]/50 transition-all focus:outline-none flex flex-col justify-between">
                <div>
                    <!-- Mini Preview Box -->
                    <div class="h-28 w-full rounded-lg border border-[#e5e7eb] bg-[#f8f9fa] p-2.5 flex flex-col justify-between mb-3.5 relative overflow-hidden">
                        <div class="flex items-center justify-between mb-1">
                            <div class="flex items-center space-x-1">
                                <div class="w-1.5 h-1.5 rounded-full bg-red-400"></div>
                                <div class="w-1.5 h-1.5 rounded-full bg-amber-400"></div>
                                <div class="w-1.5 h-1.5 rounded-full bg-emerald-400"></div>
                            </div>
                            <div class="w-8 h-1.5 rounded-full bg-[#e5e7eb]"></div>
                        </div>
                        <div class="p-2 rounded bg-[#ffffff] border border-[#e5e7eb] shadow-sm space-y-1.5">
                            <div class="flex items-center justify-between">
                                <div class="h-2 w-1/3 bg-[#059669] rounded-full"></div>
                                <div class="h-1.5 w-1/4 bg-[#e5e7eb] rounded"></div>
                            </div>
                            <div class="h-2 w-full bg-[#f3f4f6] rounded"></div>
                        </div>
                        <div class="flex items-center justify-between pt-1">
                            <div class="h-1.5 w-1/4 bg-[#d1d5db] rounded"></div>
                            <div class="h-2.5 w-10 bg-[#059669] rounded-full"></div>
                        </div>
                    </div>

                    <!-- Label & Details -->
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-2">
                            <svg class="w-4 h-4 text-[#888888] group-hover:text-[#ededed] transition-colors" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path>
                            </svg>
                            <span class="text-xs font-bold text-[#ededed]">Light Mode</span>
                        </div>
                        <span class="theme-check-badge hidden h-4 w-4 rounded-full bg-[#3ecf8e] text-[#0f0f0f] items-center justify-center text-[10px] font-bold">✓</span>
                    </div>
                    <p class="text-[11px] text-[#888888] font-mono mt-1">Supabase clean white #ffffff.</p>
                </div>
                <div class="mt-3 pt-2 border-t border-[#232323] flex items-center justify-between">
                    <span class="tech-tag text-[9px] text-[#888888]">EMERALD #059669</span>
                    <span class="theme-status-pill text-[10px] font-mono text-[#888888]">Inactive</span>
                </div>
            </button>
        </div>
    </div>

    <!-- Profile Information Box -->
    <form action="{{ route('settings.profile.update') }}" method="POST" class="rounded-xl p-8 bg-[#171717] border border-[#2e2e2e] space-y-6">
        @csrf
        @method('PUT')

        <div class="flex items-center space-x-3.5 pb-4 border-b border-[#232323]">
            <div class="h-9 w-9 rounded-[6px] bg-[#141414] border border-[#2e2e2e] flex items-center justify-center text-[#3ecf8e] shrink-0">
                <svg class="w-4 h-4 text-[#3ecf8e]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                </svg>
            </div>
            <div>
                <h2 class="text-base font-bold text-[#ededed]">Profile Information</h2>
                <p class="text-xs text-[#888888] font-mono mt-0.5">Update your name, username, gender, and personal information.</p>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
            <!-- First Name -->
            <div>
                <label for="first_name" class="block text-xs font-mono uppercase tracking-wider text-[#a3a3a3] mb-2">First Name</label>
                <input type="text" name="first_name" id="first_name" required value="{{ old('first_name', $user->first_name) }}"
                       class="w-full px-3.5 py-2 bg-[#141414] border border-[#2e2e2e] rounded-[6px] text-xs text-[#ededed] placeholder-[#666666] focus:outline-none focus:border-[#3ecf8e] focus:ring-1 focus:ring-[#3ecf8e]">
            </div>

            <!-- Last Name -->
            <div>
                <label for="last_name" class="block text-xs font-mono uppercase tracking-wider text-[#a3a3a3] mb-2">Last Name</label>
                <input type="text" name="last_name" id="last_name" required value="{{ old('last_name', $user->last_name) }}"
                       class="w-full px-3.5 py-2 bg-[#141414] border border-[#2e2e2e] rounded-[6px] text-xs text-[#ededed] placeholder-[#666666] focus:outline-none focus:border-[#3ecf8e] focus:ring-1 focus:ring-[#3ecf8e]">
            </div>

            <!-- Username -->
            <div>
                <label for="username" class="block text-xs font-mono uppercase tracking-wider text-[#a3a3a3] mb-2">Username</label>
                <input type="text" name="username" id="username" required value="{{ old('username', $user->username) }}"
                       class="w-full px-3.5 py-2 bg-[#141414] border border-[#2e2e2e] rounded-[6px] text-xs text-[#ededed] placeholder-[#666666] focus:outline-none focus:border-[#3ecf8e] focus:ring-1 focus:ring-[#3ecf8e]">
            </div>

            <!-- Gender -->
            <div>
                <label for="gender" class="block text-xs font-mono uppercase tracking-wider text-[#a3a3a3] mb-2">Gender</label>
                <select name="gender" id="gender"
                        class="w-full px-3.5 py-2 bg-[#141414] border border-[#2e2e2e] rounded-[6px] text-xs text-[#ededed] focus:outline-none focus:border-[#3ecf8e] focus:ring-1 focus:ring-[#3ecf8e]">
                    <option value="" disabled {{ is_null($user->gender) ? 'selected' : '' }} class="bg-[#141414] text-[#888888]">Select Gender...</option>
                    <option value="male" {{ $user->gender === 'male' ? 'selected' : '' }} class="bg-[#141414] text-[#ededed]">Male</option>
                    <option value="female" {{ $user->gender === 'female' ? 'selected' : '' }} class="bg-[#141414] text-[#ededed]">Female</option>
                    <option value="other" {{ $user->gender === 'other' ? 'selected' : '' }} class="bg-[#141414] text-[#ededed]">Other / Prefer not to say</option>
                </select>
            </div>
        </div>

        <div class="flex justify-end pt-4 border-t border-[#232323]">
            <button type="submit" class="px-5 py-2 bg-[#3ecf8e] hover:bg-[#00c573] font-semibold text-xs rounded-full text-[#0f0f0f] transition shadow-none">
                Save Profile Information &rarr;
            </button>
        </div>
    </form>

    <!-- Change Password Box -->
    <form action="{{ route('settings.password.update') }}" method="POST" class="rounded-xl p-8 bg-[#171717] border border-[#2e2e2e] space-y-6">
        @csrf
        @method('PUT')

        <div class="flex items-center space-x-3.5 pb-4 border-b border-[#232323]">
            <div class="h-9 w-9 rounded-[6px] bg-[#141414] border border-[#2e2e2e] flex items-center justify-center text-[#3ecf8e] shrink-0">
                <svg class="w-4 h-4 text-[#3ecf8e]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m-2 4a5 5 0 11-7.07-7.07m7.07 7.07L17 17m0 0a2 2 0 102 2 2 2 0 00-2-2zm0 0l-3-3m0 0h.01"></path>
                </svg>
            </div>
            <div>
                <h2 class="text-base font-bold text-[#ededed]">Change Password</h2>
                <p class="text-xs text-[#888888] font-mono mt-0.5">Ensure your account is authenticated with a secure key.</p>
            </div>
        </div>

        <div class="space-y-4">
            <!-- Current Password -->
            <div>
                <label for="current_password" class="block text-xs font-mono uppercase tracking-wider text-[#a3a3a3] mb-2">Current Password</label>
                <input type="password" name="current_password" id="current_password" required placeholder="••••••••"
                       class="w-full px-3.5 py-2 bg-[#141414] border border-[#2e2e2e] rounded-[6px] text-xs text-[#ededed] placeholder-[#666666] focus:outline-none focus:border-[#3ecf8e] focus:ring-1 focus:ring-[#3ecf8e]">
            </div>

            <!-- New Password -->
            <div>
                <label for="new_password" class="block text-xs font-mono uppercase tracking-wider text-[#a3a3a3] mb-2">New Password</label>
                <input type="password" name="password" id="new_password" required placeholder="••••••••"
                       class="w-full px-3.5 py-2 bg-[#141414] border border-[#2e2e2e] rounded-[6px] text-xs text-[#ededed] placeholder-[#666666] focus:outline-none focus:border-[#3ecf8e] focus:ring-1 focus:ring-[#3ecf8e]">
            </div>

            <!-- Confirm New Password -->
            <div>
                <label for="new_password_confirmation" class="block text-xs font-mono uppercase tracking-wider text-[#a3a3a3] mb-2">Confirm New Password</label>
                <input type="password" name="password_confirmation" id="new_password_confirmation" required placeholder="••••••••"
                       class="w-full px-3.5 py-2 bg-[#141414] border border-[#2e2e2e] rounded-[6px] text-xs text-[#ededed] placeholder-[#666666] focus:outline-none focus:border-[#3ecf8e] focus:ring-1 focus:ring-[#3ecf8e]">
            </div>
        </div>

        <div class="flex justify-end pt-4 border-t border-[#232323]">
            <button type="submit" class="px-5 py-2 bg-red-600 hover:bg-red-500 font-semibold text-xs rounded-full text-white transition shadow-none">
                Update Password
            </button>
        </div>
    </form>

    <!-- Gmail Connection Box -->
    <div class="rounded-xl p-8 bg-[#171717] border border-[#2e2e2e] space-y-6">
        <div class="flex items-center space-x-3.5 pb-4 border-b border-[#232323]">
            <div class="h-9 w-9 rounded-[6px] bg-[#141414] border border-[#2e2e2e] flex items-center justify-center text-[#3ecf8e] shrink-0">
                <svg class="w-4 h-4" viewBox="0 0 24 24">
                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                </svg>
            </div>
            <div>
                <h2 class="text-base font-bold text-[#ededed]">Gmail Integration</h2>
                <p class="text-xs text-[#888888] font-mono mt-0.5">Link your Google account to authorize instant access logins and routing alerts.</p>
            </div>
        </div>

        <!-- Primary Account Email -->
        <div class="bg-[#141414] border border-[#2e2e2e] p-4 rounded-[6px] flex items-center justify-between">
            <div class="flex items-center space-x-3">
                <div class="h-5 w-5 bg-[#171717] border border-[#2e2e2e] text-[#3ecf8e] rounded-full flex items-center justify-center shrink-0">
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path></svg>
                </div>
                <div>
                    <p class="text-[11px] text-[#888888] font-mono">Primary Account Email</p>
                    <p class="text-xs font-semibold text-[#ededed] font-mono mt-0.5">{{ $user->email }}</p>
                </div>
            </div>
            <div class="text-[10px] text-[#666666] uppercase tracking-wider font-bold font-mono">
                Account Login
            </div>
        </div>

        @if(empty($user->gmail))
            <!-- Case 1: Not connected at all -->
            <form action="{{ route('settings.gmail.connect') }}" method="POST" class="space-y-4">
                @csrf
                <div>
                    <label for="gmail" class="block text-xs font-mono uppercase tracking-wider text-[#a3a3a3] mb-2">Google Email Address</label>
                    <div class="flex gap-3">
                        <input type="email" name="gmail" id="gmail" required placeholder="example@gmail.com"
                               class="flex-1 px-3.5 py-2 bg-[#141414] border border-[#2e2e2e] rounded-[6px] text-xs text-[#ededed] placeholder-[#666666] focus:outline-none focus:border-[#3ecf8e]">
                        <button type="submit" class="px-5 py-2 bg-[#3ecf8e] hover:bg-[#00c573] font-semibold text-xs rounded-full text-[#0f0f0f] transition shadow-none shrink-0">
                            Connect Gmail &rarr;
                        </button>
                    </div>
                    <p class="text-[10px] text-[#666666] font-mono mt-2">Connecting your Gmail requires completing verification to receive platform notifications via email.</p>
                </div>
            </form>

        @elseif(!empty($user->gmail) && empty($user->gmail_verified_at))
            <!-- Case 2: Code verification is pending -->
            <div class="bg-[#141414] border border-yellow-500/25 p-4 rounded-[6px] space-y-4">
                <div class="flex items-start space-x-3 text-xs text-yellow-400 leading-normal font-mono">
                    <svg class="w-4 h-4 text-yellow-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    <div>
                        <span class="font-bold text-[#ededed] block">Connection Pending Verification</span>
                        A 6-digit confirmation code was dispatched for <span class="font-semibold text-yellow-400">{{ $user->gmail }}</span>. Submit below:
                    </div>
                </div>

                <form action="{{ route('settings.gmail.verify') }}" method="POST" class="flex gap-3">
                    @csrf
                    <input type="text" name="code" required maxlength="6" placeholder="######"
                           class="w-32 text-center tracking-[0.3em] font-mono px-3 py-2 bg-[#171717] border border-[#2e2e2e] rounded-[6px] text-sm text-[#ededed] focus:outline-none focus:border-[#3ecf8e]">
                    <button type="submit" class="px-5 py-2 bg-[#3ecf8e] hover:bg-[#00c573] font-semibold text-xs rounded-full text-[#0f0f0f] transition shadow-none shrink-0">
                        Confirm Code &rarr;
                    </button>
                </form>

                <div class="flex items-center justify-between text-[10px] pt-2 border-t border-[#232323] font-mono">
                    <div class="flex items-center space-x-1">
                        <span class="text-[#666666]">Wrong address?</span>
                        <form action="{{ route('settings.gmail.disconnect') }}" method="POST" class="m-0 inline">
                            @csrf
                            <button type="submit" class="text-red-400 hover:underline">Cancel & Reset</button>
                        </form>
                    </div>
                    
                    <form action="{{ route('settings.gmail.connect') }}" method="POST" class="m-0 inline">
                        @csrf
                        <input type="hidden" name="gmail" value="{{ $user->gmail }}">
                        <button type="submit" id="resend-gmail-btn" class="text-[#3ecf8e] hover:underline font-semibold bg-transparent border-0 p-0 cursor-pointer">
                            Didn't receive code? Resend Code
                        </button>
                    </form>
                </div>
            </div>

        @else
            <!-- Case 3: Fully verified and connected -->
            <div class="flex items-center justify-between bg-[#141414] border border-[#2e2e2e] p-4 rounded-[6px]">
                <div class="flex items-center space-x-3">
                    <div class="h-5 w-5 bg-[#171717] border border-[#2e2e2e] text-[#3ecf8e] rounded-full flex items-center justify-center shrink-0">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    </div>
                    <div>
                        <p class="text-[11px] text-[#888888] font-mono">Linked Google Account</p>
                        <p class="text-xs font-semibold text-[#ededed] font-mono mt-0.5">{{ $user->gmail }}</p>
                    </div>
                </div>
                <form action="{{ route('settings.gmail.disconnect') }}" method="POST" class="m-0">
                    @csrf
                    <button type="submit" class="px-3 py-1.5 border border-[#2e2e2e] hover:bg-[#222222] hover:text-red-400 text-xs font-mono rounded-[6px] text-[#888888] transition">
                        Disconnect
                    </button>
                </form>
            </div>
        @endif
    </div>

    <!-- GitHub Connection Box -->
    <div class="rounded-xl p-8 bg-[#171717] border border-[#2e2e2e] space-y-6">
        <div class="flex items-center space-x-3.5 pb-4 border-b border-[#232323]">
            <div class="h-9 w-9 rounded-[6px] bg-[#141414] border border-[#2e2e2e] flex items-center justify-center text-[#ededed] shrink-0">
                <svg class="w-4 h-4 text-[#ededed]" fill="currentColor" viewBox="0 0 24 24">
                    <path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.477 2 2 6.477 2 12c0 4.42 2.865 8.166 6.839 9.489.5.092.682-.217.682-.482 0-.237-.008-.866-.013-1.7-2.782.603-3.369-1.34-3.369-1.34-.454-1.156-1.11-1.464-1.11-1.464-.908-.62.069-.608.069-.608 1.003.07 1.531 1.03 1.531 1.03.892 1.529 2.341 1.087 2.91.831.092-.646.35-1.086.636-1.336-2.22-.253-4.555-1.11-4.555-4.943 0-1.091.39-1.984 1.029-2.683-.103-.253-.446-1.27.098-2.647 0 0 .84-.269 2.75 1.025A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.294 2.747-1.025 2.747-1.025.546 1.377.203 2.394.1 2.647.64.699 1.028 1.592 1.028 2.683 0 3.842-2.339 4.687-4.566 4.935.359.309.678.919.678 1.852 0 1.336-.012 2.415-.012 2.743 0 .267.18.579.688.481C19.137 20.162 22 16.418 22 12c0-5.523-4.477-10-10-10z"/>
                </svg>
            </div>
            <div>
                <h2 class="text-base font-bold text-[#ededed]">GitHub Connection</h2>
                <p class="text-xs text-[#888888] font-mono mt-0.5">Link your GitHub profile to synchronize repository telemetry and contribution metrics.</p>
            </div>
        </div>

        @if(empty($user->github_username))
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between bg-[#141414] border border-[#2e2e2e] p-4 rounded-[6px] gap-4">
                <div class="text-xs text-[#888888] leading-relaxed">
                    Connecting your GitHub account automatically imports commit telemetry and populates your contribution grid graphics.
                </div>
                <a href="{{ route('auth.provider.redirect', 'github') }}" class="inline-flex items-center px-5 py-2 bg-[#3ecf8e] hover:bg-[#00c573] font-semibold text-xs rounded-full text-[#0f0f0f] transition shadow-none shrink-0 text-center justify-center">
                    Connect GitHub &rarr;
                </a>
            </div>
        @else
            <div class="flex items-center justify-between bg-[#141414] border border-[#2e2e2e] p-4 rounded-[6px]">
                <div class="flex items-center space-x-3">
                    <div class="h-5 w-5 bg-[#171717] border border-[#2e2e2e] text-[#3ecf8e] rounded-full flex items-center justify-center shrink-0">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    </div>
                    <div>
                        <p class="text-[11px] text-[#888888] font-mono">Linked GitHub Account</p>
                        <p class="text-xs font-semibold text-[#ededed] font-mono mt-0.5">{{ '@' . $user->github_username }}</p>
                    </div>
                </div>
                <form action="{{ route('settings.github.disconnect') }}" method="POST" class="m-0">
                    @csrf
                    <button type="submit" class="px-3 py-1.5 border border-[#2e2e2e] hover:bg-[#222222] hover:text-red-400 text-xs font-mono rounded-[6px] text-[#888888] transition">
                        Disconnect
                    </button>
                </form>
            </div>
        @endif
    </div>

    <!-- Notification Settings Box -->
    <form action="{{ route('settings.notifications.update') }}" method="POST" class="rounded-xl p-8 bg-[#171717] border border-[#2e2e2e] space-y-6">
        @csrf

        <div class="flex items-center space-x-3.5 pb-4 border-b border-[#232323]">
            <div class="h-9 w-9 rounded-[6px] bg-[#141414] border border-[#2e2e2e] flex items-center justify-center text-[#3ecf8e] shrink-0">
                <svg class="w-4 h-4 text-[#3ecf8e]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                </svg>
            </div>
            <div>
                <h2 class="text-base font-bold text-[#ededed]">Notification Preferences</h2>
                <p class="text-xs text-[#888888] font-mono mt-0.5">Toggle exactly which telemetry and class alerts you wish to receive.</p>
            </div>
        </div>

        <div class="space-y-4">
            <h3 class="text-xs font-mono uppercase font-bold tracking-wider text-[#a3a3a3]">Subscribed Alert Triggers</h3>

            <!-- 1. Class -->
            <label class="flex items-start justify-between cursor-pointer select-none group py-1">
                <div class="pr-4">
                    <span class="font-medium text-[#ededed] block text-xs group-hover:text-[#3ecf8e] transition-colors">Class Invites & Admissions</span>
                    <span class="text-[11px] text-[#888888]">Notify me when an instructor invites me to a class or joins my roster.</span>
                </div>
                <div class="relative shrink-0 mt-0.5">
                    <input type="checkbox" name="notify_class" id="notify_class" value="1" {{ $user->notify_class ? 'checked' : '' }} class="sr-only peer">
                    <div class="w-9 h-5 bg-[#141414] border border-[#2e2e2e] rounded-full peer peer-checked:bg-[#3ecf8e] peer-checked:border-[#3ecf8e] after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-[#888888] after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-4 peer-checked:after:bg-[#0f0f0f]"></div>
                </div>
            </label>

            <!-- 2. Modules -->
            <label class="flex items-start justify-between cursor-pointer select-none group py-1">
                <div class="pr-4">
                    <span class="font-medium text-[#ededed] block text-xs group-hover:text-[#3ecf8e] transition-colors">Module Upload Updates</span>
                    <span class="text-[11px] text-[#888888]">Notify me when instructors upload study lesson guides or sub-modules.</span>
                </div>
                <div class="relative shrink-0 mt-0.5">
                    <input type="checkbox" name="notify_module" id="notify_module" value="1" {{ $user->notify_module ? 'checked' : '' }} class="sr-only peer">
                    <div class="w-9 h-5 bg-[#141414] border border-[#2e2e2e] rounded-full peer peer-checked:bg-[#3ecf8e] peer-checked:border-[#3ecf8e] after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-[#888888] after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-4 peer-checked:after:bg-[#0f0f0f]"></div>
                </div>
            </label>

            <!-- 3. Labs -->
            <label class="flex items-start justify-between cursor-pointer select-none group py-1">
                <div class="pr-4">
                    <span class="font-medium text-[#ededed] block text-xs group-hover:text-[#3ecf8e] transition-colors">Laboratory Exercise Actions</span>
                    <span class="text-[11px] text-[#888888]">Notify me when new coding challenge tasks or lab exercises are assigned.</span>
                </div>
                <div class="relative shrink-0 mt-0.5">
                    <input type="checkbox" name="notify_lab" id="notify_lab" value="1" {{ $user->notify_lab ? 'checked' : '' }} class="sr-only peer">
                    <div class="w-9 h-5 bg-[#141414] border border-[#2e2e2e] rounded-full peer peer-checked:bg-[#3ecf8e] peer-checked:border-[#3ecf8e] after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-[#888888] after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-4 peer-checked:after:bg-[#0f0f0f]"></div>
                </div>
            </label>

            <!-- 4. Certificates -->
            <label class="flex items-start justify-between cursor-pointer select-none group py-1">
                <div class="pr-4">
                    <span class="font-medium text-[#ededed] block text-xs group-hover:text-[#3ecf8e] transition-colors">Certificate Accomplishments</span>
                    <span class="text-[11px] text-[#888888]">Notify me when I successfully achieve 100% progress and earn verified certificates.</span>
                </div>
                <div class="relative shrink-0 mt-0.5">
                    <input type="checkbox" name="notify_certificate" id="notify_certificate" value="1" {{ $user->notify_certificate ? 'checked' : '' }} class="sr-only peer">
                    <div class="w-9 h-5 bg-[#141414] border border-[#2e2e2e] rounded-full peer peer-checked:bg-[#3ecf8e] peer-checked:border-[#3ecf8e] after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-[#888888] after:rounded-full after:h-4 after:w-4 after:transition-all peer-checked:after:translate-x-4 peer-checked:after:bg-[#0f0f0f]"></div>
                </div>
            </label>
        </div>

        <div class="flex justify-end pt-4 border-t border-[#232323]">
            <button type="submit" class="px-5 py-2 bg-[#3ecf8e] hover:bg-[#00c573] font-semibold text-xs rounded-full text-[#0f0f0f] transition shadow-none">
                Save Preferences &rarr;
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

    // Settings Theme Card Management
    function updateSettingsThemeCards(activeTheme) {
        const themes = ['system', 'dark', 'light'];
        themes.forEach(t => {
            const card = document.getElementById(`theme-card-${t}`);
            if (!card) return;
            const check = card.querySelector('.theme-check-badge');
            const statusPill = card.querySelector('.theme-status-pill');
            
            if (t === activeTheme) {
                card.classList.add('border-[#3ecf8e]', 'active-theme-card');
                card.classList.remove('border-[#2e2e2e]');
                if (check) {
                    check.classList.remove('hidden');
                    check.classList.add('flex');
                }
                if (statusPill) {
                    statusPill.textContent = 'Active';
                    statusPill.classList.add('text-[#3ecf8e]', 'font-bold');
                    statusPill.classList.remove('text-[#888888]');
                }
            } else {
                card.classList.remove('border-[#3ecf8e]', 'active-theme-card');
                card.classList.add('border-[#2e2e2e]');
                if (check) {
                    check.classList.add('hidden');
                    check.classList.remove('flex');
                }
                if (statusPill) {
                    statusPill.textContent = 'Inactive';
                    statusPill.classList.remove('text-[#3ecf8e]', 'font-bold');
                    statusPill.classList.add('text-[#888888]');
                }
            }
        });

        const indicator = document.getElementById('theme-status-text');
        if (indicator) {
            const labelMap = {
                system: 'Active: System',
                dark: 'Active: Dark Mode',
                light: 'Active: Light Mode'
            };
            indicator.textContent = labelMap[activeTheme] || 'Active';
        }
    }

    function selectTheme(theme) {
        if (window.applyTheme) {
            window.applyTheme(theme);
        } else {
            localStorage.setItem('theme', theme);
            const isDark = theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches);
            if (isDark) {
                document.documentElement.classList.add('dark');
                document.documentElement.classList.remove('light');
            } else {
                document.documentElement.classList.remove('dark');
                document.documentElement.classList.add('light');
            }
        }
        updateSettingsThemeCards(theme);
    }

    // Sync theme on initial load & on custom events
    const initialTheme = localStorage.getItem('theme') || 'system';
    updateSettingsThemeCards(initialTheme);

    window.addEventListener('certicode:themechange', function(e) {
        if (e.detail && e.detail.theme) {
            updateSettingsThemeCards(e.detail.theme);
        }
    });
</script>
@endsection
@endsection

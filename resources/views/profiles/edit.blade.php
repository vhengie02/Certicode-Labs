@extends('layouts.app')

@section('title', 'Edit Profile')
@section('page_header', 'Profile Settings')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="rounded-xl p-8 bg-[#171717] border border-[#2e2e2e]">
        <div class="flex items-center space-x-4 mb-8">
            <div class="h-12 w-12 rounded-[6px] bg-[#141414] flex items-center justify-center text-[#3ecf8e] text-lg font-bold border border-[#2e2e2e] font-mono">
                {{ strtoupper(substr($user->name, 0, 2)) }}
            </div>
            <div>
                <h2 class="text-lg font-bold text-[#ededed]">Account Specifications</h2>
                <p class="text-xs text-[#3ecf8e] mt-0.5 uppercase tracking-wider font-mono font-semibold">{{ $user->role }} Profile Level</p>
                <p class="text-xs text-[#888888] font-mono mt-0.5">{{ $user->email }}</p>
            </div>
        </div>

        <form action="{{ route('profiles.update', $user->id) }}" method="POST" class="space-y-6">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <!-- First Name -->
                <div>
                    <label for="first_name" class="block text-xs font-mono uppercase tracking-wider text-[#a3a3a3] mb-2">First Name</label>
                    <input type="text" name="first_name" id="first_name" required value="{{ old('first_name', $user->first_name) }}"
                        class="w-full px-3.5 py-2.5 bg-[#141414] border border-[#2e2e2e] rounded-[6px] text-xs text-[#ededed] placeholder-[#666666] focus:outline-none focus:border-[#3ecf8e] focus:ring-1 focus:ring-[#3ecf8e]">
                    @error('first_name') <p class="text-red-400 text-xs mt-1 font-mono">{{ $message }}</p> @enderror
                </div>

                <!-- Last Name -->
                <div>
                    <label for="last_name" class="block text-xs font-mono uppercase tracking-wider text-[#a3a3a3] mb-2">Last Name</label>
                    <input type="text" name="last_name" id="last_name" required value="{{ old('last_name', $user->last_name) }}"
                        class="w-full px-3.5 py-2.5 bg-[#141414] border border-[#2e2e2e] rounded-[6px] text-xs text-[#ededed] placeholder-[#666666] focus:outline-none focus:border-[#3ecf8e] focus:ring-1 focus:ring-[#3ecf8e]">
                    @error('last_name') <p class="text-red-400 text-xs mt-1 font-mono">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                <!-- Username -->
                <div>
                    <label for="username" class="block text-xs font-mono uppercase tracking-wider text-[#a3a3a3] mb-2">Username</label>
                    <input type="text" name="username" id="username" required value="{{ old('username', $user->username) }}"
                        class="w-full px-3.5 py-2.5 bg-[#141414] border border-[#2e2e2e] rounded-[6px] text-xs text-[#ededed] placeholder-[#666666] focus:outline-none focus:border-[#3ecf8e] focus:ring-1 focus:ring-[#3ecf8e]">
                    @error('username') <p class="text-red-400 text-xs mt-1 font-mono">{{ $message }}</p> @enderror
                </div>

                <!-- Gender -->
                <div>
                    <label for="gender" class="block text-xs font-mono uppercase tracking-wider text-[#a3a3a3] mb-2">Gender</label>
                    <select name="gender" id="gender"
                        class="w-full px-3.5 py-2.5 bg-[#141414] border border-[#2e2e2e] rounded-[6px] text-xs text-[#ededed] focus:outline-none focus:border-[#3ecf8e] focus:ring-1 focus:ring-[#3ecf8e]">
                        <option value="" disabled {{ is_null($user->gender) ? 'selected' : '' }} class="bg-[#141414] text-[#888888]">Select Gender...</option>
                        <option value="male" {{ $user->gender === 'male' ? 'selected' : '' }} class="bg-[#141414] text-[#ededed]">Male</option>
                        <option value="female" {{ $user->gender === 'female' ? 'selected' : '' }} class="bg-[#141414] text-[#ededed]">Female</option>
                        <option value="other" {{ $user->gender === 'other' ? 'selected' : '' }} class="bg-[#141414] text-[#ededed]">Other / Prefer not to say</option>
                    </select>
                    @error('gender') <p class="text-red-400 text-xs mt-1 font-mono">{{ $message }}</p> @enderror
                </div>
            </div>

            <!-- GitHub Account username link -->
            <div>
                <label for="github_username" class="block text-xs font-mono uppercase tracking-wider text-[#a3a3a3] mb-2 flex items-center">
                    <svg class="w-3.5 h-3.5 mr-1.5 text-[#ededed]" fill="currentColor" viewBox="0 0 24 24"><path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.477 2 2 6.477 2 12c0 4.42 2.865 8.166 6.839 9.489.5.092.682-.217.682-.482 0-.237-.008-.866-.013-1.7-2.782.603-3.369-1.34-3.369-1.34-.454-1.156-1.11-1.464-1.11-1.464-.908-.62.069-.608.069-.608 1.003.07 1.531 1.03 1.531 1.03.892 1.529 2.341 1.087 2.91.831.092-.646.35-1.086.636-1.336-2.22-.253-4.555-1.11-4.555-4.943 0-1.091.39-1.984 1.029-2.683-.103-.253-.446-1.27.098-2.647 0 0 .84-.269 2.75 1.025A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.294 2.747-1.025 2.747-1.025.546 1.377.203 2.394.1 2.647.64.699 1.028 1.592 1.028 2.683 0 3.842-2.339 4.687-4.566 4.935.359.309.678.919.678 1.852 0 1.336-.012 2.415-.012 2.743 0 .267.18.579.688.481C19.137 20.162 22 16.418 22 12c0-5.523-4.477-10-10-10z"></path></svg>
                    Linked GitHub Username
                </label>
                <input type="text" name="github_username" id="github_username" value="{{ old('github_username', $user->github_username) }}" placeholder="e.g. githubusername"
                    class="w-full px-3.5 py-2.5 bg-[#141414] border border-[#2e2e2e] rounded-[6px] text-xs text-[#ededed] placeholder-[#666666] focus:outline-none focus:border-[#3ecf8e] focus:ring-1 focus:ring-[#3ecf8e]">
                <p class="text-[10px] text-[#666666] font-mono mt-1.5">Required to parse your commit metrics and track collaboration activities.</p>
                @error('github_username') <p class="text-red-400 text-xs mt-1 font-mono">{{ $message }}</p> @enderror
            </div>

            <!-- Role select (Only Admin can edit) -->
            <div>
                <label for="role" class="block text-xs font-mono uppercase tracking-wider text-[#a3a3a3] mb-2">Access Role</label>
                @if(auth()->user()->role === 'admin')
                    <select name="role" id="role"
                        class="w-full px-3.5 py-2.5 bg-[#141414] border border-[#2e2e2e] rounded-[6px] text-xs text-[#ededed] focus:outline-none focus:border-[#3ecf8e] focus:ring-1 focus:ring-[#3ecf8e]">
                        <option value="student" {{ $user->role === 'student' ? 'selected' : '' }} class="bg-[#141414] text-[#ededed]">Student</option>
                        <option value="instructor" {{ $user->role === 'instructor' ? 'selected' : '' }} class="bg-[#141414] text-[#ededed]">Instructor</option>
                        <option value="admin" {{ $user->role === 'admin' ? 'selected' : '' }} class="bg-[#141414] text-[#ededed]">Administrator</option>
                    </select>
                @else
                    <input type="text" id="user_role_display" name="role_display" disabled value="{{ ucfirst($user->role) }}"
                        class="w-full px-3.5 py-2.5 bg-[#141414]/50 border border-[#2e2e2e] rounded-[6px] text-xs text-[#666666] font-mono focus:outline-none cursor-not-allowed">
                    <input type="hidden" name="role" value="{{ $user->role }}">
                @endif
                @error('role') <p class="text-red-400 text-xs mt-1 font-mono">{{ $message }}</p> @enderror
            </div>

            <!-- Actions -->
            <div class="flex justify-end space-x-3 pt-6 border-t border-[#232323]">
                @if(auth()->user()->role === 'admin' || auth()->user()->role === 'instructor')
                    <a href="{{ route('students.index') }}" class="px-4 py-2 border border-[#2e2e2e] text-xs font-mono font-medium rounded-[6px] text-[#a3a3a3] bg-[#141414] hover:bg-[#202020] hover:text-[#ededed] transition-colors">
                        &larr; Back to Directory
                    </a>
                @else
                    <a href="{{ route('dashboard') }}" class="px-4 py-2 border border-[#2e2e2e] text-xs font-mono font-medium rounded-[6px] text-[#a3a3a3] bg-[#141414] hover:bg-[#202020] hover:text-[#ededed] transition-colors">
                        Cancel
                    </a>
                @endif
                <button type="submit" class="px-5 py-2 text-xs font-semibold rounded-full text-[#0f0f0f] bg-[#3ecf8e] hover:bg-[#00c573] transition-colors shadow-none">
                    Save Modifications &rarr;
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

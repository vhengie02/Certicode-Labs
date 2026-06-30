@extends('layouts.app')

@section('title', 'Edit Profile')
@section('page_header', 'Profile Settings')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="glass-panel rounded-2xl p-8 border border-slate-800">
        <div class="flex items-center space-x-4 mb-8">
            <div class="h-14 w-14 rounded-2xl bg-gradient-to-tr from-indigo-500 to-violet-600 flex items-center justify-center text-white text-xl font-bold border border-indigo-400/20 shadow-lg shadow-indigo-500/10">
                {{ strtoupper(substr($user->name, 0, 2)) }}
            </div>
            <div>
                <h2 class="text-xl font-extrabold text-white">Account Specifications</h2>
                <p class="text-xs text-indigo-400 mt-1 uppercase tracking-wider font-semibold">{{ $user->role }} Profile Level</p>
                <p class="text-xs text-slate-400 font-mono mt-0.5">{{ $user->email }}</p>
            </div>
        </div>

        <form action="{{ route('profiles.update', $user->id) }}" method="POST" class="space-y-6">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- First Name -->
                <div>
                    <label for="first_name" class="block text-sm font-semibold text-slate-300">First Name</label>
                    <input type="text" name="first_name" id="first_name" required value="{{ old('first_name', $user->first_name) }}"
                        class="mt-2 block w-full px-4 py-3 bg-slate-900 border border-slate-700 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                    @error('first_name') <p class="text-rose-400 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <!-- Last Name -->
                <div>
                    <label for="last_name" class="block text-sm font-semibold text-slate-300">Last Name</label>
                    <input type="text" name="last_name" id="last_name" required value="{{ old('last_name', $user->last_name) }}"
                        class="mt-2 block w-full px-4 py-3 bg-slate-900 border border-slate-700 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                    @error('last_name') <p class="text-rose-400 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Username -->
                <div>
                    <label for="username" class="block text-sm font-semibold text-slate-300">Username</label>
                    <input type="text" name="username" id="username" required value="{{ old('username', $user->username) }}"
                        class="mt-2 block w-full px-4 py-3 bg-slate-900 border border-slate-700 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                    @error('username') <p class="text-rose-400 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <!-- Gender -->
                <div>
                    <label for="gender" class="block text-sm font-semibold text-slate-300">Gender</label>
                    <select name="gender" id="gender"
                        class="mt-2 block w-full px-4 py-3 bg-slate-900 border border-slate-700 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                        <option value="" disabled {{ is_null($user->gender) ? 'selected' : '' }}>Select Gender...</option>
                        <option value="male" {{ $user->gender === 'male' ? 'selected' : '' }}>Male</option>
                        <option value="female" {{ $user->gender === 'female' ? 'selected' : '' }}>Female</option>
                        <option value="other" {{ $user->gender === 'other' ? 'selected' : '' }}>Other / Prefer not to say</option>
                    </select>
                    @error('gender') <p class="text-rose-400 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <!-- GitHub Account username link -->
            <div>
                <label for="github_username" class="block text-sm font-semibold text-slate-300 flex items-center">
                    <svg class="w-4 h-4 mr-1.5 text-indigo-400" fill="currentColor" viewBox="0 0 24 24"><path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.477 2 2 6.477 2 12c0 4.42 2.865 8.166 6.839 9.489.5.092.682-.217.682-.482 0-.237-.008-.866-.013-1.7-2.782.603-3.369-1.34-3.369-1.34-.454-1.156-1.11-1.464-1.11-1.464-.908-.62.069-.608.069-.608 1.003.07 1.531 1.03 1.531 1.03.892 1.529 2.341 1.087 2.91.831.092-.646.35-1.086.636-1.336-2.22-.253-4.555-1.11-4.555-4.943 0-1.091.39-1.984 1.029-2.683-.103-.253-.446-1.27.098-2.647 0 0 .84-.269 2.75 1.025A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.294 2.747-1.025 2.747-1.025.546 1.377.203 2.394.1 2.647.64.699 1.028 1.592 1.028 2.683 0 3.842-2.339 4.687-4.566 4.935.359.309.678.919.678 1.852 0 1.336-.012 2.415-.012 2.743 0 .267.18.579.688.481C19.137 20.162 22 16.418 22 12c0-5.523-4.477-10-10-10z"></path></svg>
                    Linked GitHub Username
                </label>
                <input type="text" name="github_username" id="github_username" value="{{ old('github_username', $user->github_username) }}" placeholder="e.g. githubusername"
                    class="mt-2 block w-full px-4 py-3 bg-slate-900 border border-slate-700 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                <p class="text-xs text-slate-500 mt-1.5">Required to parse your commit metrics and track collaboration activities.</p>
                @error('github_username') <p class="text-rose-400 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <!-- Role select (Only Admin can edit) -->
            <div>
                <label for="role" class="block text-sm font-semibold text-slate-300">Access Role</label>
                @if(auth()->user()->role === 'admin')
                    <select name="role" id="role"
                        class="mt-2 block w-full px-4 py-3 bg-slate-900 border border-slate-700 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                        <option value="student" {{ $user->role === 'student' ? 'selected' : '' }}>Student</option>
                        <option value="instructor" {{ $user->role === 'instructor' ? 'selected' : '' }}>Instructor</option>
                        <option value="admin" {{ $user->role === 'admin' ? 'selected' : '' }}>Administrator</option>
                    </select>
                @else
                    <input type="text" disabled value="{{ ucfirst($user->role) }}"
                        class="mt-2 block w-full px-4 py-3 bg-slate-900/50 border border-slate-800 rounded-xl text-slate-500 font-semibold focus:outline-none cursor-not-allowed">
                    <input type="hidden" name="role" value="{{ $user->role }}">
                @endif
                @error('role') <p class="text-rose-400 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <!-- Actions -->
            <div class="flex justify-end space-x-3 pt-6 border-t border-slate-800/80">
                @if(auth()->user()->role === 'admin' || auth()->user()->role === 'instructor')
                    <a href="{{ route('students.index') }}" class="px-5 py-2.5 border border-slate-700 text-sm font-semibold rounded-xl text-slate-300 bg-slate-800 hover:bg-slate-700 transition-colors">
                        Back to Directory
                    </a>
                @else
                    <a href="{{ route('dashboard') }}" class="px-5 py-2.5 border border-slate-700 text-sm font-semibold rounded-xl text-slate-300 bg-slate-800 hover:bg-slate-700 transition-colors">
                        Cancel
                    </a>
                @endif
                <button type="submit" class="px-5 py-2.5 border border-transparent text-sm font-semibold rounded-xl text-white bg-indigo-600 hover:bg-indigo-700 transition-colors shadow-lg shadow-indigo-600/30">
                    Save Modifications
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

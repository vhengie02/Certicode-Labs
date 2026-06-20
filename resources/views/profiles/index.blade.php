@extends('layouts.app')

@section('title', 'Student Directory')
@section('page_header', 'Student Directory')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-extrabold text-white tracking-tight">Active Students</h1>
            <p class="text-sm text-slate-400 mt-1">Review student profiles, linked accounts, and active lab enrollment records.</p>
        </div>
    </div>

    <!-- Student table index -->
    <div class="glass-panel rounded-2xl border border-slate-800 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-800">
                <thead>
                    <tr class="bg-slate-900/30">
                        <th class="px-6 py-3.5 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Student Name</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Email Address</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">GitHub Username</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Sessions Started</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Registration Date</th>
                        <th class="px-6 py-3.5 text-right text-xs font-semibold text-slate-400 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800 bg-transparent text-slate-300">
                    @forelse($students as $student)
                        <tr>
                            <!-- Name -->
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-bold text-white flex items-center space-x-3">
                                <div class="h-9 w-9 rounded-full bg-indigo-500/10 flex items-center justify-center text-indigo-400 border border-indigo-500/20 font-bold">
                                    {{ strtoupper(substr($student->name, 0, 2)) }}
                                </div>
                                <span>{{ $student->name }}</span>
                            </td>

                            <!-- Email -->
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-300 font-medium">
                                {{ $student->email }}
                            </td>

                            <!-- GitHub Account -->
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                @if($student->github_username)
                                    <a href="https://github.com/{{ $student->github_username }}" target="_blank" class="inline-flex items-center text-indigo-400 hover:text-indigo-300 transition-colors">
                                        <svg class="w-4 h-4 mr-1.5" fill="currentColor" viewBox="0 0 24 24"><path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.477 2 2 6.477 2 12c0 4.42 2.865 8.166 6.839 9.489.5.092.682-.217.682-.482 0-.237-.008-.866-.013-1.7-2.782.603-3.369-1.34-3.369-1.34-.454-1.156-1.11-1.464-1.11-1.464-.908-.62.069-.608.069-.608 1.003.07 1.531 1.03 1.531 1.03.892 1.529 2.341 1.087 2.91.831.092-.646.35-1.086.636-1.336-2.22-.253-4.555-1.11-4.555-4.943 0-1.091.39-1.984 1.029-2.683-.103-.253-.446-1.27.098-2.647 0 0 .84-.269 2.75 1.025A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.294 2.747-1.025 2.747-1.025.546 1.377.203 2.394.1 2.647.64.699 1.028 1.592 1.028 2.683 0 3.842-2.339 4.687-4.566 4.935.359.309.678.919.678 1.852 0 1.336-.012 2.415-.012 2.743 0 .267.18.579.688.481C19.137 20.162 22 16.418 22 12c0-5.523-4.477-10-10-10z"></path></svg>
                                        {{ $student->github_username }}
                                    </a>
                                @else
                                    <span class="text-slate-500 italic">No account linked</span>
                                @endif
                            </td>

                            <!-- Sessions Started count -->
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-400 font-semibold font-mono">
                                {{ $student->labSessions()->count() }} sessions
                            </td>

                            <!-- Registration Date -->
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-400 font-medium">
                                {{ $student->created_at->format('M d, Y') }}
                            </td>

                            <!-- Action buttons -->
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-semibold space-x-2">
                                <a href="{{ route('profiles.edit', $student->id) }}" class="inline-flex items-center px-3 py-1.5 border border-slate-700 text-xs font-semibold rounded-lg text-slate-300 bg-slate-800 hover:bg-slate-700 transition-colors">
                                    Edit
                                </a>
                                @if(auth()->user()->role === 'admin')
                                    <form action="{{ route('profiles.destroy', $student->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this student profile?');" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="inline-flex items-center px-3 py-1.5 border border-rose-500/20 text-xs font-semibold rounded-lg text-rose-400 bg-rose-500/5 hover:bg-rose-500/10 transition-colors">
                                            Delete
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-sm text-slate-500">
                                No registered students found in this course catalog.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

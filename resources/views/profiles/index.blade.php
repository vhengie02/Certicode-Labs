@extends('layouts.app')

@section('title', 'Student Directory')
@section('page_header', 'Student Directory')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold text-[#ededed] tracking-tight">Active Students</h1>
            <p class="text-xs text-[#888888] font-mono mt-1">Review student profiles, linked telemetry accounts, and active enrollments.</p>
        </div>
    </div>

    <!-- Student table index -->
    <div class="rounded-xl border border-[#2e2e2e] bg-[#171717] overflow-hidden">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-[#232323]">
                <thead>
                    <tr class="bg-[#141414]">
                        <th class="px-6 py-3.5 text-left text-[11px] font-mono font-semibold text-[#888888] uppercase tracking-wider">Student Name</th>
                        <th class="px-6 py-3.5 text-left text-[11px] font-mono font-semibold text-[#888888] uppercase tracking-wider">Email Address</th>
                        <th class="px-6 py-3.5 text-left text-[11px] font-mono font-semibold text-[#888888] uppercase tracking-wider">GitHub Username</th>
                        <th class="px-6 py-3.5 text-left text-[11px] font-mono font-semibold text-[#888888] uppercase tracking-wider">Sessions Started</th>
                        <th class="px-6 py-3.5 text-left text-[11px] font-mono font-semibold text-[#888888] uppercase tracking-wider">Registration Date</th>
                        <th class="px-6 py-3.5 text-right text-[11px] font-mono font-semibold text-[#888888] uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#232323] bg-transparent text-[#ededed]">
                    @forelse($students as $student)
                        <tr>
                            <!-- Name -->
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-[#ededed] flex items-center space-x-3">
                                <div class="h-8 w-8 rounded-[6px] bg-[#141414] flex items-center justify-center text-[#3ecf8e] border border-[#2e2e2e] font-mono font-bold text-xs">
                                    {{ strtoupper(substr($student->name, 0, 2)) }}
                                </div>
                                <span>{{ $student->name }}</span>
                            </td>

                            <!-- Email -->
                            <td class="px-6 py-4 whitespace-nowrap text-xs text-[#a3a3a3] font-mono">
                                {{ $student->email }}
                            </td>

                            <!-- GitHub Account -->
                            <td class="px-6 py-4 whitespace-nowrap text-xs font-mono">
                                @if($student->github_username)
                                    <a href="https://github.com/{{ $student->github_username }}" target="_blank" class="inline-flex items-center text-[#3ecf8e] hover:text-[#00c573] transition-colors">
                                        <svg class="w-3.5 h-3.5 mr-1.5 text-[#ededed]" fill="currentColor" viewBox="0 0 24 24"><path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.477 2 2 6.477 2 12c0 4.42 2.865 8.166 6.839 9.489.5.092.682-.217.682-.482 0-.237-.008-.866-.013-1.7-2.782.603-3.369-1.34-3.369-1.34-.454-1.156-1.11-1.464-1.11-1.464-.908-.62.069-.608.069-.608 1.003.07 1.531 1.03 1.531 1.03.892 1.529 2.341 1.087 2.91.831.092-.646.35-1.086.636-1.336-2.22-.253-4.555-1.11-4.555-4.943 0-1.091.39-1.984 1.029-2.683-.103-.253-.446-1.27.098-2.647 0 0 .84-.269 2.75 1.025A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.294 2.747-1.025 2.747-1.025.546 1.377.203 2.394.1 2.647.64.699 1.028 1.592 1.028 2.683 0 3.842-2.339 4.687-4.566 4.935.359.309.678.919.678 1.852 0 1.336-.012 2.415-.012 2.743 0 .267.18.579.688.481C19.137 20.162 22 16.418 22 12c0-5.523-4.477-10-10-10z"></path></svg>
                                        {{ $student->github_username }}
                                    </a>
                                @else
                                    <span class="text-[#666666] italic">Not linked</span>
                                @endif
                            </td>

                            <!-- Sessions Started count -->
                            <td class="px-6 py-4 whitespace-nowrap text-xs text-[#a3a3a3] font-mono">
                                {{ $student->labSessions()->count() }} sessions
                            </td>

                            <!-- Registration Date -->
                            <td class="px-6 py-4 whitespace-nowrap text-xs text-[#888888] font-mono">
                                {{ $student->created_at->format('M d, Y') }}
                            </td>

                            <!-- Action buttons -->
                            <td class="px-6 py-4 whitespace-nowrap text-right text-xs font-mono space-x-2">
                                <a href="{{ route('profiles.edit', $student->id) }}" class="inline-flex items-center px-3 py-1 border border-[#2e2e2e] text-xs rounded-[6px] text-[#ededed] bg-[#141414] hover:bg-[#202020] hover:border-[#383838] transition-colors">
                                    Edit
                                </a>
                                @if(auth()->user()->role === 'admin')
                                    <form action="{{ route('profiles.destroy', $student->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this student profile?');" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="inline-flex items-center px-3 py-1 border border-red-500/20 text-xs rounded-[6px] text-red-400 bg-[#141414] hover:bg-red-500/10 transition-colors">
                                            Delete
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center text-xs text-[#666666] font-mono">
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

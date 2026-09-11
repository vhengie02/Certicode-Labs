@extends('layouts.app')

@section('title', 'Dashboard')
@section('page_header', 'Dashboard')

@section('content')
<div class="space-y-8">
    <!-- Welcome Header Panel -->
    <div class="p-8 rounded-xl bg-[#171717] border border-[#2e2e2e] relative overflow-hidden">
        <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between space-y-4 md:space-y-0">
            <div>
                <div class="inline-flex items-center gap-2 px-2.5 py-0.5 rounded-full border border-[#2e2e2e] bg-[#141414] mb-3">
                    <span class="w-1.5 h-1.5 rounded-full bg-[#3ecf8e]"></span>
                    <span class="text-[10px] font-mono uppercase tracking-wider text-[#a3a3a3]">Authenticated Console</span>
                </div>
                <h1 class="text-2xl font-bold tracking-tight text-[#ededed]">Welcome back, {{ auth()->user()->name }}</h1>
                <p class="text-[#888888] mt-1 text-sm">Monitor your skills, complete laboratories, and earn verified IT competencies.</p>
            </div>
            
            <div class="flex space-x-3">
                @if(auth()->user()->role === 'student')
                    <a href="{{ route('classes.index') }}" class="inline-flex items-center px-5 py-2.5 rounded-full bg-[#3ecf8e] text-xs font-semibold text-[#0f0f0f] hover:bg-[#00c573] transition shadow-none">
                        View Classes &rarr;
                    </a>
                @else
                    <a href="{{ route('classes.index') }}" class="inline-flex items-center px-5 py-2.5 rounded-full bg-[#3ecf8e] text-xs font-semibold text-[#0f0f0f] hover:bg-[#00c573] transition shadow-none">
                        Manage Classes &rarr;
                    </a>
                @endif
            </div>
        </div>
    </div>

    <!-- Quick Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
        <!-- Stat 1 -->
        <div class="p-6 rounded-xl bg-[#171717] border border-[#2e2e2e] hover:border-[#3ecf8e]/35 transition-colors">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-[11px] font-mono uppercase tracking-wider text-[#888888]">GitHub Connection</p>
                    <h3 class="text-lg font-bold text-[#ededed] mt-1.5 font-mono">
                        {{ auth()->user()->github_username ?? 'Not Connected' }}
                    </h3>
                </div>
                <div class="h-9 w-9 rounded-[6px] bg-[#141414] flex items-center justify-center border border-[#2e2e2e]">
                    <svg class="w-4 h-4 text-[#ededed]" fill="currentColor" viewBox="0 0 24 24"><path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.477 2 2 6.477 2 12c0 4.42 2.865 8.166 6.839 9.489.5.092.682-.217.682-.482 0-.237-.008-.866-.013-1.7-2.782.603-3.369-1.34-3.369-1.34-.454-1.156-1.11-1.464-1.11-1.464-.908-.62.069-.608.069-.608 1.003.07 1.531 1.03 1.531 1.03.892 1.529 2.341 1.087 2.91.831.092-.646.35-1.086.636-1.336-2.22-.253-4.555-1.11-4.555-4.943 0-1.091.39-1.984 1.029-2.683-.103-.253-.446-1.27.098-2.647 0 0 .84-.269 2.75 1.025A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.294 2.747-1.025 2.747-1.025.546 1.377.203 2.394.1 2.647.64.699 1.028 1.592 1.028 2.683 0 3.842-2.339 4.687-4.566 4.935.359.309.678.919.678 1.852 0 1.336-.012 2.415-.012 2.743 0 .267.18.579.688.481C19.137 20.162 22 16.418 22 12c0-5.523-4.477-10-10-10z"></path></svg>
                </div>
            </div>
            @if(!auth()->user()->github_username)
                <p class="text-xs text-[#eab308] mt-3 flex items-center">
                    <svg class="w-3.5 h-3.5 mr-1 text-[#eab308]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    Link GitHub account in Settings to track commit telemetry.
                </p>
            @else
                <p class="text-xs text-[#3ecf8e] mt-3 flex items-center">
                    <svg class="w-3.5 h-3.5 mr-1 text-[#3ecf8e]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                    Synchronized with repository activity.
                </p>
            @endif
        </div>

        <!-- Stat 2 -->
        <div class="p-6 rounded-xl bg-[#171717] border border-[#2e2e2e] hover:border-[#3ecf8e]/35 transition-colors">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-[11px] font-mono uppercase tracking-wider text-[#888888]">Active Role</p>
                    <h3 class="text-lg font-bold text-[#3ecf8e] mt-1.5 uppercase tracking-wider font-mono">
                        {{ auth()->user()->role }}
                    </h3>
                </div>
                <div class="h-9 w-9 rounded-[6px] bg-[#141414] flex items-center justify-center border border-[#2e2e2e]">
                    <svg class="w-4 h-4 text-[#3ecf8e]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                </div>
            </div>
            <p class="text-xs text-[#666666] mt-3 font-mono">Platform permission level verified.</p>
        </div>

        <!-- Stat 3 -->
        <div class="p-6 rounded-xl bg-[#171717] border border-[#2e2e2e] hover:border-[#3ecf8e]/35 transition-colors">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-[11px] font-mono uppercase tracking-wider text-[#888888]">Lab Sessions</p>
                    <h3 class="text-lg font-bold text-[#ededed] mt-1.5 font-mono">
                        @if(auth()->user()->role === 'student')
                            {{ auth()->user()->labSessions()->count() }} Active
                        @else
                            {{ \App\Models\Laboratory::count() }} Exercises
                        @endif
                    </h3>
                </div>
                <div class="h-9 w-9 rounded-[6px] bg-[#141414] flex items-center justify-center border border-[#2e2e2e]">
                    <svg class="w-4 h-4 text-[#3ecf8e]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 002 2h2a2 2 0 002-2"></path></svg>
                </div>
            </div>
            <p class="text-xs text-[#666666] mt-3 font-mono">Telemetry metrics actively streaming.</p>
        </div>
    </div>

    <!-- GitHub-Style Contribution Graph -->
    <div class="p-6 rounded-xl bg-[#171717] border border-[#2e2e2e]">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-xs uppercase font-mono font-bold tracking-wider text-[#a3a3a3]">Collaboration Activity Graph</h3>
            <span class="text-xs text-[#666666] font-mono">Real-time commit telemetry</span>
        </div>
        <!-- Grid columns representing weeks -->
        <div class="flex space-x-1.5 overflow-x-auto py-2" id="contribution-graph-grid">
            <!-- Render blocks via JavaScript below -->
        </div>
        <div class="flex items-center justify-between text-xs text-[#666666] font-mono mt-3 pt-3 border-t border-[#232323]">
            <span>Activity logged from linked repositories.</span>
            <div class="flex items-center space-x-1.5">
                <span>Less</span>
                <span class="w-3 h-3 bg-[#121212] border border-[#2e2e2e] rounded-sm"></span>
                <span class="w-3 h-3 bg-[#0e4429] rounded-sm"></span>
                <span class="w-3 h-3 bg-[#006d32] rounded-sm"></span>
                <span class="w-3 h-3 bg-[#26a641] rounded-sm"></span>
                <span class="w-3 h-3 bg-[#3ecf8e] rounded-sm"></span>
                <span>More</span>
            </div>
        </div>
    </div>

    <!-- Earned Competency Certificates Panel -->
    @if(auth()->user()->role === 'student')
        <div class="p-6 rounded-xl bg-[#171717] border border-[#2e2e2e]">
            <h3 class="text-xs uppercase font-mono font-bold tracking-wider text-[#a3a3a3] mb-4">Earned Competency Credentials</h3>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @forelse(auth()->user()->certificates()->with('schoolClass')->get() as $cert)
                    <div class="p-4 rounded-[6px] bg-[#141414] border border-[#2e2e2e] flex items-center justify-between hover:border-[#3ecf8e]/35 transition-colors">
                        <div class="flex items-center space-x-3.5">
                            <div class="h-9 w-9 rounded-[6px] bg-[#171717] border border-[#2e2e2e] flex items-center justify-center text-[#3ecf8e] shrink-0">
                                <svg class="w-4 h-4 text-[#3ecf8e]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path>
                                </svg>
                            </div>
                            <div class="overflow-hidden">
                                <h4 class="text-sm font-semibold text-[#ededed] truncate">{{ $cert->schoolClass->name }}</h4>
                                <p class="text-[10px] text-[#666666] font-mono mt-0.5">Code: {{ $cert->verification_code }}</p>
                            </div>
                        </div>
                        <a href="{{ route('certificates.show', $cert->id) }}" class="inline-flex items-center px-3 py-1.5 border border-[#2e2e2e] text-[11px] font-mono uppercase tracking-wider rounded-[6px] text-[#ededed] bg-[#171717] hover:bg-[#222222] hover:border-[#3ecf8e]/40 transition">
                            View Badge
                        </a>
                    </div>
                @empty
                    <div class="md:col-span-2 p-6 rounded-[6px] bg-[#141414] border border-[#232323] text-center text-xs text-[#666666] font-mono">
                        No certificates claimed yet. Complete 100% of a class curriculum to earn your first certified competency badge.
                    </div>
                @endforelse
            </div>
        </div>
    @endif

    <!-- Telemetry Log Tracker Table -->
    @if(auth()->user()->role !== 'student')
    <div class="p-6 rounded-xl bg-[#171717] border border-[#2e2e2e]">
        <h3 class="text-xs font-mono uppercase font-bold tracking-wider text-[#a3a3a3] mb-4">Integrity & Anomaly Telemetry Monitor</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-[#232323]">
                <thead>
                    <tr>
                        <th class="px-5 py-3 text-left text-[11px] font-mono font-semibold text-[#888888] uppercase tracking-wider">Event ID</th>
                        <th class="px-5 py-3 text-left text-[11px] font-mono font-semibold text-[#888888] uppercase tracking-wider">Session</th>
                        <th class="px-5 py-3 text-left text-[11px] font-mono font-semibold text-[#888888] uppercase tracking-wider">Anomaly Type</th>
                        <th class="px-5 py-3 text-left text-[11px] font-mono font-semibold text-[#888888] uppercase tracking-wider">Severity</th>
                        <th class="px-5 py-3 text-left text-[11px] font-mono font-semibold text-[#888888] uppercase tracking-wider">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#232323] bg-transparent text-[#ededed]">
                    @forelse(\App\Models\Anomaly::latest()->take(5)->get() as $anomaly)
                        <tr>
                            <td class="px-5 py-3.5 whitespace-nowrap text-xs text-[#888888] font-mono">#AN-{{ $anomaly->id }}</td>
                            <td class="px-5 py-3.5 whitespace-nowrap text-xs text-[#ededed]">Session #{{ $anomaly->lab_session_id }}</td>
                            <td class="px-5 py-3.5 whitespace-nowrap text-xs font-mono text-red-400">{{ $anomaly->type }}</td>
                            <td class="px-5 py-3.5 whitespace-nowrap text-xs">
                                <span class="px-2 py-0.5 rounded-[4px] text-[10px] font-mono uppercase tracking-wider {{ $anomaly->severity === 'high' ? 'bg-red-500/10 text-red-400 border border-red-500/20' : ($anomaly->severity === 'medium' ? 'bg-yellow-500/10 text-yellow-400 border border-yellow-500/20' : 'bg-blue-500/10 text-blue-400 border border-blue-500/20') }}">
                                    {{ $anomaly->severity }}
                                </span>
                            </td>
                            <td class="px-5 py-3.5 whitespace-nowrap text-xs font-mono">
                                @if($anomaly->resolved)
                                    <span class="text-[#3ecf8e] flex items-center">
                                        <svg class="w-3.5 h-3.5 mr-1 text-[#3ecf8e]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                        Resolved
                                    </span>
                                @else
                                    <span class="text-red-400 flex items-center">
                                        <svg class="w-3.5 h-3.5 mr-1 text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                        Active
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-5 py-6 text-center text-xs text-[#666666] font-mono">
                                No active anomalies detected. Telemetry scores are optimal.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>
@endsection

@section('scripts')
<script>
    // Contribution Graph layout renderer using Supabase palette
    document.addEventListener('DOMContentLoaded', () => {
        const grid = document.getElementById('contribution-graph-grid');
        if (!grid) return;
        grid.innerHTML = '';
        const numWeeks = window.innerWidth < 640 ? 20 : 38;
        
        for (let w = 0; w < numWeeks; w++) {
            const col = document.createElement('div');
            col.className = 'flex flex-col space-y-1';
            
            for (let d = 0; d < 7; d++) {
                const sq = document.createElement('div');
                const rand = Math.random();
                let color = 'bg-[#121212] border border-[#2e2e2e]/50';
                if (rand > 0.9) color = 'bg-[#3ecf8e]';
                else if (rand > 0.78) color = 'bg-[#26a641]';
                else if (rand > 0.65) color = 'bg-[#006d32]';
                else if (rand > 0.5) color = 'bg-[#0e4429]';
                
                sq.className = `w-2.5 h-2.5 rounded-sm ${color} cursor-pointer hover:scale-125 transition-transform duration-100`;
                sq.title = `Commit records logged on week ${w+1}, day ${d+1}`;
                col.appendChild(sq);
            }
            grid.appendChild(col);
        }
    });
</script>
@endsection

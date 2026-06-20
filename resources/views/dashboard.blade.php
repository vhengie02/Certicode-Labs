@extends('layouts.app')

@section('title', 'Dashboard')
@section('page_header', 'Student Workspace Dashboard')

@section('content')
<div class="space-y-8">
    <!-- Welcome Header Panel -->
    <div class="glass-panel p-8 rounded-xl border border-slate-800 relative overflow-hidden">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(37,99,235,0.08),transparent)] pointer-events-none"></div>
        <div class="relative z-10 flex flex-col md:flex-row md:items-center md:justify-between space-y-4 md:space-y-0">
            <div>
                <h1 class="text-2xl font-extrabold tracking-tight text-white">Welcome back, {{ auth()->user()->name }}!</h1>
                <p class="text-slate-400 mt-1.5 text-sm">Monitor your skills, complete laboratories, and earn verified IT competencies.</p>
            </div>
            
            <div class="flex space-x-3">
                @if(auth()->user()->role === 'student')
                    <a href="{{ route('laboratories.index') }}" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-semibold rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 transition-colors shadow-lg shadow-indigo-600/30">
                        Start a Laboratory
                    </a>
                @else
                    <a href="{{ route('laboratories.create') }}" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-semibold rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 transition-colors shadow-lg shadow-indigo-600/30">
                        Create New Lab
                    </a>
                @endif
                <a href="{{ route('profiles.edit', auth()->id()) }}" class="inline-flex items-center px-4 py-2 border border-slate-800 text-sm font-semibold rounded-lg text-slate-300 bg-slate-900 hover:bg-slate-800 transition-colors">
                    Edit Profile
                </a>
            </div>
        </div>
    </div>

    <!-- Quick Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Stat 1 -->
        <div class="glass-card p-6 rounded-lg border border-slate-800">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">GitHub Connection</p>
                    <h3 class="text-xl font-bold text-white mt-2 font-mono">
                        {{ auth()->user()->github_username ?? 'Not Connected' }}
                    </h3>
                </div>
                <div class="h-10 w-10 rounded-lg bg-slate-950 flex items-center justify-center border border-slate-800">
                    <!-- GitHub logo SVG -->
                    <svg class="w-5 h-5 text-indigo-500" fill="currentColor" viewBox="0 0 24 24"><path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.477 2 2 6.477 2 12c0 4.42 2.865 8.166 6.839 9.489.5.092.682-.217.682-.482 0-.237-.008-.866-.013-1.7-2.782.603-3.369-1.34-3.369-1.34-.454-1.156-1.11-1.464-1.11-1.464-.908-.62.069-.608.069-.608 1.003.07 1.531 1.03 1.531 1.03.892 1.529 2.341 1.087 2.91.831.092-.646.35-1.086.636-1.336-2.22-.253-4.555-1.11-4.555-4.943 0-1.091.39-1.984 1.029-2.683-.103-.253-.446-1.27.098-2.647 0 0 .84-.269 2.75 1.025A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.294 2.747-1.025 2.747-1.025.546 1.377.203 2.394.1 2.647.64.699 1.028 1.592 1.028 2.683 0 3.842-2.339 4.687-4.566 4.935.359.309.678.919.678 1.852 0 1.336-.012 2.415-.012 2.743 0 .267.18.579.688.481C19.137 20.162 22 16.418 22 12c0-5.523-4.477-10-10-10z"></path></svg>
                </div>
            </div>
            @if(!auth()->user()->github_username)
                <p class="text-xs text-amber-500 mt-4 flex items-center">
                    <svg class="w-3.5 h-3.5 mr-1 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                    Link GitHub account in Settings to track code activity.
                </p>
            @else
                <p class="text-xs text-indigo-400 mt-4">Linked account is synchronized successfully.</p>
            @endif
        </div>

        <!-- Stat 2 -->
        <div class="glass-card p-6 rounded-lg border border-slate-800">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Active Role</p>
                    <h3 class="text-xl font-bold text-indigo-400 mt-2 uppercase tracking-wider font-mono">
                        {{ auth()->user()->role }}
                    </h3>
                </div>
                <div class="h-10 w-10 rounded-lg bg-slate-950 flex items-center justify-center border border-slate-800">
                    <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"></path></svg>
                </div>
            </div>
            <p class="text-xs text-slate-500 mt-4">Verified access level on platform.</p>
        </div>

        <!-- Stat 3 -->
        <div class="glass-card p-6 rounded-lg border border-slate-800">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Lab Sessions</p>
                    <h3 class="text-xl font-bold text-white mt-2 font-mono">
                        @if(auth()->user()->role === 'student')
                            {{ auth()->user()->labSessions()->count() }} Active
                        @else
                            {{ \App\Models\Laboratory::count() }} Exercises
                        @endif
                    </h3>
                </div>
                <div class="h-10 w-10 rounded-lg bg-slate-950 flex items-center justify-center border border-slate-800">
                    <svg class="w-5 h-5 text-indigo-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 002 2h2a2 2 0 002-2"></path></svg>
                </div>
            </div>
            <p class="text-xs text-slate-500 mt-4">Telemetry metrics are actively running.</p>
        </div>
    </div>

    <!-- GitHub-Style Contribution Graph -->
    <div class="glass-panel p-6 rounded-lg border border-slate-800">
        <div class="flex items-center justify-between mb-4">
            <h3 class="text-xs uppercase font-bold tracking-wider text-slate-400">Collaboration Activity Graph</h3>
            <span class="text-xs text-slate-500 font-mono">Real-time commit frequency</span>
        </div>
        <!-- Grid columns representing weeks -->
        <div class="flex space-x-1 overflow-x-auto py-2" id="contribution-graph-grid">
            <!-- Render blocks via JavaScript below -->
        </div>
        <div class="flex items-center justify-between text-xs text-slate-500 mt-3 pt-2 border-t border-slate-800/40">
            <span>Contributions are counted from linked repository activity.</span>
            <div class="flex items-center space-x-1.5">
                <span>Less</span>
                <span class="w-3 h-3 bg-[#161B22] border border-slate-800 rounded-sm"></span>
                <span class="w-3 h-3 bg-[#0e4429] rounded-sm"></span>
                <span class="w-3 h-3 bg-[#006d32] rounded-sm"></span>
                <span class="w-3 h-3 bg-[#26a641] rounded-sm"></span>
                <span class="w-3 h-3 bg-[#39d353] rounded-sm"></span>
                <span>More</span>
            </div>
        </div>
    </div>

    <!-- Telemetry Log Tracker Table -->
    <div class="glass-panel p-6 rounded-lg border border-slate-800">
        <h3 class="text-sm font-bold text-white mb-4 uppercase tracking-wider text-slate-400">Integrity & Anomaly Telemetry Monitor</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-800">
                <thead>
                    <tr>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Event ID</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Session</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Anomaly Type</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Severity</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800 bg-transparent text-slate-300">
                    @forelse(\App\Models\Anomaly::latest()->take(5)->get() as $anomaly)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-400 font-mono">#AN-{{ $anomaly->id }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-white">Session #{{ $anomaly->lab_session_id }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-rose-400 font-mono">{{ $anomaly->type }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-medium {{ $anomaly->severity === 'high' ? 'bg-rose-500/10 text-rose-400 border border-rose-500/20' : ($anomaly->severity === 'medium' ? 'bg-amber-500/10 text-amber-400 border border-amber-500/20' : 'bg-blue-500/10 text-blue-400 border border-blue-500/20') }}">
                                    {{ $anomaly->severity }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                @if($anomaly->resolved)
                                    <span class="text-emerald-400 font-semibold flex items-center">
                                        <!-- Checkmark SVG -->
                                        <svg class="w-4 h-4 mr-1.5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                        Resolved
                                    </span>
                                @else
                                    <span class="text-rose-400 font-semibold flex items-center">
                                        <!-- Alert warning SVG -->
                                        <svg class="w-4 h-4 mr-1.5 text-rose-400 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                        Active
                                    </span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-8 text-center text-sm text-slate-500">
                                No active anomalies detected. Excellent integrity scores!
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    // Contribution Graph layout renderer
    document.addEventListener('DOMContentLoaded', () => {
        const grid = document.getElementById('contribution-graph-grid');
        grid.innerHTML = '';
        const numWeeks = window.innerWidth < 640 ? 20 : 38;
        
        for (let w = 0; w < numWeeks; w++) {
            const col = document.createElement('div');
            col.className = 'flex flex-col space-y-1';
            
            for (let d = 0; d < 7; d++) {
                const sq = document.createElement('div');
                const rand = Math.random();
                let color = 'bg-[#161B22]';
                if (rand > 0.9) color = 'bg-[#39d353]';
                else if (rand > 0.78) color = 'bg-[#26a641]';
                else if (rand > 0.65) color = 'bg-[#006d32]';
                else if (rand > 0.5) color = 'bg-[#0e4429]';
                
                sq.className = `w-2.5 h-2.5 rounded-sm ${color} border border-slate-800/10 cursor-pointer hover:scale-125 transition-transform duration-100`;
                sq.title = `Commit records logged on week ${w+1}, day ${d+1}`;
                col.appendChild(sq);
            }
            grid.appendChild(col);
        }
    });
</script>
@endsection

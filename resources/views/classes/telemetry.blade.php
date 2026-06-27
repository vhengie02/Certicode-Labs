@extends('layouts.app')

@section('title', 'Telemetry & Integrity Dashboard')
@section('page_header', $class->name . ' - Telemetry Monitor')

@section('content')
<div class="max-w-7xl mx-auto space-y-6">
    <!-- Back to Class View and Header Actions -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <a href="{{ route('classes.show', $class->id) }}" class="inline-flex items-center text-xs font-semibold text-slate-450 hover:text-white transition-colors">
            <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
            Back to Course Syllabus
        </a>
    </div>

    <!-- Stats Grid -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Stat 1 -->
        <div class="glass-panel p-6 rounded-xl border border-slate-800">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Total Active Sessions</p>
                    <h3 class="text-2xl font-bold text-white mt-2 font-mono">
                        {{ $sessions->where('status', 'in_progress')->count() }}
                    </h3>
                </div>
                <div class="h-10 w-10 rounded-lg bg-slate-950 flex items-center justify-center border border-slate-800">
                    <svg class="w-5 h-5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
            </div>
            <p class="text-xs text-slate-500 mt-4">Active coding workspaces right now.</p>
        </div>

        <!-- Stat 2 -->
        <div class="glass-panel p-6 rounded-xl border border-slate-800">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Active Anomalies</p>
                    <h3 class="text-2xl font-bold mt-2 font-mono {{ $anomalies->where('resolved', false)->count() > 0 ? 'text-rose-400' : 'text-white' }}">
                        {{ $anomalies->where('resolved', false)->count() }}
                    </h3>
                </div>
                <div class="h-10 w-10 rounded-lg bg-slate-950 flex items-center justify-center border border-slate-800">
                    <svg class="w-5 h-5 {{ $anomalies->where('resolved', false)->count() > 0 ? 'text-rose-500 animate-pulse' : 'text-slate-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                </div>
            </div>
            <p class="text-xs text-slate-500 mt-4">Integrity alerts flagged by telemetry core.</p>
        </div>

        <!-- Stat 3 -->
        <div class="glass-panel p-6 rounded-xl border border-slate-800">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold text-slate-400 uppercase tracking-wider">Completed Sessions</p>
                    <h3 class="text-2xl font-bold text-white mt-2 font-mono">
                        {{ $sessions->where('status', 'completed')->count() }}
                    </h3>
                </div>
                <div class="h-10 w-10 rounded-lg bg-slate-950 flex items-center justify-center border border-slate-800">
                    <svg class="w-5 h-5 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
            </div>
            <p class="text-xs text-slate-500 mt-4">Finished and successfully graded exercises.</p>
        </div>
    </div>

    <!-- Active Integrity & Anomaly Logs -->
    <div class="glass-panel p-6 rounded-xl border border-slate-800">
        <h3 class="text-sm font-bold text-white mb-4 uppercase tracking-wider text-slate-400">Integrity Violation Alerts</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-800">
                <thead>
                    <tr>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Student</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Exercise</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Anomaly Type</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Severity</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Description</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Time</th>
                        <th class="px-6 py-3.5 text-right text-xs font-semibold text-slate-400 uppercase tracking-wider">Status / Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800 bg-transparent text-slate-300">
                    @forelse($anomalies as $anomaly)
                        <tr class="{{ !$anomaly->resolved ? 'bg-rose-500/[0.02]' : '' }}">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-white font-medium">
                                {{ $anomaly->labSession->user->name }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-300">
                                {{ $anomaly->labSession->laboratory->title }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold font-mono text-rose-400">
                                {{ $anomaly->type }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <span class="px-2.5 py-0.5 rounded-full text-xs font-medium {{ $anomaly->severity === 'high' ? 'bg-rose-500/10 text-rose-400 border border-rose-500/20' : ($anomaly->severity === 'medium' ? 'bg-amber-500/10 text-amber-400 border border-amber-500/20' : 'bg-blue-500/10 text-blue-400 border border-blue-500/20') }}">
                                    {{ $anomaly->severity }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-xs text-slate-400 leading-normal max-w-xs truncate" title="{{ $anomaly->description }}">
                                {{ $anomaly->description }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-xs text-slate-500 font-mono">
                                {{ $anomaly->created_at->diffForHumans() }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                @if($anomaly->resolved)
                                    <span class="text-emerald-400 font-semibold flex items-center justify-end">
                                        <svg class="w-4 h-4 mr-1 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                        Resolved
                                    </span>
                                @else
                                    <form action="{{ route('anomalies.resolve', $anomaly->id) }}" method="POST" class="inline-block m-0">
                                        @csrf
                                        <button type="submit" class="px-3 py-1 bg-slate-900 border border-slate-800 hover:border-emerald-500/40 text-xs font-semibold rounded text-slate-300 hover:text-white transition">
                                            Resolve Anomaly
                                        </button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-8 text-center text-sm text-slate-500">
                                No anomalies logged in this class curriculum. Excellent integrity scores!
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <!-- Active and Completed Lab Workspaces sessions -->
    <div class="glass-panel p-6 rounded-xl border border-slate-800">
        <h3 class="text-sm font-bold text-white mb-4 uppercase tracking-wider text-slate-400">Class Workspace Sessions</h3>
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-slate-800">
                <thead>
                    <tr>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Session ID</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Student</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Laboratory Exercise</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Status</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Performance Score</th>
                        <th class="px-6 py-3.5 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Timing Info</th>
                        <th class="px-6 py-3.5 text-right text-xs font-semibold text-slate-400 uppercase tracking-wider">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800 bg-transparent text-slate-300">
                    @forelse($sessions as $sess)
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-450 font-mono">#SESS-{{ $sess->id }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-white font-medium">{{ $sess->user->name }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-slate-350">{{ $sess->laboratory->title }}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <span class="px-2 py-0.5 rounded text-[11px] font-medium {{ $sess->status === 'completed' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 animate-pulse' }}">
                                    {{ $sess->status }}
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-mono font-bold text-white">
                                {{ $sess->performance_score }}%
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-xs text-slate-450 leading-relaxed font-mono">
                                <div>Start: {{ $sess->started_at ? \Carbon\Carbon::parse($sess->started_at)->format('M-d H:i') : '-' }}</div>
                                <div>End: {{ $sess->ended_at ? \Carbon\Carbon::parse($sess->ended_at)->format('M-d H:i') : '-' }}</div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm">
                                <a href="{{ route('sessions.telemetry-timeline', $sess->id) }}" class="inline-flex items-center px-3 py-1.5 bg-slate-900 border border-slate-800 hover:border-indigo-500/40 text-xs font-bold rounded-lg text-indigo-400 hover:text-white transition">
                                    <svg class="w-3.5 h-3.5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 002 2h2a2 2 0 002-2"></path></svg>
                                    View Log Timeline
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-6 py-8 text-center text-sm text-slate-500">
                                No workspace sessions started in this course yet.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection

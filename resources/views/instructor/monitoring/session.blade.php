@extends('layouts.app')

@section('title', 'Instructor Live Monitoring - ' . $laboratory->title)
@section('page_header', 'Live Lab Monitoring: ' . $laboratory->title)

@section('content')
<div class="max-w-7xl mx-auto space-y-6" x-data="instructorMonitor()">
    <!-- Header & Navigation -->
    <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
        <div class="space-y-1">
            <div class="flex items-center gap-2">
                @if($schoolClass)
                    <a href="{{ route('classes.show', $schoolClass->id) }}" class="text-xs font-semibold text-slate-400 hover:text-white transition-colors flex items-center gap-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                        Back to {{ $schoolClass->name }}
                    </a>
                    <span class="text-slate-600">/</span>
                @endif
                <a href="{{ route('laboratories.show', $laboratory->id) }}" class="text-xs font-semibold text-slate-400 hover:text-white transition-colors">
                    Lab Details
                </a>
            </div>
            <h2 class="text-xl font-bold text-white flex items-center gap-2.5">
                <span>{{ $laboratory->title }}</span>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse mr-1.5"></span>
                    Live Telemetry
                </span>
                @if($laboratory->starter_files && count($laboratory->starter_files) > 0)
                    <span class="text-xs font-mono text-slate-400 bg-slate-800/80 px-2 py-0.5 rounded border border-slate-700">
                        {{ count($laboratory->starter_files) }} files
                    </span>
                @endif
            </h2>
        </div>

        <div class="flex items-center gap-3">
            <!-- Sort Selector -->
            <div class="flex items-center rounded-lg bg-slate-900 border border-slate-800 p-1 text-xs">
                <a href="{{ request()->fullUrlWithQuery(['sort' => 'name']) }}" 
                   class="px-3 py-1.5 rounded-md font-medium transition-colors {{ $sortBy !== 'group' ? 'bg-[#3ecf8e] text-slate-950 font-semibold' : 'text-slate-400 hover:text-white' }}">
                    By Student Name
                </a>
                <a href="{{ request()->fullUrlWithQuery(['sort' => 'group']) }}" 
                   class="px-3 py-1.5 rounded-md font-medium transition-colors {{ $sortBy === 'group' ? 'bg-[#3ecf8e] text-slate-950 font-semibold' : 'text-slate-400 hover:text-white' }}">
                    By Group / Team
                </a>
            </div>

            <!-- Auto-refresh button -->
            <button @click="refreshData()" class="p-2 rounded-lg bg-slate-900 border border-slate-800 text-slate-400 hover:text-white transition-colors" title="Refresh Live Stream">
                <svg class="w-4 h-4" :class="{ 'animate-spin': isRefreshing }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
            </button>
        </div>
    </div>

    @if($laboratory->isLiveLab())
        <!-- Live Lab Shared Countdown Banner (Feature 9) -->
        <div class="p-5 rounded-xl border {{ $laboratory->isLiveActive() ? 'bg-emerald-950/20 border-emerald-500/30' : ($laboratory->isLiveNotStarted() ? 'bg-amber-950/20 border-amber-500/30' : 'bg-red-950/20 border-red-500/30') }} flex flex-col md:flex-row items-center justify-between gap-4">
            <div class="flex items-center gap-3.5">
                @if($laboratory->isLiveActive())
                    <span class="relative flex h-3.5 w-3.5 flex-shrink-0">
                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                        <span class="relative inline-flex rounded-full h-3.5 w-3.5 bg-emerald-500"></span>
                    </span>
                    <div>
                        <div class="text-xs font-mono font-bold uppercase tracking-wider text-emerald-400 flex items-center gap-2">
                            <span>Live Lab Shared Countdown Active</span>
                            <span class="px-2 py-0.5 rounded text-[10px] bg-emerald-500/20 text-emerald-300 font-mono">Synchronized Clock</span>
                        </div>
                        <p class="text-xs text-slate-400 mt-0.5">All students share this timer. Late joiners only receive remaining time. Auto-submits on cutoff.</p>
                    </div>
                @elseif($laboratory->isLiveNotStarted())
                    <span class="h-3.5 w-3.5 rounded-full bg-amber-400 flex-shrink-0"></span>
                    <div>
                        <div class="text-xs font-mono font-bold uppercase tracking-wider text-amber-400">
                            Live Lab Locked / Not Yet Started
                        </div>
                        <p class="text-xs text-slate-400 mt-0.5">Students are currently blocked from entering until you open the session window.</p>
                    </div>
                @else
                    <span class="h-3.5 w-3.5 rounded-full bg-red-400 flex-shrink-0"></span>
                    <div>
                        <div class="text-xs font-mono font-bold uppercase tracking-wider text-red-400">
                            Live Lab Countdown Expired / Closed
                        </div>
                        <p class="text-xs text-slate-400 mt-0.5">Shared time limit reached. Active student workspaces have been auto-submitted.</p>
                    </div>
                @endif
            </div>

            <div class="flex items-center gap-4 flex-wrap">
                @if($laboratory->isLiveActive())
                    <div class="text-center px-4 py-2 rounded-lg bg-slate-950/80 border border-emerald-500/30">
                        <div class="text-[10px] font-mono uppercase text-slate-400">Shared Remaining</div>
                        <div class="text-2xl font-mono font-bold text-emerald-400 tracking-wider">
                            {{ sprintf('%02d:%02d', floor($laboratory->getRemainingLiveSeconds() / 60), $laboratory->getRemainingLiveSeconds() % 60) }}
                        </div>
                    </div>
                    <form action="{{ route('laboratories.end-live', $laboratory->id) }}" method="POST" onsubmit="return confirm('End this live lab now? All in-progress student workspaces will be auto-submitted and assessed.');">
                        @csrf
                        <button type="submit" class="px-4 py-2 rounded-lg bg-red-500/20 hover:bg-red-500/30 text-red-300 border border-red-500/30 text-xs font-semibold transition">
                            ⏹️ End Live Lab
                        </button>
                    </form>
                @elseif($laboratory->isLiveNotStarted())
                    <form action="{{ route('laboratories.open-live', $laboratory->id) }}" method="POST" class="flex items-center gap-2">
                        @csrf
                        <div class="flex items-center gap-1.5 bg-slate-900 border border-slate-700 rounded-lg px-3 py-1.5">
                            <span class="text-xs font-mono text-slate-400">Window:</span>
                            <input type="number" name="duration_minutes" value="{{ $laboratory->live_duration_minutes ?? $laboratory->time_limit ?? 60 }}" min="1" max="600" class="w-14 bg-transparent text-xs font-mono text-white focus:outline-none">
                            <span class="text-xs font-mono text-slate-400">min</span>
                        </div>
                        <button type="submit" class="px-4 py-2 rounded-lg bg-[#3ecf8e] text-slate-950 font-bold text-xs hover:bg-[#00c573] transition shadow-sm">
                            ▶️ Open Live Lab &rarr;
                        </button>
                    </form>
                @else
                    @if($laboratory->getRemainingLiveSeconds() > 0)
                        <form action="{{ route('laboratories.reopen-live', $laboratory->id) }}" method="POST">
                            @csrf
                            <button type="submit" class="px-4 py-2 rounded-lg bg-amber-500/20 hover:bg-amber-500/30 text-amber-300 border border-amber-500/30 text-xs font-semibold transition" title="Carries forward leftover {{ max(1, (int) ceil($laboratory->getRemainingLiveSeconds() / 60)) }}m">
                                🔄 Reopen Live Lab ({{ max(1, (int) ceil($laboratory->getRemainingLiveSeconds() / 60)) }}m left)
                            </button>
                        </form>
                    @else
                        <form action="{{ route('laboratories.reopen-live', $laboratory->id) }}" method="POST" class="flex items-center gap-2">
                            @csrf
                            <input type="number" name="extend_minutes" value="15" min="1" max="180" class="w-16 px-2.5 py-1.5 bg-slate-900 border border-slate-700 text-xs font-mono rounded text-white" title="Extend duration in minutes">
                            <button type="submit" class="px-3.5 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-xs text-slate-200 border border-slate-700 transition">
                                🔄 Extend & Reopen (+min)
                            </button>
                        </form>
                    @endif
                @endif
            </div>
        </div>
    @endif

    <!-- Live Telemetry KPI Metrics -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="glass-panel p-5 rounded-xl border border-slate-800 bg-slate-900/60">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Active Workspaces</span>
                <span class="h-2 w-2 rounded-full bg-emerald-400 animate-ping"></span>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl font-bold font-mono text-white">{{ $activeSessions }}</span>
                <span class="text-xs text-slate-500">of {{ $totalStudents }} total</span>
            </div>
            <div class="mt-2 w-full bg-slate-800 rounded-full h-1.5 overflow-hidden">
                <div class="bg-emerald-400 h-1.5 rounded-full" style="width: {{ $totalStudents > 0 ? round(($activeSessions / $totalStudents) * 100) : 0 }}%"></div>
            </div>
        </div>

        <div class="glass-panel p-5 rounded-xl border border-slate-800 bg-slate-900/60">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Average WPM Baseline</span>
                <svg class="w-4 h-4 text-sky-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl font-bold font-mono text-sky-400">{{ $avgWpm }}</span>
                <span class="text-xs text-slate-500">words / min</span>
            </div>
            <p class="text-xs text-slate-500 mt-2">Active keystroke velocity cadence.</p>
        </div>

        <div class="glass-panel p-5 rounded-xl border border-slate-800 bg-slate-900/60">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Integrity Alerts</span>
                <svg class="w-4 h-4 {{ $totalAnomalies > 0 ? 'text-amber-400' : 'text-slate-500' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl font-bold font-mono {{ $totalAnomalies > 0 ? 'text-amber-400' : 'text-white' }}">{{ $totalAnomalies }}</span>
                <span class="text-xs text-slate-500">flagged events</span>
            </div>
            <p class="text-xs text-slate-500 mt-2">Focus losses, paste flags & presence checks.</p>
        </div>

        <div class="glass-panel p-5 rounded-xl border border-slate-800 bg-slate-900/60">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Completed Sessions</span>
                <svg class="w-4 h-4 text-[#3ecf8e]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl font-bold font-mono text-[#3ecf8e]">{{ $completedSessions }}</span>
                <span class="text-xs text-slate-500">submitted</span>
            </div>
            <p class="text-xs text-slate-500 mt-2">Finalized and evaluated solutions.</p>
        </div>
    </div>

    <!-- Student & Team Monitoring Roster -->
    <div class="glass-panel rounded-xl border border-slate-800 overflow-hidden">
        <div class="p-4 border-b border-slate-800 bg-slate-950/40 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h3 class="font-bold text-white text-sm">Active Student / Team Workspaces</h3>
                <p class="text-xs text-slate-400 mt-0.5">Real-time status, WPM tracking, task progress, and anomaly audit trails.</p>
            </div>
            <input type="text" x-model="searchQuery" placeholder="Filter by student or team..." 
                   class="bg-slate-900 border border-slate-700/80 rounded-lg px-3 py-1.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:border-[#3ecf8e] w-full sm:w-64">
        </div>

        @if($sessions->isEmpty())
            <div class="p-12 text-center text-slate-500">
                <svg class="w-12 h-12 mx-auto mb-3 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                <p class="text-sm font-medium text-slate-400">No active student sessions found for this laboratory.</p>
                <p class="text-xs text-slate-500 mt-1">Students will appear here immediately upon launching the VS Code extension.</p>
            </div>
        @else
            <div class="divide-y divide-slate-800/80">
                @foreach($sessions as $session)
                    @php
                        $user = $session->user;
                        $group = $session->group;
                        $tasksCount = is_array($session->completed_tasks) ? count($session->completed_tasks) : 0;
                        $anomaliesList = $session->anomalies;
                        $diffStats = $session->diff_stats ?? ['added' => 0, 'deleted' => 0];
                    @endphp
                    <div class="p-5 hover:bg-slate-900/40 transition-colors" 
                         x-show="matchesSearch('{{ strtolower($user->name ?? '') }}', '{{ strtolower($group->name ?? '') }}')">
                        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                            <!-- Student / Team Identity (Fixed Width for Perfect Alignment) -->
                            <div class="flex items-center gap-3 w-full lg:w-64 xl:w-72 shrink-0 min-w-0">
                                <div class="w-10 h-10 rounded-full bg-slate-800 border border-slate-700 flex items-center justify-center font-bold text-sm text-slate-200 uppercase shrink-0">
                                    {{ strtoupper(substr($user->name ?? 'S', 0, 2)) }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <span class="font-bold text-white text-sm truncate" title="{{ $user->name ?? 'Unknown Student' }}">{{ $user->name ?? 'Unknown Student' }}</span>
                                        @if($session->status === 'in_progress')
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/30 shrink-0">
                                                Active
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-800 text-slate-400 border border-slate-700 shrink-0">
                                                Completed
                                            </span>
                                        @endif
                                    </div>
                                    <div class="text-xs text-slate-400 mt-0.5 flex items-center gap-1.5 truncate">
                                        <span class="truncate" title="{{ $user->email ?? 'No email' }}">{{ $user->email ?? 'No email' }}</span>
                                        @if($group)
                                            <span class="text-slate-600 shrink-0">•</span>
                                            <span class="text-[#3ecf8e] font-semibold flex items-center gap-1 shrink-0 truncate" title="{{ $group->name }}">
                                                <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                                <span class="truncate">{{ $group->name }}</span>
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            <!-- Live Telemetry Stats (Fixed Grid & Width for Vertical Alignment) -->
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 sm:gap-3 text-xs w-full lg:w-[440px] xl:w-[480px] shrink-0">
                                <!-- WPM Widget -->
                                <div class="bg-slate-950/60 p-2.5 rounded-lg border border-slate-800 text-center flex flex-col justify-center min-h-[64px]">
                                    <span class="text-slate-500 text-[10px] uppercase font-bold block truncate">Live WPM</span>
                                    <span class="text-base font-mono font-bold text-sky-400 my-0.5">{{ $session->wpm ?? 0 }}</span>
                                    <span class="text-[10px] text-slate-500 block truncate">words/min</span>
                                </div>

                                <!-- Tasks Completed -->
                                <div class="bg-slate-950/60 p-2.5 rounded-lg border border-slate-800 text-center flex flex-col justify-center min-h-[64px]">
                                    <span class="text-slate-500 text-[10px] uppercase font-bold block truncate">Tasks Done</span>
                                    <span class="text-base font-mono font-bold text-emerald-400 my-0.5">{{ $tasksCount }}</span>
                                    <span class="text-[10px] text-slate-500 block truncate">completed</span>
                                </div>

                                <!-- Focus Losses -->
                                <div class="bg-slate-950/60 p-2.5 rounded-lg border border-slate-800 text-center flex flex-col justify-center min-h-[64px]">
                                    <span class="text-slate-500 text-[10px] uppercase font-bold block truncate">Focus Lost</span>
                                    <span class="text-base font-mono font-bold my-0.5 {{ ($session->focus_lost_count ?? 0) > 2 ? 'text-amber-400' : 'text-slate-300' }}">
                                        {{ $session->focus_lost_count ?? 0 }}
                                    </span>
                                    <span class="text-[10px] text-slate-500 block truncate">window switches</span>
                                </div>

                                <!-- Paste Anomalies -->
                                <div class="bg-slate-950/60 p-2.5 rounded-lg border border-slate-800 text-center flex flex-col justify-center min-h-[64px]">
                                    <span class="text-slate-500 text-[10px] uppercase font-bold block truncate">Paste Flags</span>
                                    <span class="text-base font-mono font-bold my-0.5 {{ ($session->paste_anomaly_count ?? 0) > 0 ? 'text-rose-400 font-semibold' : 'text-slate-300' }}">
                                        {{ $session->paste_anomaly_count ?? 0 }}
                                    </span>
                                    <span class="text-[10px] text-slate-500 block truncate">injections</span>
                                </div>
                            </div>

                            <!-- Diff Tracking Pill & Action Buttons (Aligned to End) -->
                            <div class="flex items-center gap-2.5 justify-start lg:justify-end shrink-0">
                                <!-- Diff Stats Pill -->
                                <div class="text-[11px] font-mono bg-slate-950 px-2.5 py-1.5 rounded border border-slate-800 flex items-center gap-1.5 shrink-0" title="Line Diff Breakdown">
                                    <span class="text-[#3ecf8e] font-semibold">+{{ $diffStats['added'] ?? 0 }}</span>
                                    <span class="text-slate-600">/</span>
                                    <span class="text-rose-400 font-semibold">-{{ $diffStats['deleted'] ?? 0 }}</span>
                                </div>

                                <!-- Anomaly History Modal Toggle -->
                                <button @click="openAnomalyModal({{ $session->id }}, '{{ addslashes($user->name ?? 'Student') }}', {{ json_encode($anomaliesList) }})"
                                        class="px-3 py-1.5 rounded-lg border border-slate-700 bg-slate-800/80 hover:bg-slate-700 text-xs font-semibold text-slate-200 transition-colors flex items-center gap-1.5 shrink-0">
                                    <svg class="w-3.5 h-3.5 {{ $anomaliesList->count() > 0 ? 'text-amber-400' : 'text-slate-400' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                    <span>Anomalies ({{ $anomaliesList->count() }})</span>
                                </button>

                                <!-- End / Reopen Session Form with consistent button width -->
                                @if($session->status === 'in_progress')
                                    <form action="{{ route('instructor.sessions.end', $session->id) }}" method="POST" onsubmit="return confirm('End this student session? Their code will be auto-submitted and evaluated.');" class="shrink-0">
                                        @csrf
                                        <button type="submit" class="w-24 py-1.5 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/20 text-xs font-semibold transition-colors text-center">
                                            End Session
                                        </button>
                                    </form>
                                @else
                                    <form action="{{ route('instructor.sessions.reopen', $session->id) }}" method="POST" class="shrink-0">
                                        @csrf
                                        <button type="submit" class="w-24 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 border border-slate-700 text-xs font-semibold transition-colors text-center">
                                            Reopen
                                        </button>
                                    </form>
                                @endif
                            </div>
                        </div>

                        <!-- Team Contribution Drill-Down (Feature 1 & Feature 6) -->
                        @if($session->code_contributions && count($session->code_contributions) > 0)
                            <div class="mt-3 pt-3 border-t border-slate-800/60 text-xs">
                                <span class="text-slate-400 font-semibold text-[11px] uppercase tracking-wider block mb-1.5">Teammate Contributions</span>
                                <div class="flex flex-wrap gap-2">
                                    @foreach($session->code_contributions as $contrib)
                                        <div class="px-2.5 py-1 rounded bg-slate-950 border border-slate-800 flex items-center gap-2">
                                            <span class="w-2 h-2 rounded-full" style="background-color: {{ $contrib['color'] ?? '#3ecf8e' }}"></span>
                                            <span class="text-white font-medium">{{ $contrib['user_name'] ?? 'Teammate' }}</span>
                                            <span class="text-emerald-400 font-mono">+{{ $contrib['lines_added'] ?? 0 }}</span>
                                            <span class="text-rose-400 font-mono">-{{ $contrib['lines_deleted'] ?? 0 }}</span>
                                            <span class="text-slate-500">({{ $contrib['percentage'] ?? 0 }}%)</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>

    <!-- Anomaly Timeline & Proof Snapshot Modal -->
    <div x-show="isModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm" style="display: none;">
        <div class="glass-panel rounded-2xl border border-slate-700 max-w-2xl w-full p-6 space-y-4 max-h-[85vh] overflow-y-auto" @click.away="isModalOpen = false">
            <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-amber-400"></span>
                    <h3 class="text-base font-bold text-white">
                        Integrity & Anomaly Audit Trail — <span x-text="selectedStudentName" class="text-[#3ecf8e]"></span>
                    </h3>
                </div>
                <button @click="isModalOpen = false" class="text-slate-400 hover:text-white">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <template x-if="selectedAnomalies.length === 0">
                <div class="py-12 text-center text-slate-500">
                    <svg class="w-10 h-10 mx-auto mb-2 text-emerald-500/60" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <p class="text-sm font-medium text-slate-300">Clean session: No anomalies or suspicious activity detected.</p>
                </div>
            </template>

            <div class="space-y-3" x-show="selectedAnomalies.length > 0">
                <template x-for="item in selectedAnomalies" :key="item.id">
                    <div class="p-4 rounded-xl border border-slate-800 bg-slate-950/60 space-y-2">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider"
                                      :class="{
                                          'bg-rose-500/20 text-rose-400 border border-rose-500/30': item.severity === 'high',
                                          'bg-amber-500/20 text-amber-400 border border-amber-500/30': item.severity === 'medium',
                                          'bg-slate-800 text-slate-400': item.severity === 'low'
                                      }" x-text="item.severity"></span>
                                <span class="text-xs font-bold text-white font-mono" x-text="item.type"></span>
                            </div>
                            <span class="text-[11px] text-slate-500" x-text="item.created_at"></span>
                        </div>
                        <p class="text-xs text-slate-300" x-text="item.description"></p>

                        <!-- Paste snippet metadata preview -->
                        <template x-if="item.metadata && item.metadata.snippet">
                            <div class="mt-2 bg-slate-900 p-2.5 rounded border border-slate-800">
                                <span class="text-[10px] text-slate-400 uppercase font-bold block mb-1">Captured Paste Snippet:</span>
                                <pre class="text-[11px] font-mono text-amber-200 overflow-x-auto whitespace-pre-wrap" x-text="item.metadata.snippet"></pre>
                            </div>
                        </template>

                        <!-- Camera Snapshot proof modal preview -->
                        <template x-if="item.image_path">
                            <div class="mt-2">
                                <span class="text-[10px] text-slate-400 uppercase font-bold block mb-1">Camera Absence Snapshot:</span>
                                <img :src="'/' + item.image_path" class="w-48 h-auto rounded border border-slate-700 hover:scale-105 transition-transform" alt="Anomaly Proof">
                            </div>
                        </template>
                    </div>
                </template>
            </div>

            <div class="pt-2 flex justify-end">
                <button @click="isModalOpen = false" class="px-4 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-xs font-semibold text-white">
                    Close Audit Log
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function instructorMonitor() {
    return {
        searchQuery: '',
        isRefreshing: false,
        isModalOpen: false,
        selectedStudentName: '',
        selectedAnomalies: [],
        matchesSearch(name, group) {
            if (!this.searchQuery.trim()) return true;
            const q = this.searchQuery.toLowerCase();
            return name.includes(q) || group.includes(q);
        },
        openAnomalyModal(sessionId, studentName, anomalies) {
            this.selectedStudentName = studentName;
            this.selectedAnomalies = anomalies || [];
            this.isModalOpen = true;
        },
        refreshData() {
            this.isRefreshing = true;
            fetch('{{ route("instructor.monitoring.data", $laboratory->id) }}')
                .then(res => res.json())
                .then(data => {
                    this.isRefreshing = false;
                    // Reload page gracefully to refresh server-rendered stats and badges
                    window.location.reload();
                })
                .catch(() => {
                    this.isRefreshing = false;
                });
        }
    };
}
</script>
@endsection

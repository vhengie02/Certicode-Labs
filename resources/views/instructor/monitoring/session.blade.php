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

    <!-- Live WebSocket Telemetry Alert Banner -->
    <div x-show="hasLiveUpdate" x-cloak class="p-3.5 rounded-xl bg-emerald-500/10 border border-emerald-500/30 flex items-center justify-between text-emerald-400 text-sm font-medium transition-all shadow-sm">
        <div class="flex items-center gap-2.5">
            <span class="w-2.5 h-2.5 rounded-full bg-emerald-400 animate-ping"></span>
            <span x-text="liveUpdateMessage"></span>
        </div>
        <button @click="refreshData()" class="text-xs px-3 py-1 rounded-lg bg-emerald-500/20 hover:bg-emerald-500/30 text-emerald-300 font-semibold transition-colors">
            Refresh Panel &rarr;
        </button>
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
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
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
            <p class="text-xs text-slate-500 mt-2">Focus losses, paste flags & presence.</p>
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

        <div class="glass-panel p-5 rounded-xl border border-slate-800 bg-slate-900/60">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Cohort Plagiarism</span>
                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase {{ ($plagiarismAnalysis['flagged_pairs_count'] ?? 0) > 0 ? 'bg-rose-500/20 text-rose-400 border border-rose-500/30' : 'bg-emerald-500/20 text-emerald-400' }}">
                    {{ ($plagiarismAnalysis['flagged_pairs_count'] ?? 0) > 0 ? 'Flagged' : 'Clean' }}
                </span>
            </div>
            <div class="mt-2 flex items-baseline gap-2">
                <span class="text-2xl font-bold font-mono {{ ($plagiarismAnalysis['flagged_pairs_count'] ?? 0) > 0 ? 'text-rose-400' : 'text-emerald-400' }}" x-text="plagiarism.flagged_pairs_count ?? {{ $plagiarismAnalysis['flagged_pairs_count'] ?? 0 }}">
                    {{ $plagiarismAnalysis['flagged_pairs_count'] ?? 0 }}
                </span>
                <span class="text-xs text-slate-500">flagged pairs</span>
            </div>
            <p class="text-xs text-slate-500 mt-2">
                <a href="#plagiarism-section" class="text-[#3ecf8e] hover:underline flex items-center gap-1">
                    <span>Inspect Similarity Matrix</span> &darr;
                </a>
            </p>
        </div>
    </div>

    <!-- Student & Team Monitoring Roster -->
    <div class="glass-panel rounded-xl border border-slate-800 overflow-hidden">
        <div class="p-4 border-b border-slate-800 bg-slate-950/40 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h3 class="font-bold text-white text-sm">Active Student / Team Workspaces</h3>
                <p class="text-xs text-slate-400 mt-0.5">Real-time status, WPM tracking, task progress, and anomaly audit trails.</p>
            </div>
            <input type="text" id="monitoring-search-query" name="search_query" x-model="searchQuery" placeholder="Filter by student or team..." 
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
                        $gradeSessionPayload = [
                            'id' => $session->id,
                            'student_name' => $user->name ?? 'Unknown Student',
                            'is_team' => (bool) $session->group_id,
                            'team_name' => $group->name ?? null,
                            'status' => $session->status,
                            'performance_score' => (float) ($session->performance_score ?? 0),
                            'instructor_grade_override' => $session->instructor_grade_override,
                            'effective_score' => (float) ($session->effective_score ?? 0),
                            'is_overridden' => $session->isGradeOverridden(),
                            'instructor_override_reason' => $session->instructor_override_reason,
                            'instructor_overridden_at' => $session->instructor_overridden_at ? $session->instructor_overridden_at->diffForHumans() : null,
                            'overridden_by_name' => $session->overriddenByUser->name ?? null,
                            'ai_grade_summary' => $session->ai_grade_summary,
                        ];
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

                                <!-- Grade & AI Summary Button (Feature 10) -->
                                <button @click="openGradeModal({{ json_encode($gradeSessionPayload) }})"
                                        class="px-3 py-1.5 rounded-lg border text-xs font-semibold transition-colors flex items-center gap-1.5 shrink-0 {{ $session->isGradeOverridden() ? 'border-purple-500/40 bg-purple-500/10 hover:bg-purple-500/20 text-purple-300' : 'border-slate-700 bg-slate-800/80 hover:bg-slate-700 text-slate-200' }}"
                                        title="View AI Grade Explanation & Manual Override">
                                    <svg class="w-3.5 h-3.5 {{ $session->isGradeOverridden() ? 'text-purple-400' : 'text-[#3ecf8e]' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"></path></svg>
                                    <span>
                                        @if($session->isGradeOverridden())
                                            Grade: {{ $session->effective_score }}% (Overridden)
                                        @else
                                            Grade: {{ $session->performance_score ?? 0 }}%
                                        @endif
                                    </span>
                                </button>

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

    <!-- Cohort Code Similarity & Plagiarism Detector (Item 2) -->
    <div id="plagiarism-section" class="glass-panel rounded-xl border border-slate-800 overflow-hidden space-y-4 p-6 bg-slate-950/40">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-slate-800 pb-4">
            <div>
                <div class="flex items-center gap-2.5">
                    <span class="w-2.5 h-2.5 rounded-full" :class="(plagiarism.flagged_pairs_count || 0) > 0 ? 'bg-rose-500 animate-pulse' : 'bg-emerald-400'"></span>
                    <h3 class="font-bold text-white text-base">In-Session Cohort Plagiarism Detector</h3>
                    <span class="text-xs font-mono px-2 py-0.5 rounded bg-slate-800 text-slate-300 border border-slate-700" x-text="(plagiarism.total_students_with_code || 0) + ' submissions analyzed'"></span>
                </div>
                <p class="text-xs text-slate-400 mt-1">Lightweight AST and token n-gram similarity engine checking student submissions for unauthorized collaboration.</p>
            </div>
            
            <div class="flex items-center gap-2">
                <button @click="showSimilarityMatrix = !showSimilarityMatrix" class="px-3 py-1.5 rounded-lg bg-slate-900 border border-slate-700 text-xs font-semibold text-slate-300 hover:text-white transition flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path></svg>
                    <span x-text="showSimilarityMatrix ? 'Hide Matrix' : 'Show Matrix (Heatmap)'"></span>
                </button>
                <button @click="runPlagiarismScan()" :disabled="isScanningPlagiarism" class="px-3.5 py-1.5 rounded-lg bg-[#3ecf8e] hover:bg-[#00c573] text-slate-950 font-bold text-xs transition flex items-center gap-1.5 shadow-sm disabled:opacity-50">
                    <svg class="w-3.5 h-3.5" :class="{ 'animate-spin': isScanningPlagiarism }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                    <span x-text="isScanningPlagiarism ? 'Scanning...' : 'Re-Scan Submissions'"></span>
                </button>
            </div>
        </div>

        <!-- Clean Cohort Banner -->
        <template x-if="!plagiarism.flagged_pairs || plagiarism.flagged_pairs.length === 0">
            <div class="py-8 px-4 rounded-xl border border-slate-800/80 bg-slate-900/30 text-center">
                <div class="w-10 h-10 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 mx-auto flex items-center justify-center mb-2.5">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                </div>
                <h4 class="text-sm font-semibold text-white">No Suspicious Code Duplication Detected</h4>
                <p class="text-xs text-slate-400 mt-1 max-w-md mx-auto">All student code submissions exhibit distinct structural token distributions with similarity scores below the alert threshold.</p>
            </div>
        </template>

        <!-- Flagged Pairs Table -->
        <template x-if="plagiarism.flagged_pairs && plagiarism.flagged_pairs.length > 0">
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">
                        Flagged Cohort Pairs (<span x-text="plagiarism.flagged_pairs.length"></span>)
                    </span>
                    <span class="text-xs text-rose-400 font-medium">⚠️ Review identical code structures below</span>
                </div>

                <div class="overflow-x-auto border border-slate-800 rounded-xl">
                    <table class="w-full text-left text-xs">
                        <thead class="bg-slate-900/90 text-slate-400 font-semibold uppercase text-[10px] tracking-wider border-b border-slate-800">
                            <tr>
                                <th class="py-3 px-4">Student 1</th>
                                <th class="py-3 px-4">Student 2</th>
                                <th class="py-3 px-4">Similarity Score</th>
                                <th class="py-3 px-4">Risk Rating</th>
                                <th class="py-3 px-4">Pattern Summary</th>
                                <th class="py-3 px-4 text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800/60 bg-slate-950/20 font-mono">
                            <template x-for="(pair, idx) in plagiarism.flagged_pairs" :key="idx">
                                <tr class="hover:bg-slate-900/40 transition">
                                    <td class="py-3 px-4 font-sans font-medium text-white">
                                        <div class="flex items-center gap-2">
                                            <span class="w-2 h-2 rounded-full bg-slate-500"></span>
                                            <span x-text="pair.student_a.name"></span>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4 font-sans font-medium text-white">
                                        <div class="flex items-center gap-2">
                                            <span class="w-2 h-2 rounded-full bg-slate-500"></span>
                                            <span x-text="pair.student_b.name"></span>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="flex items-center gap-2">
                                            <span class="font-bold text-sm" :class="pair.risk === 'high' ? 'text-rose-400' : 'text-amber-400'" x-text="pair.similarity + '%'"></span>
                                            <div class="w-16 bg-slate-800 rounded-full h-1.5 overflow-hidden">
                                                <div class="h-1.5 rounded-full" :class="pair.risk === 'high' ? 'bg-rose-500' : 'bg-amber-400'" :style="'width: ' + pair.similarity + '%'"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4 font-sans">
                                        <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider"
                                              :class="pair.risk === 'high' ? 'bg-rose-500/20 text-rose-300 border border-rose-500/30' : 'bg-amber-500/20 text-amber-300 border border-amber-500/30'"
                                              x-text="pair.risk === 'high' ? 'High Risk' : 'Moderate Overlap'"></span>
                                    </td>
                                    <td class="py-3 px-4 font-sans text-slate-400 text-xs truncate max-w-xs" x-text="pair.reason"></td>
                                    <td class="py-3 px-4 text-right font-sans">
                                        <button @click="openCompareModal(pair)" class="px-3 py-1 rounded-md bg-slate-800 hover:bg-slate-700 text-slate-200 hover:text-white text-xs font-semibold transition flex items-center gap-1 ml-auto border border-slate-700">
                                            <span>Compare Code</span> &rarr;
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>

        <!-- Similarity Heatmap Matrix -->
        <div x-show="showSimilarityMatrix" x-cloak class="mt-4 pt-4 border-t border-slate-800 space-y-3">
            <div class="flex items-center justify-between">
                <span class="text-xs font-semibold text-slate-400 uppercase tracking-wider">Cohort Pairwise Similarity Heatmap</span>
                <span class="text-[11px] text-slate-500 font-mono">Row & Column = Students</span>
            </div>

            <template x-if="plagiarism.students && plagiarism.students.length > 0">
                <div class="overflow-x-auto border border-slate-800 rounded-xl p-3 bg-slate-900/40">
                    <table class="text-center text-xs font-mono">
                        <thead>
                            <tr>
                                <th class="p-2 text-left font-sans text-slate-400 text-[11px]">Student</th>
                                <template x-for="st in plagiarism.students" :key="'col_' + st.session_id">
                                    <th class="p-2 text-[10px] font-sans text-slate-400 max-w-[80px] truncate" :title="st.name" x-text="st.name.split(' ')[0]"></th>
                                </template>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="rowSt in plagiarism.students" :key="'row_' + rowSt.session_id">
                                <tr class="border-t border-slate-800/40">
                                    <td class="p-2 text-left font-sans text-xs text-white font-medium whitespace-nowrap" x-text="rowSt.name"></td>
                                    <template x-for="colSt in plagiarism.students" :key="'cell_' + rowSt.session_id + '_' + colSt.session_id">
                                        <td class="p-2 text-[11px] font-bold rounded">
                                            <span class="px-2 py-1 rounded block"
                                                  :class="{
                                                      'bg-slate-800/80 text-slate-500': rowSt.session_id === colSt.session_id,
                                                      'bg-rose-500/20 text-rose-300 border border-rose-500/30': rowSt.session_id !== colSt.session_id && (plagiarism.matrix[rowSt.session_id]?.[colSt.session_id] || 0) >= 75,
                                                      'bg-amber-500/20 text-amber-300 border border-amber-500/30': rowSt.session_id !== colSt.session_id && (plagiarism.matrix[rowSt.session_id]?.[colSt.session_id] || 0) >= 50 && (plagiarism.matrix[rowSt.session_id]?.[colSt.session_id] || 0) < 75,
                                                      'bg-slate-900/60 text-slate-400': rowSt.session_id !== colSt.session_id && (plagiarism.matrix[rowSt.session_id]?.[colSt.session_id] || 0) < 50
                                                  }"
                                                  x-text="(plagiarism.matrix[rowSt.session_id]?.[colSt.session_id] || 0).toFixed(0) + '%'"></span>
                                        </td>
                                    </template>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </template>
        </div>
    </div>

    <!-- Side-by-Side Code Comparison Modal -->
    <div x-show="isCompareModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm" style="display: none;">
        <div class="glass-panel rounded-2xl border border-slate-700 max-w-5xl w-full p-6 space-y-4 max-h-[90vh] overflow-y-auto" @click.away="isCompareModalOpen = false">
            <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                <div class="flex items-center gap-3">
                    <span class="w-2.5 h-2.5 rounded-full bg-rose-400"></span>
                    <h3 class="text-base font-bold text-white flex items-center gap-2">
                        <span>Side-by-Side Code Comparison</span>
                        <template x-if="selectedPair">
                            <span class="text-xs px-2.5 py-0.5 rounded-full font-mono font-bold"
                                  :class="selectedPair.risk === 'high' ? 'bg-rose-500/20 text-rose-300 border border-rose-500/30' : 'bg-amber-500/20 text-amber-300 border border-amber-500/30'"
                                  x-text="selectedPair.similarity + '% Match'"></span>
                        </template>
                    </h3>
                </div>
                <button @click="isCompareModalOpen = false" class="text-slate-400 hover:text-white">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <template x-if="selectedPair">
                <div class="space-y-4">
                    <div class="p-3 rounded-lg bg-slate-900 border border-slate-800 text-xs text-slate-300 flex items-center justify-between">
                        <span><strong>Analysis:</strong> <span x-text="selectedPair.reason"></span></span>
                        <span class="font-mono text-slate-500 text-[11px]" x-text="'Tokens: ' + (selectedPair.student_a.token_count || 0) + ' vs ' + (selectedPair.student_b.token_count || 0)"></span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Student A -->
                        <div class="space-y-2">
                            <div class="flex items-center justify-between text-xs font-semibold text-slate-300 bg-slate-900/80 px-3 py-2 rounded-t-lg border border-slate-800">
                                <span class="text-white" x-text="selectedPair.student_a.name"></span>
                                <span class="text-[10px] text-slate-500 font-mono" x-text="selectedPair.student_a.code_length + ' bytes'"></span>
                            </div>
                            <pre class="p-3 bg-slate-950 rounded-b-lg border border-slate-800 text-xs font-mono text-slate-200 overflow-x-auto max-h-80 whitespace-pre-wrap leading-relaxed" x-text="selectedPair.student_a.code_preview"></pre>
                        </div>

                        <!-- Student B -->
                        <div class="space-y-2">
                            <div class="flex items-center justify-between text-xs font-semibold text-slate-300 bg-slate-900/80 px-3 py-2 rounded-t-lg border border-slate-800">
                                <span class="text-white" x-text="selectedPair.student_b.name"></span>
                                <span class="text-[10px] text-slate-500 font-mono" x-text="selectedPair.student_b.code_length + ' bytes'"></span>
                            </div>
                            <pre class="p-3 bg-slate-950 rounded-b-lg border border-slate-800 text-xs font-mono text-slate-200 overflow-x-auto max-h-80 whitespace-pre-wrap leading-relaxed" x-text="selectedPair.student_b.code_preview"></pre>
                        </div>
                    </div>
                </div>
            </template>

            <div class="pt-2 flex justify-end">
                <button @click="isCompareModalOpen = false" class="px-4 py-2 rounded-lg bg-slate-800 hover:bg-slate-700 text-xs font-semibold text-white">
                    Close Comparison
                </button>
            </div>
        </div>
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

    <!-- AI Grade Summary & Instructor Override Modal (Feature 10) -->
    <div x-show="isGradeModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm" style="display: none;">
        <div class="glass-panel rounded-2xl border border-slate-700 max-w-2xl w-full p-6 space-y-4 max-h-[85vh] overflow-y-auto" @click.away="isGradeModalOpen = false">
            <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                <div class="space-y-0.5">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full" :class="selectedGradeSession?.is_overridden ? 'bg-purple-400 animate-pulse' : 'bg-[#3ecf8e]'"></span>
                        <h3 class="text-base font-bold text-white">
                            AI Grade Assessment &amp; Explanation
                        </h3>
                    </div>
                    <div class="flex items-center gap-2 text-xs text-slate-400">
                        <span class="font-medium text-slate-200" x-text="selectedGradeSession?.student_name"></span>
                        <template x-if="selectedGradeSession?.is_team">
                            <span class="px-1.5 py-0.5 rounded bg-sky-500/20 text-sky-300 border border-sky-500/30 text-[10px]" x-text="'Team: ' + selectedGradeSession.team_name"></span>
                        </template>
                        <template x-if="selectedGradeSession?.is_team">
                            <span class="text-[11px] text-slate-500 italic">(Evaluating combined submission as single unit)</span>
                        </template>
                    </div>
                </div>
                <button @click="isGradeModalOpen = false" class="text-slate-400 hover:text-white">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <!-- Side-by-side Score Banner -->
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                <div class="bg-slate-950/80 p-3 rounded-xl border border-slate-800 text-center">
                    <span class="text-[10px] uppercase font-bold text-slate-400 block">AI Assessed Grade</span>
                    <span class="text-xl font-mono font-bold text-[#3ecf8e] my-0.5 block" x-text="(selectedGradeSession?.performance_score ?? 0) + '%'"></span>
                    <span class="text-[10px] text-slate-500">From submission model</span>
                </div>

                <div class="bg-slate-950/80 p-3 rounded-xl border border-slate-800 text-center">
                    <span class="text-[10px] uppercase font-bold text-slate-400 block">Effective Grade</span>
                    <span class="text-xl font-mono font-bold text-white my-0.5 block" x-text="(selectedGradeSession?.effective_score ?? 0) + '%'"></span>
                    <span class="text-[10px] font-semibold" :class="selectedGradeSession?.is_overridden ? 'text-purple-400' : 'text-emerald-400'"
                          x-text="selectedGradeSession?.is_overridden ? 'Instructor Overridden' : 'AI Score Applied'"></span>
                </div>

                <div class="col-span-2 sm:col-span-1 bg-slate-950/80 p-3 rounded-xl border border-slate-800 text-center flex flex-col justify-center">
                    <span class="text-[10px] uppercase font-bold text-slate-400 block">Test Verification</span>
                    <template x-if="selectedGradeSession?.ai_grade_summary?.test_cases_total">
                        <span class="text-xl font-mono font-bold text-sky-400 my-0.5 block">
                            <span x-text="selectedGradeSession.ai_grade_summary.test_cases_passed"></span> / <span x-text="selectedGradeSession.ai_grade_summary.test_cases_total"></span>
                        </span>
                    </template>
                    <template x-if="!selectedGradeSession?.ai_grade_summary?.test_cases_total">
                        <span class="text-xs font-mono text-slate-500 my-1 block">N/A</span>
                    </template>
                    <span class="text-[10px] text-slate-500">Verified checklist tests</span>
                </div>
            </div>

            <!-- Plain-Language AI Grade Summary -->
            <div class="bg-slate-950/60 p-4 rounded-xl border border-slate-800 space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-purple-300 flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                        Plain-Language AI Grade Explanation
                    </span>
                    <span class="text-[10px] px-2 py-0.5 rounded bg-slate-800 text-slate-400 font-mono">Instructor Only</span>
                </div>
                <p class="text-xs text-slate-200 leading-relaxed font-sans" x-text="selectedGradeSession?.ai_grade_summary?.summary || 'No AI grade summary recorded for this session yet.'"></p>

                <template x-if="selectedGradeSession?.ai_grade_summary?.code_quality_notes">
                    <div class="mt-2 pt-2 border-t border-slate-800/80 text-xs flex items-start gap-2">
                        <span class="text-slate-400 font-semibold shrink-0">Code Quality:</span>
                        <span class="text-slate-300" x-text="selectedGradeSession.ai_grade_summary.code_quality_notes"></span>
                    </div>
                </template>
            </div>

            <!-- Competency Breakdown List -->
            <template x-if="selectedGradeSession?.ai_grade_summary?.competencies && Object.keys(selectedGradeSession.ai_grade_summary.competencies).length > 0">
                <div class="space-y-2">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400">Assessed Competencies</h4>
                    <div class="space-y-2 max-h-48 overflow-y-auto pr-1">
                        <template x-for="(comp, compKey) in selectedGradeSession.ai_grade_summary.competencies" :key="compKey">
                            <div class="p-3 rounded-lg bg-slate-950/80 border border-slate-800 flex items-start justify-between gap-3">
                                <div class="space-y-0.5 min-w-0 flex-1">
                                    <span class="text-xs font-mono font-bold text-white uppercase tracking-wider" x-text="compKey.replace(/_/g, ' ')"></span>
                                    <p class="text-xs text-slate-400 leading-relaxed" x-text="comp.reason"></p>
                                </div>
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase shrink-0"
                                      :class="comp.passed ? 'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30' : 'bg-rose-500/20 text-rose-400 border border-rose-500/30'"
                                      x-text="comp.passed ? 'Passed' : 'Failed'"></span>
                            </div>
                        </template>
                    </div>
                </div>
            </template>

            <!-- Instructor Grade Override Box -->
            <div class="bg-slate-900/90 p-4 rounded-xl border border-slate-700/80 space-y-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-[#3ecf8e]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                        <h4 class="text-xs font-bold uppercase tracking-wider text-slate-200">Manual Grade Override &amp; Audit Record</h4>
                    </div>
                    <template x-if="selectedGradeSession?.is_overridden">
                        <span class="px-2 py-0.5 rounded bg-purple-500/20 text-purple-300 border border-purple-500/30 text-[10px] font-semibold">
                            Override in effect
                        </span>
                    </template>
                </div>

                <template x-if="selectedGradeSession?.is_overridden">
                    <div class="text-xs bg-purple-950/40 border border-purple-800/40 p-3 rounded-lg text-purple-200 space-y-1">
                        <div class="flex items-center justify-between font-medium">
                            <span>Active Override: <strong class="text-white" x-text="selectedGradeSession.instructor_grade_override + '%'"></strong> (Original AI: <span x-text="selectedGradeSession.performance_score + '%'"></span>)</span>
                            <span class="text-[10px] text-purple-300" x-text="selectedGradeSession.overridden_by_name ? 'By ' + selectedGradeSession.overridden_by_name + ' • ' + (selectedGradeSession.instructor_overridden_at || '') : ''"></span>
                        </div>
                        <template x-if="selectedGradeSession.instructor_override_reason">
                            <p class="text-[11px] text-purple-300/80 italic" x-text="'&ldquo;' + selectedGradeSession.instructor_override_reason + '&rdquo;'"></p>
                        </template>
                    </div>
                </template>

                <form @submit.prevent="submitGradeOverride()" class="space-y-3">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
                        <div class="space-y-1">
                            <label for="override-score-input" class="text-[11px] font-semibold text-slate-400 uppercase">Override Score (0–100)</label>
                            <input type="number" id="override-score-input" name="override_score" step="0.1" min="0" max="100" x-model="overrideScoreInput" required
                                   class="w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-lg text-sm font-mono text-white focus:outline-none focus:border-[#3ecf8e]">
                        </div>
                        <div class="sm:col-span-2 space-y-1">
                            <label for="override-reason-input" class="text-[11px] font-semibold text-slate-400 uppercase">Audit Note / Reason</label>
                            <input type="text" id="override-reason-input" name="override_reason" x-model="overrideReasonInput" placeholder="e.g. Awarded partial credit for custom error handling logic..."
                                   class="w-full px-3 py-2 bg-slate-950 border border-slate-700 rounded-lg text-xs text-white focus:outline-none focus:border-[#3ecf8e]">
                        </div>
                    </div>

                    <template x-if="overrideFeedbackMessage">
                        <div class="p-2.5 rounded-lg text-xs" :class="overrideFeedbackSuccess ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-rose-500/10 text-rose-400 border border-rose-500/20'" x-text="overrideFeedbackMessage"></div>
                    </template>

                    <div class="flex items-center justify-end gap-2 pt-1">
                        <button type="button" @click="isGradeModalOpen = false" class="px-3.5 py-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-xs font-semibold text-slate-300">
                            Close
                        </button>
                        <button type="submit" :disabled="isSavingOverride" class="px-4 py-1.5 rounded-lg bg-[#3ecf8e] text-slate-950 font-bold text-xs hover:bg-[#00c573] transition disabled:opacity-50 flex items-center gap-1.5">
                            <span x-show="!isSavingOverride">Save Grade Override</span>
                            <span x-show="isSavingOverride">Saving...</span>
                        </button>
                    </div>
                </form>
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
        hasLiveUpdate: false,
        liveUpdateMessage: '',
        selectedStudentName: '',
        selectedAnomalies: [],
        ws: null,
        wsConnected: false,

        // Feature 10: AI Grade Summary & Manual Override State
        isGradeModalOpen: false,
        selectedGradeSession: null,
        overrideScoreInput: '',
        overrideReasonInput: '',
        isSavingOverride: false,
        overrideFeedbackMessage: '',
        overrideFeedbackSuccess: false,

        openGradeModal(sessionData) {
            this.selectedGradeSession = sessionData;
            this.overrideScoreInput = (sessionData.instructor_grade_override !== null && sessionData.instructor_grade_override !== undefined)
                ? sessionData.instructor_grade_override
                : sessionData.performance_score;
            this.overrideReasonInput = sessionData.instructor_override_reason || '';
            this.overrideFeedbackMessage = '';
            this.isGradeModalOpen = true;
        },

        submitGradeOverride() {
            if (!this.selectedGradeSession) return;
            this.isSavingOverride = true;
            this.overrideFeedbackMessage = '';

            const url = `/instructor/sessions/${this.selectedGradeSession.id}/override-grade`;
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';

            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': token,
                },
                body: JSON.stringify({
                    override_score: parseFloat(this.overrideScoreInput),
                    override_reason: this.overrideReasonInput,
                })
            })
            .then(res => {
                if (!res.ok) {
                    return res.json().then(err => { throw new Error(err.message || 'Failed to save grade override.'); });
                }
                return res.json();
            })
            .then(data => {
                this.isSavingOverride = false;
                this.overrideFeedbackSuccess = true;
                this.overrideFeedbackMessage = 'Grade override saved successfully!';
                if (data && data.session) {
                    this.selectedGradeSession.instructor_grade_override = data.session.instructor_grade_override;
                    this.selectedGradeSession.effective_score = data.session.effective_score;
                    this.selectedGradeSession.is_overridden = data.session.is_overridden;
                    this.selectedGradeSession.instructor_override_reason = data.session.override_reason;
                    this.selectedGradeSession.instructor_overridden_at = data.session.overridden_at;
                    this.selectedGradeSession.overridden_by_name = data.session.overridden_by_name;
                }
                setTimeout(() => {
                    window.location.reload();
                }, 800);
            })
            .catch(err => {
                this.isSavingOverride = false;
                this.overrideFeedbackSuccess = false;
                this.overrideFeedbackMessage = err.message || 'Error saving grade override.';
            });
        },

        // Cohort Plagiarism State
        showSimilarityMatrix: false,
        isScanningPlagiarism: false,
        isCompareModalOpen: false,
        selectedPair: null,
        plagiarism: @json($plagiarismAnalysis),

        openCompareModal(pair) {
            this.selectedPair = pair;
            this.isCompareModalOpen = true;
        },

        runPlagiarismScan() {
            this.isScanningPlagiarism = true;
            fetch('{{ route("instructor.monitoring.plagiarism", $laboratory->id) }}')
                .then(res => res.json())
                .then(data => {
                    this.isScanningPlagiarism = false;
                    if (data && data.analysis) {
                        this.plagiarism = data.analysis;
                    }
                })
                .catch(() => {
                    this.isScanningPlagiarism = false;
                });
        },

        init() {
            this.initWebSocket();
        },

        initWebSocket() {
            try {
                if (typeof window.WebSocket === 'undefined') return;
                const isSecure = window.location.protocol === 'https:';
                const wsProtocol = isSecure ? 'wss:' : 'ws:';
                const wsHost = window.location.hostname;
                const wsPort = window.location.port ? window.location.port : (isSecure ? '443' : '80');
                const wsEndpoint = `${wsProtocol}//${wsHost}:${wsPort}/app/certicode-key?protocol=7&client=js&version=8.4.0`;

                this.ws = new WebSocket(wsEndpoint);
                this.ws.onopen = () => {
                    this.wsConnected = true;
                    try {
                        this.ws.send(JSON.stringify({
                            event: 'pusher:subscribe',
                            data: { channel: 'private-instructor.monitoring.{{ $laboratory->id }}' }
                        }));
                    } catch {}
                };
                this.ws.onmessage = (event) => {
                    try {
                        const payload = JSON.parse(event.data);
                        if (payload.event === 'anomaly.detected' || payload.event === 'diff.updated' || payload.event === 'leaderboard.updated') {
                            this.showLiveAlert(payload.event);
                        }
                    } catch {}
                };
                this.ws.onclose = () => {
                    this.wsConnected = false;
                };
            } catch {
                this.wsConnected = false;
            }
        },

        showLiveAlert(eventType) {
            this.hasLiveUpdate = true;
            this.liveUpdateMessage = eventType === 'anomaly.detected'
                ? '⚠️ New proctoring anomaly detected!'
                : '⚡ Live student code diff / activity updated!';
        },

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
window.instructorMonitor = instructorMonitor;
if (window.Alpine) {
    window.Alpine.data('instructorMonitor', instructorMonitor);
} else {
    document.addEventListener('alpine:init', () => {
        window.Alpine.data('instructorMonitor', instructorMonitor);
    });
}
</script>
@endsection

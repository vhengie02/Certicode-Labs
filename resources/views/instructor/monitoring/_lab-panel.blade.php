{{-- Live monitoring for one lab. Included by classes/telemetry.blade.php when ?lab= is set. --}}
<div class="space-y-6" x-data="instructorMonitor()">
    <!-- Lab header: title on the left; live-lab timer and controls on the right -->
    @php
        $isLive = $laboratory->isLiveLab();
        $liveRemaining = $isLive ? max(0, (int) $laboratory->getRemainingLiveSeconds()) : 0;
        $leftoverMinutes = max(1, (int) ceil($liveRemaining / 60));
    @endphp
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
        <div class="min-w-0">
            <div class="flex items-center gap-2.5 flex-wrap">
                <h2 class="text-xl font-semibold text-[#ededed] truncate">{{ $laboratory->title }}</h2>
                @if($laboratory->is_group_lab)
                    <span class="px-2 py-0.5 rounded-full border border-[#2e2e2e] font-mono text-[10px] uppercase tracking-[0.12em] text-[#888888]">group lab</span>
                @endif
            </div>
            <p class="text-sm text-[#888888] mt-1">
                {{ $laboratory->module->title ?? 'Module' }}
                <span aria-hidden="true">&middot;</span>
                {{ $isLive ? 'Live lab' : 'Open lab, self-paced' }}
                <span aria-hidden="true">&middot;</span>
                <a href="{{ route('laboratories.show', $laboratory->id) }}" class="text-[#a3a3a3] hover:text-[#ededed] underline-offset-2 hover:underline">Lab details</a>
            </p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
            @if($isLive)
                @if($laboratory->isLiveActive())
                    {{-- Shared timer: counts down in the browser from the server's remaining time --}}
                    <div class="live-timer flex items-center gap-2.5 h-10 pl-3 pr-2 rounded-lg border border-[#3ecf8e]/35 bg-[#3ecf8e]/[0.06]"
                         data-remaining="{{ $liveRemaining }}" role="timer" aria-label="Time left in this live lab">
                        <span class="relative flex h-2 w-2" aria-hidden="true">
                            <span class="absolute inline-flex h-full w-full rounded-full bg-[#3ecf8e] opacity-60 animate-ping"></span>
                            <span class="relative inline-flex h-2 w-2 rounded-full bg-[#3ecf8e]"></span>
                        </span>
                        <span class="font-mono text-[11px] uppercase tracking-[0.14em] text-[#3ecf8e]">Live</span>
                        <span class="live-timer-clock font-mono text-lg font-semibold tabular-nums text-[#ededed]">{{ $liveRemaining >= 3600 ? sprintf('%d:%02d:%02d', intdiv($liveRemaining, 3600), intdiv($liveRemaining % 3600, 60), $liveRemaining % 60) : sprintf('%02d:%02d', intdiv($liveRemaining, 60), $liveRemaining % 60) }}</span>
                        <span class="text-xs text-[#888888]">left</span>
                        <x-info-tip align="right">Every student shares this one timer. Students who join late only get the time that's left, and every open workspace is submitted automatically when it reaches zero.</x-info-tip>
                    </div>
                    <form action="{{ route('laboratories.end-live', $laboratory->id) }}" method="POST" onsubmit="return confirm('End this live lab now? Every open student workspace will be submitted and graded.');">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-2 h-10 px-3.5 rounded-lg border border-red-500/30 bg-red-500/10 text-sm font-medium text-red-300 hover:bg-red-500/20 transition-colors">
                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><rect x="6" y="6" width="12" height="12" rx="2"/></svg>
                            End lab
                        </button>
                    </form>
                @elseif($laboratory->isLiveNotStarted())
                    <span class="inline-flex items-center gap-2 h-10 px-3 rounded-lg border border-amber-400/30 bg-amber-400/[0.06] text-sm text-amber-300">
                        <span class="h-2 w-2 rounded-full bg-amber-400" aria-hidden="true"></span>
                        Not started
                        <x-info-tip align="right">Students can't enter this lab until you start it. Starting it begins one shared countdown for everyone.</x-info-tip>
                    </span>
                    <form action="{{ route('laboratories.open-live', $laboratory->id) }}" method="POST" class="flex items-center gap-2">
                        @csrf
                        <label class="flex items-center gap-1.5 h-10 px-3 rounded-lg border border-[#2e2e2e] bg-[#171717] text-sm text-[#888888]">
                            <span class="sr-only">Duration in minutes</span>
                            <input type="number" name="duration_minutes" value="{{ $laboratory->live_duration_minutes ?? $laboratory->time_limit ?? 60 }}" min="1" max="600"
                                   class="w-12 bg-transparent text-right font-mono text-[#ededed] focus:outline-none">
                            min
                        </label>
                        <button type="submit" class="inline-flex items-center gap-2 h-10 px-4 rounded-lg bg-[#3ecf8e] text-sm font-semibold text-[#06150e] hover:bg-[#00c573] transition-colors">
                            <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5.5v13a1 1 0 0 0 1.5.86l10.5-6.5a1 1 0 0 0 0-1.72L9.5 4.64A1 1 0 0 0 8 5.5z"/></svg>
                            Start live lab
                        </button>
                    </form>
                @else
                    <span class="inline-flex items-center gap-2 h-10 px-3 rounded-lg border border-[#2e2e2e] bg-[#171717] text-sm text-[#a3a3a3]">
                        <span class="h-2 w-2 rounded-full bg-[#666666]" aria-hidden="true"></span>
                        Ended
                    </span>
                    <form action="{{ route('laboratories.reopen-live', $laboratory->id) }}" method="POST" class="flex items-center gap-2">
                        @csrf
                        @if($liveRemaining > 0)
                            <button type="submit" class="inline-flex items-center gap-2 h-10 px-3.5 rounded-lg border border-[#2e2e2e] bg-[#171717] text-sm font-medium text-[#ededed] hover:border-[#383838] transition-colors" title="Resumes the shared timer with the time that was left">
                                Reopen ({{ $leftoverMinutes }} min left)
                            </button>
                        @else
                            <label class="flex items-center gap-1.5 h-10 px-3 rounded-lg border border-[#2e2e2e] bg-[#171717] text-sm text-[#888888]">
                                <span class="sr-only">Extra minutes</span>
                                +<input type="number" name="extend_minutes" value="15" min="1" max="180" class="w-10 bg-transparent text-right font-mono text-[#ededed] focus:outline-none">
                                min
                            </label>
                            <button type="submit" class="inline-flex items-center h-10 px-3.5 rounded-lg border border-[#2e2e2e] bg-[#171717] text-sm font-medium text-[#ededed] hover:border-[#383838] transition-colors">
                                Extend and reopen
                            </button>
                        @endif
                    </form>
                @endif
            @endif

            @if($laboratory->is_group_lab)
                <div class="flex items-center h-10 rounded-lg bg-[#171717] border border-[#2e2e2e] p-1 text-xs" role="group" aria-label="Sort roster">
                    <a href="{{ request()->fullUrlWithQuery(['sort' => 'name']) }}" @if($sortBy !== 'group') aria-current="true" @endif
                       class="px-3 py-1.5 rounded-md font-medium transition-colors {{ $sortBy !== 'group' ? 'bg-[#3ecf8e] text-[#06150e] font-semibold' : 'text-[#888888] hover:text-[#ededed]' }}">By student</a>
                    <a href="{{ request()->fullUrlWithQuery(['sort' => 'group']) }}" @if($sortBy === 'group') aria-current="true" @endif
                       class="px-3 py-1.5 rounded-md font-medium transition-colors {{ $sortBy === 'group' ? 'bg-[#3ecf8e] text-[#06150e] font-semibold' : 'text-[#888888] hover:text-[#ededed]' }}">By team</a>
                </div>
            @endif

            <button type="button" @click="refreshData()" class="inline-flex items-center justify-center h-10 w-10 rounded-lg bg-[#171717] border border-[#2e2e2e] text-[#a3a3a3] hover:text-[#ededed] hover:border-[#383838] transition-colors" aria-label="Refresh live data" title="Refresh">
                <svg class="w-4 h-4" :class="{ 'animate-spin': isRefreshing }" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
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


    {{-- KPI Metrics --}}
    @php
        $flaggedPairs = $plagiarismAnalysis['flagged_pairs_count'] ?? 0;
    @endphp
    <dl class="grid grid-cols-2 lg:grid-cols-5 gap-3 sm:gap-4">
        <div class="rounded-xl border border-[#2e2e2e] bg-[#171717] p-5">
            <dt class="flex items-center gap-1.5 font-mono text-[11px] uppercase tracking-[0.14em] text-[#888888]">
                Connected now
                <x-info-tip align="left">Students whose VS Code extension checked in during the last 75 seconds, out of everyone who has opened this lab. The extension checks in every 15–20 seconds, so a student who closes VS Code drops off within about a minute.</x-info-tip>
            </dt>
            <dd class="mt-2 flex items-baseline gap-1.5">
                <span class="cc-display text-3xl font-bold tabular-nums text-[#ededed]">{{ $activeSessions }}</span>
                <span class="text-xs text-[#888888]">of {{ $totalStudents }}</span>
            </dd>
            <dd class="mt-3 h-1.5 w-full rounded-full bg-[#232323] overflow-hidden" aria-hidden="true">
                <span class="block h-full rounded-full bg-[#3ecf8e]" style="width: {{ $totalStudents > 0 ? round(($activeSessions / $totalStudents) * 100) : 0 }}%"></span>
            </dd>
        </div>

        <div class="rounded-xl border border-[#2e2e2e] bg-[#171717] p-5">
            <dt class="flex items-center gap-1.5 font-mono text-[11px] uppercase tracking-[0.14em] text-[#888888]">
                Avg typing speed
                <x-info-tip>Average words per minute across everyone in this lab, counting 5 keystrokes as one word. Students who haven't typed yet count as 0, so the average starts low and rises as people work.</x-info-tip>
            </dt>
            <dd class="mt-2 flex items-baseline gap-1.5">
                <span class="cc-display text-3xl font-bold tabular-nums text-[#ededed]">{{ $avgWpm }}</span>
                <span class="text-xs text-[#888888]">wpm</span>
            </dd>
        </div>

        <div class="rounded-xl border border-[#2e2e2e] bg-[#171717] p-5">
            <dt class="flex items-center gap-1.5 font-mono text-[11px] uppercase tracking-[0.14em] text-[#888888]">
                Integrity flags
                <x-info-tip>Every flag raised in this lab, including ones you've resolved: switching away from VS Code 3+ times in 2 minutes, suspicious pastes, the camera not seeing exactly one face, and long idle periods. Open a student's "Anomalies" to see each one.</x-info-tip>
            </dt>
            <dd class="mt-2 flex items-baseline gap-1.5">
                <span class="cc-display text-3xl font-bold tabular-nums {{ $totalAnomalies > 0 ? 'text-amber-400' : 'text-[#ededed]' }}">{{ $totalAnomalies }}</span>
                <span class="text-xs text-[#888888]">events</span>
            </dd>
        </div>

        <div class="rounded-xl border border-[#2e2e2e] bg-[#171717] p-5">
            <dt class="flex items-center gap-1.5 font-mono text-[11px] uppercase tracking-[0.14em] text-[#888888]">
                Submitted
                <x-info-tip>Sessions that are finished and graded: submitted by the student, ended by you, or auto-submitted when a live lab's timer ran out.</x-info-tip>
            </dt>
            <dd class="mt-2 flex items-baseline gap-1.5">
                <span class="cc-display text-3xl font-bold tabular-nums text-[#ededed]">{{ $completedSessions }}</span>
                <span class="text-xs text-[#888888]">of {{ $totalStudents }}</span>
            </dd>
        </div>

        <div class="col-span-2 lg:col-span-1 rounded-xl border border-[#2e2e2e] bg-[#171717] p-5">
            <dt class="flex items-center gap-1.5 font-mono text-[11px] uppercase tracking-[0.14em] text-[#888888]">
                Similar code
                <x-info-tip align="right">Pairs of students whose submitted code is at least 50% alike (75%+ is high risk). Comments are stripped and variable names, strings and numbers are ignored, so renaming things doesn't hide copying. Matching starter code can raise the score, so check the side-by-side view before acting.</x-info-tip>
            </dt>
            <dd class="mt-2 flex items-baseline gap-1.5">
                <span class="cc-display text-3xl font-bold tabular-nums {{ $flaggedPairs > 0 ? 'text-red-400' : 'text-[#ededed]' }}" x-text="plagiarism.flagged_pairs_count ?? {{ (int) $flaggedPairs }}">{{ $flaggedPairs }}</span>
                <span class="text-xs text-[#888888]">flagged pairs</span>
            </dd>
            <dd class="mt-1"><a href="#plagiarism-section" class="text-xs font-medium text-[#3ecf8e] hover:underline underline-offset-2">Compare code &darr;</a></dd>
        </div>
    </dl>

    <!-- Student & Team Monitoring Roster -->
    <div class="glass-panel rounded-xl border border-slate-800">
        <div class="p-4 rounded-t-xl border-b border-slate-800 bg-slate-950/40 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <h3 class="font-bold text-white text-sm">{{ $laboratory->is_group_lab ? 'Active Student / Team Workspaces' : 'Active Student Workspaces' }}</h3>
                <p class="text-xs text-slate-400 mt-0.5">Real-time status, WPM tracking, task progress, and anomaly audit trails.</p>
            </div>
            <input type="text" id="monitoring-search-query" name="search_query" x-model="searchQuery" placeholder="{{ $laboratory->is_group_lab ? 'Filter by student or team...' : 'Filter by student name...' }}" 
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
                         x-show="matchesSearch({{ \Illuminate\Support\Js::from(strtolower($user->name ?? '')) }}, {{ \Illuminate\Support\Js::from(strtolower($group->name ?? '')) }})">
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
                                            @if($session->isActivelyConnected())
                                                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[10px] font-semibold bg-emerald-500/10 text-emerald-400 border border-emerald-500/30 shrink-0">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
                                                    Active
                                                </span>
                                            @elseif($session->isIdle())
                                                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[10px] font-semibold bg-amber-500/10 text-amber-400 border border-amber-500/30 shrink-0" title="No heartbeat recently">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                                                    Idle
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1.5 px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-800 text-slate-400 border border-slate-700 shrink-0" title="Disconnected - no active heartbeat">
                                                    <span class="w-1.5 h-1.5 rounded-full bg-slate-500"></span>
                                                    Offline
                                                </span>
                                            @endif
                                        @elseif($session->status === 'abandoned')
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-rose-500/10 text-rose-400 border border-rose-500/30 shrink-0" title="Session timed out or abandoned">
                                                Abandoned
                                            </span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-800 text-slate-400 border border-slate-700 shrink-0">
                                                Completed
                                            </span>
                                        @endif
                                    </div>
                                    <div class="text-xs text-slate-400 mt-0.5 flex items-center gap-1.5 truncate">
                                        <span class="truncate" title="{{ $user->email ?? 'No email' }}">{{ $user->email ?? 'No email' }}</span>
                                        @if($laboratory->is_group_lab && $group)
                                            <span class="text-slate-600 shrink-0">•</span>
                                            <span class="text-[#3ecf8e] font-semibold flex items-center gap-1 shrink-0 truncate" title="{{ $group->name }}">
                                                <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                                <span class="truncate">{{ $group->name }}</span>
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>

                            {{-- Workspace Stats (Fixed Grid & Width for Vertical Alignment) --}}
                            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 sm:gap-3 text-xs w-full lg:w-[440px] xl:w-[480px] shrink-0">
                                <!-- WPM Widget -->
                                <div class="bg-slate-950/60 p-2.5 rounded-lg border border-slate-800 text-center flex flex-col justify-center min-h-[64px]">
                                    <span class="flex items-center justify-center gap-1 min-w-0"><span class="text-slate-500 text-[10px] uppercase font-bold truncate">Live WPM</span><x-info-tip align="left" class="shrink-0">This student's typing speed in words per minute, as reported by the extension (5 keystrokes = 1 word).</x-info-tip></span>
                                    <span class="text-base font-mono font-bold text-sky-400 my-0.5">{{ $session->wpm ?? 0 }}</span>
                                    <span class="text-[10px] text-slate-500 block truncate">words/min</span>
                                </div>

                                <!-- Tasks Completed -->
                                <div class="bg-slate-950/60 p-2.5 rounded-lg border border-slate-800 text-center flex flex-col justify-center min-h-[64px]">
                                    <span class="flex items-center justify-center gap-1 min-w-0"><span class="text-slate-500 text-[10px] uppercase font-bold truncate">Tasks Done</span><x-info-tip align="center" class="shrink-0">Lab tasks this student's code has passed so far. Tasks are checked each time they run their code.</x-info-tip></span>
                                    <span class="text-base font-mono font-bold text-emerald-400 my-0.5">{{ $tasksCount }}</span>
                                    <span class="text-[10px] text-slate-500 block truncate">completed</span>
                                </div>

                                <!-- Focus Losses -->
                                <div class="bg-slate-950/60 p-2.5 rounded-lg border border-slate-800 text-center flex flex-col justify-center min-h-[64px]">
                                    <span class="flex items-center justify-center gap-1 min-w-0"><span class="text-slate-500 text-[10px] uppercase font-bold truncate">Focus Lost</span><x-info-tip align="center" class="shrink-0">How many times VS Code lost focus, for example switching to a browser. Turns amber above 2; 3 or more within 2 minutes raises an integrity flag.</x-info-tip></span>
                                    <span class="text-base font-mono font-bold my-0.5 {{ ($session->focus_lost_count ?? 0) > 2 ? 'text-amber-400' : 'text-slate-300' }}">
                                        {{ $session->focus_lost_count ?? 0 }}
                                    </span>
                                    <span class="text-[10px] text-slate-500 block truncate">window switches</span>
                                </div>

                                <!-- Paste Anomalies -->
                                <div class="bg-slate-950/60 p-2.5 rounded-lg border border-slate-800 text-center flex flex-col justify-center min-h-[64px]">
                                    <span class="flex items-center justify-center gap-1 min-w-0"><span class="text-slate-500 text-[10px] uppercase font-bold truncate">Paste Flags</span><x-info-tip align="right" class="shrink-0">Pastes of 25+ characters that include a line break or more than 3 words. Pastes of the student's own starter code or of snippets from the lab chat are not counted.</x-info-tip></span>
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
                                <button @click="openGradeModal({{ \Illuminate\Support\Js::from($gradeSessionPayload) }})"
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
                                <button @click="openAnomalyModal({{ $session->id }}, {{ \Illuminate\Support\Js::from($user->name ?? 'Student') }}, {{ \Illuminate\Support\Js::from($anomaliesList) }})"
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
                        @if($laboratory->is_group_lab && $session->code_contributions && count($session->code_contributions) > 0)
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
                    <span class="text-xs text-rose-400 font-medium">Review the matching code below</span>
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

                        <!-- Idle timeout metadata preview -->
                        <template x-if="item.type === 'idle_timeout' && item.metadata">
                            <div class="mt-2 bg-slate-900/90 p-2.5 rounded-lg border border-amber-500/20 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    <span class="text-xs font-semibold text-amber-300">Continuous Inactivity: <span x-text="(item.metadata.idle_minutes || 10) + ' min'"></span></span>
                                </div>
                                <template x-if="item.metadata.shared_remaining_minutes !== null && item.metadata.shared_remaining_minutes !== undefined">
                                    <span class="text-[11px] font-mono text-slate-400 bg-slate-800/80 px-2 py-0.5 rounded border border-slate-700" x-text="item.metadata.shared_remaining_minutes + ' min remain in Live Lab'"></span>
                                </template>
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
                        @if($laboratory->is_group_lab)
                            <template x-if="selectedGradeSession?.is_team">
                                <span class="px-1.5 py-0.5 rounded bg-sky-500/20 text-sky-300 border border-sky-500/30 text-[10px]" x-text="'Team: ' + selectedGradeSession.team_name"></span>
                            </template>
                            <template x-if="selectedGradeSession?.is_team">
                                <span class="text-[11px] text-slate-500 italic">(Evaluating combined submission as single unit)</span>
                            </template>
                        @endif
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
            const realtime = @json(\App\Support\Realtime::clientConfig());
            if (realtime && typeof window.WebSocket !== 'undefined') {
                this.initWebSocket(realtime);
            } else {
                this.initPolling();
            }
        },

        initPolling() {
            // Fallback when live updates (Pusher) are not configured
            const countAnomalies = (data) => (data.sessions || []).reduce((sum, s) => sum + (s.anomalies ? s.anomalies.length : 0), 0);
            setInterval(() => {
                fetch('{{ route("instructor.monitoring.data", $laboratory->id) }}', { headers: { 'Accept': 'application/json' } })
                    .then(res => res.json())
                    .then(data => {
                        if (!data || !Array.isArray(data.sessions)) return;
                        const total = countAnomalies(data);
                        if (this.lastAnomalyCount !== undefined && total > this.lastAnomalyCount) {
                            this.showLiveAlert('anomaly.detected');
                        }
                        this.lastAnomalyCount = total;
                    })
                    .catch(() => {});
            }, 10000);
        },

        // Minimal Pusher Channels client: connect, sign the private channel via /broadcasting/auth, subscribe.
        initWebSocket(realtime) {
            const channel = 'private-instructor.lab.{{ $laboratory->id }}';
            const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';
            let retryDelay = 2000;

            const connect = () => {
                const ws = new WebSocket(`wss://${realtime.ws_host}/app/${realtime.key}?protocol=7&client=js&version=8.4.0&flash=false`);
                this.ws = ws;

                ws.onmessage = async (event) => {
                    let payload;
                    try { payload = JSON.parse(event.data); } catch { return; }

                    if (payload.event === 'pusher:connection_established') {
                        const socketId = JSON.parse(payload.data).socket_id;
                        try {
                            const res = await fetch('/broadcasting/auth', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
                                body: new URLSearchParams({ socket_id: socketId, channel_name: channel })
                            });
                            if (!res.ok) throw new Error('auth ' + res.status);
                            const auth = await res.json();
                            ws.send(JSON.stringify({ event: 'pusher:subscribe', data: { channel, auth: auth.auth } }));
                        } catch {
                            ws.close();
                        }
                    } else if (payload.event === 'pusher_internal:subscription_succeeded') {
                        this.wsConnected = true;
                        retryDelay = 2000;
                    } else if (payload.event === 'pusher:ping') {
                        ws.send(JSON.stringify({ event: 'pusher:pong', data: {} }));
                    } else if (['anomaly.detected', 'diff.updated', 'leaderboard.updated'].includes(payload.event)) {
                        this.showLiveAlert(payload.event);
                    }
                };
                ws.onclose = () => {
                    this.wsConnected = false;
                    if (this.ws !== ws) return;
                    setTimeout(connect, retryDelay);
                    retryDelay = Math.min(retryDelay * 2, 60000);
                };
            };

            connect();
        },

        showLiveAlert(eventType) {
            this.hasLiveUpdate = true;
            this.liveUpdateMessage = eventType === 'anomaly.detected'
                ? 'New integrity flag from a student.'
                : 'Student activity updated.';
        },

        matchesSearch(name, group) {
            if (!this.searchQuery.trim()) return true;
            const q = this.searchQuery.toLowerCase();
            return name.includes(q) || ({{ $laboratory->is_group_lab ? 'true' : 'false' }} && Boolean(group) && group.includes(q));
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
// Live lab timer: counts down in the browser from the server's remaining seconds.
// At zero the page reloads, which lets the server close the lab and show "Ended".
(() => {
    const format = (secs) => {
        const h = Math.floor(secs / 3600), m = Math.floor((secs % 3600) / 60), s = secs % 60;
        const pad = (n) => String(n).padStart(2, '0');
        return h > 0 ? `${h}:${pad(m)}:${pad(s)}` : `${pad(m)}:${pad(s)}`;
    };
    document.querySelectorAll('.live-timer').forEach((timer) => {
        const clock = timer.querySelector('.live-timer-clock');
        const endsAt = Date.now() + parseInt(timer.dataset.remaining || '0', 10) * 1000;
        const tick = () => {
            const left = Math.max(0, Math.round((endsAt - Date.now()) / 1000));
            clock.textContent = format(left);
            timer.classList.toggle('is-ending', left > 0 && left <= 300);
            if (left === 0) {
                timer.classList.add('is-over');
                setTimeout(() => window.location.reload(), 2500);
                return;
            }
            setTimeout(tick, 1000);
        };
        tick();
    });
})();

window.instructorMonitor = instructorMonitor;
if (window.Alpine) {
    window.Alpine.data('instructorMonitor', instructorMonitor);
} else {
    document.addEventListener('alpine:init', () => {
        window.Alpine.data('instructorMonitor', instructorMonitor);
    });
}
</script>

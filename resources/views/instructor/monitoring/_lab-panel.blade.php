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
            Refresh
        </button>
    </div>


    {{-- KPI Metrics --}}
    @php
        $flaggedPairs = $plagiarismAnalysis['flagged_pairs_count'] ?? 0;
    @endphp
    <dl class="grid grid-cols-2 lg:grid-cols-5 gap-3 sm:gap-4">
        <div class="rounded-xl border border-[#2e2e2e] bg-[#171717] p-5">
            <dt class="flex items-center gap-1.5 text-sm text-[#888888]">
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
            <dt class="flex items-center gap-1.5 text-sm text-[#888888]">
                Avg typing speed
                <x-info-tip>Average words per minute across everyone in this lab, counting 5 keystrokes as one word. Students who haven't typed yet count as 0, so the average starts low and rises as people work.</x-info-tip>
            </dt>
            <dd class="mt-2 flex items-baseline gap-1.5">
                <span class="cc-display text-3xl font-bold tabular-nums text-[#ededed]">{{ $avgWpm }}</span>
                <span class="text-xs text-[#888888]">wpm</span>
            </dd>
        </div>

        <div class="rounded-xl border border-[#2e2e2e] bg-[#171717] p-5">
            <dt class="flex items-center gap-1.5 text-sm text-[#888888]">
                Open flags
                <x-info-tip>Flags you haven't resolved yet: switching away from VS Code 3+ times in 2 minutes, suspicious pastes, the camera not seeing exactly one face, and long idle periods. Use a student's Flags button to see each one.</x-info-tip>
            </dt>
            <dd class="mt-2 flex items-baseline gap-1.5">
                @php $openFlagTotal = $sessions->sum(fn ($s) => $s->anomalies->where('resolved', false)->count()); @endphp
                <span class="cc-display text-3xl font-bold tabular-nums {{ $openFlagTotal > 0 ? 'text-amber-400' : 'text-[#ededed]' }}">{{ $openFlagTotal }}</span>
                <span class="text-xs text-[#888888]">of {{ $totalAnomalies }} raised</span>
            </dd>
        </div>

        <div class="rounded-xl border border-[#2e2e2e] bg-[#171717] p-5">
            <dt class="flex items-center gap-1.5 text-sm text-[#888888]">
                Submitted
                <x-info-tip>Sessions that are finished and graded: submitted by the student, ended by you, or auto-submitted when a live lab's timer ran out.</x-info-tip>
            </dt>
            <dd class="mt-2 flex items-baseline gap-1.5">
                <span class="cc-display text-3xl font-bold tabular-nums text-[#ededed]">{{ $completedSessions }}</span>
                <span class="text-xs text-[#888888]">of {{ $totalStudents }}</span>
            </dd>
        </div>

        <div class="col-span-2 lg:col-span-1 rounded-xl border border-[#2e2e2e] bg-[#171717] p-5">
            <dt class="flex items-center gap-1.5 text-sm text-[#888888]">
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

    {{-- Roster: one row per student, sortable and filterable in the browser --}}
    @php
        $taskTotal = count($laboratory->tasks_definition ?? []);
        $stateLabels = ['active' => 'Active', 'idle' => 'Idle', 'offline' => 'Offline', 'completed' => 'Submitted', 'abandoned' => 'Timed out'];
        $rosterIndex = $sessions->map(function ($s) use ($taskTotal) {
            $state = $s->getConnectionState();
            $openFlags = $s->anomalies->where('resolved', false)->count();
            $done = is_array($s->completed_tasks) ? count($s->completed_tasks) : 0;
            return [
                'id' => $s->id,
                'name' => strtolower($s->user->name ?? ''),
                'group' => strtolower($s->group->name ?? ''),
                'state' => $state,
                // Higher means look sooner: open flags first, then students who dropped off, then warning counts
                'attention' => $openFlags * 3 + (in_array($state, ['idle', 'offline'], true) ? 2 : 0)
                    + (($s->focus_lost_count ?? 0) > 2 ? 1 : 0) + (($s->paste_anomaly_count ?? 0) > 0 ? 1 : 0),
                'progress' => $taskTotal > 0 ? $done / $taskTotal : 0,
                'wpm' => (int) ($s->wpm ?? 0),
            ];
        })->values();
        $filters = ['all' => 'All', 'attention' => 'Needs attention', 'active' => 'Active', 'idle' => 'Idle', 'offline' => 'Offline', 'completed' => 'Submitted'];
    @endphp
    <section class="rounded-xl border border-[#2e2e2e] bg-[#171717]" aria-labelledby="roster-heading">
        <div class="p-4 sm:p-5 border-b border-[#2e2e2e] space-y-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <h3 id="roster-heading" class="text-base font-semibold text-[#ededed]">
                    {{ $laboratory->is_group_lab ? 'Students and teams' : 'Students' }}
                    <span class="ml-1.5 text-sm font-normal text-[#888888] tabular-nums">{{ $sessions->count() }}</span>
                </h3>
                <div class="flex flex-col sm:flex-row gap-2 sm:items-center">
                    <label class="relative block">
                        <span class="sr-only">Search students</span>
                        <svg class="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 w-3.5 h-3.5 text-[#666666]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path stroke-linecap="round" d="m20 20-3.5-3.5"/></svg>
                        <input type="search" id="monitoring-search-query" name="search_query" x-model="searchQuery"
                               placeholder="{{ $laboratory->is_group_lab ? 'Search student or team' : 'Search students' }}"
                               class="block h-9 w-full sm:w-56 rounded-lg border border-[#2e2e2e] bg-[#0f0f0f] pl-8 pr-3 text-sm text-[#ededed] placeholder-[#666666] focus:outline-none focus:border-[#3ecf8e]/60 focus:ring-2 focus:ring-[#3ecf8e]/15 transition-colors">
                    </label>
                    <label class="flex items-center gap-2 h-9 rounded-lg border border-[#2e2e2e] bg-[#0f0f0f] pl-3 pr-2 text-sm text-[#888888]">
                        Sort
                        <select x-model="sortKey" class="bg-transparent text-[#ededed] focus:outline-none pr-1 cursor-pointer">
                            <option value="attention" class="bg-[#171717]">Needs attention first</option>
                            <option value="name" class="bg-[#171717]">Name</option>
                            <option value="progress" class="bg-[#171717]">Most tasks done</option>
                            <option value="wpm" class="bg-[#171717]">Typing speed</option>
                        </select>
                    </label>
                    @if($laboratory->is_group_lab)
                        <div class="flex items-center h-9 rounded-lg bg-[#0f0f0f] border border-[#2e2e2e] p-1 text-xs" role="group" aria-label="Group rows">
                            <a href="{{ request()->fullUrlWithQuery(['sort' => 'name']) }}" @if($sortBy !== 'group') aria-current="true" @endif
                               class="px-2.5 py-1 rounded-md transition-colors {{ $sortBy !== 'group' ? 'bg-[#2e2e2e] text-[#ededed]' : 'text-[#888888] hover:text-[#ededed]' }}">By student</a>
                            <a href="{{ request()->fullUrlWithQuery(['sort' => 'group']) }}" @if($sortBy === 'group') aria-current="true" @endif
                               class="px-2.5 py-1 rounded-md transition-colors {{ $sortBy === 'group' ? 'bg-[#2e2e2e] text-[#ededed]' : 'text-[#888888] hover:text-[#ededed]' }}">By team</a>
                        </div>
                    @endif
                </div>
            </div>

            @if($sessions->isNotEmpty())
                <div class="flex gap-1.5 overflow-x-auto -mx-1 px-1 pb-0.5" role="tablist" aria-label="Filter students by state">
                    @foreach($filters as $key => $label)
                        <button type="button" role="tab" @click="statusFilter = '{{ $key }}'" :aria-selected="statusFilter === '{{ $key }}'"
                                x-show="'{{ $key }}' === 'all' || filterCount('{{ $key }}') > 0"
                                class="shrink-0 inline-flex items-center gap-1.5 h-8 px-3 rounded-full border text-xs font-medium transition-colors active:scale-[0.98]"
                                :class="statusFilter === '{{ $key }}' ? 'border-[#3ecf8e]/50 bg-[#3ecf8e]/10 text-[#ededed]' : 'border-[#2e2e2e] text-[#a3a3a3] hover:text-[#ededed] hover:border-[#3e3e3e]'">
                            {{ $label }}
                            <span class="tabular-nums {{ $key === 'attention' ? 'text-amber-300' : 'text-[#888888]' }}" x-text="filterCount('{{ $key }}')"></span>
                        </button>
                    @endforeach
                </div>
            @endif
        </div>

        @if($sessions->isEmpty())
            <div class="px-6 py-14 text-center">
                <p class="text-sm font-medium text-[#ededed]">No one has started this lab yet</p>
                <p class="mt-1.5 text-sm text-[#888888] max-w-sm mx-auto">Students show up here as soon as they open the lab in VS Code.</p>
            </div>
        @else
            {{-- Column headings (desktop) --}}
            <div class="hidden lg:grid lg:grid-cols-[minmax(0,1.7fr)_6.5rem_minmax(7rem,1fr)_4.5rem_4.5rem_4rem_7rem_7.5rem_6rem] items-center gap-4 px-5 py-2.5 border-b border-[#2e2e2e] text-xs text-[#888888]" aria-hidden="true">
                <span>Student</span>
                <span>Status</span>
                <span>Tasks</span>
                <span class="text-right">Typing</span>
                <span class="text-right" title="Times VS Code lost focus">Focus lost</span>
                <span class="text-right" title="Suspicious pastes">Pastes</span>
                <span>Flags</span>
                <span>Grade</span>
                <span class="sr-only">Actions</span>
            </div>

            <div class="flex flex-col divide-y divide-[#2e2e2e]">
                @foreach($sessions as $session)
                    @php
                        $user = $session->user;
                        $group = $session->group;
                        $tasksCount = is_array($session->completed_tasks) ? count($session->completed_tasks) : 0;
                        $anomaliesList = $session->anomalies;
                        $openFlags = $anomaliesList->where('resolved', false)->count();
                        $diffStats = $session->diff_stats ?? [];
                        $linesAdded = (int) ($diffStats['lines_added'] ?? 0);
                        $linesDeleted = (int) ($diffStats['lines_deleted'] ?? 0);
                        $state = $session->getConnectionState();
                        $focusLost = (int) ($session->focus_lost_count ?? 0);
                        $pastes = (int) ($session->paste_anomaly_count ?? 0);
                        $wpm = (int) ($session->wpm ?? 0);
                        $isGraded = $session->status === 'completed';
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
                        $stateStyles = [
                            'active' => ['dot' => 'bg-[#3ecf8e]', 'text' => 'text-[#ededed]'],
                            'idle' => ['dot' => 'bg-amber-400', 'text' => 'text-amber-200'],
                            'offline' => ['dot' => 'bg-[#666666]', 'text' => 'text-[#a3a3a3]'],
                            'completed' => ['dot' => 'bg-[#3ecf8e]/50', 'text' => 'text-[#a3a3a3]'],
                            'abandoned' => ['dot' => 'bg-red-400/70', 'text' => 'text-[#a3a3a3]'],
                        ][$state] ?? ['dot' => 'bg-[#666666]', 'text' => 'text-[#a3a3a3]'];
                    @endphp
                    <div class="roster-row group/row px-4 sm:px-5 py-3.5 transition-colors hover:bg-white/[0.02] {{ $openFlags > 0 ? 'shadow-[inset_2px_0_0_0_rgba(251,191,36,0.7)]' : '' }}"
                         x-show="showRow({{ $session->id }})" :style="{ order: rowOrder({{ $session->id }}) }">
                        <div class="grid grid-cols-3 lg:grid-cols-[minmax(0,1.7fr)_6.5rem_minmax(7rem,1fr)_4.5rem_4.5rem_4rem_7rem_7.5rem_6rem] items-center gap-x-4 gap-y-3">
                            {{-- Student --}}
                            <div class="col-span-2 lg:col-span-1 flex items-center gap-3 min-w-0">
                                <span class="w-9 h-9 rounded-lg bg-[#232323] border border-[#2e2e2e] flex items-center justify-center text-xs font-semibold text-[#a3a3a3] shrink-0" aria-hidden="true">{{ strtoupper(substr($user->name ?? 'S', 0, 2)) }}</span>
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-[#ededed] truncate" title="{{ $user->name ?? 'Unknown Student' }}">{{ $user->name ?? 'Unknown Student' }}</p>
                                    <p class="text-xs text-[#888888] truncate">
                                        @if($laboratory->is_group_lab && $group)
                                            <span class="text-[#3ecf8e]">{{ $group->name }}</span> <span aria-hidden="true">&middot;</span>
                                        @endif
                                        <span title="{{ $user->email ?? '' }}">{{ $user->email ?? 'No email' }}</span>
                                    </p>
                                </div>
                            </div>

                            {{-- Status --}}
                            <div class="flex items-center justify-end lg:justify-start gap-2 text-sm {{ $stateStyles['text'] }}" title="{{ ['active' => 'Heartbeat in the last 75 seconds', 'idle' => 'No heartbeat for over a minute', 'offline' => 'No heartbeat for over 5 minutes', 'completed' => 'Finished and graded', 'abandoned' => 'Ran out of time without submitting'][$state] ?? '' }}">
                                <span class="w-2 h-2 rounded-full shrink-0 {{ $stateStyles['dot'] }} {{ $state === 'active' ? 'animate-pulse motion-reduce:animate-none' : '' }}" aria-hidden="true"></span>
                                {{ $stateLabels[$state] ?? ucfirst($state) }}
                            </div>

                            {{-- Tasks --}}
                            <div class="col-span-3 lg:col-span-1 min-w-0">
                                <div class="flex items-baseline justify-between gap-2 text-sm">
                                    <span class="lg:hidden text-xs text-[#888888]">Tasks</span>
                                    <span class="tabular-nums text-[#ededed]">{{ $tasksCount }}<span class="text-[#666666]">/{{ $taskTotal }}</span></span>
                                    @if($linesAdded || $linesDeleted)
                                        <span class="font-mono text-[11px] text-[#666666]" title="Lines added and removed since the starter code"><span class="text-[#3ecf8e]/80">+{{ $linesAdded }}</span> <span class="text-red-400/70">&minus;{{ $linesDeleted }}</span></span>
                                    @endif
                                </div>
                                <div class="mt-1.5 h-1 rounded-full bg-[#2e2e2e] overflow-hidden" aria-hidden="true">
                                    <span class="block h-full rounded-full bg-[#3ecf8e]" style="width: {{ $taskTotal > 0 ? round(min(1, $tasksCount / $taskTotal) * 100) : 0 }}%"></span>
                                </div>
                            </div>

                            {{-- Typing / focus / pastes --}}
                            <div class="flex flex-col gap-0.5 lg:block text-sm lg:text-right tabular-nums">
                                <span class="lg:hidden text-xs text-[#888888]">Typing</span>
                                <span class="{{ $wpm > 0 ? 'text-[#ededed]' : 'text-[#666666]' }}">{{ $wpm }}<span class="text-xs text-[#666666] ml-0.5">wpm</span></span>
                            </div>
                            <div class="flex flex-col gap-0.5 lg:block text-sm lg:text-right tabular-nums">
                                <span class="lg:hidden text-xs text-[#888888]">Focus lost</span>
                                <span class="{{ $focusLost > 2 ? 'text-amber-300 font-medium' : ($focusLost > 0 ? 'text-[#ededed]' : 'text-[#666666]') }}">{{ $focusLost }}</span>
                            </div>
                            <div class="flex flex-col gap-0.5 lg:block text-sm lg:text-right tabular-nums">
                                <span class="lg:hidden text-xs text-[#888888]">Pastes</span>
                                <span class="{{ $pastes > 0 ? 'text-red-300 font-medium' : 'text-[#666666]' }}">{{ $pastes }}</span>
                            </div>

                            {{-- Flags --}}
                            <div>
                                <button type="button" @click="openAnomalyModal({{ $session->id }}, {{ \Illuminate\Support\Js::from($user->name ?? 'Student') }}, {{ \Illuminate\Support\Js::from($anomaliesList) }})"
                                        class="inline-flex items-center gap-1.5 h-8 px-2.5 rounded-lg text-xs font-medium transition-colors active:scale-[0.98] {{ $openFlags > 0 ? 'bg-amber-400/10 text-amber-200 border border-amber-400/30 hover:bg-amber-400/15' : 'text-[#888888] border border-transparent hover:border-[#2e2e2e] hover:text-[#ededed]' }}"
                                        title="See this student's integrity trail">
                                    @if($openFlags > 0)
                                        {{ $openFlags }} open
                                    @elseif($anomaliesList->count() > 0)
                                        {{ $anomaliesList->count() }} resolved
                                    @else
                                        None
                                    @endif
                                </button>
                            </div>

                            {{-- Grade --}}
                            <div>
                                <button type="button" @click="openGradeModal({{ \Illuminate\Support\Js::from($gradeSessionPayload) }})"
                                        class="inline-flex items-center gap-1.5 h-8 px-2.5 rounded-lg text-xs font-medium transition-colors active:scale-[0.98] border {{ $isGraded ? 'border-[#2e2e2e] text-[#ededed] hover:border-[#3e3e3e]' : 'border-transparent text-[#888888] hover:border-[#2e2e2e] hover:text-[#ededed]' }}"
                                        title="AI grade explanation and override">
                                    @if($isGraded)
                                        <span class="tabular-nums">{{ rtrim(rtrim(number_format($session->effective_score, 1), '0'), '.') }}%</span>
                                        @if($session->isGradeOverridden())
                                            <span class="text-[10px] text-[#3ecf8e]">edited</span>
                                        @endif
                                    @else
                                        Not submitted
                                    @endif
                                </button>
                            </div>

                            {{-- End / reopen --}}
                            <div class="flex justify-end">
                                @if($session->status === 'in_progress')
                                    <form action="{{ route('instructor.sessions.end', $session->id) }}" method="POST" onsubmit="return confirm('End this student\'s session? Their code is submitted and graded now.');">
                                        @csrf
                                        <button type="submit" class="h-8 px-2.5 rounded-lg text-xs font-medium text-[#888888] hover:text-red-300 hover:bg-red-500/10 transition-colors active:scale-[0.98]">End session</button>
                                    </form>
                                @else
                                    <form action="{{ route('instructor.sessions.reopen', $session->id) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="h-8 px-2.5 rounded-lg text-xs font-medium text-[#888888] hover:text-[#ededed] hover:bg-white/5 transition-colors active:scale-[0.98]">Reopen</button>
                                    </form>
                                @endif
                            </div>
                        </div>

                        {{-- Team contribution (group labs) --}}
                        @if($laboratory->is_group_lab && $session->code_contributions && count($session->code_contributions) > 0)
                            <div class="mt-3 flex flex-wrap gap-2 text-xs lg:pl-12">
                                @foreach($session->code_contributions as $contrib)
                                    <span class="inline-flex items-center gap-2 px-2.5 py-1 rounded-md bg-[#0f0f0f] border border-[#2e2e2e]">
                                        <span class="w-2 h-2 rounded-full" style="background-color: {{ preg_match('/^#[0-9a-fA-F]{3,8}$/', $contrib['avatar_color'] ?? '') ? $contrib['avatar_color'] : '#3ecf8e' }}"></span>
                                        <span class="text-[#ededed]">{{ $contrib['name'] ?? $contrib['user_name'] ?? 'Teammate' }}</span>
                                        <span class="font-mono text-[#3ecf8e]/80">+{{ $contrib['lines_added'] ?? 0 }}</span>
                                        <span class="font-mono text-red-400/70">&minus;{{ $contrib['lines_deleted'] ?? 0 }}</span>
                                        <span class="text-[#888888]">{{ $contrib['percentage'] ?? 0 }}%</span>
                                    </span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endforeach
            </div>

            <p x-show="visibleCount() === 0" x-cloak class="px-6 py-10 text-center text-sm text-[#888888]">
                No students match. <button type="button" class="text-[#3ecf8e] hover:underline underline-offset-2" @click="statusFilter = 'all'; searchQuery = ''">Show everyone</button>
            </p>
        @endif
    </section>

    <!-- Cohort Code Similarity & Plagiarism Detector (Item 2) -->
    <section id="plagiarism-section" class="rounded-xl border border-[#2e2e2e] bg-[#171717] overflow-hidden space-y-4 p-5 sm:p-6 scroll-mt-20">
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 border-b border-neutral-800 pb-4">
            <div>
                <div class="flex items-center gap-2.5">
                                        <h3 class="font-semibold text-[#ededed] text-base">Similar submissions</h3>
                    <span class="text-sm text-[#888888] tabular-nums" x-text="(plagiarism.total_students_with_code || 0) + ' compared'"></span>
                </div>
                <p class="text-sm text-[#888888] mt-1 max-w-[65ch]">Every pair of submitted solutions, compared by structure. Renamed variables don&rsquo;t hide a match, but shared starter code can raise a score, so check the code before acting.</p>
            </div>
            
            <div class="flex items-center gap-2">
                <button @click="showSimilarityMatrix = !showSimilarityMatrix" class="px-3 py-1.5 rounded-lg bg-neutral-900 border border-neutral-700 text-xs font-semibold text-neutral-300 hover:text-white transition flex items-center gap-1.5">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 10h16M4 14h16M4 18h16"></path></svg>
                    <span x-text="showSimilarityMatrix ? 'Hide all pairs' : 'Show all pairs'"></span>
                </button>
                <button @click="runPlagiarismScan()" :disabled="isScanningPlagiarism" class="px-3.5 py-1.5 rounded-lg bg-[#3ecf8e] hover:bg-[#00c573] text-neutral-950 font-bold text-xs transition flex items-center gap-1.5 shadow-sm disabled:opacity-50">
                    <svg class="w-3.5 h-3.5" :class="{ 'animate-spin': isScanningPlagiarism }" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"></path></svg>
                    <span x-text="isScanningPlagiarism ? 'Comparing…' : 'Compare again'"></span>
                </button>
            </div>
        </div>

        <!-- Clean Cohort Banner -->
        <template x-if="!plagiarism.flagged_pairs || plagiarism.flagged_pairs.length === 0">
            <div class="py-8 px-4 rounded-xl border border-neutral-800/80 bg-neutral-900/30 text-center">
                <div class="w-10 h-10 rounded-full bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 mx-auto flex items-center justify-center mb-2.5">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                </div>
                <h4 class="text-sm font-semibold text-[#ededed]">No similar submissions</h4>
                <p class="text-sm text-[#888888] mt-1 max-w-md mx-auto">Every pair of submissions is less than 50% alike.</p>
            </div>
        </template>

        <!-- Flagged Pairs Table -->
        <template x-if="plagiarism.flagged_pairs && plagiarism.flagged_pairs.length > 0">
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <span class="text-sm text-[#a3a3a3]">
                        <span x-text="plagiarism.flagged_pairs.length"></span> <span x-text="plagiarism.flagged_pairs.length === 1 ? 'pair to review' : 'pairs to review'"></span>
                    </span>
                </div>

                <div class="overflow-x-auto border border-neutral-800 rounded-xl">
                    <table class="w-full text-left text-xs">
                        <thead class="text-[#888888] text-xs border-b border-neutral-800">
                            <tr>
                                <th class="py-2.5 px-4 font-normal">Student</th>
                                <th class="py-2.5 px-4 font-normal">Student</th>
                                <th class="py-2.5 px-4 font-normal">Similarity</th>
                                <th class="py-2.5 px-4 font-normal">Risk</th>
                                <th class="py-2.5 px-4 font-normal">Why</th>
                                <th class="py-2.5 px-4"><span class="sr-only">Action</span></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-neutral-800/60 bg-neutral-950/20 font-mono">
                            <template x-for="(pair, idx) in plagiarism.flagged_pairs" :key="idx">
                                <tr class="hover:bg-neutral-900/40 transition">
                                    <td class="py-3 px-4 font-sans font-medium text-white">
                                        <span x-text="pair.student_a.name"></span>
                                    </td>
                                    <td class="py-3 px-4 font-sans font-medium text-white">
                                        <span x-text="pair.student_b.name"></span>
                                    </td>
                                    <td class="py-3 px-4">
                                        <div class="flex items-center gap-2">
                                            <span class="font-bold text-sm" :class="pair.risk === 'high' ? 'text-rose-400' : 'text-amber-400'" x-text="pair.similarity + '%'"></span>
                                            <div class="w-16 bg-neutral-800 rounded-full h-1.5 overflow-hidden">
                                                <div class="h-1.5 rounded-full" :class="pair.risk === 'high' ? 'bg-rose-500' : 'bg-amber-400'" :style="'width: ' + pair.similarity + '%'"></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="py-3 px-4 font-sans">
                                        <span class="px-2 py-0.5 rounded-md text-xs font-medium"
                                              :class="pair.risk === 'high' ? 'bg-rose-500/20 text-rose-300 border border-rose-500/30' : 'bg-amber-500/20 text-amber-300 border border-amber-500/30'"
                                              x-text="pair.risk === 'high' ? 'High' : 'Moderate'"></span>
                                    </td>
                                    <td class="py-3 px-4 font-sans text-neutral-400 text-xs truncate max-w-xs" x-text="pair.reason"></td>
                                    <td class="py-3 px-4 text-right font-sans">
                                        <button @click="openCompareModal(pair)" class="px-3 py-1 rounded-md bg-neutral-800 hover:bg-neutral-700 text-neutral-200 hover:text-white text-xs font-semibold transition flex items-center gap-1 ml-auto border border-neutral-700">
                                            <span>Compare</span>
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
        <div x-show="showSimilarityMatrix" x-cloak class="mt-4 pt-4 border-t border-neutral-800 space-y-3">
            <div class="flex items-center justify-between">
                <span class="text-sm text-[#a3a3a3]">Every pair</span>
                <span class="text-xs text-[#888888]">% alike, by student</span>
            </div>

            <template x-if="plagiarism.students && plagiarism.students.length > 0">
                <div class="overflow-x-auto border border-neutral-800 rounded-xl p-3 bg-neutral-900/40">
                    <table class="text-center text-xs font-mono">
                        <thead>
                            <tr>
                                <th class="p-2 text-left font-sans text-neutral-400 text-[11px]">Student</th>
                                <template x-for="st in plagiarism.students" :key="'col_' + st.session_id">
                                    <th class="p-2 text-[10px] font-sans text-neutral-400 max-w-[80px] truncate" :title="st.name" x-text="st.name.split(' ')[0]"></th>
                                </template>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="rowSt in plagiarism.students" :key="'row_' + rowSt.session_id">
                                <tr class="border-t border-neutral-800/40">
                                    <td class="p-2 text-left font-sans text-xs text-white font-medium whitespace-nowrap" x-text="rowSt.name"></td>
                                    <template x-for="colSt in plagiarism.students" :key="'cell_' + rowSt.session_id + '_' + colSt.session_id">
                                        <td class="p-2 text-[11px] font-bold rounded">
                                            <span class="px-2 py-1 rounded block"
                                                  :class="{
                                                      'bg-neutral-800/80 text-neutral-500': rowSt.session_id === colSt.session_id,
                                                      'bg-rose-500/20 text-rose-300 border border-rose-500/30': rowSt.session_id !== colSt.session_id && (plagiarism.matrix[rowSt.session_id]?.[colSt.session_id] || 0) >= 75,
                                                      'bg-amber-500/20 text-amber-300 border border-amber-500/30': rowSt.session_id !== colSt.session_id && (plagiarism.matrix[rowSt.session_id]?.[colSt.session_id] || 0) >= 50 && (plagiarism.matrix[rowSt.session_id]?.[colSt.session_id] || 0) < 75,
                                                      'bg-neutral-900/60 text-neutral-400': rowSt.session_id !== colSt.session_id && (plagiarism.matrix[rowSt.session_id]?.[colSt.session_id] || 0) < 50
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
    </section>

    <!-- Side-by-Side Code Comparison Modal -->
    <div x-show="isCompareModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm" style="display: none;">
        <div class="glass-panel rounded-2xl border border-neutral-700 max-w-5xl w-full p-6 space-y-4 max-h-[90vh] overflow-y-auto" @click.away="isCompareModalOpen = false">
            <div class="flex items-center justify-between border-b border-neutral-800 pb-3">
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
                <button @click="isCompareModalOpen = false" class="text-neutral-400 hover:text-white">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <template x-if="selectedPair">
                <div class="space-y-4">
                    <div class="p-3 rounded-lg bg-neutral-900 border border-neutral-800 text-xs text-neutral-300 flex items-center justify-between">
                        <span><strong>Analysis:</strong> <span x-text="selectedPair.reason"></span></span>
                        <span class="font-mono text-neutral-500 text-[11px]" x-text="'Tokens: ' + (selectedPair.student_a.token_count || 0) + ' vs ' + (selectedPair.student_b.token_count || 0)"></span>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <!-- Student A -->
                        <div class="space-y-2">
                            <div class="flex items-center justify-between text-xs font-semibold text-neutral-300 bg-neutral-900/80 px-3 py-2 rounded-t-lg border border-neutral-800">
                                <span class="text-white" x-text="selectedPair.student_a.name"></span>
                                <span class="text-[10px] text-neutral-500 font-mono" x-text="selectedPair.student_a.code_length + ' bytes'"></span>
                            </div>
                            <pre class="p-3 bg-neutral-950 rounded-b-lg border border-neutral-800 text-xs font-mono text-neutral-200 overflow-x-auto max-h-80 whitespace-pre-wrap leading-relaxed" x-text="selectedPair.student_a.code_preview"></pre>
                        </div>

                        <!-- Student B -->
                        <div class="space-y-2">
                            <div class="flex items-center justify-between text-xs font-semibold text-neutral-300 bg-neutral-900/80 px-3 py-2 rounded-t-lg border border-neutral-800">
                                <span class="text-white" x-text="selectedPair.student_b.name"></span>
                                <span class="text-[10px] text-neutral-500 font-mono" x-text="selectedPair.student_b.code_length + ' bytes'"></span>
                            </div>
                            <pre class="p-3 bg-neutral-950 rounded-b-lg border border-neutral-800 text-xs font-mono text-neutral-200 overflow-x-auto max-h-80 whitespace-pre-wrap leading-relaxed" x-text="selectedPair.student_b.code_preview"></pre>
                        </div>
                    </div>
                </div>
            </template>

            <div class="pt-2 flex justify-end">
                <button @click="isCompareModalOpen = false" class="px-4 py-2 rounded-lg bg-neutral-800 hover:bg-neutral-700 text-xs font-semibold text-white">
                    Close Comparison
                </button>
            </div>
        </div>
    </div>

    <!-- Anomaly Timeline & Proof Snapshot Modal -->
    <div x-show="isModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm" style="display: none;">
        <div class="glass-panel rounded-2xl border border-neutral-700 max-w-2xl w-full p-6 space-y-4 max-h-[85vh] overflow-y-auto" @click.away="isModalOpen = false">
            <div class="flex items-center justify-between border-b border-neutral-800 pb-3">
                <div class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-amber-400"></span>
                    <h3 class="text-base font-bold text-white">
                        Integrity & Anomaly Audit Trail — <span x-text="selectedStudentName" class="text-[#3ecf8e]"></span>
                    </h3>
                </div>
                <button @click="isModalOpen = false" class="text-neutral-400 hover:text-white">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <template x-if="selectedAnomalies.length === 0">
                <div class="py-12 text-center text-neutral-500">
                    <svg class="w-10 h-10 mx-auto mb-2 text-emerald-500/60" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <p class="text-sm font-medium text-neutral-300">Clean session: No anomalies or suspicious activity detected.</p>
                </div>
            </template>

            <div class="space-y-3" x-show="selectedAnomalies.length > 0">
                <template x-for="item in selectedAnomalies" :key="item.id">
                    <div class="p-4 rounded-xl border border-neutral-800 bg-neutral-950/60 space-y-2">
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider"
                                      :class="{
                                          'bg-rose-500/20 text-rose-400 border border-rose-500/30': item.severity === 'high',
                                          'bg-amber-500/20 text-amber-400 border border-amber-500/30': item.severity === 'medium',
                                          'bg-neutral-800 text-neutral-400': item.severity === 'low'
                                      }" x-text="item.severity"></span>
                                <span class="text-xs font-bold text-white font-mono" x-text="item.type"></span>
                            </div>
                            <span class="text-[11px] text-neutral-500" x-text="item.created_at"></span>
                        </div>
                        <p class="text-xs text-neutral-300" x-text="item.description"></p>

                        <!-- Paste snippet metadata preview -->
                        <template x-if="item.metadata && item.metadata.snippet">
                            <div class="mt-2 bg-neutral-900 p-2.5 rounded border border-neutral-800">
                                <span class="text-[10px] text-neutral-400 uppercase font-bold block mb-1">Captured Paste Snippet:</span>
                                <pre class="text-[11px] font-mono text-amber-200 overflow-x-auto whitespace-pre-wrap" x-text="item.metadata.snippet"></pre>
                            </div>
                        </template>

                        <!-- Camera Snapshot proof modal preview -->
                        <template x-if="item.image_path">
                            <div class="mt-2">
                                <span class="text-[10px] text-neutral-400 uppercase font-bold block mb-1">Camera Absence Snapshot:</span>
                                <img :src="'/' + item.image_path" class="w-48 h-auto rounded border border-neutral-700 hover:scale-105 transition-transform" alt="Anomaly Proof">
                            </div>
                        </template>

                        <!-- Idle timeout metadata preview -->
                        <template x-if="item.type === 'idle_timeout' && item.metadata">
                            <div class="mt-2 bg-neutral-900/90 p-2.5 rounded-lg border border-amber-500/20 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <svg class="w-4 h-4 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    <span class="text-xs font-semibold text-amber-300">Continuous Inactivity: <span x-text="(item.metadata.idle_minutes || 10) + ' min'"></span></span>
                                </div>
                                <template x-if="item.metadata.shared_remaining_minutes !== null && item.metadata.shared_remaining_minutes !== undefined">
                                    <span class="text-[11px] font-mono text-neutral-400 bg-neutral-800/80 px-2 py-0.5 rounded border border-neutral-700" x-text="item.metadata.shared_remaining_minutes + ' min remain in Live Lab'"></span>
                                </template>
                            </div>
                        </template>
                    </div>
                </template>
            </div>

            <div class="pt-2 flex justify-end">
                <button @click="isModalOpen = false" class="px-4 py-2 rounded-lg bg-neutral-800 hover:bg-neutral-700 text-xs font-semibold text-white">
                    Close Audit Log
                </button>
            </div>
        </div>
    </div>

    <!-- AI Grade Summary & Instructor Override Modal (Feature 10) -->
    <div x-show="isGradeModalOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm" style="display: none;">
        <div class="glass-panel rounded-2xl border border-neutral-700 max-w-2xl w-full p-6 space-y-4 max-h-[85vh] overflow-y-auto" @click.away="isGradeModalOpen = false">
            <div class="flex items-center justify-between border-b border-neutral-800 pb-3">
                <div class="space-y-0.5">
                    <div class="flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full" :class="selectedGradeSession?.is_overridden ? 'bg-purple-400 animate-pulse' : 'bg-[#3ecf8e]'"></span>
                        <h3 class="text-base font-bold text-white">
                            AI Grade Assessment &amp; Explanation
                        </h3>
                    </div>
                    <div class="flex items-center gap-2 text-xs text-neutral-400">
                        <span class="font-medium text-neutral-200" x-text="selectedGradeSession?.student_name"></span>
                        @if($laboratory->is_group_lab)
                            <template x-if="selectedGradeSession?.is_team">
                                <span class="px-1.5 py-0.5 rounded bg-sky-500/20 text-sky-300 border border-sky-500/30 text-[10px]" x-text="'Team: ' + selectedGradeSession.team_name"></span>
                            </template>
                            <template x-if="selectedGradeSession?.is_team">
                                <span class="text-[11px] text-neutral-500 italic">(Evaluating combined submission as single unit)</span>
                            </template>
                        @endif
                    </div>
                </div>
                <button @click="isGradeModalOpen = false" class="text-neutral-400 hover:text-white">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <!-- Side-by-side Score Banner -->
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3">
                <div class="bg-neutral-950/80 p-3 rounded-xl border border-neutral-800 text-center">
                    <span class="text-[10px] uppercase font-bold text-neutral-400 block">AI Assessed Grade</span>
                    <span class="text-xl font-mono font-bold text-[#3ecf8e] my-0.5 block" x-text="(selectedGradeSession?.performance_score ?? 0) + '%'"></span>
                    <span class="text-[10px] text-neutral-500">From submission model</span>
                </div>

                <div class="bg-neutral-950/80 p-3 rounded-xl border border-neutral-800 text-center">
                    <span class="text-[10px] uppercase font-bold text-neutral-400 block">Effective Grade</span>
                    <span class="text-xl font-mono font-bold text-white my-0.5 block" x-text="(selectedGradeSession?.effective_score ?? 0) + '%'"></span>
                    <span class="text-[10px] font-semibold" :class="selectedGradeSession?.is_overridden ? 'text-purple-400' : 'text-emerald-400'"
                          x-text="selectedGradeSession?.is_overridden ? 'Instructor Overridden' : 'AI Score Applied'"></span>
                </div>

                <div class="col-span-2 sm:col-span-1 bg-neutral-950/80 p-3 rounded-xl border border-neutral-800 text-center flex flex-col justify-center">
                    <span class="text-[10px] uppercase font-bold text-neutral-400 block">Test Verification</span>
                    <template x-if="selectedGradeSession?.ai_grade_summary?.test_cases_total">
                        <span class="text-xl font-mono font-bold text-[#ededed] my-0.5 block">
                            <span x-text="selectedGradeSession.ai_grade_summary.test_cases_passed"></span> / <span x-text="selectedGradeSession.ai_grade_summary.test_cases_total"></span>
                        </span>
                    </template>
                    <template x-if="!selectedGradeSession?.ai_grade_summary?.test_cases_total">
                        <span class="text-xs font-mono text-neutral-500 my-1 block">N/A</span>
                    </template>
                    <span class="text-[10px] text-neutral-500">Verified checklist tests</span>
                </div>
            </div>

            <!-- Plain-Language AI Grade Summary -->
            <div class="bg-neutral-950/60 p-4 rounded-xl border border-neutral-800 space-y-2">
                <div class="flex items-center justify-between">
                    <span class="text-xs font-bold uppercase tracking-wider text-purple-300 flex items-center gap-1.5">
                        <svg class="w-4 h-4 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                        Plain-Language AI Grade Explanation
                    </span>
                    <span class="text-[10px] px-2 py-0.5 rounded bg-neutral-800 text-neutral-400 font-mono">Instructor Only</span>
                </div>
                <p class="text-xs text-neutral-200 leading-relaxed font-sans" x-text="selectedGradeSession?.ai_grade_summary?.summary || 'No AI grade summary recorded for this session yet.'"></p>

                <template x-if="selectedGradeSession?.ai_grade_summary?.code_quality_notes">
                    <div class="mt-2 pt-2 border-t border-neutral-800/80 text-xs flex items-start gap-2">
                        <span class="text-neutral-400 font-semibold shrink-0">Code Quality:</span>
                        <span class="text-neutral-300" x-text="selectedGradeSession.ai_grade_summary.code_quality_notes"></span>
                    </div>
                </template>
            </div>

            <!-- Competency Breakdown List -->
            <template x-if="selectedGradeSession?.ai_grade_summary?.competencies && Object.keys(selectedGradeSession.ai_grade_summary.competencies).length > 0">
                <div class="space-y-2">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-neutral-400">Assessed Competencies</h4>
                    <div class="space-y-2 max-h-48 overflow-y-auto pr-1">
                        <template x-for="(comp, compKey) in selectedGradeSession.ai_grade_summary.competencies" :key="compKey">
                            <div class="p-3 rounded-lg bg-neutral-950/80 border border-neutral-800 flex items-start justify-between gap-3">
                                <div class="space-y-0.5 min-w-0 flex-1">
                                    <span class="text-xs font-mono font-bold text-white uppercase tracking-wider" x-text="compKey.replace(/_/g, ' ')"></span>
                                    <p class="text-xs text-neutral-400 leading-relaxed" x-text="comp.reason"></p>
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
            <div class="bg-neutral-900/90 p-4 rounded-xl border border-neutral-700/80 space-y-3">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <svg class="w-4 h-4 text-[#3ecf8e]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                        <h4 class="text-xs font-bold uppercase tracking-wider text-neutral-200">Manual Grade Override &amp; Audit Record</h4>
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
                            <label for="override-score-input" class="text-[11px] font-semibold text-neutral-400 uppercase">Override Score (0–100)</label>
                            <input type="number" id="override-score-input" name="override_score" step="0.1" min="0" max="100" x-model="overrideScoreInput" required
                                   class="w-full px-3 py-2 bg-neutral-950 border border-neutral-700 rounded-lg text-sm font-mono text-white focus:outline-none focus:border-[#3ecf8e]">
                        </div>
                        <div class="sm:col-span-2 space-y-1">
                            <label for="override-reason-input" class="text-[11px] font-semibold text-neutral-400 uppercase">Audit Note / Reason</label>
                            <input type="text" id="override-reason-input" name="override_reason" x-model="overrideReasonInput" placeholder="e.g. Awarded partial credit for custom error handling logic..."
                                   class="w-full px-3 py-2 bg-neutral-950 border border-neutral-700 rounded-lg text-xs text-white focus:outline-none focus:border-[#3ecf8e]">
                        </div>
                    </div>

                    <template x-if="overrideFeedbackMessage">
                        <div class="p-2.5 rounded-lg text-xs" :class="overrideFeedbackSuccess ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-rose-500/10 text-rose-400 border border-rose-500/20'" x-text="overrideFeedbackMessage"></div>
                    </template>

                    <div class="flex items-center justify-end gap-2 pt-1">
                        <button type="button" @click="isGradeModalOpen = false" class="px-3.5 py-1.5 rounded-lg bg-neutral-800 hover:bg-neutral-700 text-xs font-semibold text-neutral-300">
                            Close
                        </button>
                        <button type="submit" :disabled="isSavingOverride" class="px-4 py-1.5 rounded-lg bg-[#3ecf8e] text-neutral-950 font-bold text-xs hover:bg-[#00c573] transition disabled:opacity-50 flex items-center gap-1.5">
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
        statusFilter: 'all',
        sortKey: 'attention',
        roster: @json($rosterIndex ?? []),

        matchesFilter(row, filter) {
            if (filter === 'all') return true;
            if (filter === 'attention') return row.attention > 0;
            return row.state === filter;
        },
        filterCount(filter) {
            return this.roster.filter((row) => this.matchesFilter(row, filter)).length;
        },
        showRow(id) {
            const row = this.roster.find((r) => r.id === id);
            if (!row) return true;
            return this.matchesFilter(row, this.statusFilter) && this.matchesSearch(row.name, row.group);
        },
        visibleCount() {
            return this.roster.filter((row) => this.showRow(row.id)).length;
        },
        sortedIds() {
            const byName = (a, b) => a.name.localeCompare(b.name);
            const sorters = {
                attention: (a, b) => b.attention - a.attention || byName(a, b),
                name: byName,
                progress: (a, b) => b.progress - a.progress || byName(a, b),
                wpm: (a, b) => b.wpm - a.wpm || byName(a, b),
            };
            return [...this.roster].sort(sorters[this.sortKey] || byName).map((r) => r.id);
        },
        rowOrder(id) {
            return this.sortedIds().indexOf(id);
        },
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

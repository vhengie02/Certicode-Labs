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

            <button type="button" @click="refreshData()" class="inline-flex items-center justify-center h-10 w-10 rounded-lg bg-[#171717] border border-[#2e2e2e] text-[#a3a3a3] hover:text-[#ededed] hover:border-[#383838] transition-colors" aria-label="Refresh live data" title="Refresh">
                <svg class="w-4 h-4" :class="{ 'animate-spin': isRefreshing }" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
            </button>
        </div>
    </div>

    <!-- Live WebSocket Telemetry Alert Banner -->
    <div x-show="hasLiveUpdate" x-cloak role="status"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 -translate-y-1" x-transition:enter-end="opacity-100 translate-y-0"
         class="flex items-center justify-between gap-3 p-3 pl-4 rounded-xl border text-sm"
         :class="liveAlertAnomaly ? 'border-amber-400/30 bg-amber-400/[0.06] text-amber-100' : 'border-[#2e2e2e] bg-[#171717] text-[#ededed]'">
        <div class="flex items-center gap-3 min-w-0">
            <template x-if="liveAlertAnomaly && liveAlertAnomaly.snapshot_url">
                <a :href="liveAlertAnomaly.snapshot_url" target="_blank" rel="noopener" class="shrink-0" title="Open the webcam snapshot">
                    <img :src="liveAlertAnomaly.snapshot_url" class="h-12 w-16 object-cover rounded-md border border-amber-400/30" alt="Webcam snapshot from the new flag">
                </a>
            </template>
            <span x-show="!(liveAlertAnomaly && liveAlertAnomaly.snapshot_url)" class="relative flex h-2 w-2 shrink-0" aria-hidden="true">
                <span class="absolute inline-flex h-full w-full rounded-full opacity-60 animate-ping motion-reduce:animate-none" :class="liveAlertAnomaly ? 'bg-amber-400' : 'bg-[#3ecf8e]'"></span>
                <span class="relative inline-flex h-2 w-2 rounded-full" :class="liveAlertAnomaly ? 'bg-amber-400' : 'bg-[#3ecf8e]'"></span>
            </span>
            <span class="min-w-0" x-text="liveUpdateMessage"></span>
        </div>
        <div class="flex items-center gap-1 shrink-0">
            <button type="button" @click="refreshData()" class="h-8 px-3 rounded-lg bg-white/[0.06] hover:bg-white/10 text-xs font-medium transition-colors active:scale-[0.98]">
                Load changes
            </button>
            <button type="button" @click="hasLiveUpdate = false; liveAlertAnomaly = null" class="h-8 w-8 inline-flex items-center justify-center rounded-lg text-[#888888] hover:text-[#ededed] hover:bg-white/5 transition-colors" aria-label="Dismiss">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M6 18 18 6M6 6l12 12"/></svg>
            </button>
        </div>
    </div>


    {{-- KPI strip: one panel, five cells --}}
    @php
        $flaggedPairs = $plagiarismAnalysis['flagged_pairs_count'] ?? 0;
        $openFlagTotal = $sessions->sum(fn ($s) => $s->anomalies->where('resolved', false)->count());
        $connectedPct = $totalStudents > 0 ? round(($activeSessions / $totalStudents) * 100) : 0;
        $submittedPct = $totalStudents > 0 ? round(($completedSessions / $totalStudents) * 100) : 0;
    @endphp
    <dl class="grid grid-cols-2 lg:grid-cols-5 rounded-xl border border-[#2e2e2e] bg-[#171717] [&>div]:border-[#2e2e2e] [&>div:nth-child(odd)]:border-r lg:[&>div]:border-r [&>div:last-child]:border-r-0 [&>div:nth-child(-n+4)]:border-b lg:[&>div:nth-child(n)]:border-b-0">
        <div class="p-4 sm:p-5">
            <dt class="flex items-center gap-1.5 text-sm text-[#888888]">
                Connected now
                <x-info-tip align="left">Students whose VS Code extension checked in during the last 75 seconds, out of everyone who has opened this lab. The extension checks in every 15–20 seconds, so a student who closes VS Code drops off within about a minute.</x-info-tip>
            </dt>
            <dd class="mt-2 flex items-baseline gap-1.5">
                <span class="cc-display text-3xl font-bold tabular-nums tracking-tight text-[#ededed]">{{ $activeSessions }}</span>
                <span class="text-xs text-[#888888]">of {{ $totalStudents }}</span>
            </dd>
            <dd class="mt-3 h-1 w-full rounded-full bg-[#2e2e2e] overflow-hidden" aria-hidden="true">
                <span class="block h-full rounded-full bg-[#3ecf8e] transition-[width] duration-500" style="width: {{ $connectedPct }}%"></span>
            </dd>
        </div>

        <div class="p-4 sm:p-5">
            <dt class="flex items-center gap-1.5 text-sm text-[#888888]">
                Submitted
                <x-info-tip>Sessions that are finished and graded: submitted by the student, ended by you, or auto-submitted when a live lab's timer ran out.</x-info-tip>
            </dt>
            <dd class="mt-2 flex items-baseline gap-1.5">
                <span class="cc-display text-3xl font-bold tabular-nums tracking-tight text-[#ededed]">{{ $completedSessions }}</span>
                <span class="text-xs text-[#888888]">of {{ $totalStudents }}</span>
            </dd>
            <dd class="mt-3 h-1 w-full rounded-full bg-[#2e2e2e] overflow-hidden" aria-hidden="true">
                <span class="block h-full rounded-full bg-[#3ecf8e]/60 transition-[width] duration-500" style="width: {{ $submittedPct }}%"></span>
            </dd>
        </div>

        <div class="p-4 sm:p-5">
            <dt class="flex items-center gap-1.5 text-sm text-[#888888]">
                Open flags
                <x-info-tip>Flags you haven't resolved yet: switching away from VS Code 3+ times in 2 minutes, suspicious pastes, the camera not seeing exactly one face, and long idle periods. Open a student to review and resolve each one.</x-info-tip>
            </dt>
            <dd class="mt-2 flex items-baseline gap-1.5">
                <span class="cc-display text-3xl font-bold tabular-nums tracking-tight {{ $openFlagTotal > 0 ? 'text-amber-400' : 'text-[#ededed]' }}">{{ $openFlagTotal }}</span>
                <span class="text-xs text-[#888888]">of {{ $totalAnomalies }} raised</span>
            </dd>
            @if($openFlagTotal > 0)
                <dd class="mt-2"><button type="button" @click="statusFilter = 'attention'; $nextTick(() => document.getElementById('roster-heading')?.scrollIntoView({ behavior: 'smooth', block: 'start' }))" class="text-xs font-medium text-amber-300 hover:underline underline-offset-2">Show who needs attention</button></dd>
            @endif
        </div>

        <div class="p-4 sm:p-5">
            <dt class="flex items-center gap-1.5 text-sm text-[#888888]">
                Similar code
                <x-info-tip>Pairs of students whose submitted code is at least 50% alike (75%+ is high risk). Comments are stripped and variable names, strings and numbers are ignored, so renaming things doesn't hide copying. Matching starter code can raise the score, so check the side-by-side view before acting.</x-info-tip>
            </dt>
            <dd class="mt-2 flex items-baseline gap-1.5">
                <span class="cc-display text-3xl font-bold tabular-nums tracking-tight {{ $flaggedPairs > 0 ? 'text-red-400' : 'text-[#ededed]' }}" x-text="plagiarism.flagged_pairs_count ?? {{ (int) $flaggedPairs }}">{{ $flaggedPairs }}</span>
                <span class="text-xs text-[#888888]">flagged pairs</span>
            </dd>
            <dd class="mt-2"><a href="#plagiarism-section" class="text-xs font-medium text-[#3ecf8e] hover:underline underline-offset-2">Compare code</a></dd>
        </div>

        <div class="col-span-2 lg:col-span-1 p-4 sm:p-5">
            <dt class="flex items-center gap-1.5 text-sm text-[#888888]">
                Avg typing speed
                <x-info-tip align="right">Average words per minute across everyone in this lab, counting 5 keystrokes as one word. Students who haven't typed yet count as 0, so the average starts low and rises as people work.</x-info-tip>
            </dt>
            <dd class="mt-2 flex items-baseline gap-1.5">
                <span class="cc-display text-3xl font-bold tabular-nums tracking-tight text-[#ededed]">{{ $avgWpm }}</span>
                <span class="text-xs text-[#888888]">wpm</span>
            </dd>
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
        // Everything the student drawer shows, keyed by session id
        $studentDetails = $sessions->mapWithKeys(function ($s) use ($taskTotal, $stateLabels) {
            $state = $s->getConnectionState();
            $diff = $s->diff_stats ?? [];
            return [$s->id => [
                'id' => $s->id,
                'name' => $s->user->name ?? 'Unknown Student',
                'email' => $s->user->email ?? '',
                'initials' => strtoupper(substr($s->user->name ?? 'S', 0, 2)),
                'team_name' => $s->group->name ?? null,
                'state' => $state,
                'state_label' => $stateLabels[$state] ?? ucfirst($state),
                'status' => $s->status,
                'last_seen' => $s->last_ping_at?->diffForHumans() ?? $s->started_at?->diffForHumans(),
                'started_at' => $s->started_at?->format('M j, H:i'),
                'tasks_done' => is_array($s->completed_tasks) ? count($s->completed_tasks) : 0,
                'task_total' => $taskTotal,
                'wpm' => (int) ($s->wpm ?? 0),
                'keystrokes' => (int) ($s->keystroke_count ?? 0),
                'focus_lost' => (int) ($s->focus_lost_count ?? 0),
                'pastes' => (int) ($s->paste_anomaly_count ?? 0),
                'lines_added' => (int) ($diff['lines_added'] ?? 0),
                'lines_deleted' => (int) ($diff['lines_deleted'] ?? 0),
                'contributions' => array_values($s->code_contributions ?? []),
                'anomalies' => $s->anomalies->sortByDesc('id')->values(),
                'timeline_url' => route('sessions.telemetry-timeline', $s->id),
                'is_team' => (bool) $s->group_id,
                'performance_score' => (float) ($s->performance_score ?? 0),
                'instructor_grade_override' => $s->instructor_grade_override,
                'effective_score' => (float) ($s->effective_score ?? 0),
                'is_overridden' => $s->isGradeOverridden(),
                'instructor_override_reason' => $s->instructor_override_reason,
                'instructor_overridden_at' => $s->instructor_overridden_at?->diffForHumans(),
                'overridden_by_name' => $s->overriddenByUser->name ?? null,
                'ai_grade_summary' => $s->ai_grade_summary,
            ]];
        });
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
                            <button type="button" @click="openStudent({{ $session->id }})" class="col-span-2 lg:col-span-1 flex items-center gap-3 min-w-0 text-left rounded-lg -m-1 p-1 focus:outline-none focus-visible:ring-2 focus-visible:ring-[#3ecf8e]/50" title="Open {{ $user->name ?? 'student' }}">
                                <span class="w-9 h-9 rounded-lg bg-[#232323] border border-[#2e2e2e] flex items-center justify-center text-xs font-semibold text-[#a3a3a3] shrink-0 transition-colors group-hover/row:border-[#3e3e3e]" aria-hidden="true">{{ strtoupper(substr($user->name ?? 'S', 0, 2)) }}</span>
                                <div class="min-w-0">
                                    <p class="text-sm font-medium text-[#ededed] truncate group-hover/row:underline underline-offset-2 decoration-[#3e3e3e]">{{ $user->name ?? 'Unknown Student' }}</p>
                                    <p class="text-xs text-[#888888] truncate">
                                        @if($laboratory->is_group_lab && $group)
                                            <span class="text-[#3ecf8e]">{{ $group->name }}</span> <span aria-hidden="true">&middot;</span>
                                        @endif
                                        <span>{{ $user->email ?? 'No email' }}</span>
                                    </p>
                                </div>
                            </button>

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
                                <button type="button" @click="openStudent({{ $session->id }}, 'flags')"
                                        class="inline-flex items-center gap-1.5 h-8 px-2.5 rounded-lg text-xs font-medium transition-colors active:scale-[0.98] {{ $openFlags > 0 ? 'bg-amber-400/10 text-amber-200 border border-amber-400/30 hover:bg-amber-400/15' : 'text-[#888888] border border-transparent hover:border-[#2e2e2e] hover:text-[#ededed]' }}"
                                        title="Review this student's flags">
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
                                <button type="button" @click="openStudent({{ $session->id }}, 'grade')"
                                        class="inline-flex items-center gap-1.5 h-8 px-2.5 rounded-lg text-xs font-medium transition-colors active:scale-[0.98] border {{ $isGraded ? 'border-[#2e2e2e] text-[#ededed] hover:border-[#3e3e3e]' : 'border-transparent text-[#888888] hover:border-[#2e2e2e] hover:text-[#ededed]' }}"
                                        title="See how the AI graded this and change the grade">
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

    {{-- Similar submissions: flagged pairs first, the full grid on demand --}}
    <section id="plagiarism-section" class="rounded-xl border border-[#2e2e2e] bg-[#171717] scroll-mt-20" aria-labelledby="similar-heading">
        <div class="p-4 sm:p-5 border-b border-[#2e2e2e] flex flex-col sm:flex-row sm:items-start sm:justify-between gap-4">
            <div class="min-w-0">
                <h3 id="similar-heading" class="text-base font-semibold text-[#ededed]">
                    Similar submissions
                    <span class="ml-1.5 text-sm font-normal text-[#888888] tabular-nums" x-text="(plagiarism.total_students_with_code || 0) + ' compared'"></span>
                </h3>
                <p class="text-sm text-[#888888] mt-1 max-w-[65ch] text-pretty">Every pair of submitted solutions, compared by structure. Renamed variables don&rsquo;t hide a match, but shared starter code can raise a score, so check the code before acting.</p>
            </div>
            <div class="flex items-center gap-2 shrink-0">
                <button type="button" @click="showSimilarityMatrix = !showSimilarityMatrix" :aria-expanded="showSimilarityMatrix.toString()"
                        class="inline-flex items-center gap-1.5 h-9 px-3 rounded-lg border border-[#2e2e2e] bg-[#0f0f0f] text-xs font-medium text-[#a3a3a3] hover:text-[#ededed] hover:border-[#3e3e3e] transition-colors active:scale-[0.98]">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><rect x="4" y="4" width="6" height="6" rx="1"/><rect x="14" y="4" width="6" height="6" rx="1"/><rect x="4" y="14" width="6" height="6" rx="1"/><rect x="14" y="14" width="6" height="6" rx="1"/></svg>
                    <span x-text="showSimilarityMatrix ? 'Hide grid' : 'Show every pair'"></span>
                </button>
                <button type="button" @click="runPlagiarismScan()" :disabled="isScanningPlagiarism"
                        class="inline-flex items-center gap-1.5 h-9 px-3 rounded-lg border border-[#2e2e2e] bg-[#0f0f0f] text-xs font-medium text-[#ededed] hover:border-[#3ecf8e]/40 transition-colors active:scale-[0.98] disabled:opacity-50">
                    <svg class="w-3.5 h-3.5" :class="{ 'animate-spin': isScanningPlagiarism }" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                    <span x-text="isScanningPlagiarism ? 'Comparing…' : 'Compare again'"></span>
                </button>
            </div>
        </div>

        {{-- No flagged pairs --}}
        <template x-if="!plagiarism.flagged_pairs || plagiarism.flagged_pairs.length === 0">
            <div class="px-6 py-10 flex flex-col items-center text-center">
                <span class="w-9 h-9 rounded-lg border border-[#3ecf8e]/25 bg-[#3ecf8e]/[0.06] text-[#3ecf8e] flex items-center justify-center" aria-hidden="true">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                </span>
                <p class="mt-3 text-sm font-medium text-[#ededed]">No similar submissions</p>
                <p class="mt-1 text-sm text-[#888888]">Every pair of submissions is less than 50% alike.</p>
            </div>
        </template>

        {{-- Flagged pairs, highest first --}}
        <template x-if="plagiarism.flagged_pairs && plagiarism.flagged_pairs.length > 0">
            <div>
                <div class="hidden md:grid md:grid-cols-[minmax(0,1.4fr)_10rem_6rem_minmax(0,1fr)_6.5rem] items-center gap-4 px-5 py-2.5 border-b border-[#2e2e2e] text-xs text-[#888888]" aria-hidden="true">
                    <span>Students</span>
                    <span>Alike</span>
                    <span>Risk</span>
                    <span>Why</span>
                    <span class="sr-only">Action</span>
                </div>
                <ul class="divide-y divide-[#2e2e2e]">
                    <template x-for="(pair, idx) in plagiarism.flagged_pairs" :key="idx">
                        <li class="px-4 sm:px-5 py-3.5 grid grid-cols-[minmax(0,1fr)_auto] md:grid-cols-[minmax(0,1.4fr)_10rem_6rem_minmax(0,1fr)_6.5rem] items-center gap-x-4 gap-y-2.5 transition-colors hover:bg-white/[0.02]"
                            :class="pair.risk === 'high' ? 'shadow-[inset_2px_0_0_0_rgba(248,113,113,0.7)]' : 'shadow-[inset_2px_0_0_0_rgba(251,191,36,0.6)]'">
                            <div class="min-w-0 flex items-center gap-2 text-sm">
                                <span class="font-medium text-[#ededed] truncate" x-text="pair.student_a.name"></span>
                                <span class="text-[#666666] shrink-0" aria-label="and">&amp;</span>
                                <span class="font-medium text-[#ededed] truncate" x-text="pair.student_b.name"></span>
                            </div>
                            <div class="flex items-center gap-2.5 md:order-none order-3">
                                <span class="w-10 text-sm font-semibold tabular-nums" :class="pair.risk === 'high' ? 'text-red-400' : 'text-amber-400'" x-text="pair.similarity + '%'"></span>
                                <span class="flex-1 h-1 rounded-full bg-[#2e2e2e] overflow-hidden min-w-[4rem]" aria-hidden="true">
                                    <span class="block h-full rounded-full" :class="pair.risk === 'high' ? 'bg-red-400' : 'bg-amber-400'" :style="'width: ' + Math.min(100, pair.similarity) + '%'"></span>
                                </span>
                            </div>
                            <div class="order-4 md:order-none">
                                <span class="inline-flex items-center gap-1.5 text-xs font-medium" :class="pair.risk === 'high' ? 'text-red-300' : 'text-amber-300'">
                                    <span class="w-1.5 h-1.5 rounded-full" :class="pair.risk === 'high' ? 'bg-red-400' : 'bg-amber-400'" aria-hidden="true"></span>
                                    <span x-text="pair.risk === 'high' ? 'High' : 'Moderate'"></span>
                                </span>
                            </div>
                            <p class="col-span-2 md:col-span-1 order-5 md:order-none text-xs text-[#888888] line-clamp-2" :title="pair.reason" x-text="pair.reason"></p>
                            <div class="order-2 md:order-none flex justify-end">
                                <button type="button" @click="openCompareModal(pair)"
                                        class="h-8 px-3 rounded-lg border border-[#2e2e2e] text-xs font-medium text-[#ededed] hover:border-[#3e3e3e] hover:bg-white/[0.03] transition-colors active:scale-[0.98]">
                                    Compare
                                </button>
                            </div>
                        </li>
                    </template>
                </ul>
            </div>
        </template>

        {{-- Every pair, as a grid --}}
        <div x-show="showSimilarityMatrix" x-cloak x-transition.opacity class="p-4 sm:p-5 border-t border-[#2e2e2e] space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-[#888888]">
                <span class="text-sm text-[#a3a3a3]">% alike, every pair</span>
                <span class="flex items-center gap-3">
                    <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-sm bg-red-500/25 border border-red-500/40"></span>75%+</span>
                    <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-sm bg-amber-500/20 border border-amber-500/35"></span>50–74%</span>
                    <span class="inline-flex items-center gap-1.5"><span class="w-2.5 h-2.5 rounded-sm bg-[#232323] border border-[#2e2e2e]"></span>Under 50%</span>
                </span>
            </div>

            <template x-if="plagiarism.students && plagiarism.students.length > 0">
                <div class="overflow-x-auto rounded-lg border border-[#2e2e2e] bg-[#0f0f0f]">
                    <table class="text-center text-xs">
                        <thead>
                            <tr>
                                <th scope="col" class="sticky left-0 bg-[#0f0f0f] px-3 py-2 text-left font-normal text-[#888888]">Student</th>
                                <template x-for="st in plagiarism.students" :key="'col_' + st.session_id">
                                    <th scope="col" class="px-1.5 py-2 font-normal text-[#888888] max-w-[5rem] truncate" :title="st.name" x-text="st.name.split(' ')[0]"></th>
                                </template>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="rowSt in plagiarism.students" :key="'row_' + rowSt.session_id">
                                <tr>
                                    <th scope="row" class="sticky left-0 bg-[#0f0f0f] px-3 py-1 text-left font-medium text-[#ededed] whitespace-nowrap" x-text="rowSt.name"></th>
                                    <template x-for="colSt in plagiarism.students" :key="'cell_' + rowSt.session_id + '_' + colSt.session_id">
                                        <td class="p-0.5">
                                            <span class="block min-w-[3rem] px-2 py-1.5 rounded tabular-nums border"
                                                  :class="similarityCellClass(rowSt.session_id, colSt.session_id)"
                                                  :title="rowSt.session_id === colSt.session_id ? '' : rowSt.name + ' & ' + colSt.name"
                                                  x-text="rowSt.session_id === colSt.session_id ? '–' : similarityAt(rowSt.session_id, colSt.session_id).toFixed(0) + '%'"></span>
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

    {{-- Side-by-side code comparison --}}
    <div x-show="isCompareModalOpen" x-cloak @keydown.escape.window="isCompareModalOpen = false"
         class="fixed inset-0 z-50 flex items-center justify-center p-4" role="dialog" aria-modal="true" aria-labelledby="compare-title">
        <div x-show="isCompareModalOpen" x-transition.opacity class="absolute inset-0 bg-black/70 backdrop-blur-[2px]" @click="isCompareModalOpen = false" aria-hidden="true"></div>
        <div x-show="isCompareModalOpen"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-[0.98]" x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
             class="relative w-full max-w-5xl max-h-[90vh] flex flex-col rounded-2xl border border-[#2e2e2e] bg-[#171717] shadow-2xl shadow-black/50">
            <template x-if="selectedPair">
                <div class="flex flex-col min-h-0">
                    <div class="flex items-start justify-between gap-4 p-5 border-b border-[#2e2e2e]">
                        <div class="min-w-0">
                            <h3 id="compare-title" class="text-base font-semibold text-[#ededed] flex items-center gap-2.5 flex-wrap">
                                Compare code
                                <span class="px-2 py-0.5 rounded-md border text-xs font-medium tabular-nums"
                                      :class="selectedPair.risk === 'high' ? 'border-red-500/30 bg-red-500/10 text-red-300' : 'border-amber-400/30 bg-amber-400/10 text-amber-300'"
                                      x-text="selectedPair.similarity + '% alike'"></span>
                            </h3>
                            <p class="mt-1 text-sm text-[#888888]" x-text="selectedPair.reason"></p>
                        </div>
                        <button type="button" @click="isCompareModalOpen = false" class="h-8 w-8 shrink-0 inline-flex items-center justify-center rounded-lg text-[#888888] hover:text-[#ededed] hover:bg-white/5 transition-colors" aria-label="Close">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M6 18 18 6M6 6l12 12"/></svg>
                        </button>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-px bg-[#2e2e2e] overflow-y-auto min-h-0">
                        <template x-for="side in [selectedPair.student_a, selectedPair.student_b]" :key="side.name">
                            <div class="bg-[#0f0f0f] flex flex-col min-w-0">
                                <div class="flex items-center justify-between gap-2 px-4 py-2.5 border-b border-[#232323] text-xs">
                                    <span class="font-medium text-[#ededed] truncate" x-text="side.name"></span>
                                    <span class="font-mono text-[#666666] shrink-0" x-text="(side.token_count || 0) + ' tokens · ' + (side.code_length || 0) + ' bytes'"></span>
                                </div>
                                <pre class="p-4 text-xs font-mono text-[#d4d4d4] overflow-x-auto max-h-[60vh] leading-relaxed whitespace-pre" x-text="side.code_preview"></pre>
                            </div>
                        </template>
                    </div>
                </div>
            </template>
        </div>
    </div>

    {{-- Student drawer: overview, flags and grade for one student --}}
    <div x-show="drawerOpen" x-cloak @keydown.escape.window="closeStudent()" class="fixed inset-0 z-50" role="dialog" aria-modal="true" aria-labelledby="drawer-title">
        <div x-show="drawerOpen" x-transition.opacity.duration.200ms class="absolute inset-0 bg-black/60" @click="closeStudent()" aria-hidden="true"></div>
        <aside x-show="drawerOpen" x-ref="drawer" tabindex="-1"
               x-transition:enter="transition ease-out duration-300" x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
               x-transition:leave="transition ease-in duration-200" x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
               class="absolute inset-y-0 right-0 w-full max-w-xl flex flex-col bg-[#171717] border-l border-[#2e2e2e] shadow-2xl shadow-black/60 focus:outline-none">
            <template x-if="student">
                <div class="flex flex-col h-full min-h-0">
                    {{-- Who --}}
                    <div class="p-5 pb-0">
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex items-center gap-3 min-w-0">
                                <span class="w-11 h-11 rounded-xl bg-[#232323] border border-[#2e2e2e] flex items-center justify-center text-sm font-semibold text-[#a3a3a3] shrink-0" aria-hidden="true" x-text="student.initials"></span>
                                <div class="min-w-0">
                                    <h3 id="drawer-title" class="text-lg font-semibold text-[#ededed] truncate" x-text="student.name"></h3>
                                    <p class="text-sm text-[#888888] truncate">
                                        <template x-if="student.team_name"><span><span class="text-[#3ecf8e]" x-text="student.team_name"></span> &middot; </span></template>
                                        <span x-text="student.email || 'No email'"></span>
                                    </p>
                                </div>
                            </div>
                            <button type="button" @click="closeStudent()" class="h-8 w-8 shrink-0 inline-flex items-center justify-center rounded-lg text-[#888888] hover:text-[#ededed] hover:bg-white/5 transition-colors" aria-label="Close">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M6 18 18 6M6 6l12 12"/></svg>
                            </button>
                        </div>

                        <div class="mt-4 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-sm">
                            <span class="inline-flex items-center gap-2" :class="stateStyle(student.state).text">
                                <span class="w-2 h-2 rounded-full" :class="stateStyle(student.state).dot" aria-hidden="true"></span>
                                <span x-text="student.state_label"></span>
                            </span>
                            <span class="text-[#888888]" x-show="student.last_seen" x-text="'Last seen ' + student.last_seen"></span>
                            <span class="text-[#888888]" x-show="student.started_at" x-text="'Started ' + student.started_at"></span>
                        </div>

                        {{-- Tabs --}}
                        <div class="mt-5 flex gap-1 border-b border-[#2e2e2e] -mx-5 px-5" role="tablist" aria-label="Student details">
                            <template x-for="t in [['overview', 'Overview'], ['flags', 'Flags'], ['grade', 'Grade']]" :key="t[0]">
                                <button type="button" role="tab" @click="drawerTab = t[0]" :aria-selected="(drawerTab === t[0]).toString()"
                                        class="relative inline-flex items-center gap-1.5 px-3 py-2.5 text-sm font-medium transition-colors"
                                        :class="drawerTab === t[0] ? 'text-[#ededed] monitor-tab-active' : 'text-[#888888] hover:text-[#ededed]'">
                                    <span x-text="t[1]"></span>
                                    <span x-show="t[0] === 'flags' && openFlagCount(student) > 0" class="min-w-[1.25rem] h-5 px-1.5 rounded-full bg-amber-400/15 text-amber-300 text-[11px] tabular-nums inline-flex items-center justify-center" x-text="openFlagCount(student)"></span>
                                </button>
                            </template>
                        </div>
                    </div>

                    <div class="flex-1 min-h-0 overflow-y-auto p-5">
                        {{-- Overview --}}
                        <div x-show="drawerTab === 'overview'" class="space-y-5">
                            <div>
                                <div class="flex items-baseline justify-between text-sm">
                                    <span class="text-[#888888]">Tasks done</span>
                                    <span class="tabular-nums text-[#ededed]"><span x-text="student.tasks_done"></span><span class="text-[#666666]" x-text="'/' + student.task_total"></span></span>
                                </div>
                                <div class="mt-2 h-1.5 rounded-full bg-[#2e2e2e] overflow-hidden" aria-hidden="true">
                                    <span class="block h-full rounded-full bg-[#3ecf8e]" :style="'width: ' + (student.task_total > 0 ? Math.min(100, Math.round(student.tasks_done / student.task_total * 100)) : 0) + '%'"></span>
                                </div>
                            </div>

                            <dl class="grid grid-cols-2 sm:grid-cols-3 gap-px rounded-xl overflow-hidden border border-[#2e2e2e] bg-[#2e2e2e]">
                                <template x-for="m in overviewMetrics(student)" :key="m.label">
                                    <div class="bg-[#0f0f0f] p-3.5">
                                        <dt class="text-xs text-[#888888]" x-text="m.label"></dt>
                                        <dd class="mt-1 text-lg font-semibold tabular-nums" :class="m.tone"><span x-text="m.value"></span><span x-show="m.unit" class="ml-1 text-xs font-normal text-[#666666]" x-text="m.unit"></span></dd>
                                    </div>
                                </template>
                            </dl>

                            <template x-if="student.contributions && student.contributions.length > 0">
                                <div>
                                    <h4 class="text-sm font-medium text-[#ededed]">Team contribution</h4>
                                    <ul class="mt-2.5 space-y-2.5">
                                        <template x-for="(c, ci) in student.contributions" :key="ci">
                                            <li>
                                                <div class="flex items-center justify-between gap-3 text-sm">
                                                    <span class="flex items-center gap-2 min-w-0">
                                                        <span class="w-2 h-2 rounded-full shrink-0" :style="'background-color: ' + safeColor(c.avatar_color)"></span>
                                                        <span class="text-[#ededed] truncate" x-text="c.name || c.user_name || 'Teammate'"></span>
                                                    </span>
                                                    <span class="flex items-center gap-2 shrink-0 font-mono text-xs">
                                                        <span class="text-[#3ecf8e]/80" x-text="'+' + (c.lines_added || 0)"></span>
                                                        <span class="text-red-400/70" x-text="'−' + (c.lines_deleted || 0)"></span>
                                                        <span class="w-10 text-right text-[#a3a3a3]" x-text="(c.percentage || 0) + '%'"></span>
                                                    </span>
                                                </div>
                                                <div class="mt-1.5 h-1 rounded-full bg-[#2e2e2e] overflow-hidden" aria-hidden="true">
                                                    <span class="block h-full rounded-full" :style="'width: ' + Math.min(100, c.percentage || 0) + '%; background-color: ' + safeColor(c.avatar_color)"></span>
                                                </div>
                                            </li>
                                        </template>
                                    </ul>
                                </div>
                            </template>

                            <a :href="student.timeline_url" class="flex items-center justify-between gap-3 p-3.5 rounded-xl border border-[#2e2e2e] hover:border-[#3e3e3e] hover:bg-white/[0.02] transition-colors group/link">
                                <span>
                                    <span class="block text-sm font-medium text-[#ededed]">Activity timeline</span>
                                    <span class="block text-xs text-[#888888] mt-0.5">Every keystroke burst, paste and focus change, in order</span>
                                </span>
                                <svg class="w-4 h-4 text-[#888888] transition-transform group-hover/link:translate-x-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                            </a>
                        </div>

                        {{-- Flags --}}
                        <div x-show="drawerTab === 'flags'" class="space-y-3">
                            <template x-if="student.anomalies.length === 0">
                                <div class="py-12 text-center">
                                    <p class="text-sm font-medium text-[#ededed]">No flags</p>
                                    <p class="mt-1 text-sm text-[#888888]">Nothing unusual came up in this session.</p>
                                </div>
                            </template>
                            <template x-for="item in student.anomalies" :key="item.id">
                                <article class="rounded-xl border p-4 space-y-2.5 transition-opacity" :class="item.resolved ? 'border-[#2e2e2e] opacity-60' : (item.severity === 'high' ? 'border-red-500/25 bg-red-500/[0.03]' : 'border-[#2e2e2e] bg-[#0f0f0f]')">
                                    <div class="flex items-start justify-between gap-3">
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <span class="text-sm font-medium text-[#ededed]" x-text="flagLabel(item.type)"></span>
                                                <span class="px-1.5 py-0.5 rounded border text-[11px] font-medium"
                                                      :class="{ 'border-red-500/30 bg-red-500/10 text-red-300': item.severity === 'high', 'border-amber-400/30 bg-amber-400/10 text-amber-300': item.severity === 'medium', 'border-[#2e2e2e] text-[#a3a3a3]': item.severity !== 'high' && item.severity !== 'medium' }"
                                                      x-text="item.severity ? item.severity.charAt(0).toUpperCase() + item.severity.slice(1) : 'Low'"></span>
                                            </div>
                                            <p class="mt-0.5 text-xs text-[#888888]" x-text="relativeTime(item.created_at)"></p>
                                        </div>
                                        <template x-if="item.resolved">
                                            <span class="inline-flex items-center gap-1 text-xs text-[#888888] shrink-0">
                                                <svg class="w-3.5 h-3.5 text-[#3ecf8e]" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                                Resolved
                                            </span>
                                        </template>
                                        <template x-if="!item.resolved">
                                            <button type="button" @click="resolveFlag(item)" :disabled="item._resolving"
                                                    class="h-8 px-3 shrink-0 rounded-lg border border-[#2e2e2e] text-xs font-medium text-[#ededed] hover:border-[#3ecf8e]/40 transition-colors active:scale-[0.98] disabled:opacity-50"
                                                    x-text="item._resolving ? 'Resolving…' : 'Resolve'"></button>
                                        </template>
                                    </div>
                                    <p class="text-sm text-[#a3a3a3]" x-text="item.description"></p>

                                    <template x-if="item.metadata && item.metadata.snippet">
                                        <div class="rounded-lg border border-[#2e2e2e] bg-[#0f0f0f]"
                                             x-data="{ open: false, long: item.metadata.snippet.length > 200 || item.metadata.snippet.split('\n').length > 5 }">
                                            <span class="block px-3 pt-2 text-xs text-[#888888]">Pasted text</span>
                                            <pre class="px-3 pb-2.5 pt-1 text-xs font-mono text-amber-200/90 whitespace-pre-wrap break-words"
                                                 :class="long && !open ? 'max-h-24 overflow-hidden [mask-image:linear-gradient(to_bottom,black_60%,transparent)]' : 'max-h-96 overflow-y-auto'"
                                                 x-text="item.metadata.snippet"></pre>
                                            <template x-if="long">
                                                <button type="button" @click="open = !open"
                                                        class="block w-full px-3 py-1.5 border-t border-[#2e2e2e] text-left text-xs font-medium text-[#a3a3a3] hover:text-[#ededed] transition-colors"
                                                        x-text="open ? 'Show less' : 'Show more'"></button>
                                            </template>
                                        </div>
                                    </template>

                                    <template x-if="item.snapshot_url">
                                        <a :href="item.snapshot_url" target="_blank" rel="noopener" class="block w-fit" title="Open full size">
                                            <img :src="item.snapshot_url" loading="lazy" class="w-56 h-auto rounded-lg border border-[#2e2e2e] hover:border-[#3e3e3e] transition-colors" alt="Webcam snapshot taken when this flag was raised">
                                        </a>
                                    </template>

                                    <template x-if="item.type === 'idle_timeout' && item.metadata">
                                        <p class="text-xs text-amber-300/90">
                                            Idle for <span x-text="(item.metadata.idle_minutes || 10) + ' min'"></span><template x-if="item.metadata.shared_remaining_minutes !== null && item.metadata.shared_remaining_minutes !== undefined"><span x-text="', with ' + item.metadata.shared_remaining_minutes + ' min left in the live lab'"></span></template>
                                        </p>
                                    </template>
                                </article>
                            </template>
                            <p x-show="resolveError" x-cloak class="text-xs text-red-300" x-text="resolveError"></p>
                        </div>

                        {{-- Grade --}}
                        <div x-show="drawerTab === 'grade'" class="space-y-5">
                            <template x-if="student.status !== 'completed'">
                                <div class="py-12 text-center">
                                    <p class="text-sm font-medium text-[#ededed]">Not submitted yet</p>
                                    <p class="mt-1 text-sm text-[#888888] max-w-xs mx-auto">The AI grades the code once the student submits, you end the session, or a live lab's timer runs out.</p>
                                </div>
                            </template>

                            <template x-if="student.status === 'completed'">
                                <div class="space-y-5">
                                    <div class="grid grid-cols-3 gap-px rounded-xl overflow-hidden border border-[#2e2e2e] bg-[#2e2e2e]">
                                        <div class="bg-[#0f0f0f] p-3.5">
                                            <p class="text-xs text-[#888888]">Final grade</p>
                                            <p class="mt-1 text-2xl font-semibold tabular-nums text-[#ededed]" x-text="formatScore(student.effective_score) + '%'"></p>
                                            <p class="text-xs mt-0.5" :class="student.is_overridden ? 'text-[#3ecf8e]' : 'text-[#888888]'" x-text="student.is_overridden ? 'Your grade' : 'AI grade'"></p>
                                        </div>
                                        <div class="bg-[#0f0f0f] p-3.5">
                                            <p class="text-xs text-[#888888]">AI grade</p>
                                            <p class="mt-1 text-2xl font-semibold tabular-nums" :class="student.is_overridden ? 'text-[#666666] line-through decoration-1' : 'text-[#ededed]'" x-text="formatScore(student.performance_score) + '%'"></p>
                                        </div>
                                        <div class="bg-[#0f0f0f] p-3.5">
                                            <p class="text-xs text-[#888888]">Tests passed</p>
                                            <p class="mt-1 text-2xl font-semibold tabular-nums text-[#ededed]">
                                                <template x-if="student.ai_grade_summary && student.ai_grade_summary.test_cases_total">
                                                    <span><span x-text="student.ai_grade_summary.test_cases_passed"></span><span class="text-[#666666]" x-text="'/' + student.ai_grade_summary.test_cases_total"></span></span>
                                                </template>
                                                <template x-if="!(student.ai_grade_summary && student.ai_grade_summary.test_cases_total)"><span class="text-[#666666]">–</span></template>
                                            </p>
                                        </div>
                                    </div>

                                    <section aria-labelledby="ai-grade-heading">
                                        <h4 id="ai-grade-heading" class="text-sm font-medium text-[#ededed]">How the AI graded this</h4>
                                        <template x-if="student.is_team">
                                            <p class="mt-0.5 text-xs text-[#888888]">The team's combined code is graded as one submission.</p>
                                        </template>
                                        <p class="mt-2 text-sm text-[#a3a3a3] leading-relaxed text-pretty" x-text="(student.ai_grade_summary && student.ai_grade_summary.summary) || 'No AI explanation was saved for this session.'"></p>
                                        <template x-if="student.ai_grade_summary && student.ai_grade_summary.code_quality_notes">
                                            <p class="mt-2 text-sm text-[#a3a3a3]"><span class="text-[#ededed]">Code quality:</span> <span x-text="student.ai_grade_summary.code_quality_notes"></span></p>
                                        </template>
                                    </section>

                                    <template x-if="student.ai_grade_summary && student.ai_grade_summary.competencies && Object.keys(student.ai_grade_summary.competencies).length > 0">
                                        <section>
                                            <h4 class="text-sm font-medium text-[#ededed]">Skills checked</h4>
                                            <ul class="mt-2 rounded-xl border border-[#2e2e2e] divide-y divide-[#2e2e2e]">
                                                <template x-for="(comp, compKey) in student.ai_grade_summary.competencies" :key="compKey">
                                                    <li class="flex items-start gap-3 p-3">
                                                        <span class="mt-0.5 w-4 h-4 shrink-0 rounded-full flex items-center justify-center" :class="comp.passed ? 'bg-[#3ecf8e]/15 text-[#3ecf8e]' : 'bg-red-500/15 text-red-400'" aria-hidden="true">
                                                            <svg x-show="comp.passed" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                                            <svg x-show="!comp.passed" class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 18 18 6M6 6l12 12"/></svg>
                                                        </span>
                                                        <div class="min-w-0">
                                                            <p class="text-sm text-[#ededed]"><span x-text="humanize(compKey)"></span><span class="sr-only" x-text="comp.passed ? ': passed' : ': failed'"></span></p>
                                                            <p class="text-xs text-[#888888] mt-0.5 leading-relaxed" x-text="comp.reason"></p>
                                                        </div>
                                                    </li>
                                                </template>
                                            </ul>
                                        </section>
                                    </template>
                                </div>
                            </template>

                            {{-- Change the grade (only once there is one) --}}
                            <form x-show="student.status === 'completed'" @submit.prevent="submitGradeOverride()" class="rounded-xl border border-[#2e2e2e] bg-[#0f0f0f] p-4 space-y-3">
                                <div class="flex items-center justify-between gap-3">
                                    <h4 class="text-sm font-medium text-[#ededed]">Change the grade</h4>
                                    <span x-show="student.is_overridden" class="text-xs text-[#888888]" x-text="'Changed' + (student.overridden_by_name ? ' by ' + student.overridden_by_name : '') + (student.instructor_overridden_at ? ', ' + student.instructor_overridden_at : '')"></span>
                                </div>
                                <template x-if="student.is_overridden && student.instructor_override_reason">
                                    <p class="text-xs text-[#a3a3a3] border-l-2 border-[#3ecf8e]/40 pl-2.5" x-text="student.instructor_override_reason"></p>
                                </template>
                                <div class="grid grid-cols-[7rem_minmax(0,1fr)] gap-3">
                                    <label class="block">
                                        <span class="block text-xs text-[#888888] mb-1">Grade (0–100)</span>
                                        <input type="number" id="override-score-input" name="override_score" step="0.1" min="0" max="100" x-model="overrideScoreInput" required
                                               class="w-full h-9 px-3 rounded-lg border border-[#2e2e2e] bg-[#171717] text-sm font-mono tabular-nums text-[#ededed] focus:outline-none focus:border-[#3ecf8e]/60 focus:ring-2 focus:ring-[#3ecf8e]/15">
                                    </label>
                                    <label class="block">
                                        <span class="block text-xs text-[#888888] mb-1">Reason <span class="text-[#666666]">(saved in the audit log)</span></span>
                                        <input type="text" id="override-reason-input" name="override_reason" x-model="overrideReasonInput" maxlength="1000" placeholder="Partial credit for the custom error handling"
                                               class="w-full h-9 px-3 rounded-lg border border-[#2e2e2e] bg-[#171717] text-sm text-[#ededed] placeholder-[#666666] focus:outline-none focus:border-[#3ecf8e]/60 focus:ring-2 focus:ring-[#3ecf8e]/15">
                                    </label>
                                </div>
                                <div class="flex items-center justify-between gap-3">
                                    <p class="text-xs min-w-0" :class="overrideFeedbackSuccess ? 'text-[#3ecf8e]' : 'text-red-300'" x-text="overrideFeedbackMessage" role="status"></p>
                                    <button type="submit" :disabled="isSavingOverride"
                                            class="h-9 px-4 shrink-0 rounded-lg bg-[#3ecf8e] text-sm font-semibold text-[#06150e] hover:bg-[#00c573] transition-colors active:scale-[0.98] disabled:opacity-50"
                                            x-text="isSavingOverride ? 'Saving…' : 'Save grade'"></button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </template>
        </aside>
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
        hasLiveUpdate: false,
        liveAlertAnomaly: null,
        liveUpdateMessage: '',
        ws: null,
        wsConnected: false,

        // Student drawer
        students: @json($studentDetails ?? (object) []),
        drawerOpen: false,
        drawerTab: 'overview',
        student: null,
        lastFocus: null,
        resolveError: '',

        // Grade override form inside the drawer
        overrideScoreInput: '',
        overrideReasonInput: '',
        isSavingOverride: false,
        overrideFeedbackMessage: '',
        overrideFeedbackSuccess: false,

        openStudent(id, tab = 'overview') {
            const data = this.students[id];
            if (!data) return;
            this.lastFocus = document.activeElement;
            this.student = data;
            this.drawerTab = tab;
            this.resolveError = '';
            this.overrideScoreInput = (data.instructor_grade_override !== null && data.instructor_grade_override !== undefined)
                ? data.instructor_grade_override
                : data.performance_score;
            this.overrideReasonInput = data.instructor_override_reason || '';
            this.overrideFeedbackMessage = '';
            this.drawerOpen = true;
            document.documentElement.classList.add('overflow-hidden');
            this.$nextTick(() => this.$refs.drawer && this.$refs.drawer.focus());
        },
        closeStudent() {
            if (!this.drawerOpen) return;
            this.drawerOpen = false;
            document.documentElement.classList.remove('overflow-hidden');
            if (this.lastFocus && this.lastFocus.focus) this.lastFocus.focus();
        },
        stateStyle(state) {
            return {
                active: { dot: 'bg-[#3ecf8e]', text: 'text-[#ededed]' },
                idle: { dot: 'bg-amber-400', text: 'text-amber-200' },
                offline: { dot: 'bg-[#666666]', text: 'text-[#a3a3a3]' },
                completed: { dot: 'bg-[#3ecf8e]/50', text: 'text-[#a3a3a3]' },
                abandoned: { dot: 'bg-red-400/70', text: 'text-[#a3a3a3]' },
            }[state] || { dot: 'bg-[#666666]', text: 'text-[#a3a3a3]' };
        },
        openFlagCount(s) {
            return (s.anomalies || []).filter((a) => !a.resolved).length;
        },
        overviewMetrics(s) {
            const open = this.openFlagCount(s);
            return [
                { label: 'Typing speed', value: s.wpm, unit: 'wpm', tone: s.wpm > 0 ? 'text-[#ededed]' : 'text-[#666666]' },
                { label: 'Keystrokes', value: s.keystrokes.toLocaleString(), unit: '', tone: 'text-[#ededed]' },
                { label: 'Lines changed', value: '+' + s.lines_added + ' / −' + s.lines_deleted, unit: '', tone: 'text-[#ededed]' },
                { label: 'Focus lost', value: s.focus_lost, unit: s.focus_lost === 1 ? 'time' : 'times', tone: s.focus_lost > 2 ? 'text-amber-300' : (s.focus_lost > 0 ? 'text-[#ededed]' : 'text-[#666666]') },
                { label: 'Suspicious pastes', value: s.pastes, unit: '', tone: s.pastes > 0 ? 'text-red-300' : 'text-[#666666]' },
                { label: 'Open flags', value: open, unit: '', tone: open > 0 ? 'text-amber-300' : 'text-[#666666]' },
            ];
        },
        flagLabel(type) {
            const label = String(type || 'flag').replace(/_/g, ' ');
            return label.charAt(0).toUpperCase() + label.slice(1);
        },
        humanize(key) {
            return this.flagLabel(key);
        },
        relativeTime(value) {
            const then = new Date(value);
            if (!value || isNaN(then)) return value || '';
            const secs = Math.round((Date.now() - then.getTime()) / 1000);
            const units = [[31536000, 'year'], [2592000, 'month'], [86400, 'day'], [3600, 'hour'], [60, 'minute']];
            for (const [size, name] of units) {
                if (Math.abs(secs) >= size) return new Intl.RelativeTimeFormat(undefined, { numeric: 'auto' }).format(-Math.floor(secs / size), name);
            }
            return 'just now';
        },
        formatScore(value) {
            const n = Number(value || 0);
            return Number.isInteger(n) ? String(n) : n.toFixed(1).replace(/\.0$/, '');
        },
        safeColor(color) {
            return /^#[0-9a-fA-F]{3,8}$/.test(color || '') ? color : '#3ecf8e';
        },
        resolveFlag(item) {
            item._resolving = true;
            this.resolveError = '';
            const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';
            // The endpoint redirects back on success; don't follow it, just treat it as done.
            fetch(`/anomalies/${item.id}/resolve`, { method: 'POST', headers: { 'X-CSRF-TOKEN': token }, redirect: 'manual' })
                .then((res) => {
                    if (res.type !== 'opaqueredirect' && !res.ok) throw new Error();
                    item.resolved = true;
                    const row = this.roster.find((r) => r.id === this.student.id);
                    if (row) row.attention = Math.max(0, row.attention - 3);
                })
                .catch(() => { this.resolveError = "Couldn't resolve that flag. Try again."; })
                .finally(() => { item._resolving = false; });
        },

        submitGradeOverride() {
            if (!this.student) return;
            this.isSavingOverride = true;
            this.overrideFeedbackMessage = '';

            const url = `/instructor/sessions/${this.student.id}/override-grade`;
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
                    return res.json().then(err => { throw new Error(err.message || "Couldn't save the grade. Try again."); });
                }
                return res.json();
            })
            .then(data => {
                this.isSavingOverride = false;
                this.overrideFeedbackSuccess = true;
                this.overrideFeedbackMessage = 'Grade saved. Updating the roster…';
                if (data && data.session) {
                    this.student.instructor_grade_override = data.session.instructor_grade_override;
                    this.student.effective_score = data.session.effective_score;
                    this.student.is_overridden = data.session.is_overridden;
                    this.student.instructor_override_reason = data.session.override_reason;
                    this.student.instructor_overridden_at = data.session.overridden_at;
                    this.student.overridden_by_name = data.session.overridden_by_name;
                }
                setTimeout(() => {
                    window.location.reload();
                }, 800);
            })
            .catch(err => {
                this.isSavingOverride = false;
                this.overrideFeedbackSuccess = false;
                this.overrideFeedbackMessage = err.message || "Couldn't save the grade. Try again.";
            });
        },

        // Cohort Plagiarism State
        showSimilarityMatrix: false,
        isScanningPlagiarism: false,
        isCompareModalOpen: false,
        selectedPair: null,
        plagiarism: @json($plagiarismAnalysis),

        similarityAt(a, b) {
            return Number((this.plagiarism.matrix && this.plagiarism.matrix[a] && this.plagiarism.matrix[a][b]) || 0);
        },
        similarityCellClass(a, b) {
            if (a === b) return 'border-transparent text-[#444444]';
            const v = this.similarityAt(a, b);
            if (v >= 75) return 'border-red-500/40 bg-red-500/25 text-red-200 font-semibold';
            if (v >= 50) return 'border-amber-500/35 bg-amber-500/20 text-amber-200 font-medium';
            return 'border-[#2e2e2e] bg-[#232323] text-[#888888]';
        },

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
            const newestAnomaly = (data) => (data.sessions || []).flatMap(s => (s.anomalies || []).map(a => ({ ...a, student_name: s.user_name })))
                .reduce((latest, a) => (!latest || a.id > latest.id ? a : latest), null);
            setInterval(() => {
                fetch('{{ route("instructor.monitoring.data", $laboratory->id) }}', { headers: { 'Accept': 'application/json' } })
                    .then(res => res.json())
                    .then(data => {
                        if (!data || !Array.isArray(data.sessions)) return;
                        const newest = newestAnomaly(data);
                        const newestId = newest ? newest.id : 0;
                        if (this.lastAnomalyId !== undefined && newestId > this.lastAnomalyId) {
                            this.showLiveAlert('anomaly.detected', newest);
                        }
                        this.lastAnomalyId = newestId;
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
                        let data = null;
                        try { data = typeof payload.data === 'string' ? JSON.parse(payload.data) : payload.data; } catch {}
                        this.showLiveAlert(payload.event, data && data.anomaly);
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

        showLiveAlert(eventType, anomaly = null) {
            this.hasLiveUpdate = true;
            if (eventType !== 'anomaly.detected') {
                // Don't let a routine activity update hide an unseen flag.
                if (!this.liveAlertAnomaly) this.liveUpdateMessage = 'Student activity updated.';
                return;
            }
            this.liveAlertAnomaly = anomaly && anomaly.type ? anomaly : null;
            if (!this.liveAlertAnomaly) {
                this.liveUpdateMessage = 'New integrity flag from a student.';
                return;
            }
            const label = String(anomaly.type).replace(/_/g, ' ');
            this.liveUpdateMessage = `New flag from ${anomaly.student_name || 'a student'}: ${label.charAt(0).toUpperCase() + label.slice(1)}.`;
        },

        matchesSearch(name, group) {
            if (!this.searchQuery.trim()) return true;
            const q = this.searchQuery.toLowerCase();
            return name.includes(q) || ({{ $laboratory->is_group_lab ? 'true' : 'false' }} && Boolean(group) && group.includes(q));
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

@extends('layouts.app')

@section('title', ($lab->title ?? 'Lab').' results')

@php
    // 85 shows as "85", 85.5 as "85.5" (as on the transcript)
    $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 1), '0'), '.');
    $duration = function (int $seconds): string {
        if ($seconds <= 0) {
            return '—';
        }
        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);
        $s = $seconds % 60;

        return $h > 0 ? sprintf('%dh %02dm', $h, $m) : ($m > 0 ? sprintf('%dm %02ds', $m, $s) : "{$s}s");
    };

    $student = $session->user;
    $team = $session->group;
    $grade = $session->effective_score;
    $overridden = $session->isGradeOverridden();
    $provisional = $session->needsGradeReview();
    $timedOut = $session->status === 'abandoned';
    $isCounting = $counting && $counting->id === $session->id;
    $completedCount = $attempts->where('status', 'completed')->count();
    $latestRegrade = $regrades->last();
    $earlierRegrades = $regrades->slice(0, -1)->reverse();
    $reviewer = $session->overriddenByUser->name ?? ($latestRegrade['by'] ?? 'Your instructor');

    $subtitle = collect([
        $isStaff && ! $isOwner ? ($student->name ?? 'Student') : null,
        $team ? $team->name : null,
        $attemptNumber ? "Attempt {$attemptNumber}" : null,
    ])->filter()->implode(' · ');
@endphp

@section('content')
<div class="space-y-6">
    <x-page-header :back="route('laboratories.show', $lab->id)" :back-label="$lab->title" eyebrow="Lab results"
                   :title="$lab->title" :subtitle="$subtitle ?: null">
        @if ($isStaff)
            <a href="{{ route('instructor.monitoring.show', $lab->id) }}" class="ui-btn ui-btn-secondary">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12h4l3-8 4 16 3-8h4"/></svg>
                Monitoring
            </a>
        @endif
    </x-page-header>

    {{-- Grade, and the instructor's regrade with its reason --}}
    <section class="ui-card ui-card-body" aria-labelledby="grade-heading">
        <div class="flex flex-col sm:flex-row sm:items-start sm:justify-between gap-5">
            <div>
                <h2 id="grade-heading" class="ui-eyebrow">{{ $overridden ? 'Final grade' : ($graded ? 'Grade' : 'Not graded') }}</h2>
                @if ($graded)
                    <p class="cc-display mt-2 text-5xl font-bold tabular-nums {{ $isCounting ? 'text-[#3ecf8e]' : 'text-[#ededed]' }}">{{ $fmt($grade) }}%</p>
                @else
                    <p class="cc-display mt-2 text-5xl font-bold tabular-nums text-[#666666]">—</p>
                @endif
                <div class="mt-3 flex flex-wrap items-center gap-2">
                    @if ($overridden)
                        <span class="ui-badge ui-badge-brand">{{ $confirmed ? 'Reviewed by instructor' : 'Regraded by instructor' }}</span>
                    @elseif ($provisional)
                        <span class="ui-badge ui-badge-warn">Provisional</span>
                    @endif
                    @if ($timedOut)
                        <span class="ui-badge ui-badge-danger">Timed out</span>
                    @elseif ($isCounting && $completedCount > 1)
                        <span class="ui-badge">Best attempt</span>
                    @endif
                </div>
            </div>

            <dl class="grid grid-cols-2 gap-px rounded-xl overflow-hidden border border-[#2e2e2e] bg-[#2e2e2e] sm:min-w-[18rem]">
                <div class="bg-[#0f0f0f] p-3.5">
                    <dt class="text-xs text-[#888888]">AI grade</dt>
                    <dd class="mt-1 text-xl font-semibold tabular-nums {{ $overridden && ! $confirmed ? 'text-[#666666] line-through decoration-1' : 'text-[#ededed]' }}">
                        {{ $submitted || $session->performance_score ? $fmt($session->performance_score ?? 0).'%' : '—' }}
                    </dd>
                </div>
                <div class="bg-[#0f0f0f] p-3.5">
                    <dt class="text-xs text-[#888888]">Lab grade</dt>
                    <dd class="mt-1 text-xl font-semibold tabular-nums text-[#ededed]">{{ $counting ? $fmt($counting->effective_score).'%' : '—' }}</dd>
                    <dd class="text-[11px] text-[#888888]">{{ $counting ? ($isCounting ? 'from this attempt' : 'from the best attempt') : 'no completed attempt' }}</dd>
                </div>
            </dl>
        </div>

        @if ($graded)
            <div class="mt-6 h-2 rounded-full bg-[#232323]" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $fmt($grade) }}" aria-label="Grade">
                <div class="h-2 rounded-full {{ $isCounting ? 'bg-[#3ecf8e]' : 'bg-[#ededed]/60' }}" style="width: {{ min(100, max(0, $grade)) }}%"></div>
            </div>
        @endif

        {{-- Why the grade stands where it does --}}
        <div class="mt-5 space-y-2 text-sm text-[#a3a3a3]">
            @if ($timedOut)
                <p>This attempt timed out before it was submitted, so it doesn't count toward the lab grade.</p>
            @elseif ($session->status === 'completed' && ! $submitted && ! $overridden)
                <p>This attempt ended before it was submitted, so the grade comes from the progress saved when it ended.</p>
            @endif
            @if ($provisional)
                <p>This grade comes from automatic checks only. Your instructor will review the code and may change it.</p>
            @endif
            @if ($session->status === 'completed' && $counting)
                @if ($isCounting)
                    <p>{{ $completedCount > 1 ? 'This is the best of the completed attempts, so this grade is the one that counts.' : 'This grade counts for the lab.' }}</p>
                @else
                    <p>
                        The best grade on this lab is {{ $fmt($counting->effective_score) }}%
                        (<a href="{{ route('sessions.show', $counting->id) }}" class="font-semibold text-[#3ecf8e] hover:underline underline-offset-2">attempt {{ $attempts->search(fn ($a) => $a->id === $counting->id) + 1 }}</a>), and that is the one that counts.
                    </p>
                @endif
            @endif
        </div>

        @if ($overridden)
            <div id="review" class="mt-6 rounded-xl border border-[#2e2e2e] bg-[#141414] p-4 sm:p-5">
                <h3 class="text-sm font-semibold text-[#ededed]">{{ $confirmed ? 'Instructor review' : 'Why the grade changed' }}</h3>
                <p class="mt-1 text-sm text-[#a3a3a3]">
                    @if ($confirmed)
                        {{ $reviewer }} reviewed the grade and kept it at {{ $fmt($session->instructor_grade_override) }}%@if ($session->instructor_overridden_at) on <x-local-time :at="$session->instructor_overridden_at" />@endif.
                    @else
                        {{ $reviewer }} changed the grade from {{ $fmt($previousGrade) }}% to {{ $fmt($session->instructor_grade_override) }}%@if ($session->instructor_overridden_at) on <x-local-time :at="$session->instructor_overridden_at" />@endif.
                    @endif
                </p>
                @if (filled($session->instructor_override_reason))
                    <blockquote class="mt-3 border-l-2 border-[#3ecf8e]/40 pl-3 text-sm text-[#ededed] leading-relaxed whitespace-pre-line break-words">{{ $session->instructor_override_reason }}</blockquote>
                @else
                    <p class="mt-3 text-sm text-[#888888]">No reason was given.</p>
                @endif

                @if ($earlierRegrades->isNotEmpty())
                    <details class="mt-4">
                        <summary class="cursor-pointer text-sm text-[#888888] hover:text-[#ededed] transition-colors">
                            {{ $earlierRegrades->count() }} earlier {{ \Illuminate\Support\Str::plural('change', $earlierRegrades->count()) }}
                        </summary>
                        <ol class="mt-3 space-y-3" role="list">
                            @foreach ($earlierRegrades as $change)
                                <li class="text-sm">
                                    <p class="text-[#a3a3a3]">{{ $change['by'] }} set it to {{ $fmt($change['score']) }}%@if ($change['at']) on <x-local-time :at="$change['at']" />@endif.</p>
                                    @if (filled($change['reason']))
                                        <p class="mt-1 border-l-2 border-[#2e2e2e] pl-3 text-[#888888] whitespace-pre-line break-words">{{ $change['reason'] }}</p>
                                    @endif
                                </li>
                            @endforeach
                        </ol>
                    </details>
                @endif
            </div>
        @endif
    </section>

    {{-- Stats --}}
    <dl class="grid grid-cols-2 {{ $showPersonalStats ? 'lg:grid-cols-4' : 'lg:grid-cols-3' }} gap-3 sm:gap-4" aria-label="Session stats">
        <div class="rounded-xl border border-[#2e2e2e] bg-[#171717] p-5">
            <dt class="font-mono text-[11px] uppercase tracking-[0.14em] text-[#888888]">Time spent</dt>
            <dd class="cc-display mt-2 text-2xl font-bold tabular-nums text-[#ededed]">{{ $duration($stats['time_spent_seconds']) }}</dd>
            <dd class="mt-1 text-xs text-[#888888]">{{ $stats['time_limit_minutes'] ? "of a {$stats['time_limit_minutes']} min limit" : ($lab->isLiveLab() ? 'live lab' : 'no time limit') }}</dd>
        </div>
        <div class="rounded-xl border border-[#2e2e2e] bg-[#171717] p-5">
            <dt class="font-mono text-[11px] uppercase tracking-[0.14em] text-[#888888]">Tasks done</dt>
            <dd class="cc-display mt-2 text-2xl font-bold tabular-nums text-[#ededed]">{{ $stats['tasks_done'] }}<span class="text-[#666666]">/{{ $stats['tasks_total'] }}</span></dd>
            <dd class="mt-1 text-xs text-[#888888]">{{ $stats['tasks_total'] > 0 ? round($stats['tasks_done'] / $stats['tasks_total'] * 100).'% of the checklist' : 'no checklist' }}</dd>
        </div>
        @if ($showPersonalStats)
            <div class="rounded-xl border border-[#2e2e2e] bg-[#171717] p-5">
                <dt class="font-mono text-[11px] uppercase tracking-[0.14em] text-[#888888]">Typing speed</dt>
                <dd class="cc-display mt-2 text-2xl font-bold tabular-nums text-[#ededed]">{{ $stats['wpm'] }} <span class="text-sm font-medium text-[#888888]">WPM</span></dd>
                <dd class="mt-1 text-xs text-[#888888]">{{ number_format($stats['keystrokes']) }} {{ \Illuminate\Support\Str::plural('keystroke', $stats['keystrokes']) }}</dd>
            </div>
        @endif
        <div class="rounded-xl border border-[#2e2e2e] bg-[#171717] p-5">
            <dt class="font-mono text-[11px] uppercase tracking-[0.14em] text-[#888888]">Lines changed</dt>
            <dd class="cc-display mt-2 text-2xl font-bold tabular-nums text-[#ededed]">
                <span class="text-[#3ecf8e]">+{{ number_format($stats['lines_added']) }}</span>
                <span class="text-[#888888]">−{{ number_format($stats['lines_deleted']) }}</span>
            </dd>
            <dd class="mt-1 text-xs text-[#888888]">added and removed</dd>
        </div>
    </dl>

    <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_340px] gap-6 items-start">
        {{-- Tasks and the grader's feedback --}}
        <section class="ui-card" aria-labelledby="tasks-heading">
            <div class="px-5 pt-5 pb-3 border-b border-[#232323]">
                <h2 id="tasks-heading" class="ui-card-title">Tasks</h2>
                <p class="ui-card-subtitle">{{ $stats['tasks_done'] }} of {{ $stats['tasks_total'] }} done{{ $team ? ' · graded as one team submission' : '' }}</p>
            </div>
            @if (empty($tasks))
                <p class="px-5 py-10 text-center text-sm text-[#888888]">This lab has no task checklist.</p>
            @else
                <ol class="divide-y divide-[#232323]" role="list">
                    @foreach ($tasks as $task)
                        <li class="flex items-start gap-3 px-5 py-4">
                            @if ($task['done'])
                                <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-[#3ecf8e]/15 text-[#3ecf8e]" aria-hidden="true">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                </span>
                            @else
                                <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-[#232323] text-[#888888]" aria-hidden="true">
                                    <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24"><path stroke-linecap="round" d="M6 18 18 6M6 6l12 12"/></svg>
                                </span>
                            @endif
                            <div class="min-w-0">
                                <p class="text-sm text-[#ededed] break-words">{{ $task['task'] }}<span class="sr-only">{{ $task['done'] ? ': done' : ': not done' }}</span></p>
                                @if (filled($task['feedback']))
                                    <p class="mt-1 text-sm text-[#888888] leading-relaxed break-words">{{ $task['feedback'] }}</p>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ol>
            @endif

            @if (filled($feedback['overall']) || filled($feedback['code_quality']))
                <div class="space-y-3 border-t border-[#232323] px-5 py-4">
                    <h3 class="text-sm font-semibold text-[#ededed]">Feedback</h3>
                    @if (filled($feedback['overall']))
                        <p class="text-sm text-[#a3a3a3] leading-relaxed whitespace-pre-line break-words">{{ $feedback['overall'] }}</p>
                    @endif
                    @if (filled($feedback['code_quality']))
                        <p class="text-sm text-[#a3a3a3] leading-relaxed break-words"><span class="text-[#ededed]">Code quality:</span> {{ $feedback['code_quality'] }}</p>
                    @endif
                </div>
            @endif
        </section>

        <aside class="space-y-6">
            {{-- Session details --}}
            <section class="ui-card ui-card-body" aria-labelledby="details-heading">
                <h2 id="details-heading" class="ui-card-title">Session details</h2>
                <dl class="mt-3 divide-y divide-[#232323] text-sm">
                    <div class="flex items-baseline justify-between gap-4 py-2">
                        <dt class="text-[#888888]">Started</dt>
                        <dd class="text-right text-[#ededed]">@if ($session->started_at)<x-local-time :at="$session->started_at" />@else — @endif</dd>
                    </div>
                    <div class="flex items-baseline justify-between gap-4 py-2">
                        <dt class="text-[#888888]">{{ $submitted && ! $timedOut ? 'Submitted' : 'Ended' }}</dt>
                        <dd class="text-right text-[#ededed]">@if ($session->ended_at)<x-local-time :at="$session->ended_at" />@else — @endif</dd>
                    </div>
                    @if ($showPersonalStats)
                        <div class="flex items-baseline justify-between gap-4 py-2">
                            <dt class="flex items-center gap-1.5 text-[#888888]">Focus lost
                                <x-info-tip align="left" label="About focus lost">Times the lab window lost focus.</x-info-tip>
                            </dt>
                            <dd class="tabular-nums text-[#ededed]">{{ $stats['focus_lost'] }}</dd>
                        </div>
                        <div class="flex items-baseline justify-between gap-4 py-2">
                            <dt class="flex items-center gap-1.5 text-[#888888]">Pastes flagged
                                <x-info-tip align="left" label="About flagged pastes">Large pastes into the editor.</x-info-tip>
                            </dt>
                            <dd class="tabular-nums text-[#ededed]">{{ $stats['pastes'] }}</dd>
                        </div>
                    @endif
                </dl>
            </section>

            {{-- Team --}}
            @if ($team)
                <section class="ui-card ui-card-body" aria-labelledby="team-heading">
                    <h2 id="team-heading" class="ui-card-title">{{ $team->name }}</h2>
                    <p class="ui-card-subtitle">The team's code was graded as one submission.</p>
                    <ul class="mt-3 space-y-1.5 text-sm text-[#a3a3a3]" role="list">
                        @foreach ($team->members as $member)
                            <li>{{ $member->name }}</li>
                        @endforeach
                    </ul>
                </section>
            @endif

            {{-- Attempts --}}
            <section class="ui-card" aria-labelledby="attempts-heading">
                <div class="px-5 pt-5 pb-3">
                    <h2 id="attempts-heading" class="ui-card-title">Attempts</h2>
                    <p class="ui-card-subtitle">The best completed attempt counts.</p>
                </div>
                <ol class="divide-y divide-[#232323] border-t border-[#232323]" role="list">
                    @foreach ($attempts->reverse() as $index => $attempt)
                        @php
                            $isCurrent = $attempt->id === $session->id;
                            $attemptDate = $attempt->ended_at ?? $attempt->started_at;
                        @endphp
                        <li>
                            <a href="{{ route('sessions.show', $attempt->id) }}" @if ($isCurrent) aria-current="page" @endif
                               class="flex items-center justify-between gap-3 px-5 py-3 transition-colors hover:bg-[#1c1c1c] {{ $isCurrent ? 'bg-[#141414]' : '' }}">
                                <span class="min-w-0">
                                    <span class="block text-sm font-medium text-[#ededed]">Attempt {{ $index + 1 }}{{ $isCurrent ? ' (this one)' : '' }}</span>
                                    @if ($attemptDate)
                                        <span class="block text-xs text-[#888888]"><x-local-time :at="$attemptDate" date /></span>
                                    @endif
                                </span>
                                <span class="shrink-0 text-right">
                                    @if ($attempt->status === 'in_progress')
                                        <span class="ui-badge">In progress</span>
                                    @elseif ($attempt->status === 'abandoned' && $attempt->instructor_grade_override === null)
                                        <span class="text-sm text-[#888888]">Timed out</span>
                                    @else
                                        <span class="block text-sm font-semibold tabular-nums {{ $counting && $attempt->id === $counting->id ? 'text-[#3ecf8e]' : 'text-[#ededed]' }}">{{ $fmt($attempt->effective_score) }}%</span>
                                        @if ($attempt->instructor_grade_override !== null)
                                            <span class="block text-[10px] text-[#888888]">regraded</span>
                                        @endif
                                    @endif
                                </span>
                            </a>
                        </li>
                    @endforeach
                </ol>
            </section>
        </aside>
    </div>
</div>
@endsection

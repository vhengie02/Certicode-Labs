@extends('layouts.app')

@section('title', ($selectedLab ? $selectedLab->title . ' · ' : '') . 'Monitoring · ' . $class->name)

@section('content')
<div class="space-y-6">
    {{-- Class header --}}
    <header>
        <a href="{{ route('classes.show', $class->id) }}" class="inline-flex items-center gap-1.5 text-sm text-[#888888] hover:text-[#ededed] transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            {{ $class->name }}
        </a>
        <h1 class="cc-display mt-2 text-3xl font-bold text-[#ededed]">Monitoring</h1>
        <p class="mt-1 text-[15px] text-[#a3a3a3]">Integrity flags and student sessions across the class, or live detail for one lab.</p>
    </header>

    {{-- Lab switcher --}}
    <nav class="monitor-tabs -mx-4 sm:mx-0 px-4 sm:px-0 flex items-center gap-1.5 overflow-x-auto border-b border-[#2e2e2e] pb-px" aria-label="Choose what to monitor">
        <a href="{{ route('classes.telemetry', $class->id) }}" @if(!$selectedLab) aria-current="page" @endif
           class="shrink-0 relative px-3 py-2.5 text-sm font-medium transition-colors {{ !$selectedLab ? 'text-[#ededed] monitor-tab-active' : 'text-[#888888] hover:text-[#ededed]' }}">
            All labs
        </a>
        @foreach ($labs as $lab)
            @php
                $isSelected = $selectedLab && $selectedLab->id === $lab->id;
                $isLive = $lab->availability_mode === 'live' && $lab->live_status === 'active';
            @endphp
            <a href="{{ route('classes.telemetry', ['class_id' => $class->id, 'lab' => $lab->id]) }}" @if($isSelected) aria-current="page" @endif
               class="shrink-0 relative inline-flex items-center gap-2 px-3 py-2.5 text-sm font-medium transition-colors {{ $isSelected ? 'text-[#ededed] monitor-tab-active' : 'text-[#888888] hover:text-[#ededed]' }}">
                @if ($isLive)
                    <span class="relative flex h-2 w-2" aria-hidden="true">
                        <span class="absolute inline-flex h-full w-full rounded-full bg-[#3ecf8e] opacity-60 animate-ping"></span>
                        <span class="relative inline-flex h-2 w-2 rounded-full bg-[#3ecf8e]"></span>
                    </span>
                    <span class="sr-only">Live now:</span>
                @endif
                <span class="max-w-[14rem] truncate">{{ $lab->title }}</span>
            </a>
        @endforeach
    </nav>

    @if ($selectedLab)
        @include('instructor.monitoring._lab-panel')
    @else
        @php
            $inProgressCount = $sessions->where('status', 'in_progress')->count();
            $openFlags = $anomalies->where('resolved', false)->count();
            $submittedCount = $sessions->where('status', 'completed')->count();
            $scored = $sessions->where('status', 'completed')->filter(fn ($s) => $s->performance_score !== null);
            $avgScore = $scored->isNotEmpty() ? round($scored->avg(fn ($s) => (float) $s->effective_score)) : null;
            $severityStyle = fn ($severity) => [
                'high' => 'bg-red-500/10 text-red-400 border-red-500/20',
                'medium' => 'bg-yellow-500/10 text-yellow-400 border-yellow-500/20',
            ][$severity] ?? 'bg-sky-500/10 text-sky-400 border-sky-500/20';
            $labLink = fn ($labId) => route('classes.telemetry', ['class_id' => $class->id, 'lab' => $labId]);
        @endphp

        <dl class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
            <div class="rounded-xl border border-[#2e2e2e] bg-[#171717] p-5">
                <dt class="flex items-center gap-1.5 font-mono text-[11px] uppercase tracking-[0.14em] text-[#888888]">
                    In progress
                    <x-info-tip align="left">Sessions that have started but haven't been submitted, across every lab in this class. It counts students whether or not they're connected right now; pick a lab above to see who is online.</x-info-tip>
                </dt>
                <dd class="cc-display mt-2 text-3xl font-bold tabular-nums text-[#ededed]">{{ $inProgressCount }}</dd>
            </div>
            <div class="rounded-xl border border-[#2e2e2e] bg-[#171717] p-5">
                <dt class="flex items-center gap-1.5 font-mono text-[11px] uppercase tracking-[0.14em] text-[#888888]">
                    Open flags
                    <x-info-tip>Integrity flags you haven't resolved yet: switching away from VS Code 3+ times in 2 minutes, suspicious pastes, the camera not seeing exactly one face, and long idle periods. Resolve a flag once you've reviewed it.</x-info-tip>
                </dt>
                <dd class="cc-display mt-2 text-3xl font-bold tabular-nums {{ $openFlags > 0 ? 'text-amber-400' : 'text-[#ededed]' }}">{{ $openFlags }}</dd>
            </div>
            <div class="rounded-xl border border-[#2e2e2e] bg-[#171717] p-5">
                <dt class="flex items-center gap-1.5 font-mono text-[11px] uppercase tracking-[0.14em] text-[#888888]">
                    Submitted
                    <x-info-tip>Sessions that are finished and graded, across every lab: submitted by the student, ended by you, or auto-submitted when a live lab's timer ran out.</x-info-tip>
                </dt>
                <dd class="cc-display mt-2 text-3xl font-bold tabular-nums text-[#ededed]">{{ $submittedCount }}</dd>
            </div>
            <div class="rounded-xl border border-[#2e2e2e] bg-[#171717] p-5">
                <dt class="flex items-center gap-1.5 font-mono text-[11px] uppercase tracking-[0.14em] text-[#888888]">
                    Average grade
                    <x-info-tip align="right">The mean grade of submitted sessions. Where you've overridden a grade, your grade is used instead of the AI's.</x-info-tip>
                </dt>
                <dd class="cc-display mt-2 text-3xl font-bold tabular-nums text-[#ededed]">{{ $avgScore === null ? '—' : $avgScore . '%' }}</dd>
            </div>
        </dl>

        {{-- Integrity flags --}}
        <section class="rounded-xl border border-[#2e2e2e] bg-[#171717]" aria-labelledby="flags-heading">
            <div class="flex items-center justify-between gap-3 px-5 pt-5 pb-3">
                <h2 id="flags-heading" class="text-sm font-semibold text-[#ededed]">Integrity flags</h2>
                <span class="text-xs text-[#888888]">{{ $anomalies->count() }} total</span>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-y border-[#232323] text-left font-mono text-[11px] uppercase tracking-[0.12em] text-[#888888]">
                            <th scope="col" class="px-5 py-2.5 font-medium">Student</th>
                            <th scope="col" class="px-5 py-2.5 font-medium">Lab</th>
                            <th scope="col" class="px-5 py-2.5 font-medium">What happened</th>
                            <th scope="col" class="px-5 py-2.5 font-medium">Severity</th>
                            <th scope="col" class="px-5 py-2.5 font-medium">When</th>
                            <th scope="col" class="px-5 py-2.5 font-medium text-right"><span class="sr-only">Status</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#232323]">
                        @forelse ($anomalies as $anomaly)
                            @php $flagSession = $anomaly->labSession; @endphp
                            <tr class="{{ $anomaly->resolved ? '' : 'bg-amber-400/[0.03]' }}">
                                <td class="px-5 py-3 whitespace-nowrap font-medium text-[#ededed]">{{ $flagSession?->user?->name ?? 'Unknown' }}</td>
                                <td class="px-5 py-3 whitespace-nowrap">
                                    @if ($flagSession?->laboratory)
                                        <a href="{{ $labLink($flagSession->lab_id) }}" class="text-[#a3a3a3] hover:text-[#3ecf8e] hover:underline underline-offset-2">{{ $flagSession->laboratory->title }}</a>
                                    @else
                                        <span class="text-[#888888]">Removed lab</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 max-w-xs">
                                    <span class="block font-mono text-xs text-[#ededed]">{{ str_replace('_', ' ', $anomaly->type) }}</span>
                                    <span class="block text-xs text-[#888888] truncate" title="{{ $anomaly->description }}">{{ $anomaly->description }}</span>
                                </td>
                                <td class="px-5 py-3 whitespace-nowrap">
                                    <span class="px-2 py-0.5 rounded-md border font-mono text-[10px] uppercase tracking-wider {{ $severityStyle($anomaly->severity) }}">{{ $anomaly->severity }}</span>
                                </td>
                                <td class="px-5 py-3 whitespace-nowrap text-xs text-[#888888]" title="{{ $anomaly->created_at }}">{{ $anomaly->created_at?->diffForHumans() }}</td>
                                <td class="px-5 py-3 whitespace-nowrap text-right">
                                    @if ($anomaly->resolved)
                                        <span class="inline-flex items-center gap-1 text-xs text-[#888888]">
                                            <svg class="w-3.5 h-3.5 text-[#3ecf8e]" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                            Resolved
                                        </span>
                                    @else
                                        <form action="{{ route('anomalies.resolve', $anomaly->id) }}" method="POST" class="inline">
                                            @csrf
                                            <button type="submit" class="h-8 px-3 rounded-lg border border-[#2e2e2e] text-xs font-medium text-[#ededed] hover:border-[#3ecf8e]/40 transition-colors">Resolve</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-10 text-center text-sm text-[#888888]">No integrity flags in this class.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Sessions --}}
        <section class="rounded-xl border border-[#2e2e2e] bg-[#171717]" aria-labelledby="sessions-heading">
            <div class="flex items-center justify-between gap-3 px-5 pt-5 pb-3">
                <h2 id="sessions-heading" class="text-sm font-semibold text-[#ededed]">Student sessions</h2>
                <span class="text-xs text-[#888888]">{{ $sessions->count() }} total</span>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-y border-[#232323] text-left font-mono text-[11px] uppercase tracking-[0.12em] text-[#888888]">
                            <th scope="col" class="px-5 py-2.5 font-medium">Student</th>
                            <th scope="col" class="px-5 py-2.5 font-medium">Lab</th>
                            <th scope="col" class="px-5 py-2.5 font-medium">Status</th>
                            <th scope="col" class="px-5 py-2.5 font-medium">
                                <span class="inline-flex items-center gap-1.5">Grade <x-info-tip>The AI grade for submitted sessions, or your override if you've set one. In-progress sessions show the score so far.</x-info-tip></span>
                            </th>
                            <th scope="col" class="px-5 py-2.5 font-medium">Started</th>
                            <th scope="col" class="px-5 py-2.5 font-medium text-right"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#232323]">
                        @forelse ($sessions as $sess)
                            @php
                                $statusStyle = [
                                    'completed' => 'text-[#3ecf8e] border-[#3ecf8e]/30 bg-[#3ecf8e]/[0.06]',
                                    'in_progress' => 'text-sky-400 border-sky-500/30 bg-sky-500/[0.06]',
                                ][$sess->status] ?? 'text-[#888888] border-[#2e2e2e]';
                            @endphp
                            <tr>
                                <td class="px-5 py-3 whitespace-nowrap font-medium text-[#ededed]">{{ $sess->user?->name ?? 'Unknown' }}</td>
                                <td class="px-5 py-3 whitespace-nowrap">
                                    @if ($sess->laboratory)
                                        <a href="{{ $labLink($sess->lab_id) }}" class="text-[#a3a3a3] hover:text-[#3ecf8e] hover:underline underline-offset-2">{{ $sess->laboratory->title }}</a>
                                    @else
                                        <span class="text-[#888888]">Removed lab</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 whitespace-nowrap">
                                    <span class="px-2 py-0.5 rounded-md border text-[11px] font-medium {{ $statusStyle }}">{{ str_replace('_', ' ', $sess->status) }}</span>
                                </td>
                                <td class="px-5 py-3 whitespace-nowrap font-mono tabular-nums text-[#ededed]">{{ round((float) $sess->effective_score) }}%</td>
                                <td class="px-5 py-3 whitespace-nowrap text-xs text-[#888888]">{{ $sess->started_at ? \Carbon\Carbon::parse($sess->started_at)->format('M j, H:i') : '—' }}</td>
                                <td class="px-5 py-3 whitespace-nowrap text-right">
                                    <a href="{{ route('sessions.telemetry-timeline', $sess->id) }}" class="inline-flex items-center h-8 px-3 rounded-lg border border-[#2e2e2e] text-xs font-medium text-[#ededed] hover:border-[#3ecf8e]/40 transition-colors">Timeline</a>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="6" class="px-5 py-10 text-center text-sm text-[#888888]">No student has started a lab in this class yet.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    @endif
</div>
@endsection

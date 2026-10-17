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

    {{-- Lab switcher: "All labs" plus a searchable picker, so any number of labs fits on one row --}}
    @php
        $isLiveLab = fn ($lab) => $lab->availability_mode === 'live' && $lab->live_status === 'active';
        // Live labs first, then the controller's order.
        $pickerLabs = $labs->sortBy(fn ($lab) => $isLiveLab($lab) ? 0 : 1, SORT_REGULAR, false)->values();
        $liveLabCount = $labs->filter($isLiveLab)->count();
    @endphp
    <nav class="flex flex-wrap items-center gap-2 border-b border-[#2e2e2e] pb-3" aria-label="Choose what to monitor">
        <a href="{{ route('classes.telemetry', $class->id) }}" @if(!$selectedLab) aria-current="page" @endif
           class="shrink-0 inline-flex items-center h-9 px-3 rounded-lg border text-sm font-medium transition-colors {{ !$selectedLab ? 'bg-[#1c1c1c] border-[#383838] text-[#ededed]' : 'border-transparent text-[#888888] hover:text-[#ededed] hover:bg-[#1c1c1c]' }}">
            All labs
        </a>

        @if ($labs->isNotEmpty())
            <div class="relative min-w-0 flex-1 sm:flex-none"
                 x-data="{ open: false, q: '' }"
                 @keydown.escape.window="open = false"
                 @click.outside="open = false">
                <button type="button" @click="open = !open; if (open) $nextTick(() => $refs.search?.focus())"
                        :aria-expanded="open" aria-haspopup="listbox"
                        class="w-full sm:w-auto sm:max-w-[28rem] inline-flex items-center gap-2 h-9 px-3 rounded-lg border text-sm font-medium transition-colors {{ $selectedLab ? 'bg-[#1c1c1c] border-[#383838] text-[#ededed]' : 'border-[#2e2e2e] text-[#888888] hover:text-[#ededed] hover:border-[#383838]' }}">
                    @if ($selectedLab && $isLiveLab($selectedLab))
                        <span class="relative flex h-2 w-2 shrink-0" aria-hidden="true">
                            <span class="absolute inline-flex h-full w-full rounded-full bg-[#3ecf8e] opacity-60 animate-ping"></span>
                            <span class="relative inline-flex h-2 w-2 rounded-full bg-[#3ecf8e]"></span>
                        </span>
                    @endif
                    <span class="truncate">{{ $selectedLab ? $selectedLab->title : 'Pick a lab' }}</span>
                    <span class="shrink-0 text-xs text-[#666666] tabular-nums">{{ $labs->count() }}</span>
                    @if (!$selectedLab && $liveLabCount > 0)
                        <span class="shrink-0 inline-flex items-center gap-1 rounded-full bg-[#3ecf8e]/10 px-1.5 py-0.5 text-[11px] text-[#3ecf8e]">{{ $liveLabCount }} live</span>
                    @endif
                    <svg class="ml-auto w-4 h-4 shrink-0 transition-transform" :class="open && 'rotate-180'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                </button>

                <div x-show="open" x-cloak x-transition.opacity.duration.100ms
                     class="absolute left-0 z-40 mt-2 w-[min(28rem,calc(100vw-2rem))] rounded-xl border border-[#2e2e2e] bg-[#171717] shadow-[0_24px_60px_-20px_rgba(0,0,0,0.8)] overflow-hidden">
                    @if ($labs->count() > 6)
                        <div class="p-2 border-b border-[#2e2e2e]">
                            <input x-ref="search" x-model="q" type="search" placeholder="Search labs" aria-label="Search labs" class="ui-input h-9 w-full">
                        </div>
                    @endif
                    <ul class="max-h-80 overflow-y-auto py-1" role="listbox" aria-label="Labs">
                        @foreach ($pickerLabs as $lab)
                            @php $isSelected = $selectedLab && $selectedLab->id === $lab->id; @endphp
                            <li x-show="!q || {{ \Illuminate\Support\Js::from(mb_strtolower($lab->title)) }}.includes(q.toLowerCase())" role="option" @if($isSelected) aria-selected="true" @endif>
                                <a href="{{ route('classes.telemetry', ['class_id' => $class->id, 'lab' => $lab->id]) }}" @if($isSelected) aria-current="page" @endif
                                   class="flex items-center gap-2.5 px-3 py-2 text-sm transition-colors {{ $isSelected ? 'bg-[#1c1c1c] text-[#ededed]' : 'text-[#a3a3a3] hover:bg-[#1c1c1c] hover:text-[#ededed]' }}">
                                    <span class="flex h-2 w-2 shrink-0" aria-hidden="true">
                                        @if ($isLiveLab($lab))
                                            <span class="inline-flex h-2 w-2 rounded-full bg-[#3ecf8e]"></span>
                                        @endif
                                    </span>
                                    @if ($isLiveLab($lab))<span class="sr-only">Live now:</span>@endif
                                    <span class="min-w-0 flex-1 truncate" title="{{ $lab->title }}">{{ $lab->title }}</span>
                                    @if ($isSelected)
                                        <svg class="w-4 h-4 shrink-0 text-[#3ecf8e]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif
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
                <dt class="flex items-center gap-1.5 text-sm text-[#888888]">
                    In progress
                    <x-info-tip align="left">Sessions that have started but haven't been submitted, across every lab in this class. It counts students whether or not they're connected right now; pick a lab above to see who is online.</x-info-tip>
                </dt>
                <dd class="cc-display mt-2 text-3xl font-bold tabular-nums text-[#ededed]">{{ $inProgressCount }}</dd>
            </div>
            <div class="rounded-xl border border-[#2e2e2e] bg-[#171717] p-5">
                <dt class="flex items-center gap-1.5 text-sm text-[#888888]">
                    Open flags
                    <x-info-tip>Integrity flags you haven't resolved yet: switching away from VS Code 3+ times in 2 minutes, suspicious pastes, the camera not seeing exactly one face, and long idle periods. Resolve a flag once you've reviewed it.</x-info-tip>
                </dt>
                <dd class="cc-display mt-2 text-3xl font-bold tabular-nums {{ $openFlags > 0 ? 'text-amber-400' : 'text-[#ededed]' }}">{{ $openFlags }}</dd>
            </div>
            <div class="rounded-xl border border-[#2e2e2e] bg-[#171717] p-5">
                <dt class="flex items-center gap-1.5 text-sm text-[#888888]">
                    Submitted
                    <x-info-tip>Sessions that are finished and graded, across every lab: submitted by the student, ended by you, or auto-submitted when a live lab's timer ran out.</x-info-tip>
                </dt>
                <dd class="cc-display mt-2 text-3xl font-bold tabular-nums text-[#ededed]">{{ $submittedCount }}</dd>
            </div>
            <div class="rounded-xl border border-[#2e2e2e] bg-[#171717] p-5">
                <dt class="flex items-center gap-1.5 text-sm text-[#888888]">
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
                        <tr class="border-y border-[#232323] text-left text-xs text-[#888888]">
                            <th scope="col" class="px-5 py-2.5 font-normal">Student</th>
                            <th scope="col" class="px-5 py-2.5 font-normal">Lab</th>
                            <th scope="col" class="px-5 py-2.5 font-normal">What happened</th>
                            <th scope="col" class="px-5 py-2.5 font-normal">Severity</th>
                            <th scope="col" class="px-5 py-2.5 font-normal">When</th>
                            <th scope="col" class="px-5 py-2.5 font-normal text-right"><span class="sr-only">Status</span></th>
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
                                    <span class="block text-sm text-[#ededed]">{{ ucfirst(str_replace('_', ' ', $anomaly->type)) }}</span>
                                    <span class="block text-xs text-[#888888] truncate" title="{{ $anomaly->description }}">{{ $anomaly->description }}</span>
                                </td>
                                <td class="px-5 py-3 whitespace-nowrap">
                                    <span class="px-2 py-0.5 rounded-md border text-xs {{ $severityStyle($anomaly->severity) }}">{{ ucfirst($anomaly->severity) }}</span>
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
                        <tr class="border-y border-[#232323] text-left text-xs text-[#888888]">
                            <th scope="col" class="px-5 py-2.5 font-normal">Student</th>
                            <th scope="col" class="px-5 py-2.5 font-normal">Lab</th>
                            <th scope="col" class="px-5 py-2.5 font-normal">Status</th>
                            <th scope="col" class="px-5 py-2.5 font-normal">
                                <span class="inline-flex items-center gap-1.5">Grade <x-info-tip>The AI grade for submitted sessions, or your override if you've set one.</x-info-tip></span>
                            </th>
                            <th scope="col" class="px-5 py-2.5 font-normal">Started</th>
                            <th scope="col" class="px-5 py-2.5 font-normal text-right"><span class="sr-only">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#232323]">
                        @forelse ($sessions->sortByDesc(fn ($s) => $s->started_at) as $sess)
                            @php
                                [$statusLabel, $statusDot] = [
                                    'completed' => ['Submitted', 'bg-[#3ecf8e]/60'],
                                    'in_progress' => ['In progress', 'bg-[#3ecf8e]'],
                                    'abandoned' => ['Timed out', 'bg-red-400/70'],
                                ][$sess->status] ?? [ucfirst(str_replace('_', ' ', $sess->status)), 'bg-[#666666]'];
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
                                    <span class="inline-flex items-center gap-2 text-sm text-[#a3a3a3]"><span class="w-2 h-2 rounded-full {{ $statusDot }}" aria-hidden="true"></span>{{ $statusLabel }}</span>
                                </td>
                                <td class="px-5 py-3 whitespace-nowrap tabular-nums">
                                    @if ($sess->status === 'completed')
                                        <span class="text-[#ededed]">{{ rtrim(rtrim(number_format((float) $sess->effective_score, 1), '0'), '.') }}%</span>
                                    @else
                                        <span class="text-xs text-[#888888]">Not submitted</span>
                                    @endif
                                </td>
                                <td class="px-5 py-3 whitespace-nowrap text-xs text-[#888888]">{{ $sess->started_at ? \Carbon\Carbon::parse($sess->started_at)->format('M j, H:i') : 'Not started' }}</td>
                                <td class="px-5 py-3 whitespace-nowrap text-right">
                                    <a href="{{ route('sessions.telemetry-timeline', $sess->id) }}" class="inline-flex items-center h-8 px-2.5 rounded-lg text-xs font-medium text-[#888888] hover:text-[#ededed] hover:bg-white/5 transition-colors">Timeline</a>
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

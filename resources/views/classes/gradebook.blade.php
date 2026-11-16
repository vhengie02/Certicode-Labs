@extends('layouts.app')

@section('title', 'Gradebook · '.$class->name)

@use('App\Support\ClassGradebook')

@php
    $labs = $gradebook->labs;
    $labCount = $labs->count();
    $fmt = fn ($score) => rtrim(rtrim(number_format((float) $score, 2, '.', ''), '0'), '.');
@endphp

@section('content')
<div class="space-y-6">
    <x-page-header :back="route('classes.show', $class->id)" :back-label="$class->name" title="Gradebook"
                   subtitle="Each student's grade on every lab: their best completed attempt, with your regrades in place of the AI score.">
        @if ($gradebook->rows && $labCount)
            <a href="{{ route('classes.gradebook.export', $class->id) }}" class="ui-btn ui-btn-primary">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v11m0 0l-4-4m4 4l4-4M5 20h14"/></svg>
                Export to Excel
            </a>
        @endif
    </x-page-header>

    <div class="flex flex-wrap items-center gap-x-6 gap-y-2 text-sm text-[#888888]">
        <span>A lab is passed at <span class="text-[#ededed]">{{ $gradebook->labPassScore }}%</span>; the certificate needs <span class="text-[#ededed]">{{ $gradebook->threshold }}%</span> of labs passed.</span>
        <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-sky-400/70" aria-hidden="true"></span>Regraded by an instructor</span>
        <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-amber-400/70" aria-hidden="true"></span>Provisional, waiting for your review</span>
    </div>

    <section class="rounded-xl border border-[#2e2e2e] bg-[#171717]" aria-label="Gradebook">
        @if (! $gradebook->rows || ! $labCount)
            <p class="px-5 py-10 text-center text-sm text-[#888888]">
                {{ ! $labCount ? 'This class has no labs yet. Grades show here once you add labs and students attempt them.' : 'No students are enrolled in this class yet.' }}
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-b border-[#232323] text-left text-xs text-[#888888] align-bottom">
                            <th scope="col" class="sticky left-0 z-10 bg-[#171717] px-5 py-3 font-normal">Student</th>
                            @foreach ($labs as $lab)
                                <th scope="col" class="px-3 py-3 font-normal text-right min-w-[7rem]">
                                    <span class="block text-[#ededed] font-medium leading-snug">{{ $lab->title }}</span>
                                    <span class="block truncate max-w-[10rem] ml-auto" title="{{ $lab->module?->title }}">{{ $lab->module?->title }}</span>
                                </th>
                            @endforeach
                            <th scope="col" class="px-3 py-3 font-normal text-right border-l border-[#232323]">Average</th>
                            <th scope="col" class="px-3 py-3 font-normal text-right">Labs passed</th>
                            <th scope="col" class="px-5 py-3 font-normal text-right">Certificate</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#232323]">
                        @foreach ($gradebook->rows as $row)
                            <tr>
                                <th scope="row" class="sticky left-0 z-10 bg-[#171717] px-5 py-3 text-left font-normal whitespace-nowrap">
                                    <span class="block font-medium text-[#ededed]">{{ $row['student']->name }}</span>
                                    <span class="block text-xs text-[#888888]">{{ $row['student']->email }}</span>
                                </th>
                                @foreach ($labs as $lab)
                                    @php
                                        $cell = $row['cells'][$lab->id];
                                        $state = $cell['state'];
                                        $href = match (true) {
                                            $state === ClassGradebook::IN_PROGRESS => route('classes.telemetry', ['class_id' => $class->id, 'lab' => $lab->id]),
                                            $cell['session_id'] !== null => route('sessions.show', $cell['session_id']),
                                            default => null,
                                        };
                                        $hint = collect([
                                            $cell['team'] ? "Team: {$cell['team']}" : null,
                                            $cell['regrade'] ? 'Regraded by '.$cell['regrade']['by'].' (AI score '.($cell['ai_score'] !== null ? $fmt($cell['ai_score']).'%' : 'none').')'.($cell['regrade']['reason'] ? ': '.$cell['regrade']['reason'] : '') : null,
                                            $state === ClassGradebook::REVIEW ? 'Provisional grade from automatic checks, waiting for your review' : null,
                                        ])->filter()->implode(' · ');
                                        $tone = match (true) {
                                            $cell['overridden'] => 'bg-sky-400/10',
                                            $state === ClassGradebook::REVIEW => 'bg-amber-400/10',
                                            default => '',
                                        };
                                        $passed = $state === ClassGradebook::GRADED && $cell['score'] >= $gradebook->labPassScore;
                                    @endphp
                                    <td class="px-3 py-3 text-right whitespace-nowrap {{ $tone }}" @if ($hint) title="{{ $hint }}" @endif
                                        data-lab="{{ $lab->id }}" data-state="{{ $state }}">
                                        @if ($cell['score'] !== null)
                                            <a href="{{ $href }}" class="tabular-nums font-semibold hover:underline underline-offset-2 {{ $passed ? 'text-[#3ecf8e]' : 'text-[#ededed]' }}">{{ $fmt($cell['score']) }}%</a>
                                            @if ($cell['overridden'])
                                                <span class="block text-[10px] text-sky-300">regraded</span>
                                            @elseif ($state === ClassGradebook::REVIEW)
                                                <span class="block text-[10px] text-amber-300">review</span>
                                            @endif
                                        @elseif ($href)
                                            <a href="{{ $href }}" class="text-xs text-[#888888] hover:text-[#ededed] hover:underline underline-offset-2">{{ ClassGradebook::STATE_LABELS[$state] }}</a>
                                        @else
                                            <span class="text-[#3a3a3a]" aria-label="Not started">&ndash;</span>
                                        @endif
                                    </td>
                                @endforeach
                                <td class="px-3 py-3 text-right whitespace-nowrap tabular-nums text-[#ededed] border-l border-[#232323]">{{ $fmt($row['average']) }}%</td>
                                <td class="px-3 py-3 text-right whitespace-nowrap tabular-nums text-[#a3a3a3]">{{ $row['passed'] }}/{{ $labCount }}</td>
                                <td class="px-5 py-3 text-right whitespace-nowrap">
                                    @if ($row['meets'])
                                        <span class="ui-badge ui-badge-brand">Meets {{ $gradebook->threshold }}%</span>
                                    @else
                                        <span class="text-xs text-[#888888]">{{ $row['percent'] }}% of labs</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>
@endsection

@extends('layouts.app')

@section('title', 'Reports')

@php
    $periods = \App\Http\Controllers\Admin\ReportController::PERIODS;
    $maxWeek = max(1, collect($weeks)->max(fn ($w) => max($w['signups'], $w['sessions'])));
    $maxFlag = max(1, (int) collect($flagsByType)->max());
    $exports = ['users' => 'Users', 'classes' => 'Classes', 'sessions' => 'Lab sessions and grades', 'flags' => 'Integrity flags', 'certificates' => 'Certificates'];
@endphp

@section('content')
<div class="space-y-6">
    <x-page-header title="Reports" subtitle="Platform activity, class results and competency attainment.">
        <form method="GET" action="{{ route('admin.reports.index') }}" class="flex items-center gap-2">
            <label for="period" class="text-sm text-[#888888]">Period</label>
            <select id="period" name="period" class="ui-input !w-auto" onchange="this.form.submit()">
                @foreach ($periods as $value => $label)
                    <option value="{{ $value }}" @selected($period === (string) $value)>{{ $label }}</option>
                @endforeach
            </select>
            <noscript><button type="submit" class="ui-btn ui-btn-secondary">Apply</button></noscript>
        </form>
    </x-page-header>

    {{-- Headline numbers --}}
    <dl class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3 sm:gap-4">
        @foreach ($kpis as [$label, $value, $hint])
            <div class="ui-card p-5">
                <dt class="ui-eyebrow">{{ $label }}</dt>
                <dd class="cc-display mt-2 text-3xl font-bold tabular-nums text-[#ededed]">
                    {{ $value === null ? '—' : (is_float($value) ? $value . '%' : number_format($value)) }}
                </dd>
                <dd class="mt-1 text-xs text-[#888888]">{{ $hint }}</dd>
            </div>
        @endforeach
    </dl>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        {{-- Weekly activity --}}
        <section class="ui-card lg:col-span-2" aria-labelledby="weekly-heading">
            <div class="flex flex-wrap items-baseline justify-between gap-2 px-5 pt-5">
                <h2 id="weekly-heading" class="ui-card-title">Weekly activity</h2>
                <div class="flex items-center gap-4 text-xs text-[#888888]">
                    <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-[#3ecf8e]" aria-hidden="true"></span>Lab sessions</span>
                    <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-sky-400" aria-hidden="true"></span>Sign-ups</span>
                </div>
            </div>
            <div class="overflow-x-auto px-5 pb-5 pt-4">
                <div class="flex h-48 items-end gap-1.5 min-w-[520px]" role="img"
                     aria-label="Lab sessions and sign-ups per week. Highest week: {{ $maxWeek }}.">
                    @foreach ($weeks as $w)
                        <div class="group flex h-full min-w-0 flex-1 flex-col justify-end" title="Week of {{ $w['label'] }}: {{ $w['sessions'] }} lab sessions, {{ $w['signups'] }} sign-ups">
                            <div class="flex h-full items-end justify-center gap-[2px]">
                                <span class="w-1/2 max-w-[14px] rounded-t-sm bg-[#3ecf8e] transition-opacity group-hover:opacity-80" style="height: {{ $w['sessions'] ? max(2, round($w['sessions'] / $maxWeek * 100)) : 0 }}%"></span>
                                <span class="w-1/2 max-w-[14px] rounded-t-sm bg-sky-400 transition-opacity group-hover:opacity-80" style="height: {{ $w['signups'] ? max(2, round($w['signups'] / $maxWeek * 100)) : 0 }}%"></span>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-2 flex gap-1.5 min-w-[520px] border-t border-[#232323] pt-2 text-[10px] text-[#888888]">
                    @foreach ($weeks as $i => $w)
                        <span class="min-w-0 flex-1 text-center truncate">{{ ($i % max(1, (int) ceil(count($weeks) / 8)) === 0) ? $w['label'] : '' }}</span>
                    @endforeach
                </div>
            </div>
        </section>

        {{-- Accounts and flags --}}
        <div class="space-y-6">
            <section class="ui-card ui-card-body" aria-labelledby="roles-heading">
                <h2 id="roles-heading" class="ui-card-title">Accounts</h2>
                <dl class="mt-3 space-y-2 text-sm">
                    @foreach (['student' => 'Students', 'instructor' => 'Instructors', 'admin' => 'Admins'] as $key => $label)
                        <div class="flex justify-between"><dt class="text-[#888888]">{{ $label }}</dt><dd class="tabular-nums text-[#ededed]">{{ number_format($roles[$key] ?? 0) }}</dd></div>
                    @endforeach
                </dl>
            </section>
            <section class="ui-card ui-card-body" aria-labelledby="flags-heading">
                <h2 id="flags-heading" class="ui-card-title">Integrity flags by type</h2>
                @if ($flagsByType->isEmpty())
                    <p class="mt-3 text-sm text-[#888888]">No flags in this period.</p>
                @else
                    <ul class="mt-3 space-y-2.5" role="list">
                        @foreach ($flagsByType as $type => $count)
                            <li>
                                <div class="flex justify-between text-xs"><span class="text-[#a3a3a3]">{{ str_replace('_', ' ', $type) }}</span><span class="tabular-nums text-[#ededed]">{{ $count }}</span></div>
                                <div class="mt-1 h-1.5 rounded-full bg-[#232323]"><div class="h-1.5 rounded-full bg-amber-400" style="width: {{ round($count / $maxFlag * 100) }}%"></div></div>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>
    </div>

    {{-- Classes --}}
    <section class="ui-card overflow-hidden" aria-labelledby="classes-heading">
        <div class="flex items-baseline justify-between px-5 pt-5 pb-3">
            <h2 id="classes-heading" class="ui-card-title">Classes</h2>
            <span class="text-xs text-[#888888]">Submissions and grades within the period; students and certificates overall</span>
        </div>
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-y border-[#232323] text-left font-mono text-[11px] uppercase tracking-[0.12em] text-[#888888]">
                        <th scope="col" class="px-5 py-2.5 font-medium">Class</th>
                        <th scope="col" class="px-5 py-2.5 font-medium">Instructor</th>
                        <th scope="col" class="px-5 py-2.5 font-medium text-right">Students</th>
                        <th scope="col" class="px-5 py-2.5 font-medium text-right">Labs</th>
                        <th scope="col" class="px-5 py-2.5 font-medium text-right">Submissions</th>
                        <th scope="col" class="px-5 py-2.5 font-medium text-right">Avg grade</th>
                        <th scope="col" class="px-5 py-2.5 font-medium text-right">Certificates</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#232323]">
                    @forelse ($classes as $c)
                        <tr>
                            <td class="px-5 py-3"><a href="{{ route('classes.show', $c['id']) }}" class="font-medium text-[#ededed] hover:text-[#3ecf8e]">{{ $c['name'] }}</a>@if($c['status'] === 'ended') <span class="ui-badge ml-1.5 text-[11px]">ended</span>@endif</td>
                            <td class="px-5 py-3 text-[#a3a3a3] whitespace-nowrap">{{ $c['instructor'] }}</td>
                            <td class="px-5 py-3 text-right tabular-nums">{{ $c['students'] }}</td>
                            <td class="px-5 py-3 text-right tabular-nums">{{ $c['labs'] }}</td>
                            <td class="px-5 py-3 text-right tabular-nums">{{ $c['submissions'] }}</td>
                            <td class="px-5 py-3 text-right tabular-nums">{{ $c['avg_grade'] === null ? '—' : $c['avg_grade'] . '%' }}</td>
                            <td class="px-5 py-3 text-right tabular-nums">{{ $c['certificates'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="px-5 py-10 text-center text-sm text-[#888888]">No classes yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_340px] gap-6 items-start">
        {{-- Competencies --}}
        <section class="ui-card overflow-hidden" aria-labelledby="comp-heading">
            <div class="flex items-baseline justify-between px-5 pt-5 pb-3">
                <h2 id="comp-heading" class="ui-card-title">Competency attainment</h2>
                <a href="{{ route('admin.competencies.index') }}" class="text-xs font-medium text-[#3ecf8e] hover:underline">Manage</a>
            </div>
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="border-y border-[#232323] text-left font-mono text-[11px] uppercase tracking-[0.12em] text-[#888888]">
                            <th scope="col" class="px-5 py-2.5 font-medium">Competency</th>
                            <th scope="col" class="px-5 py-2.5 font-medium text-right">Labs</th>
                            <th scope="col" class="px-5 py-2.5 font-medium text-right">Students scored</th>
                            <th scope="col" class="px-5 py-2.5 font-medium text-right">Average</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#232323]">
                        @forelse ($competencies as $c)
                            <tr>
                                <td class="px-5 py-3"><span class="font-mono text-xs text-[#3ecf8e]">{{ $c->code }}</span> <span class="text-[#ededed]">{{ $c->name }}</span></td>
                                <td class="px-5 py-3 text-right tabular-nums">{{ $c->laboratories_count }}</td>
                                <td class="px-5 py-3 text-right tabular-nums">{{ $c->student_competencies_count }}</td>
                                <td class="px-5 py-3 text-right tabular-nums">{{ $c->student_competencies_avg_score_achieved === null ? '—' : round($c->student_competencies_avg_score_achieved) . '%' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-5 py-10 text-center text-sm text-[#888888]">No competencies defined yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Exports --}}
        <section class="ui-card" aria-labelledby="export-heading">
            <div class="ui-card-header">
                <h2 id="export-heading" class="ui-card-title">Download CSV</h2>
                <p class="ui-card-subtitle">Opens in Excel or Google Sheets. Sessions, flags and certificates follow the period above.</p>
            </div>
            <ul class="ui-card-body space-y-2" role="list">
                @foreach ($exports as $type => $label)
                    <li>
                        <a href="{{ route('admin.reports.export', ['type' => $type, 'period' => $period]) }}" class="flex items-center justify-between gap-3 rounded-lg border border-[#2e2e2e] bg-[#141414] px-3.5 py-2.5 text-sm text-[#ededed] hover:border-[#3ecf8e]/40 transition-colors">
                            {{ $label }}
                            <svg class="w-4 h-4 text-[#888888]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v12m0 0l-4-4m4 4l4-4M4 18v1a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-1"/></svg>
                        </a>
                    </li>
                @endforeach
            </ul>
        </section>
    </div>
</div>
@endsection

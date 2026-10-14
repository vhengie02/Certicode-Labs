{{--
    Verified record behind a certificate: course content with the student's grade per lab,
    and the competencies earned. Expects $certificate and $record.
--}}
@php
    $class = $record['class'];
    $fmt = fn ($n) => rtrim(rtrim(number_format((float) $n, 1), '0'), '.');
@endphp
<div class="space-y-6">
    {{-- Course --}}
    <section class="rounded-xl border border-[#2e2e2e] bg-[#171717]" aria-labelledby="course-heading">
        <div class="px-5 pt-5 pb-4 border-b border-[#232323]">
            <p class="font-mono text-[11px] uppercase tracking-[0.14em] text-[#888888]">Course</p>
            <h2 id="course-heading" class="mt-1 text-lg font-semibold text-[#ededed]">{{ $class->name }}</h2>
            @if ($class->description)
                <p class="mt-1.5 text-sm text-[#a3a3a3] max-w-3xl">{{ $class->description }}</p>
            @endif
            <dl class="mt-4 grid grid-cols-2 sm:grid-cols-4 gap-4 text-sm">
                <div><dt class="text-[#888888]">Instructor</dt><dd class="text-[#ededed]">{{ $class->instructor->name ?? 'Unassigned' }}</dd></div>
                <div><dt class="text-[#888888]">Pass mark</dt><dd class="text-[#ededed]">{{ $record['pass_mark'] }}% of labs</dd></div>
                <div><dt class="text-[#888888]">Studied</dt><dd class="text-[#ededed]">
                    @if ($record['first_activity'])
                        {{ $record['first_activity']->format('M Y') }}{{ $record['last_activity'] && $record['last_activity']->format('M Y') !== $record['first_activity']->format('M Y') ? ' – ' . $record['last_activity']->format('M Y') : '' }}
                    @else
                        —
                    @endif
                </dd></div>
                <div><dt class="text-[#888888]">Certificate issued</dt><dd class="text-[#ededed]">{{ $certificate->issued_at->format('M j, Y') }}</dd></div>
            </dl>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <caption class="sr-only">Labs in the course and the grade earned on each</caption>
                <thead>
                    <tr class="text-left font-mono text-[11px] uppercase tracking-[0.12em] text-[#888888]">
                        <th scope="col" class="px-5 py-2.5 font-medium">Module and lab</th>
                        <th scope="col" class="px-5 py-2.5 font-medium text-right">Grade</th>
                        <th scope="col" class="px-5 py-2.5 font-medium">Completed</th>
                        <th scope="col" class="px-5 py-2.5 font-medium text-right">Attempts</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($record['modules'] as $module)
                        <tr class="border-t border-[#232323] bg-[#141414]">
                            <th scope="rowgroup" colspan="4" class="px-5 py-2 text-left text-[13px] font-semibold text-[#ededed]" style="padding-left: {{ 1.25 + $module['depth'] * 1.25 }}rem">
                                {{ $module['title'] }}
                            </th>
                        </tr>
                        @forelse ($module['labs'] as $lab)
                            @php $r = $lab['result']; @endphp
                            <tr class="border-t border-[#232323]">
                                <td class="py-2.5 pr-5 text-[#d4d4d4]" style="padding-left: {{ 2.25 + $module['depth'] * 1.25 }}rem">
                                    {{ $lab['title'] }}
                                    @if ($lab['live'])<span class="ml-1.5 rounded-full border border-[#2e2e2e] px-1.5 text-[10px] text-[#888888]">live</span>@endif
                                </td>
                                <td class="px-5 py-2.5 text-right tabular-nums whitespace-nowrap">
                                    @if ($r)
                                        <span class="font-semibold {{ $r['grade'] >= $record['pass_mark'] ? 'text-[#3ecf8e]' : 'text-[#ededed]' }}">{{ $fmt($r['grade']) }}%</span>
                                        @if ($r['overridden'])<span class="block text-[10px] text-[#888888]">set by instructor</span>@endif
                                    @else
                                        <span class="text-[#666666]">Not taken</span>
                                    @endif
                                </td>
                                <td class="px-5 py-2.5 whitespace-nowrap text-[#a3a3a3]">{{ $r && $r['completed_at'] ? $r['completed_at']->format('M j, Y') : '—' }}</td>
                                <td class="px-5 py-2.5 text-right tabular-nums text-[#a3a3a3]">{{ $r['attempts'] ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr class="border-t border-[#232323]">
                                <td colspan="4" class="py-2.5 text-[13px] text-[#888888]" style="padding-left: {{ 2.25 + $module['depth'] * 1.25 }}rem">Reading only, no labs</td>
                            </tr>
                        @endforelse
                    @endforeach
                </tbody>
                <tfoot>
                    <tr class="border-t border-[#2e2e2e] bg-[#141414]">
                        <th scope="row" class="px-5 py-3 text-left font-semibold text-[#ededed]">{{ $record['labs_completed'] }} of {{ $record['labs_total'] }} labs completed ({{ $record['completion'] }}%)</th>
                        <td class="px-5 py-3 text-right font-semibold tabular-nums text-[#ededed] whitespace-nowrap">{{ $record['average_grade'] === null ? '—' : $fmt($record['average_grade']) . '% avg' }}</td>
                        <td colspan="2" class="px-5 py-3 text-xs text-[#888888]">Best attempt per lab counts</td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </section>

    {{-- Competencies --}}
    <section class="rounded-xl border border-[#2e2e2e] bg-[#171717]" aria-labelledby="competency-heading">
        <div class="px-5 pt-5 pb-3">
            <p class="font-mono text-[11px] uppercase tracking-[0.14em] text-[#888888]">Skills</p>
            <h2 id="competency-heading" class="mt-1 text-lg font-semibold text-[#ededed]">Competencies earned</h2>
        </div>
        @if (empty($record['competencies']))
            <p class="px-5 pb-5 text-sm text-[#888888]">This course's labs aren't linked to specific competencies.</p>
        @else
            <ul class="grid grid-cols-1 md:grid-cols-2 gap-3 px-5 pb-5" role="list">
                @foreach ($record['competencies'] as $c)
                    <li class="rounded-lg border border-[#2e2e2e] bg-[#141414] p-4">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <span class="font-mono text-xs text-[#3ecf8e]">{{ $c['code'] }}</span>
                                <p class="text-sm font-medium text-[#ededed]">{{ $c['name'] }}</p>
                            </div>
                            <span class="text-lg font-bold tabular-nums text-[#ededed]">{{ $fmt($c['score']) }}%</span>
                        </div>
                        <div class="mt-2.5 h-1.5 rounded-full bg-[#232323]" aria-hidden="true">
                            <div class="h-1.5 rounded-full bg-[#3ecf8e]" style="width: {{ min(100, max(0, $c['score'])) }}%"></div>
                        </div>
                        @if ($c['description'])
                            <p class="mt-2.5 text-xs text-[#888888]">{{ $c['description'] }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
</div>

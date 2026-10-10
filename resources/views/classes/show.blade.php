@extends('layouts.app')

@section('title', $class->name)

@php
    $user = auth()->user();
    $isStaff = $user->canManageClass($class);
    $topModules = $class->modules->where('parent_id', null)->sortBy('order_index');
    $enrolled = $class->students->filter(fn ($s) => $s->pivot->status === 'enrolled');
    $invited = $class->students->filter(fn ($s) => $s->pivot->status === 'invited');
    $threshold = $class->passing_threshold ?? 75;
    $isEnded = $class->isEnded();
@endphp

@section('content')
<div class="space-y-6">
    <x-page-header :back="route('classes.index')" back-label="Classes" :title="$class->name"
                   :subtitle="$class->description ?: null">
        @if ($isStaff)
            <a href="{{ route('classes.telemetry', $class->id) }}" class="ui-btn ui-btn-secondary">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12h4l3-8 4 16 3-8h4"/></svg>
                Monitoring
            </a>
            <a href="{{ route('classes.edit', $class->id) }}" class="ui-btn ui-btn-secondary">Settings</a>
        @endif
    </x-page-header>

    {{-- Key facts --}}
    <div class="flex flex-wrap items-center gap-x-6 gap-y-3 text-sm">
        @if ($isStaff)
            <div class="flex items-center gap-2">
                <span class="text-[#888888]">Join code</span>
                <code class="font-mono text-[#ededed] tracking-wider">{{ $class->code }}</code>
                <button type="button" class="ui-btn ui-btn-ghost ui-btn-sm !h-7 !px-2" data-copy="{{ $class->code }}" aria-label="Copy join code">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><rect x="9" y="9" width="11" height="11" rx="2"/><path d="M5 15V5a2 2 0 0 1 2-2h10"/></svg>
                    <span data-copy-label>Copy</span>
                </button>
            </div>
        @endif
        <div><span class="text-[#888888]">Instructor</span> <span class="text-[#ededed]">{{ $class->instructor->name ?? 'Unassigned' }}</span></div>
        <div><span class="text-[#888888]">Pass mark</span> <span class="text-[#ededed]">{{ $threshold }}%</span></div>
        @if ($class->scheduled_end_date)
            <div><span class="text-[#888888]">{{ $isEnded ? 'Ended' : 'Ends' }}</span> <span class="text-[#ededed]">{{ $class->scheduled_end_date->format('M j, Y') }}</span></div>
        @endif
        @if ($isStaff)
            <div><span class="text-[#888888]">Students</span> <span class="text-[#ededed]">{{ $enrolled->count() }}</span></div>
        @endif
        @if ($isEnded)
            <span class="ui-badge">Class ended</span>
        @endif
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_340px] gap-6 items-start">
        {{-- Syllabus --}}
        <section class="ui-card" aria-labelledby="syllabus-heading">
            <div class="flex items-center justify-between gap-3 px-5 pt-5 pb-3 border-b border-[#232323]">
                <div>
                    <h2 id="syllabus-heading" class="ui-card-title">Syllabus</h2>
                    <p class="ui-card-subtitle">{{ $topModules->count() }} {{ \Illuminate\Support\Str::plural('module', $topModules->count()) }}{{ $isStaff ? ' · Views and Submissions per item' : '' }}</p>
                </div>
                @if ($isStaff)
                    <div class="flex items-center gap-2">
                        <a href="{{ route('modules.create', $class->id) }}" class="ui-btn ui-btn-secondary ui-btn-sm">Add module</a>
                        <a href="{{ route('laboratories.create', $class->id) }}" class="ui-btn ui-btn-primary ui-btn-sm">Add lab</a>
                    </div>
                @endif
            </div>

            @if ($topModules->isEmpty())
                <div class="px-5 py-14 text-center">
                    <p class="text-sm font-medium text-[#ededed]">No modules yet</p>
                    @if ($isStaff)
                        <p class="text-sm text-[#888888] mt-1">Start with a module for your first lesson, then add labs to it.</p>
                        <a href="{{ route('modules.create', $class->id) }}" class="ui-btn ui-btn-primary mt-4">Add the first module</a>
                    @else
                        <p class="text-sm text-[#888888] mt-1">Your instructor hasn't published any lessons yet.</p>
                    @endif
                </div>
            @else
                <ul role="list">
                    @foreach ($topModules as $module)
                        @include('classes._syllabus-module', ['module' => $module, 'depth' => 0])
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- Sidebar --}}
        <aside class="space-y-6">
            @if (!$isStaff)
                @php
                    $progress = $class->getStudentProgress($user, $completedLabIds ?? null);
                    $certificate = $existingCertificate ?? null;
                    $canClaim = $progress['total'] > 0 && $progress['percent'] >= $threshold;
                @endphp
                <section class="ui-card ui-card-body" aria-labelledby="progress-heading">
                    <div class="flex items-baseline justify-between">
                        <h2 id="progress-heading" class="ui-card-title">Your progress</h2>
                        <span class="cc-display text-2xl font-bold tabular-nums text-[#ededed]">{{ $progress['percent'] }}%</span>
                    </div>
                    <div class="relative mt-4 h-2 rounded-full bg-[#232323]" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $progress['percent'] }}" aria-label="Labs done">
                        <div class="h-2 rounded-full bg-[#3ecf8e] transition-all duration-500" style="width: {{ $progress['percent'] }}%"></div>
                        <span class="absolute -top-1 h-4 w-0.5 rounded bg-[#ededed]/60" style="left: {{ $threshold }}%" title="Pass mark: {{ $threshold }}%" aria-hidden="true"></span>
                    </div>
                    <p class="mt-3 text-sm text-[#a3a3a3]">{{ $progress['completed'] }} of {{ $progress['total'] }} labs done. You need {{ $threshold }}% for a certificate.</p>

                    <div class="mt-5">
                        @if ($certificate)
                            <a href="{{ route('certificates.show', $certificate->id) }}" class="ui-btn ui-btn-primary w-full">View your certificate</a>
                        @elseif ($canClaim)
                            <form action="{{ route('classes.claim-certificate', $class->id) }}" method="POST">
                                @csrf
                                <button type="submit" class="ui-btn ui-btn-primary w-full">Claim your certificate</button>
                            </form>
                        @else
                            @php $labsNeeded = max(0, (int) ceil($progress['total'] * $threshold / 100) - $progress['completed']); @endphp
                            <p class="rounded-lg border border-[#2e2e2e] bg-[#141414] px-3.5 py-3 text-sm text-[#888888]">
                                @if ($progress['total'] === 0)
                                    Certificates unlock once this class has labs.
                                @else
                                    {{ $labsNeeded }} more {{ \Illuminate\Support\Str::plural('lab', $labsNeeded) }} to unlock your certificate.
                                @endif
                            </p>
                        @endif
                    </div>
                </section>
            @else
                <section class="ui-card" aria-labelledby="invite-heading">
                    <div class="ui-card-header">
                        <h2 id="invite-heading" class="ui-card-title">Invite a student</h2>
                        <p class="ui-card-subtitle">They need a Certicode account first. Or share the join code above.</p>
                    </div>
                    <form action="{{ route('classes.invite', $class->id) }}" method="POST" class="ui-card-body flex gap-2">
                        @csrf
                        <label for="invite-email" class="sr-only">Student email</label>
                        <input type="email" name="email" id="invite-email" required placeholder="student@school.edu" class="ui-input flex-1 min-w-0" autocomplete="off">
                        <button type="submit" class="ui-btn ui-btn-primary shrink-0">Invite</button>
                    </form>
                </section>

                <section class="ui-card" aria-labelledby="roster-heading">
                    <div class="flex items-baseline justify-between px-5 pt-5 pb-2">
                        <h2 id="roster-heading" class="ui-card-title">Students</h2>
                        <span class="text-xs text-[#888888]">{{ $enrolled->count() }} enrolled{{ $invited->isNotEmpty() ? ' · ' . $invited->count() . ' invited' : '' }}</span>
                    </div>
                    <ul class="max-h-80 overflow-y-auto px-2 pb-3" role="list">
                        @forelse ($enrolled->concat($invited) as $student)
                            <li class="flex items-center gap-3 rounded-lg px-3 py-2">
                                <span class="h-8 w-8 shrink-0 rounded-full bg-[#3ecf8e]/10 border border-[#3ecf8e]/25 flex items-center justify-center text-[11px] font-semibold text-[#3ecf8e]" aria-hidden="true">{{ strtoupper(\Illuminate\Support\Str::substr($student->name, 0, 2)) }}</span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm text-[#ededed]">{{ $student->name }}</span>
                                    <span class="block truncate text-xs text-[#888888]">{{ $student->email }}</span>
                                </span>
                                @if ($student->pivot->status === 'invited')
                                    <span class="ui-badge ui-badge-warn shrink-0 text-[11px]">Invited</span>
                                @endif
                            </li>
                        @empty
                            <li class="px-3 py-6 text-center text-sm text-[#888888]">No students yet. Share the join code to get started.</li>
                        @endforelse
                    </ul>
                </section>

                @if ($class->status !== 'completed')
                    <section class="ui-card ui-card-body" aria-labelledby="end-heading">
                        <h2 id="end-heading" class="ui-card-title">End the class</h2>
                        <p class="ui-card-subtitle">Closes the class and issues certificates to every student at or above {{ $threshold }}%. This can't be undone.</p>
                        <form action="{{ route('classes.end', $class->id) }}" method="POST" class="mt-4"
                              onsubmit="return confirm('End this class now? Students at or above {{ $threshold }}% get their certificates, and the class closes.');">
                            @csrf
                            <button type="submit" class="ui-btn ui-btn-danger w-full">End class and issue certificates</button>
                        </form>
                    </section>
                @endif
            @endif
        </aside>
    </div>
</div>
@endsection

@section('scripts')
<script>
    document.querySelectorAll('[data-copy]').forEach((button) => {
        button.addEventListener('click', async () => {
            const label = button.querySelector('[data-copy-label]');
            try {
                await navigator.clipboard.writeText(button.dataset.copy);
                label.textContent = 'Copied';
            } catch (e) {
                label.textContent = 'Press Ctrl+C';
            }
            setTimeout(() => { label.textContent = 'Copy'; }, 1800);
        });
    });
</script>
@endsection

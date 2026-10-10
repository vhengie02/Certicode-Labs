{{-- Dashboard data, loaded into dashboard.blade.php after the page appears. No layout and no scripts. --}}
<div class="space-y-8">
    <dl class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
        @foreach ($stats as $stat)
            <div class="rounded-xl border border-[#2e2e2e] bg-[#171717] p-5">
                <dt class="font-mono text-[11px] uppercase tracking-[0.14em] text-[#888888]">{{ $stat['label'] }}</dt>
                <dd class="cc-display mt-2 text-3xl font-bold tabular-nums text-[#ededed]">{{ number_format($stat['value']) }}</dd>
                <dd class="mt-1 text-xs text-[#888888]">{{ $stat['hint'] }}</dd>
            </div>
        @endforeach
    </dl>

    @if ($role === 'student')
        @if ($invitations > 0)
            <a href="{{ route('classes.index') }}" class="flex items-center justify-between gap-4 rounded-xl border border-[#3ecf8e]/30 bg-[#3ecf8e]/[0.06] px-5 py-4 hover:border-[#3ecf8e]/50 transition-colors">
                <span class="text-sm font-medium text-[#ededed]">You've been invited to {{ $invitations }} {{ \Illuminate\Support\Str::plural('class', $invitations) }}.</span>
                <span class="text-sm font-semibold text-[#3ecf8e] shrink-0">Review &rarr;</span>
            </a>
        @endif

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <section class="lg:col-span-2 rounded-xl border border-[#2e2e2e] bg-[#171717]" aria-labelledby="continue-heading">
                <h2 id="continue-heading" class="px-5 pt-5 pb-2 text-sm font-semibold text-[#ededed]">Continue where you left off</h2>
                @forelse ($inProgress as $session)
                    @php
                        $lab = $session->laboratory;
                    @endphp
                    <a href="{{ $lab ? route('laboratories.show', $lab->id) : '#' }}" class="group flex items-center gap-4 px-5 py-3.5 border-b border-[#232323] last:border-b-0 hover:bg-[#1c1c1c] transition-colors">
                        <span class="h-9 w-9 rounded-lg border border-[#2e2e2e] bg-[#141414] flex items-center justify-center text-[#3ecf8e] shrink-0" aria-hidden="true">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
                        </span>
                        <span class="flex-1 min-w-0">
                            <span class="block text-sm font-medium text-[#ededed] truncate">{{ $lab->title ?? 'Removed lab' }}</span>
                            <span class="block text-xs text-[#888888] truncate mt-0.5">
                                {{ $lab?->module?->schoolClass?->name ?? 'Class' }} &middot; started {{ $session->started_at?->diffForHumans() ?? 'recently' }}
                            </span>
                        </span>
                        <span class="text-xs font-semibold text-[#3ecf8e] shrink-0 opacity-70 group-hover:opacity-100 transition-opacity">Resume &rarr;</span>
                    </a>
                @empty
                    <div class="px-5 pb-6 pt-1">
                        <p class="text-sm text-[#888888]">No labs in progress. Open a class to start one.</p>
                        <a href="{{ route('classes.index') }}" class="inline-block mt-3 text-sm font-semibold text-[#3ecf8e] hover:underline underline-offset-2">Browse your classes &rarr;</a>
                    </div>
                @endforelse
            </section>

            <section class="rounded-xl border border-[#2e2e2e] bg-[#171717] p-5" aria-labelledby="certs-heading">
                <h2 id="certs-heading" class="text-sm font-semibold text-[#ededed]">Certificates</h2>
                <div class="mt-3 space-y-2">
                    @forelse ($certificates as $cert)
                        <a href="{{ route('certificates.show', $cert->id) }}" class="flex items-center gap-3 rounded-lg border border-[#2e2e2e] bg-[#141414] px-3.5 py-3 hover:border-[#3ecf8e]/35 transition-colors">
                            <svg class="w-4 h-4 text-[#3ecf8e] shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4M12 3l2.4 1.8 3 .2.8 2.9 2.2 2-1 2.8 1 2.8-2.2 2-.8 2.9-3 .2L12 21l-2.4-1.8-3-.2-.8-2.9-2.2-2 1-2.8-1-2.8 2.2-2 .8-2.9 3-.2z"/></svg>
                            <span class="min-w-0">
                                <span class="block text-sm font-medium text-[#ededed] truncate">{{ $cert->schoolClass->name ?? 'Course' }}</span>
                                <span class="block font-mono text-[11px] text-[#888888] truncate">{{ $cert->verification_code }}</span>
                            </span>
                        </a>
                    @empty
                        <p class="text-sm text-[#888888] leading-relaxed">None yet. Finish a class above its passing threshold and your certificate appears here.</p>
                    @endforelse
                </div>
            </section>
        </div>
    @elseif ($role === 'instructor')
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
            <section class="lg:col-span-2 rounded-xl border border-[#2e2e2e] bg-[#171717]" aria-labelledby="flags-heading">
                <div class="flex items-baseline justify-between px-5 pt-5 pb-2">
                    <h2 id="flags-heading" class="text-sm font-semibold text-[#ededed]">Recent integrity flags</h2>
                    <span class="text-xs text-[#888888]">your classes only</span>
                </div>
                @forelse ($recentAnomalies as $anomaly)
                    @php
                        $flagSession = $anomaly->labSession;
                        $severityStyle = [
                            'high' => 'bg-red-500/10 text-red-400 border-red-500/20',
                            'medium' => 'bg-yellow-500/10 text-yellow-400 border-yellow-500/20',
                        ][$anomaly->severity] ?? 'bg-sky-500/10 text-sky-400 border-sky-500/20';
                    @endphp
                    <a href="{{ $flagSession ? route('instructor.monitoring.show', $flagSession->lab_id) : '#' }}" class="flex items-center gap-4 px-5 py-3.5 border-b border-[#232323] last:border-b-0 hover:bg-[#1c1c1c] transition-colors">
                        <span class="h-9 w-9 rounded-full bg-[#141414] border border-[#2e2e2e] flex items-center justify-center text-[11px] font-semibold text-[#a3a3a3] shrink-0" aria-hidden="true">
                            {{ strtoupper(\Illuminate\Support\Str::substr($flagSession?->user?->name ?? '?', 0, 2)) }}
                        </span>
                        <span class="flex-1 min-w-0">
                            <span class="block text-sm font-medium text-[#ededed] truncate">
                                {{ $flagSession?->user?->name ?? 'Unknown student' }}
                                <span class="font-normal text-[#888888]">&middot; {{ $flagSession?->laboratory?->title ?? 'Lab' }}</span>
                            </span>
                            <span class="block font-mono text-xs text-[#888888] truncate mt-0.5">{{ str_replace('_', ' ', $anomaly->type) }} &middot; {{ $anomaly->created_at?->diffForHumans() }}</span>
                        </span>
                        <span class="hidden sm:inline-flex items-center gap-1.5 shrink-0">
                            <span class="px-2 py-0.5 rounded-md border font-mono text-[10px] uppercase tracking-wider {{ $severityStyle }}">{{ $anomaly->severity }}</span>
                            @if ($anomaly->resolved)
                                <span class="text-xs text-[#888888]">resolved</span>
                            @endif
                        </span>
                    </a>
                @empty
                    <p class="px-5 pb-6 pt-1 text-sm text-[#888888]">No flags. When a student's session trips a proctoring rule, it shows up here.</p>
                @endforelse
            </section>

            <section class="rounded-xl border border-[#2e2e2e] bg-[#171717] p-5" aria-labelledby="classes-heading">
                <div class="flex items-baseline justify-between">
                    <h2 id="classes-heading" class="text-sm font-semibold text-[#ededed]">Your classes</h2>
                    <a href="{{ route('classes.index') }}" class="text-xs font-medium text-[#3ecf8e] hover:underline underline-offset-2">View all</a>
                </div>
                <div class="mt-3 space-y-2">
                    @forelse ($classes as $class)
                        <a href="{{ route('classes.show', $class->id) }}" class="block rounded-lg border border-[#2e2e2e] bg-[#141414] px-3.5 py-3 hover:border-[#3ecf8e]/35 transition-colors">
                            <span class="flex items-center justify-between gap-3">
                                <span class="text-sm font-medium text-[#ededed] truncate">{{ $class->name }}</span>
                                @if ($class->isEnded())
                                    <span class="font-mono text-[10px] uppercase tracking-wider text-[#888888] shrink-0">ended</span>
                                @endif
                            </span>
                            <span class="block text-xs text-[#888888] mt-0.5">
                                {{ $class->students_count }} {{ \Illuminate\Support\Str::plural('student', $class->students_count) }} &middot; <span class="font-mono">{{ $class->code }}</span>
                            </span>
                        </a>
                    @empty
                        <p class="text-sm text-[#888888] leading-relaxed">You haven't created a class yet.</p>
                        <a href="{{ route('classes.create') }}" class="inline-block text-sm font-semibold text-[#3ecf8e] hover:underline underline-offset-2">Create your first class &rarr;</a>
                    @endforelse
                </div>
            </section>
        </div>
    @else
        <section class="rounded-xl border border-[#2e2e2e] bg-[#171717]" aria-labelledby="requests-heading">
            <div class="flex items-baseline justify-between px-5 pt-5 pb-2">
                <h2 id="requests-heading" class="text-sm font-semibold text-[#ededed]">Instructor requests</h2>
                @if ($pendingCount > 0)
                    <a href="{{ route('admin.instructor-requests.index') }}" class="text-xs font-medium text-[#3ecf8e] hover:underline underline-offset-2">
                        {{ $pendingCount > $pendingRequests->count() ? "View all {$pendingCount}" : 'Review' }} &rarr;
                    </a>
                @endif
            </div>
            @forelse ($pendingRequests as $requester)
                <div class="flex items-center gap-4 px-5 py-3.5 border-b border-[#232323] last:border-b-0">
                    <span class="h-9 w-9 rounded-full bg-[#3ecf8e]/10 border border-[#3ecf8e]/25 flex items-center justify-center text-[11px] font-bold text-[#3ecf8e] shrink-0" aria-hidden="true">
                        {{ strtoupper(\Illuminate\Support\Str::substr($requester->name, 0, 2)) }}
                    </span>
                    <span class="flex-1 min-w-0">
                        <span class="block text-sm font-medium text-[#ededed] truncate">{{ $requester->name }}</span>
                        <span class="block text-xs text-[#888888] truncate mt-0.5">{{ $requester->email }}</span>
                    </span>
                    <span class="text-xs text-[#888888] shrink-0">{{ $requester->instructor_requested_at?->diffForHumans() }}</span>
                </div>
            @empty
                <p class="px-5 pb-6 pt-1 text-sm text-[#888888]">No one is waiting for approval.</p>
            @endforelse
        </section>
    @endif
</div>

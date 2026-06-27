@extends('layouts.app')

@section('title', 'Workspace Telemetry Timeline')
@section('page_header', 'Session #' . $session->id . ' - Telemetry Timeline')

@section('content')
<div class="max-w-5xl mx-auto space-y-6">
    <!-- Back to Dashboard -->
    <div>
        @php
            $classId = null;
            if ($session->laboratory->module_id) {
                $module = \App\Models\Module::find($session->laboratory->module_id);
                if ($module) {
                    $classId = $module->class_id;
                }
            }
        @endphp
        @if($classId)
            <a href="{{ route('classes.telemetry', $classId) }}" class="inline-flex items-center text-xs font-semibold text-slate-450 hover:text-white transition-colors">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                Back to Telemetry Monitor
            </a>
        @else
            <a href="{{ route('classes.index') }}" class="inline-flex items-center text-xs font-semibold text-slate-450 hover:text-white transition-colors">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                Back to Dashboard
            </a>
        @endif
    </div>

    <!-- Workspace Details Panel -->
    <div class="glass-panel p-6 rounded-xl border border-slate-800 space-y-4">
        <div>
            <span class="text-[10px] font-bold text-indigo-400 uppercase tracking-wider block">Session Metadata</span>
            <h2 class="text-xl font-bold text-white mt-1">Workspace Session Log Details</h2>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 pt-2 font-mono text-xs">
            <div>
                <span class="text-slate-500 block mb-1 uppercase tracking-wider text-[10px]">Student Name</span>
                <span class="text-white font-semibold">{{ $session->user->name }}</span>
            </div>
            <div>
                <span class="text-slate-500 block mb-1 uppercase tracking-wider text-[10px]">Laboratory Exercise</span>
                <span class="text-white font-semibold">{{ $session->laboratory->title }}</span>
            </div>
            <div>
                <span class="text-slate-500 block mb-1 uppercase tracking-wider text-[10px]">Status</span>
                <span class="inline-flex px-2 py-0.5 rounded text-[10px] font-semibold uppercase {{ $session->status === 'completed' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-indigo-500/10 text-indigo-400 border border-indigo-500/20 animate-pulse' }}">
                    {{ $session->status }}
                </span>
            </div>
            <div>
                <span class="text-slate-500 block mb-1 uppercase tracking-wider text-[10px]">Performance Score</span>
                <span class="text-white font-bold text-sm">{{ $session->performance_score }}%</span>
            </div>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-2 border-t border-slate-800/60 font-mono text-xs">
            <div>
                <span class="text-slate-500 block mb-1 uppercase tracking-wider text-[10px]">Started At</span>
                <span class="text-slate-350">{{ $session->started_at ? \Carbon\Carbon::parse($session->started_at)->format('Y-m-d H:i:s') : '-' }}</span>
            </div>
            <div>
                <span class="text-slate-500 block mb-1 uppercase tracking-wider text-[10px]">Ended At</span>
                <span class="text-slate-350">{{ $session->ended_at ? \Carbon\Carbon::parse($session->ended_at)->format('Y-m-d H:i:s') : '-' }}</span>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
        <!-- Anomaly Summary Panel (Left side) -->
        <div class="lg:col-span-1 space-y-6">
            <div class="glass-panel p-6 rounded-xl border border-slate-800 space-y-4">
                <h3 class="text-xs uppercase font-bold tracking-wider text-slate-400">Flagged Anomalies</h3>
                
                <div class="space-y-3">
                    @forelse($anomalies as $anomaly)
                        <div class="p-3.5 rounded-xl border {{ !$anomaly->resolved ? 'border-rose-500/30 bg-rose-500/[0.02]' : 'border-slate-800 bg-slate-900/30' }} space-y-2">
                            <div class="flex items-center justify-between">
                                <span class="text-xs font-bold font-mono text-rose-400">{{ $anomaly->type }}</span>
                                <span class="px-2 py-0.5 rounded-full text-[9px] font-medium {{ $anomaly->severity === 'high' ? 'bg-rose-500/10 text-rose-400 border border-rose-500/20' : 'bg-amber-500/10 text-amber-400 border border-amber-500/20' }}">
                                    {{ $anomaly->severity }}
                                </span>
                            </div>
                            <p class="text-[11px] text-slate-450 leading-relaxed font-sans">{{ $anomaly->description }}</p>
                            
                            <div class="flex items-center justify-between pt-1 border-t border-slate-800/40 text-[9px] text-slate-500 font-mono">
                                <span>{{ \Carbon\Carbon::parse($anomaly->created_at)->format('H:i:s') }}</span>
                                @if(!$anomaly->resolved)
                                    <form action="{{ route('anomalies.resolve', $anomaly->id) }}" method="POST" class="m-0">
                                        @csrf
                                        <button type="submit" class="text-indigo-400 hover:text-indigo-300 font-bold uppercase tracking-wider">Resolve</button>
                                    </form>
                                @else
                                    <span class="text-emerald-500 font-bold uppercase">Resolved</span>
                                @endif
                            </div>
                        </div>
                    @empty
                        <div class="p-4 text-center text-xs text-slate-500">
                            No anomalies flagged for this student session.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

        <!-- Telemetry Logs Timeline (Right side) -->
        <div class="lg:col-span-2 glass-panel p-6 rounded-xl border border-slate-800 space-y-4">
            <h3 class="text-xs uppercase font-bold tracking-wider text-slate-400">Telemetry Stream Timeline</h3>

            <div class="relative pl-6 border-l-2 border-slate-850/80 space-y-6">
                @forelse($logs as $log)
                    @php
                        // Color styling depending on event type
                        $badgeColor = 'bg-slate-900 border-slate-800 text-slate-400';
                        $titleColor = 'text-white';
                        
                        if ($log->event_type === 'tab_switch') {
                            $badgeColor = 'bg-amber-500/10 border-amber-500/20 text-amber-400';
                            $titleColor = 'text-amber-400';
                        } elseif ($log->event_type === 'task_verified') {
                            $badgeColor = 'bg-emerald-500/10 border-emerald-500/20 text-emerald-400';
                            $titleColor = 'text-emerald-400';
                        } elseif ($log->event_type === 'code_execution') {
                            $badgeColor = 'bg-blue-500/10 border-blue-500/20 text-blue-400';
                            $titleColor = 'text-blue-400';
                        } elseif ($log->event_type === 'github_sync') {
                            $badgeColor = 'bg-purple-500/10 border-purple-500/20 text-purple-400';
                            $titleColor = 'text-purple-400';
                        }
                    @endphp
                    <div class="relative">
                        <!-- Timeline circle dot -->
                        <span class="absolute -left-[31px] top-1 h-3 w-3 rounded-full {{ $log->event_type === 'tab_switch' ? 'bg-amber-500 ring-4 ring-slate-950' : ($log->event_type === 'task_verified' ? 'bg-emerald-500 ring-4 ring-slate-950' : 'bg-slate-800 ring-4 ring-slate-950') }}"></span>
                        
                        <div class="space-y-1.5 text-left">
                            <div class="flex items-center justify-between">
                                <span class="px-2 py-0.5 border text-[10px] font-bold font-mono rounded-lg uppercase {{ $badgeColor }}">
                                    {{ str_replace('_', ' ', $log->event_type) }}
                                </span>
                                <span class="text-[10px] text-slate-500 font-mono">{{ \Carbon\Carbon::parse($log->created_at)->format('H:i:s') }}</span>
                            </div>

                            @if($log->event_type === 'tab_switch')
                                <p class="text-xs text-slate-350 leading-relaxed font-sans">
                                    Student switched focus away from workspace to tab: <span class="font-mono text-amber-400">"{{ $log->payload['tab_title'] ?? 'Unknown' }}"</span>.
                                </p>
                            @elseif($log->event_type === 'webcam_check')
                                <p class="text-xs text-slate-350 leading-relaxed font-sans">
                                    Face count telemetry audit: <span class="font-bold text-white">{{ $log->payload['face_count'] ?? 0 }} face(s)</span> visible.
                                </p>
                            @elseif($log->event_type === 'code_execution')
                                <p class="text-xs text-slate-350 leading-relaxed font-sans">
                                    Executed student source code using <span class="font-mono text-indigo-400">{{ ucfirst($log->payload['language'] ?? 'bash') }}</span>. Output status: <span class="font-mono font-semibold">{{ $log->payload['status'] ?? 'unknown' }}</span> (Time: {{ $log->payload['execution_time_ms'] ?? 0 }}ms).
                                </p>
                            @elseif($log->event_type === 'task_verified')
                                <p class="text-xs text-slate-350 leading-relaxed font-sans">
                                    Verified task successfully! Verified ID(s): <span class="font-mono text-emerald-400">[{{ implode(', ', $log->payload['verified_task_ids'] ?? []) }}]</span>. Overall progress: <span class="font-bold text-white">{{ $log->payload['current_progress'] ?? '0' }}</span>, Score: <span class="font-bold text-white">{{ $log->payload['performance_score'] ?? 0 }}%</span>.
                                </p>
                            @elseif($log->event_type === 'github_sync')
                                <p class="text-xs text-slate-350 leading-relaxed font-sans">
                                    Synced repository commits from GitHub profile. User commits: <span class="font-bold text-white">{{ $log->payload['commits'] ?? 0 }}</span>, Additions: <span class="font-mono text-emerald-400">+{{ $log->payload['additions'] ?? 0 }}</span>, Deletions: <span class="font-mono text-rose-400">-{{ $log->payload['deletions'] ?? 0 }}</span>.
                                </p>
                            @elseif($log->event_type === 'session_completed')
                                <p class="text-xs text-slate-300 font-bold leading-relaxed font-sans">
                                    Completed workspace challenge session. Final score: {{ $log->payload['final_score'] ?? 0 }}% (Completed tasks: {{ $log->payload['completed_tasks_count'] ?? 0 }}).
                                </p>
                            @else
                                <p class="text-xs text-slate-350 leading-relaxed font-sans">
                                    Telemetry event logged.
                                </p>
                            @endif

                            @if(!empty($log->payload))
                                <details class="group mt-1">
                                    <summary class="text-[10px] text-slate-500 cursor-pointer select-none hover:text-slate-400 font-mono outline-none">Show Raw Payload Payload</summary>
                                    <pre class="bg-slate-950 p-2.5 rounded-lg border border-slate-900 text-[10px] text-indigo-400 overflow-x-auto mt-1 font-mono leading-normal">{{ json_encode($log->payload, JSON_PRETTY_PRINT) }}</pre>
                                </details>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="py-4 text-center text-xs text-slate-500">
                        No telemetry logs recorded in this session.
                    </div>
                @endforelse
            </div>
        </div>
    </div>
</div>
@endsection

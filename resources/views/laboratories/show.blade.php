@extends('layouts.app')

@section('title', $laboratory->title)

@section('content')
@php
    $user = auth()->user();
    $isStudent = $user->role === 'student';
    $module = $laboratory->module;
    $backUrl = $module ? route('modules.show', ['class_id' => $module->class_id, 'module_id' => $module->id]) : route('classes.index');
    $starterFiles = $laboratory->getStarterFilesList();
    $tasks = $laboratory->tasks_definition ?? [];
    $isLive = $laboratory->isLiveLab();
    $minutes = $isLive ? ($laboratory->live_duration_minutes ?? $laboratory->time_limit ?? 60) : $laboratory->time_limit;
    $liveLeft = $isLive ? max(0, (int) $laboratory->getRemainingLiveSeconds()) : 0;
@endphp
<div class="space-y-6">
    <x-page-header :back="$backUrl" :back-label="$module->title ?? 'Classes'" :title="$laboratory->title">
        @unless ($isStudent)
            <a href="{{ route('instructor.monitoring.show', $laboratory->id) }}" class="ui-btn ui-btn-secondary">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M3 12h4l3-8 4 16 3-8h4"/></svg>
                Monitoring
            </a>
            <a href="{{ route('laboratories.edit', $laboratory->id) }}" class="ui-btn ui-btn-secondary">Edit lab</a>
        @endunless
    </x-page-header>

    {{-- At a glance --}}
    <div class="flex flex-wrap items-center gap-2">
        @if ($isLive)
            @if ($laboratory->isLiveActive())
                <span class="ui-badge ui-badge-brand"><span class="h-1.5 w-1.5 rounded-full bg-[#3ecf8e] animate-pulse" aria-hidden="true"></span>Live now &middot; {{ max(1, (int) ceil($liveLeft / 60)) }} min left</span>
            @elseif ($laboratory->isLiveNotStarted())
                <span class="ui-badge ui-badge-warn"><span class="h-1.5 w-1.5 rounded-full bg-amber-400" aria-hidden="true"></span>Live lab &middot; not started</span>
            @else
                <span class="ui-badge ui-badge-danger"><span class="h-1.5 w-1.5 rounded-full bg-red-400" aria-hidden="true"></span>Live lab &middot; ended</span>
            @endif
        @else
            <span class="ui-badge">Open lab &middot; self-paced</span>
        @endif
        <span class="ui-badge">{{ $laboratory->is_group_lab ? 'Group lab' : 'Individual' }}</span>
        <span class="ui-badge">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path stroke-linecap="round" d="M12 7v5l3 2"/></svg>
            {{ $minutes }} min {{ $isLive ? 'session' : 'time limit' }}
        </span>
        @if (count($tasks))
            <span class="ui-badge">{{ count($tasks) }} {{ \Illuminate\Support\Str::plural('task', count($tasks)) }}</span>
        @endif
    </div>

    @if ($isStudent)
    <div class="space-y-6" x-data="preLabCameraGate({{ $laboratory->id }}, {{ $activeSession ? $activeSession->id : 'null' }})">
@endif
    @if ($isStudent)
                <!-- Persistent Live Browser Proctoring Monitor Card -->
                <div x-show="browserProctorActive" x-cloak class="ui-card p-5 sm:p-6 !border-[#3ecf8e]/40">
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-[#242424]">
                        <div class="flex items-center gap-3">
                            <span class="relative flex h-3 w-3">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-3 w-3 bg-[#3ecf8e]"></span>
                            </span>
                            <div>
                                <h3 class="text-sm font-bold text-white flex items-center gap-2 flex-wrap">
                                    Browser Camera Proctor Active
                                    <span class="px-2 py-0.5 rounded text-[10px] font-mono tracking-wider font-semibold"
                                          :class="{
                                              'bg-emerald-500/20 text-emerald-400 border border-emerald-500/30': proctorStatus === 'normal',
                                              'bg-rose-500/20 text-rose-400 border border-rose-500/30 animate-pulse': proctorStatus === 'absence' || proctorStatus === 'disconnected',
                                              'bg-amber-500/20 text-amber-400 border border-amber-500/30': proctorStatus === 'multiple_faces'
                                          }"
                                          x-text="proctorStatusText">
                                    </span>
                                </h3>
                                <p class="text-xs text-[#888888] mt-0.5">Webcam stays active in this tab while coding in VS Code. Telemetry is streamed to your instructor.</p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2.5 shrink-0 flex-wrap">
                            <button type="button" @click="leaveSessionAndStopProctoring()" class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg bg-rose-500/10 hover:bg-rose-500/20 text-rose-400 border border-rose-500/30 text-xs font-semibold transition" title="Stop webcam camera and exit laboratory session">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                                <span>Stop Camera &amp; Exit</span>
                            </button>
                            <button type="button" @click="reopenVsCode()" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-[#3ecf8e] hover:bg-[#00c573] text-[#0f0f0f] text-xs font-bold transition shadow-sm">
                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M23.15 2.587L18.21.21a1.494 1.494 0 0 0-1.705.29l-9.46 8.63-4.12-3.128a.999.999 0 0 0-1.276.057L.327 7.261A1 1 0 0 0 .32 8.704l4.28 3.297-4.28 3.296a1 1 0 0 0 .007 1.443l1.322 1.203c.365.332.91.355 1.276.057l4.12-3.128 9.46 8.63c.47.43 1.15.56 1.705.29l4.94-2.377A1.5 1.5 0 0 0 24 19.985V4.015a1.5 1.5 0 0 0-.85-1.428zM18 17.57l-7.464-5.57L18 6.43v11.14z"/>
                                </svg>
                                <span>Switch to VS Code Workspace</span>
                            </button>
                        </div>
                    </div>

                    <!-- Outdated extension: an old build misses fixes the lab page relies on -->
                    <div x-show="extensionOutdated" x-cloak class="mt-4 flex flex-col sm:flex-row sm:items-center gap-3 rounded-lg border border-amber-500/30 bg-amber-500/10 px-4 py-3">
                        <p class="flex-1 text-xs text-amber-200 leading-relaxed">
                            <strong class="font-semibold">Your Certicode extension is out of date</strong>
                            <span x-text="extensionVersion ? '(v' + extensionVersion + ').' : '(older build).'"></span>
                            Install version <span class="font-mono" x-text="extensionMinVersion"></span> or newer: download it, then in VS Code open Extensions, the … menu, <strong class="font-semibold">Install from VSIX</strong>, and reload VS Code.
                        </p>
                        <a :href="extensionDownloadUrl" download class="ui-btn ui-btn-secondary text-xs shrink-0">Download update (.vsix)</a>
                    </div>

                    <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4 items-center">
                        <!-- Compact Live Camera Viewport -->
                        <div class="relative rounded-lg overflow-hidden border border-[#2e2e2e] bg-[#0d0d0d] aspect-video flex items-center justify-center">
                            <video x-ref="proctorLiveVideo" autoplay playsinline muted class="w-full h-full object-cover scale-x-[-1]"></video>
                            <canvas x-ref="proctorCanvas" class="hidden"></canvas>
                            <div class="absolute bottom-2 left-2 bg-black/80 backdrop-blur-sm px-2 py-0.5 rounded text-[10px] font-mono text-emerald-400 flex items-center gap-1.5 border border-emerald-500/30">
                                <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                                <span>Face Track Active</span>
                            </div>
                        </div>

                        <!-- Instructions & Status Log -->
                        <div class="md:col-span-2 space-y-2.5 text-xs text-[#a3a3a3]">
                            <div class="p-3 rounded-lg bg-[#171717] border border-[#262626] space-y-1.5">
                                <div class="flex items-center justify-between text-[11px] font-mono">
                                    <span class="text-[#888888]">SESSION TELEMETRY &amp; HEARTBEAT:</span>
                                    <span class="text-[#3ecf8e] flex items-center gap-1">
                                        <span class="w-1.5 h-1.5 rounded-full bg-[#3ecf8e] animate-pulse"></span>
                                        Connected (<span x-text="pingCount"></span> pings sent)
                                    </span>
                                </div>
                                <p class="text-xs text-[#d4d4d4] leading-relaxed">
                                    Your webcam stream stays isolated to this browser window. When focus shifts to VS Code, periodic AI checks confirm presence and stream telemetry directly to your instructor's live panel.
                                </p>
                            </div>

                            <div class="flex items-center gap-3 text-[11px] font-mono text-[#777777] flex-wrap">
                                <span>Status: <strong class="text-white" x-text="proctorStatusText"></strong></span>
                                <span>•</span>
                                <span>Faces: <strong class="text-white" x-text="faceCount"></strong></span>
                                <span>•</span>
                                <span>Session ID: <strong class="text-[#3ecf8e]" x-text="activeSessionId || '{{ $activeSession ? $activeSession->id : 'Pending' }}'"></strong></span>
                                <span>•</span>
                                <span class="text-amber-400">Keep this tab open while you code</span>
                            </div>
                        </div>
                    </div>
                </div>
    @endif

    <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_340px] gap-6 items-start">
        <div class="min-w-0 space-y-6">
            {{-- Instructions --}}
            <section class="ui-card" aria-labelledby="instructions-heading">
                <div class="ui-card-header">
                    <h2 id="instructions-heading" class="ui-card-title">Instructions</h2>
                </div>
                <div class="ui-card-body">
                    <p class="whitespace-pre-line text-[15px] leading-relaxed text-[#d4d4d4]">{{ $laboratory->description }}</p>
                </div>
            </section>

            {{-- Starter files --}}
            @if (count($starterFiles))
                <section class="ui-card" aria-labelledby="files-heading" x-data="{ openFile: null }">
                    <div class="flex flex-wrap items-start justify-between gap-3 px-6 pt-5 pb-3">
                        <div>
                            <h2 id="files-heading" class="ui-card-title">Starter files</h2>
                            <p class="ui-card-subtitle">Created in your VS Code workspace when the lab starts.</p>
                        </div>
                        <a href="{{ route('laboratories.starter-files.download', $laboratory->id) }}" class="ui-btn ui-btn-secondary ui-btn-sm">
                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v12m0 0l-4-4m4 4l4-4M4 18v1a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-1"/></svg>
                            Download{{ count($starterFiles) > 1 ? ' .zip' : '' }}
                        </a>
                    </div>
                    <ul class="px-3 pb-3 space-y-1.5" role="list">
                        @foreach ($starterFiles as $idx => $sfile)
                            <li class="rounded-lg border border-[#2e2e2e] bg-[#141414] overflow-hidden">
                                <button type="button" class="flex w-full items-center gap-3 px-3.5 py-2.5 text-left hover:bg-[#1a1a1a] transition-colors"
                                        @click="openFile = openFile === {{ $idx }} ? null : {{ $idx }}" :aria-expanded="openFile === {{ $idx }}">
                                    <svg class="w-4 h-4 shrink-0 text-[#888888]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5z M14 3v5h5"/></svg>
                                    <span class="min-w-0 flex-1 truncate font-mono text-[13px] text-[#ededed]">{{ $sfile['name'] }}</span>
                                    @if (!empty($sfile['is_primary']))
                                        <span class="ui-badge ui-badge-brand text-[11px]">Opens first</span>
                                    @endif
                                    @if (!empty($sfile['is_readonly']))
                                        <span class="ui-badge text-[11px]">Read-only</span>
                                    @endif
                                    <svg class="w-4 h-4 shrink-0 text-[#888888] transition-transform" :class="openFile === {{ $idx }} && 'rotate-180'" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                                </button>
                                <div x-show="openFile === {{ $idx }}" x-collapse style="display: none;" class="border-t border-[#232323] bg-[#0f0f0f]">
                                    <div class="flex justify-end px-3 pt-2">
                                        <button type="button" x-data="{ copied: false }" class="ui-btn ui-btn-ghost ui-btn-sm"
                                                @click="navigator.clipboard.writeText({{ \Illuminate\Support\Js::from($sfile['content'] ?? '') }}).then(() => { copied = true; setTimeout(() => copied = false, 2000); })">
                                            <span x-text="copied ? 'Copied' : 'Copy code'">Copy code</span>
                                        </button>
                                    </div>
                                    <pre class="overflow-x-auto px-4 pb-4 pt-1 font-mono text-[13px] leading-relaxed text-[#d4d4d4]"><code>{{ $sfile['content'] }}</code></pre>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif

            {{-- Tasks --}}
            @if (count($tasks))
                <section class="ui-card" aria-labelledby="tasks-heading">
                    <div class="ui-card-header">
                        <h2 id="tasks-heading" class="ui-card-title">Tasks</h2>
                        <p class="ui-card-subtitle">{{ $isStudent ? 'What the grader checks in your code.' : 'What the grader checks in each student’s code.' }}</p>
                    </div>
                    <ol class="ui-card-body space-y-2" role="list">
                        @foreach ($tasks as $i => $task)
                            <li class="flex items-start gap-3 rounded-lg border border-[#2e2e2e] bg-[#141414] px-3.5 py-3">
                                <span class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-[#232323] font-mono text-xs text-[#a3a3a3]">{{ $i + 1 }}</span>
                                <div class="min-w-0">
                                    <p class="text-sm text-[#ededed]">{{ $task['task'] }}</p>
                                    @if (!$isStudent && !empty($task['command']))
                                        <p class="mt-1.5 text-xs text-[#888888]">Check: <code class="font-mono text-[#3ecf8e]">{{ $task['command'] }}</code></p>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                </section>
            @endif

        <!-- Team Collaboration & Live Metrics (for Group Labs) -->
        @if($laboratory->is_group_lab && $activeSession)
            <div class="ui-card ui-card-body"
                 x-data="{
                     activeTab: 'chat',
                     messages: [],
                     newMessage: '',
                     codeSnippet: '',
                     showSnippetInput: false,
                     contributions: [],
                     diffStats: {},
                     loading: false,
                     sessionId: {{ $activeSession->id }},
                     init() {
                         this.fetchChats();
                         this.fetchSessionStats();
                         // Only while the page is in view: nobody reads it in a background tab,
                         // and coming back to it catches up at once
                         setInterval(() => {
                             if (document.hidden) return;
                             this.fetchChats();
                             this.fetchSessionStats();
                         }, 5000);
                         document.addEventListener('visibilitychange', () => {
                             if (!document.hidden) {
                                 this.fetchChats();
                                 this.fetchSessionStats();
                             }
                         });
                     },
                     async fetchChats() {
                         try {
                             const res = await fetch(`/v1/sessions/${this.sessionId}/chat`);
                             const data = await res.json();
                             if (data.chats) this.messages = data.chats;
                         } catch (e) {}
                     },
                     async fetchSessionStats() {
                         try {
                             const res = await fetch(`/v1/sessions/${this.sessionId}`);
                             const data = await res.json();
                             if (data.code_contributions) this.contributions = data.code_contributions;
                             if (data.diff_stats) this.diffStats = data.diffStats;
                         } catch (e) {}
                     },
                     async sendMessage() {
                         if (!this.newMessage.trim()) return;
                         try {
                             const res = await fetch(`/v1/sessions/${this.sessionId}/chat`, {
                                 method: 'POST',
                                 headers: { 'Content-Type': 'application/json' },
                                 body: JSON.stringify({ message: this.newMessage, code_snippet: this.codeSnippet || null })
                             });
                             const data = await res.json();
                             if (data.chat) {
                                 this.messages.push(data.chat);
                                 this.newMessage = '';
                                 this.codeSnippet = '';
                                 this.showSnippetInput = false;
                             }
                         } catch (e) {}
                     }
                 }">
                <div class="flex items-center justify-between mb-4">
                    <div class="flex space-x-2 border-b border-[#2e2e2e]">
                        <button type="button" @click="activeTab = 'chat'" 
                            :class="activeTab === 'chat' ? 'border-[#3ecf8e] text-[#3ecf8e]' : 'border-transparent text-[#888888] hover:text-[#ededed]'"
                            class="px-3 py-1.5 border-b-2 font-mono text-xs font-bold transition-colors">
                            Team Chat (Live)
                        </button>
                        <button type="button" @click="activeTab = 'diff'" 
                            :class="activeTab === 'diff' ? 'border-[#3ecf8e] text-[#3ecf8e]' : 'border-transparent text-[#888888] hover:text-[#ededed]'"
                            class="px-3 py-1.5 border-b-2 font-mono text-xs font-bold transition-colors">
                            Code Contributions & Diff
                        </button>
                    </div>
                    <span class="text-[10px] font-mono text-[#3ecf8e] flex items-center">
                        <span class="w-2 h-2 rounded-full bg-[#3ecf8e] animate-pulse mr-1.5"></span>
                        TEAM SYNC ACTIVE
                    </span>
                </div>

                <!-- Chat Pane -->
                <div x-show="activeTab === 'chat'" class="rounded-[6px] bg-[#141414] border border-[#2e2e2e] p-4 flex flex-col h-80">
                    <div class="flex-1 overflow-y-auto space-y-3 pr-2" id="web-chat-feed">
                        <template x-if="messages.length === 0">
                            <div class="h-full flex items-center justify-center text-xs font-mono text-[#666666]">
                                No messages yet. Say hello to your teammates!
                            </div>
                        </template>
                        <template x-for="msg in messages" :key="msg.id">
                            <div class="flex items-start space-x-2.5">
                                <span class="w-6 h-6 rounded-full flex items-center justify-center text-[10px] font-bold text-[#0f0f0f] flex-shrink-0"
                                      :style="`background-color: ${msg.avatar_color || '#3ecf8e'}`" x-text="msg.initials"></span>
                                <div class="flex-1 bg-[#171717] border border-[#2e2e2e] rounded-[6px] p-2.5">
                                    <div class="flex items-center justify-between text-[11px] mb-1">
                                        <span class="font-bold text-[#ededed]" x-text="msg.user_name"></span>
                                        <span class="font-mono text-[#666666] text-[10px]" x-text="msg.time"></span>
                                    </div>
                                    <p class="text-xs text-[#d4d4d4]" x-text="msg.message"></p>
                                    <template x-if="msg.code_snippet">
                                        <pre class="mt-2 p-2 bg-[#0c0c0c] border border-[#222222] rounded text-[11px] font-mono text-[#3ecf8e] overflow-x-auto whitespace-pre" x-text="msg.code_snippet"></pre>
                                    </template>
                                </div>
                            </div>
                        </template>
                    </div>

                    <form @submit.prevent="sendMessage()" class="mt-3 pt-3 border-t border-[#232323] space-y-2">
                        <template x-if="showSnippetInput">
                            <textarea id="chat-code-snippet" name="code_snippet" x-model="codeSnippet" placeholder="// Paste code snippet here..." rows="3"
                                class="w-full px-3 py-1.5 bg-[#0e0e0e] border border-[#2e2e2e] rounded text-xs font-mono text-[#ededed] focus:outline-none focus:border-[#3ecf8e]"></textarea>
                        </template>
                        <div class="flex items-center space-x-2">
                            <button type="button" @click="showSnippetInput = !showSnippetInput" 
                                class="p-2 rounded bg-[#171717] border border-[#2e2e2e] text-[#888888] hover:text-[#3ecf8e] text-xs font-mono" title="Attach Code Snippet">
                                &lt;/&gt;
                            </button>
                            <input type="text" id="chat-new-message" name="chat_message" x-model="newMessage" placeholder="Type a message to teammates..." 
                                class="flex-1 px-3 py-2 bg-[#171717] border border-[#2e2e2e] rounded text-xs text-[#ededed] focus:outline-none focus:border-[#3ecf8e]">
                            <button type="submit" class="px-4 py-2 bg-[#3ecf8e] text-[#0f0f0f] text-xs font-bold rounded hover:bg-[#00c573] transition-colors">
                                Send
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Diff & Contributions Pane -->
                <div x-show="activeTab === 'diff'" style="display: none;" class="rounded-[6px] bg-[#141414] border border-[#2e2e2e] p-4">
                    <div class="mb-4 flex items-center justify-between">
                        <span class="text-xs font-mono uppercase text-[#a3a3a3]">Team Code Contributions</span>
                        <div class="text-xs font-mono">
                            <span class="text-[#3ecf8e] font-bold" x-text="`+${diffStats.lines_added || 0}`"></span>
                            <span class="text-[#666666]">/</span>
                            <span class="text-red-400 font-bold" x-text="`-${diffStats.lines_deleted || 0}`"></span>
                        </div>
                    </div>

                    <template x-if="contributions.length === 0">
                        <div class="text-center py-6 text-xs font-mono text-[#666666]">
                            No code diffs recorded yet. Edits made in the VS Code extension will appear here.
                        </div>
                    </template>

                    <div class="space-y-4">
                        <template x-for="c in contributions" :key="c.user_id">
                            <div class="p-3 bg-[#171717] border border-[#2e2e2e] rounded-[6px]">
                                <div class="flex items-center justify-between text-xs mb-1.5">
                                    <div class="flex items-center space-x-2">
                                        <span class="w-2.5 h-2.5 rounded-full" :style="`background-color: ${c.avatar_color}`"></span>
                                        <span class="font-bold text-[#ededed]" x-text="c.name"></span>
                                    </div>
                                    <span class="font-mono text-[#3ecf8e] font-bold" x-text="`${c.contribution_percent || 0}%`"></span>
                                </div>
                                <div class="w-full bg-[#101010] h-2 rounded-full overflow-hidden mb-2">
                                    <div class="h-full rounded-full transition-all duration-500" 
                                         :style="`width: ${c.contribution_percent || 0}%; background-color: ${c.avatar_color}`"></div>
                                </div>
                                <div class="flex items-center justify-between text-[11px] font-mono text-[#888888]">
                                    <span>Lines: <strong class="text-[#3ecf8e]" x-text="`+${c.lines_added}`"></strong> / <strong class="text-red-400" x-text="`-${c.lines_deleted}`"></strong></span>
                                    <span>Edits: <strong class="text-[#ededed]" x-text="c.edit_count"></strong></span>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        @endif
        </div>

        <aside class="space-y-6 lg:sticky lg:top-2">
            @if ($isStudent)
                {{-- Start --}}
                <section class="ui-card ui-card-body" aria-labelledby="start-heading">
                    @php
                        // Students only: retakes depend on the attempts the instructor allows
                        $outOfAttempts = !$activeSession && $attempts && !$attempts['canStart'];
                        $isRetake = !$activeSession && $attempts && $attempts['used'] > 0;
                    @endphp
                    <h2 id="start-heading" class="ui-card-title">{{ $activeSession ? 'Continue the lab' : ($outOfAttempts ? 'No attempts left' : ($isRetake ? 'Retake the lab' : 'Start the lab')) }}</h2>
                    @if ($outOfAttempts)
                        <p class="ui-card-subtitle">
                            You've used {{ $attempts['used'] === 1 ? 'your only attempt' : "all {$attempts['used']} attempts" }} at this lab, and your best grade counts.
                            Ask your instructor if you need another try.
                        </p>
                        <button type="button" disabled class="ui-btn ui-btn-secondary w-full mt-5">No attempts left</button>
                    @elseif ($isLive && $laboratory->isLiveNotStarted())
                        <p class="ui-card-subtitle">Your instructor hasn't started this live lab yet. Refresh this page once they do.</p>
                        <button type="button" disabled class="ui-btn ui-btn-secondary w-full mt-5">Waiting for your instructor</button>
                    @elseif ($isLive && $laboratory->isLiveClosed())
                        <p class="ui-card-subtitle">This live lab has ended, so it can't be started any more.</p>
                        <button type="button" disabled class="ui-btn ui-btn-secondary w-full mt-5">Lab ended</button>
                    @else
                        <ol class="mt-3 space-y-2 text-[13px] text-[#a3a3a3]" role="list">
                            <li class="flex gap-2.5"><span class="font-mono text-[#666666]">1</span>A quick camera check confirms it's you.</li>
                            <li class="flex gap-2.5"><span class="font-mono text-[#666666]">2</span>VS Code opens with the starter files.</li>
                            <li class="flex gap-2.5"><span class="font-mono text-[#666666]">3</span>Keep this tab open while you code.</li>
                        </ol>
                        <form id="start-lab-form" action="{{ route('laboratories.start', $laboratory->id) }}" method="POST" class="mt-5">
                            @csrf
                            <button type="button" @click="handleStartClick()" class="ui-btn ui-btn-primary w-full">
                                {{ $activeSession ? 'Continue in VS Code' : ($isRetake ? 'Retake lab' : 'Start lab') }}
                            </button>
                        </form>
                        @if ($attempts && !$activeSession)
                            <p class="ui-hint text-center">
                                @if ($attempts['limit'] === null)
                                    Unlimited attempts. Your best grade counts.
                                @elseif ($attempts['limit'] === 1)
                                    You get one attempt at this lab, so start when you're ready.
                                @else
                                    This will be attempt {{ $attempts['used'] + 1 }} of {{ $attempts['limit'] }}. Your best grade counts.
                                @endif
                            </p>
                        @endif
                        @if ($isLive && $laboratory->isLiveActive())
                            <p class="ui-hint text-center">Shared timer: {{ max(1, (int) ceil($liveLeft / 60)) }} min left. Work is submitted when it ends.</p>
                        @endif

                    <!-- Pre-Lab Camera Permission & AI Presence Verification Modal -->
                    <div x-show="showModal" 
                         x-cloak
                         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm"
                         style="display: none;">
                        <div class="bg-[#171717] border border-[#2e2e2e] rounded-xl max-w-lg w-full p-6 shadow-2xl relative">
                            <div class="flex items-center justify-between pb-4 mb-4 border-b border-[#262626]">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 rounded-lg bg-[#3ecf8e]/10 border border-[#3ecf8e]/30 flex items-center justify-center text-[#3ecf8e]">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                                    </div>
                                    <div>
                                        <h3 class="text-sm font-bold text-[#ededed]">Pre-Lab Camera Presence Verification</h3>
                                        <p class="text-[11px] text-[#888888]">CertiCode Automated Proctoring Gate (Checkpoint 1)</p>
                                    </div>
                                </div>
                                <button type="button" @click="closeGate()" class="text-[#888888] hover:text-white text-lg font-mono">&times;</button>
                            </div>

                            <p class="text-xs text-[#a3a3a3] leading-relaxed mb-4">
                                To ensure academic integrity, CertiCode Labs verifies facial camera presence before unlocking your workspace. Low-light mode is automatically supported.
                            </p>

                            <!-- Live Camera Viewport -->
                            <div class="relative rounded-lg overflow-hidden border border-[#2e2e2e] bg-[#0d0d0d] aspect-video mb-4 flex items-center justify-center">
                                <video x-ref="videoEl" autoplay playsinline muted class="w-full h-full object-cover"></video>
                                <canvas x-ref="canvasEl" class="hidden"></canvas>

                                <div x-show="status === 'requesting'" class="absolute inset-0 bg-[#0d0d0d]/90 flex flex-col items-center justify-center p-4 text-center">
                                    <div class="w-8 h-8 rounded-full border-2 border-[#3ecf8e] border-t-transparent animate-spin mb-3"></div>
                                    <span class="text-xs font-mono text-[#ededed]">Requesting camera permissions...</span>
                                    <span class="text-[11px] text-[#666666] mt-1">Please approve the browser webcam prompt</span>
                                </div>

                                <div x-show="status === 'loading_model'" class="absolute inset-0 bg-[#0d0d0d]/90 flex flex-col items-center justify-center p-4 text-center">
                                    <div class="w-8 h-8 rounded-full border-2 border-cyan-400 border-t-transparent animate-spin mb-3"></div>
                                    <span class="text-xs font-mono text-cyan-300">Loading AI Face Models...</span>
                                    <span class="text-[11px] text-[#666666] mt-1">SsdMobilenetv1 + TinyFaceDetector client verification</span>
                                </div>

                                <div x-show="status === 'denied'" class="absolute inset-0 bg-red-950/90 border border-red-500/50 flex flex-col items-center justify-center p-4 text-center">
                                    <svg class="w-8 h-8 text-red-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                    <span class="text-xs font-bold text-red-200 uppercase tracking-wide">Camera Access Denied</span>
                                    <span class="text-[11px] text-red-300/90 mt-1 max-w-xs leading-relaxed" x-text="errorMessage"></span>
                                </div>

                                <div x-show="status === 'analyzing'" class="absolute bottom-2 left-2 right-2 bg-black/80 backdrop-blur-sm px-3 py-2 rounded text-[11px] font-mono text-cyan-300 flex items-center justify-between border border-cyan-500/30">
                                    <span class="flex items-center gap-2">
                                        <span class="w-2 h-2 rounded-full bg-cyan-400 animate-ping"></span>
                                        Running AI Face & Presence Verification...
                                    </span>
                                    <span class="text-xs text-[#a3a3a3]" x-text="`Attempt ${attempts + 1} of ${maxAttempts}`"></span>
                                </div>

                                <div x-show="status === 'failed'" class="absolute bottom-2 left-2 right-2 bg-amber-950/95 border border-amber-500/60 p-2.5 rounded text-[11px] font-mono text-amber-200 flex flex-col gap-2">
                                    <div class="flex items-center justify-between">
                                        <span x-text="errorMessage" class="leading-tight"></span>
                                        <button type="button" @click="runAiPresenceValidation()" class="underline font-bold text-amber-400 hover:text-white ml-2 flex-shrink-0">Retry</button>
                                    </div>
                                    <div class="flex justify-end" x-show="!multipleFacesDetected">
                                        <button type="button" @click="acceptLowLightPresence()" class="px-2.5 py-1 rounded bg-[#3ecf8e] text-[#0f0f0f] font-bold text-[11px] hover:bg-[#00c573] transition">
                                            Continue in low-light mode &rarr;
                                        </button>
                                    </div>
                                </div>

                                <div x-show="status === 'hard_blocked'" class="absolute inset-0 bg-[#171717]/95 border border-amber-500/80 flex flex-col items-center justify-center p-4 text-center">
                                    <svg class="w-10 h-10 text-amber-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                    <span class="text-xs font-bold text-amber-200 uppercase tracking-wide">Dim Camera Lighting Detected</span>
                                    <span class="text-[11px] text-[#a3a3a3] mt-1.5 max-w-sm leading-relaxed">
                                        Camera lighting is low or backlit. You can proceed with low-light verified mode:
                                    </span>
                                    <button type="button" @click="acceptLowLightPresence()" class="mt-3 px-4 py-2 rounded-lg bg-[#3ecf8e] text-[#0f0f0f] text-xs font-bold hover:bg-[#00c573] transition">
                                        Continue in low-light mode &rarr;
                                    </button>
                                </div>

                                <div x-show="status === 'verified'" class="absolute inset-0 bg-emerald-950/90 border border-emerald-500/60 flex flex-col items-center justify-center p-4 text-center">
                                    <div class="w-10 h-10 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center mb-2"><svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg></div>
                                    <span class="text-xs font-bold text-emerald-200">AI Presence Verified</span>
                                    <span class="text-[11px] text-emerald-300/80 mt-1">Starting VS Code workspace...</span>
                                </div>
                            </div>

                            <!-- Actions Footer -->
                            <div class="flex items-center justify-between pt-2">
                                <button type="button" @click="closeGate()" class="px-4 py-2 rounded-lg bg-[#222222] hover:bg-[#2a2a2a] text-xs font-medium text-[#ededed] transition">
                                    Cancel
                                </button>
                                
                                <div class="flex items-center gap-2">
                                    <button x-show="status === 'denied'" 
                                            type="button" 
                                            @click="requestCamera()" 
                                            class="px-4 py-2 rounded-lg bg-[#3ecf8e] text-[#0f0f0f] text-xs font-bold hover:bg-[#00c573] transition">
                                        Grant Camera Permission
                                    </button>

                                    <button x-show="status === 'failed'" 
                                            type="button" 
                                            @click="runAiPresenceValidation()" 
                                            class="px-3 py-2 rounded-lg bg-[#2a2a2a] hover:bg-[#333] text-xs font-medium text-[#ededed] transition">
                                        <span x-text="`Retry (${attempts + 1}/${maxAttempts})`"></span>
                                    </button>

                                    <button x-show="(status === 'failed' || attempts > 0) && !multipleFacesDetected" 
                                            type="button" 
                                            @click="acceptLowLightPresence()" 
                                            class="px-4 py-2 rounded-lg bg-[#3ecf8e] text-[#0f0f0f] text-xs font-bold hover:bg-[#00c573] transition flex items-center gap-1.5 shadow-sm">
                                        <span>Continue in low-light mode &rarr;</span>
                                    </button>

                                    <button x-show="status === 'verified'"
                                            type="button" 
                                            @click="launchLab()" 
                                            class="px-5 py-2 rounded-lg bg-[#3ecf8e] text-[#0f0f0f] text-xs font-bold hover:bg-[#00c573] transition">
                                        Open Workspace Now &rarr;
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- VS Code Launching & Liveness Ping Waiting Overlay -->
                    <div x-show="launchingVsCode" 
                         x-cloak
                         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/85 backdrop-blur-md"
                         style="display: none;">
                        <div class="bg-[#141414] border border-[#2e2e2e] rounded-2xl max-w-md w-full p-7 shadow-2xl text-center relative overflow-hidden">
                            <!-- Background ambient glow -->
                            <div class="absolute -top-20 -left-20 w-44 h-44 bg-[#3ecf8e]/10 rounded-full blur-3xl pointer-events-none"></div>
                            <div class="absolute -bottom-20 -right-20 w-44 h-44 bg-cyan-500/10 rounded-full blur-3xl pointer-events-none"></div>

                            <!-- Animated VS Code Icon / Status Icon -->
                            <div class="relative w-20 h-20 mx-auto mb-5 flex items-center justify-center">
                                <div x-show="!vscodeConnected" class="absolute inset-0 rounded-2xl bg-[#3ecf8e]/20 animate-ping"></div>
                                <div class="relative w-20 h-20 rounded-2xl bg-[#1e1e1e] border border-[#333] flex items-center justify-center shadow-lg">
                                    <template x-if="!vscodeConnected">
                                        <svg class="w-10 h-10 text-[#3ecf8e] animate-pulse" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4" />
                                        </svg>
                                    </template>
                                    <template x-if="vscodeConnected">
                                        <svg class="w-10 h-10 text-emerald-400" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                                        </svg>
                                    </template>
                                </div>
                            </div>

                            <h3 class="text-base font-bold text-[#ededed] mb-1.5" x-text="vscodeConnected ? 'VS Code Connected!' : (vscodeNotDetected ? 'Certicode extension not detected' : 'Starting Lab in VS Code...')"></h3>
                            <p class="text-xs text-[#888888] leading-relaxed mb-5" x-text="vscodeConnected ? 'Activity loaded and ready. You may now start coding.' : (vscodeNotDetected ? 'VS Code has not answered yet. The Certicode extension is probably not installed. Follow these steps, then open the lab again.' : 'Launching VS Code workspace. Waiting for extension heartbeat ping before continuing...')"></p>

                            <!-- Extension not detected: install instructions -->
                            <div x-show="vscodeNotDetected && !vscodeConnected" x-cloak class="mb-6 text-left bg-[#0d0d0d] p-4 rounded-xl border border-amber-500/30 space-y-3">
                                <ol class="space-y-2.5 text-xs text-[#a3a3a3]" role="list">
                                    <li class="flex gap-2.5"><span class="font-mono text-[#666666]">1</span><span>Did VS Code open? If not, install <a href="https://code.visualstudio.com/download" target="_blank" rel="noopener" class="text-[#3ecf8e] hover:underline">VS Code</a>, and allow your browser to open it when asked.</span></li>
                                    <li class="flex gap-2.5"><span class="font-mono text-[#666666]">2</span><span>Download the Certicode extension file with the button below.</span></li>
                                    <li class="flex gap-2.5"><span class="font-mono text-[#666666]">3</span><span>In VS Code open Extensions (<kbd class="rounded border border-[#2e2e2e] bg-[#141414] px-1 font-mono text-[11px] text-[#ededed]">Ctrl+Shift+X</kbd>), then the <strong class="text-[#ededed] font-medium">…</strong> menu, then <strong class="text-[#ededed] font-medium">Install from VSIX</strong>, and pick the downloaded file.</span></li>
                                    <li class="flex gap-2.5"><span class="font-mono text-[#666666]">4</span><span>If VS Code asks whether to trust this Certicode server, choose to trust it.</span></li>
                                    <li class="flex gap-2.5"><span class="font-mono text-[#666666]">5</span><span>Click <strong class="text-[#ededed] font-medium">Open lab again</strong>. This page checks for two minutes and continues on its own once the extension answers.</span></li>
                                </ol>
                                <div class="flex flex-col sm:flex-row gap-2">
                                    <a href="{{ asset('downloads/certicode-labs.vsix') }}" download class="ui-btn ui-btn-secondary flex-1 text-xs">Download extension (.vsix)</a>
                                    <button type="button" @click="reopenVsCode()" class="ui-btn ui-btn-primary flex-1 text-xs">Open lab again</button>
                                </div>
                                <p x-show="vscodePollGaveUp" x-cloak class="text-xs text-[#888888]">
                                    Stopped checking. VS Code already open?
                                    <button type="button" @click="pollForVsCodePing()" class="text-[#3ecf8e] hover:underline">Check again</button>
                                </p>
                            </div>

                            <!-- Progress checklist -->
                            <div x-show="!vscodeNotDetected || vscodeConnected" class="space-y-2.5 mb-6 text-left bg-[#0d0d0d] p-3.5 rounded-xl border border-[#222]">
                                <div class="flex items-center gap-2.5 text-xs text-[#ededed]">
                                    <span class="w-4 h-4 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center"><svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="3.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg></span>
                                    <span>Camera Presence Verified</span>
                                </div>
                                <div class="flex items-center gap-2.5 text-xs text-[#ededed]">
                                    <span class="w-4 h-4 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center"><svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="3.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg></span>
                                    <span>Lab Session Initialized</span>
                                </div>
                                <div class="flex items-center gap-2.5 text-xs">
                                    <template x-if="!vscodeConnected">
                                        <span class="w-4 h-4 rounded-full border-2 border-cyan-400 border-t-transparent animate-spin"></span>
                                    </template>
                                    <template x-if="vscodeConnected">
                                        <span class="w-4 h-4 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center"><svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="3.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg></span>
                                    </template>
                                    <span :class="vscodeConnected ? 'text-[#ededed]' : 'text-cyan-300 font-medium'">
                                        <span x-show="!vscodeConnected">Waiting for VS Code Extension Ping...</span>
                                        <span x-show="vscodeConnected">Extension Ping Confirmed (Active)</span>
                                    </span>
                                </div>
                            </div>

                            <div class="pt-2 border-t border-[#222] flex items-center justify-between">
                                <button type="button" @click="launchingVsCode = false" class="text-[11px] text-[#666666] hover:text-[#999999]">
                                    Dismiss Overlay
                                </button>
                                <button type="button" @click="reopenVsCode()" class="text-[11px] text-[#3ecf8e] hover:underline font-mono">
                                    Re-trigger VS Code &rarr;
                                </button>
                            </div>
                        </div>
                    </div>
                    @endif
                </section>

                {{-- Finished attempts, each opening its results page --}}
                @if ($results->isNotEmpty())
                    @php
                        $fmtGrade = fn ($n) => rtrim(rtrim(number_format((float) $n, 1), '0'), '.');
                        // The grade that counts: the best of the student's own completed attempts
                        $best = $results->where('status', 'completed')->filter(fn ($r) => isset($attemptNumbers[$r->id]))
                            ->sortByDesc(fn ($r) => $r->effective_score)->first();
                    @endphp
                    <section class="ui-card" aria-labelledby="results-heading">
                        <div class="px-6 pt-5 pb-3 flex items-baseline justify-between gap-3">
                            <h2 id="results-heading" class="ui-card-title">Your results</h2>
                            @if ($best)
                                <span class="text-sm text-[#888888]">Best <span class="font-semibold tabular-nums text-[#3ecf8e]">{{ $fmtGrade($best->effective_score) }}%</span></span>
                            @endif
                        </div>
                        <ol class="divide-y divide-[#232323] border-t border-[#232323]" role="list">
                            @foreach ($results as $result)
                                @php $resultDate = $result->ended_at ?? $result->started_at; @endphp
                                <li>
                                    <a href="{{ route('sessions.show', $result->id) }}" class="flex items-center justify-between gap-3 px-6 py-3 transition-colors hover:bg-[#1c1c1c]">
                                        <span class="min-w-0">
                                            <span class="block text-sm font-medium text-[#ededed]">{{ isset($attemptNumbers[$result->id]) ? 'Attempt '.$attemptNumbers[$result->id] : 'Team attempt' }}</span>
                                            @if ($resultDate)
                                                <span class="block text-xs text-[#888888]"><x-local-time :at="$resultDate" date /></span>
                                            @endif
                                        </span>
                                        <span class="shrink-0 text-right">
                                            @if ($result->status === 'abandoned' && $result->instructor_grade_override === null)
                                                <span class="text-sm text-[#888888]">Timed out</span>
                                            @else
                                                <span class="block text-sm font-semibold tabular-nums {{ $best && $result->id === $best->id ? 'text-[#3ecf8e]' : 'text-[#ededed]' }}">{{ $fmtGrade($result->effective_score) }}%</span>
                                                @if ($result->instructor_grade_override !== null)
                                                    <span class="block text-[10px] text-[#888888]">regraded</span>
                                                @endif
                                            @endif
                                        </span>
                                    </a>
                                </li>
                            @endforeach
                        </ol>
                    </section>
                @endif

                {{-- VS Code setup --}}
                <details class="ui-card group" {{ $activeSession ? '' : 'open' }}>
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-6 py-4">
                        <span class="flex items-center gap-2.5">
                            <svg class="w-4 h-4 text-[#3ecf8e]" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M23.15 2.587L18.21.21a1.494 1.494 0 0 0-1.705.29l-9.46 8.63-4.12-3.128a.999.999 0 0 0-1.276.057L.327 7.261A1 1 0 0 0 .32 8.704l4.28 3.297-4.28 3.296a1 1 0 0 0 .007 1.443l1.322 1.203c.365.332.91.355 1.276.057l4.12-3.128 9.46 8.63c.47.43 1.15.56 1.705.29l4.94-2.377A1.5 1.5 0 0 0 24 19.985V4.015a1.5 1.5 0 0 0-.85-1.428zM18 17.57l-7.464-5.57L18 6.43v11.14z"/></svg>
                            <span class="ui-card-title">Set up VS Code</span>
                        </span>
                        <svg class="w-4 h-4 text-[#888888] transition-transform group-open:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
                    </summary>
                    <div class="px-6 pb-6 space-y-4">
                        <p class="text-[13px] text-[#a3a3a3] leading-relaxed">You need the Certicode extension once. It syncs your timer, checks your progress and submits your work.</p>
                        <ol class="space-y-2.5 text-[13px] text-[#a3a3a3]" role="list">
                            <li class="flex gap-2.5"><span class="font-mono text-[#666666]">1</span><span>Download the extension file below.</span></li>
                            <li class="flex gap-2.5"><span class="font-mono text-[#666666]">2</span><span>In VS Code open Extensions (<kbd class="rounded border border-[#2e2e2e] bg-[#141414] px-1 font-mono text-[11px] text-[#ededed]">Ctrl+Shift+X</kbd>), then the <strong class="text-[#ededed] font-medium">…</strong> menu, then <strong class="text-[#ededed] font-medium">Install from VSIX</strong>.</span></li>
                            <li class="flex gap-2.5"><span class="font-mono text-[#666666]">3</span><span>Open an empty folder first (File, Open Folder) so your code is saved to disk.</span></li>
                        </ol>
                        <a href="{{ asset('downloads/certicode-labs.vsix') }}" download class="ui-btn ui-btn-secondary w-full">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v12m0 0l-4-4m4 4l4-4M4 18v1a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-1"/></svg>
                            Download extension (.vsix)
                        </a>
                        @if ($activeSession)
                            <div class="rounded-lg border border-[#2e2e2e] bg-[#141414] px-3.5 py-3 text-xs text-[#888888] space-y-1">
                                <p class="text-[#a3a3a3]">If VS Code doesn't connect on its own, click <strong class="text-[#ededed] font-medium">Continue in VS Code</strong> above and allow your browser to open it.</p>
                            </div>
                        @endif
                    </div>
                </details>
            @else
                {{-- Staff: live controls --}}
                <section class="ui-card ui-card-body" aria-labelledby="run-heading">
                    <h2 id="run-heading" class="ui-card-title">{{ $isLive ? 'Run this live lab' : 'Open lab' }}</h2>
                    @if (!$isLive)
                        <p class="ui-card-subtitle">Students can start whenever they like. Watch their progress on the monitoring page.</p>
                    @elseif ($laboratory->isLiveNotStarted())
                        <p class="ui-card-subtitle">Students can't enter until you start it. Everyone then shares one countdown.</p>
                        <form action="{{ route('laboratories.open-live', $laboratory->id) }}" method="POST" class="mt-4 space-y-3">
                            @csrf
                            <div>
                                <label for="duration_minutes" class="ui-label">Session length</label>
                                <div class="relative">
                                    <input type="number" id="duration_minutes" name="duration_minutes" value="{{ $minutes }}" min="1" max="600" class="ui-input pr-14">
                                    <span class="absolute inset-y-0 right-3 flex items-center text-sm text-[#888888] pointer-events-none" aria-hidden="true">min</span>
                                </div>
                            </div>
                            <button type="submit" class="ui-btn ui-btn-primary w-full">Start live lab</button>
                        </form>
                    @elseif ($laboratory->isLiveActive())
                        <p class="ui-card-subtitle">Running now with {{ max(1, (int) ceil($liveLeft / 60)) }} min left on the shared timer.</p>
                        <form action="{{ route('laboratories.end-live', $laboratory->id) }}" method="POST" class="mt-4"
                              onsubmit="return confirm('End this live lab now? Every open student workspace will be submitted and graded.');">
                            @csrf
                            <button type="submit" class="ui-btn ui-btn-danger w-full">End lab now</button>
                        </form>
                    @else
                        <p class="ui-card-subtitle">This live lab has ended.</p>
                        <form action="{{ route('laboratories.reopen-live', $laboratory->id) }}" method="POST" class="mt-4 space-y-3">
                            @csrf
                            @if ($liveLeft > 0)
                                <button type="submit" class="ui-btn ui-btn-secondary w-full">Reopen with {{ max(1, (int) ceil($liveLeft / 60)) }} min left</button>
                            @else
                                <div>
                                    <label for="extend_minutes" class="ui-label">Extra time</label>
                                    <div class="relative">
                                        <input type="number" id="extend_minutes" name="extend_minutes" value="15" min="1" max="180" class="ui-input pr-14">
                                        <span class="absolute inset-y-0 right-3 flex items-center text-sm text-[#888888] pointer-events-none" aria-hidden="true">min</span>
                                    </div>
                                </div>
                                <button type="submit" class="ui-btn ui-btn-secondary w-full">Extend and reopen</button>
                            @endif
                        </form>
                    @endif
                    <a href="{{ route('instructor.monitoring.show', $laboratory->id) }}" class="ui-btn ui-btn-ghost w-full mt-3">Open monitoring</a>
                </section>
            @endif
        </aside>
    </div>

    @if ($isStudent)
    </div>
    @endif
</div>

<script src="{{ asset('js/face-api.min.js') }}"></script>
<script>
function preLabCameraGate(labId, initialSessionId) {
    return {
        labId: labId,
        activeSessionId: initialSessionId,
        showModal: false,
        stream: null,
        status: 'idle', // 'idle', 'requesting', 'loading_model', 'denied', 'analyzing', 'failed', 'hard_blocked', 'verified'
        errorMessage: '',
        faceCount: 0,
        cameraVerified: false,
        attempts: 0,
        maxAttempts: 3,
        modelLoaded: false,
        modelLoading: false,
        launchingVsCode: false,
        vscodeConnected: false,
        // Timer of the next connect check (a setTimeout chain that backs off, not an interval)
        vscodePollInterval: null,
        vscodePollFirstDelayMs: 1500,
        vscodePollMaxDelayMs: 10000,
        vscodePollGiveUpMs: 120000,
        vscodePollGaveUp: false,
        // Set when the extension has not pinged within vscodeDetectTimeoutMs of launch,
        // which usually means it is not installed. Polling keeps going for a while so a late
        // ping still connects.
        vscodeNotDetected: false,
        vscodeDetectTimer: null,
        vscodeDetectTimeoutMs: 25000,
        // After connecting, how often the page checks VS Code is still open.
        vscodeWatchInterval: null,
        vscodeWatchIntervalMs: 5000,
        vscodeStaleAfterSeconds: 32,
        // Version reported by the connected extension, checked against the server's minimum.
        extensionOutdated: false,
        extensionVersion: null,
        extensionMinVersion: '',
        extensionDownloadUrl: @js(asset(config('services.vscode_extension.download_path'))),
        leavingAfterVsCodeClosed: false,
        lowLightingDetected: false,
        multipleFacesDetected: false,
        isLowLightMode: false,
        // Small luminance grid of the frame the student verified with in low-light mode.
        // In-lab checks compare against it, since face detection is unreliable in the dark.
        lowLightReference: null,

        // Continuous in-browser proctoring & heartbeat states
        browserProctorActive: false,
        proctorStatus: 'idle', // 'idle', 'normal', 'absence', 'multiple_faces', 'disconnected'
        proctorStatusText: 'Face Present (Normal)',
        vscodeUrl: '',
        pingCount: 0,
        absenceCount: 0,
        cameraDisconnectReported: false,
        lastAbsenceAlertAt: 0,
        lastMultiFaceAlertAt: 0,
        multiFaceStreak: 0,
        proctorInterval: null,
        pingInterval: null,

        init() {
            // Release webcam media stream tracks whenever the page is unloaded, navigated away from, or hidden
            window.addEventListener('pagehide', () => {
                if (this.stream) {
                    this.stream.getTracks().forEach(t => t.stop());
                }
            });
            window.addEventListener('unload', () => {
                if (this.stream) {
                    this.stream.getTracks().forEach(t => t.stop());
                }
            });

            // Note when this page goes out of view during the lab. Students are told to code in
            // VS Code with this page open behind it, so it isn't a tab switch: VS Code reports
            // its own focus changes, and coming back to this page isn't logged at all.
            document.addEventListener('visibilitychange', () => {
                if (this.browserProctorActive && this.activeSessionId && document.hidden) {
                    this.logTelemetry('lab_page_hidden', { timestamp: new Date().toISOString() });
                }
            });
        },

        async leaveSessionAndStopProctoring() {
            if (confirm('Are you sure you want to stop camera proctoring and exit this laboratory session?')) {
                const sid = this.activeSessionId;
                this.stopProctoring();
                if (sid) {
                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';
                    try {
                        await fetch(`/v1/sessions/${sid}/end`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {})
                            }
                        });
                    } catch (e) {}
                }
                window.location.reload();
            }
        },

        handleStartClick() {
            if (this.browserProctorActive && this.vscodeUrl) {
                this.launchingVsCode = true;
                window.location.href = this.vscodeUrl;
                this.pollForVsCodePing();
                return;
            }
            this.openGate();
        },

        async openGate() {
            this.showModal = true;
            this.status = 'requesting';
            this.errorMessage = '';
            await this.requestCamera();
        },

        async ensureSsdModel() {
            if (this.modelLoaded) return true;
            if (this.modelLoading) {
                while (this.modelLoading) {
                    await new Promise(r => setTimeout(r, 100));
                }
                return this.modelLoaded;
            }
            this.modelLoading = true;
            try {
                if (window.faceapi && window.faceapi.nets) {
                    const promises = [];
                    if (window.faceapi.nets.ssdMobilenetv1 && !window.faceapi.nets.ssdMobilenetv1.isLoaded) {
                        promises.push(window.faceapi.nets.ssdMobilenetv1.loadFromUri('/models').catch(e => console.warn('SSD load warning:', e)));
                    }
                    if (window.faceapi.nets.tinyFaceDetector && !window.faceapi.nets.tinyFaceDetector.isLoaded) {
                        promises.push(window.faceapi.nets.tinyFaceDetector.loadFromUri('/models').catch(e => console.warn('TinyFace load warning:', e)));
                    }
                    await Promise.all(promises);
                    // Only count as loaded if a detector really loaded; a failed download used to
                    // leave every student on the low-light fallback without anyone knowing
                    const nets = window.faceapi.nets;
                    this.modelLoaded = !!(nets.ssdMobilenetv1?.isLoaded || nets.tinyFaceDetector?.isLoaded);
                    if (!this.modelLoaded) {
                        console.error('Face detection models did not load from /models.');
                    }
                }
            } catch (err) {
                console.error('Failed to load face detection models from /models:', err);
            } finally {
                this.modelLoading = false;
            }
            return this.modelLoaded;
        },

        async requestCamera() {
            try {
                if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
                    this.status = 'denied';
                    this.errorMessage = 'Webcam media API is not supported by your browser environment. Please use an updated modern browser.';
                    return;
                }
                this.status = 'requesting';
                this.stream = await navigator.mediaDevices.getUserMedia({
                    video: { width: { ideal: 640 }, height: { ideal: 480 }, facingMode: 'user' }
                });

                this.status = 'loading_model';
                await this.ensureSsdModel();

                this.status = 'analyzing';
                this.$nextTick(async () => {
                    if (this.$refs.videoEl) {
                        this.$refs.videoEl.srcObject = this.stream;
                        try {
                            await this.$refs.videoEl.play();
                        } catch (e) {}
                    }
                    setTimeout(() => this.runAiPresenceValidation(), 600);
                });
            } catch (err) {
                this.status = 'denied';
                this.errorMessage = 'Camera access was denied or no camera device found. Workspace remains locked until permission is granted.';
            }
        },

        async runAiPresenceValidation() {
            if (!this.stream || !this.$refs.videoEl) return;
            const video = this.$refs.videoEl;
            const canvas = this.$refs.canvasEl;
            canvas.width = video.videoWidth || 640;
            canvas.height = video.videoHeight || 480;
            const ctx = canvas.getContext('2d', { willReadFrequently: true });
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
            const imageBase64 = canvas.toDataURL('image/jpeg', 0.7);

            this.status = 'analyzing';

            // Measure frame luminance to detect low light / dark rooms
            let avgLuminance = 100;
            try {
                const imgData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                const data = imgData.data;
                let totalLum = 0;
                for (let i = 0; i < data.length; i += 16) {
                    totalLum += (0.299 * data[i] + 0.587 * data[i+1] + 0.114 * data[i+2]);
                }
                avgLuminance = totalLum / (data.length / 16);
            } catch (e) {}

            this.lowLightingDetected = avgLuminance < 75;

            let detectedFaces = 0;

            // Strategy 1: SsdMobilenetv1 with lowered confidence threshold (0.15 for low-light & backlit tolerance)
            if (window.faceapi && this.modelLoaded && window.faceapi.nets.ssdMobilenetv1?.isLoaded) {
                try {
                    const detections = await window.faceapi.detectAllFaces(
                        video,
                        new window.faceapi.SsdMobilenetv1Options({ minConfidence: 0.15 })
                    );
                    detectedFaces = this.countFaces(detections);
                } catch (e) {
                    console.warn('SsdMobilenetv1 detection error:', e);
                }
            }

            // Strategy 2: TinyFaceDetector fallback (fast and sensitive in dim conditions)
            if (detectedFaces === 0 && window.faceapi && this.modelLoaded && window.faceapi.nets.tinyFaceDetector?.isLoaded) {
                try {
                    const tinyDetections = await window.faceapi.detectAllFaces(
                        video,
                        new window.faceapi.TinyFaceDetectorOptions({ inputSize: 416, scoreThreshold: 0.15 })
                    );
                    detectedFaces = this.countFaces(tinyDetections);
                } catch (e) {
                    console.warn('TinyFaceDetector detection error:', e);
                }
            }

            // Strategy 3: Contrast and brightness enhancement offscreen canvas (rescues dark/backlit faces)
            if (detectedFaces === 0 && window.faceapi && this.modelLoaded) {
                try {
                    const enhancedCanvas = document.createElement('canvas');
                    enhancedCanvas.width = canvas.width;
                    enhancedCanvas.height = canvas.height;
                    const enhancedCtx = enhancedCanvas.getContext('2d');
                    enhancedCtx.filter = 'brightness(1.6) contrast(1.4)';
                    enhancedCtx.drawImage(video, 0, 0, enhancedCanvas.width, enhancedCanvas.height);

                    if (window.faceapi.nets.tinyFaceDetector?.isLoaded) {
                        const enhancedTiny = await window.faceapi.detectAllFaces(
                            enhancedCanvas,
                            new window.faceapi.TinyFaceDetectorOptions({ inputSize: 416, scoreThreshold: 0.12 })
                        );
                        if (enhancedTiny.length > 0) {
                            detectedFaces = this.countFaces(enhancedTiny);
                        }
                    }
                    if (detectedFaces === 0 && window.faceapi.nets.ssdMobilenetv1?.isLoaded) {
                        const enhancedSsd = await window.faceapi.detectAllFaces(
                            enhancedCanvas,
                            new window.faceapi.SsdMobilenetv1Options({ minConfidence: 0.12 })
                        );
                        if (enhancedSsd.length > 0) {
                            detectedFaces = this.countFaces(enhancedSsd);
                        }
                    }
                } catch (e) {
                    console.warn('Enhanced canvas detection error:', e);
                }
            }

            // Strategy 4: Native browser FaceDetector API if available
            if (detectedFaces === 0 && 'FaceDetector' in window) {
                try {
                    const detector = new window.FaceDetector({ fastMode: true });
                    const faces = await detector.detect(video);
                    detectedFaces = this.countFaces(faces);
                } catch (e) {}
            }

            this.faceCount = detectedFaces;

            // Low-light mode skips face detection, so it is no way around a multiple-faces failure
            this.multipleFacesDetected = detectedFaces > 1;

            if (detectedFaces === 1) {
                await this.completeVerification(imageBase64, 1, false);
            } else if (detectedFaces > 1) {
                this.attempts++;
                this.errorMessage = `Multiple faces detected (${detectedFaces}). Only one person is permitted in the webcam frame.`;
                this.status = 'failed';
            } else {
                this.attempts++;
                this.errorMessage = this.lowLightingDetected
                    ? 'Low light or backlit camera detected. Position yourself in front of the screen, or click "Proceed (Low Light Mode)".'
                    : 'No face detected. Please face the webcam directly with your face visible.';
                this.status = 'failed';
            }
        },

        async acceptLowLightPresence() {
            if (!this.$refs.videoEl || this.multipleFacesDetected) return;
            const video = this.$refs.videoEl;
            const canvas = this.$refs.canvasEl || document.createElement('canvas');
            canvas.width = video.videoWidth || 640;
            canvas.height = video.videoHeight || 480;
            const ctx = canvas.getContext('2d');
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
            const imageBase64 = canvas.toDataURL('image/jpeg', 0.7);

            this.status = 'analyzing';
            await this.completeVerification(imageBase64, 1, true);
        },

        async completeVerification(imageBase64, faceCount = 1, isLowLight = false) {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';
            const headers = {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {})
            };
            const verifyPayload = JSON.stringify({
                status: 'granted',
                face_count: faceCount,
                low_light: isLowLight,
                image_base64: imageBase64
            });

            const endpoints = this.activeSessionId 
                ? [`/v1/sessions/${this.activeSessionId}/verify-camera`]
                : [`/v1/labs/${labId}/verify-camera`];

            let recorded = false;
            for (const url of endpoints) {
                try {
                    const res = await fetch(url, {
                        method: 'POST',
                        headers: headers,
                        body: verifyPayload
                    });
                    if (res.ok) {
                        recorded = true;
                        break;
                    }
                } catch (e) {
                    console.warn(`Verify endpoint ${url} network warning:`, e);
                }
            }

            // The lab only starts once the server has recorded the check
            if (!recorded) {
                this.attempts++;
                this.errorMessage = 'Your camera check could not be saved. Check your connection and try again.';
                this.status = 'failed';
                return;
            }

            this.status = 'verified';
            this.cameraVerified = true;
            this.isLowLightMode = isLowLight;
            if (isLowLight && this.$refs.videoEl) {
                this.lowLightReference = this.luminanceGrid(this.$refs.videoEl);
            }
            setTimeout(() => {
                this.launchLab();
            }, 600);
        },

        async launchLab() {
            if (!this.cameraVerified) {
                console.warn('Cannot launch lab workspace without verified camera presence.');
                return;
            }
            this.showModal = false;
            this.launchingVsCode = true;
            this.vscodeConnected = false;
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';

            try {
                const res = await fetch(`{{ route('laboratories.start', $laboratory->id) }}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {})
                    },
                    // The server checks its own record of the camera check; nothing to send
                    body: JSON.stringify({})
                });

                if (res.ok) {
                    const data = await res.json();
                    if (data && data.vscode_url) {
                        this.activeSessionId = data.session_id || this.activeSessionId;
                        this.vscodeUrl = data.vscode_url;
                        this.browserProctorActive = true;

                        // Mount camera stream to persistent live video element on the page
                        this.$nextTick(() => {
                            if (this.$refs.proctorLiveVideo && this.stream) {
                                this.$refs.proctorLiveVideo.srcObject = this.stream;
                                this.$refs.proctorLiveVideo.play().catch(() => {});
                            }
                        });

                        // Start continuous proctoring and ping heartbeat in browser
                        this.startContinuousProctoring();
                        this.startPingLoop();

                        // Launch VS Code via deep link
                        window.location.href = data.vscode_url;

                        // Poll for VS Code extension ping before dismissing loading overlay!
                        this.pollForVsCodePing();
                        return;
                    }
                }
            } catch (err) {
                console.warn('Asynchronous launch failed, falling back to form submit:', err);
            }

            this.closeGate();
            const form = document.getElementById('start-lab-form');
            if (form) {
                form.submit();
            }
        },

        // Wait for the extension's first ping. Checks start quick and back off (each one is a
        // server request), and stop after vscodePollGiveUpMs; "Check again" starts over.
        pollForVsCodePing() {
            clearTimeout(this.vscodePollInterval);
            this.vscodePollInterval = null;
            if (!this.activeSessionId) return;

            this.vscodeNotDetected = false;
            this.vscodePollGaveUp = false;
            clearTimeout(this.vscodeDetectTimer);
            this.vscodeDetectTimer = setTimeout(() => {
                if (!this.vscodeConnected) {
                    this.vscodeNotDetected = true;
                }
            }, this.vscodeDetectTimeoutMs);

            const startedAt = Date.now();
            let delay = this.vscodePollFirstDelayMs;

            const checkPing = async () => {
                this.vscodePollInterval = null;
                try {
                    const res = await fetch(`/v1/sessions/${this.activeSessionId}`);
                    if (res.ok) {
                        const data = await res.json();
                        this.applyExtensionStatus(data);
                        if (data && data.vscode_connected === true) {
                            this.vscodeConnected = true;
                            this.vscodeNotDetected = false;
                            clearTimeout(this.vscodeDetectTimer);
                            // Brief confirmation delay so student sees the green checkmark
                            setTimeout(() => {
                                this.launchingVsCode = false;
                            }, 800);
                            this.watchVsCodeConnection();
                            return;
                        }
                    }
                } catch (e) {
                    console.warn('Poll session status warning:', e);
                }

                if (Date.now() - startedAt >= this.vscodePollGiveUpMs) {
                    this.vscodePollGaveUp = true;
                    this.vscodeNotDetected = true;
                    return;
                }
                delay = Math.min(this.vscodePollMaxDelayMs, Math.round(delay * 1.6));
                this.vscodePollInterval = setTimeout(checkPing, delay);
            };

            this.vscodePollInterval = setTimeout(checkPing, 800);
        },

        // Once VS Code is connected, keep checking it is still open. When the student
        // closes VS Code, leave camera mode and the lab and go back to the lab view.
        watchVsCodeConnection() {
            clearInterval(this.vscodeWatchInterval);
            let missedChecks = 0;
            let tick = 0;

            const check = async () => {
                if (!this.browserProctorActive || !this.activeSessionId) return;
                // While the student is in VS Code this page is hidden: check every third tick
                tick++;
                if (document.hidden && tick % 3 !== 0) return;
                try {
                    const res = await fetch(`/v1/sessions/${this.activeSessionId}`, { headers: { 'Accept': 'application/json' } });
                    if (!res.ok) return;
                    const data = await res.json();
                    this.applyExtensionStatus(data);
                    if (data.status && data.status !== 'in_progress') {
                        this.leaveLabAfterVsCodeClosed('session_ended');
                        return;
                    }
                    if (data.vscode_closed === true) {
                        this.leaveLabAfterVsCodeClosed('vscode_closed');
                        return;
                    }
                    // No clean shutdown report (old extension, crash, killed process): the extension
                    // pings every 15s, so two missed pings means VS Code is gone.
                    const pingAge = data.vscode_ping_age_seconds;
                    const stale = data.vscode_connected !== true
                        || (typeof pingAge === 'number' && pingAge > this.vscodeStaleAfterSeconds);
                    missedChecks = stale ? missedChecks + 1 : 0;
                    if (missedChecks >= 2) {
                        this.leaveLabAfterVsCodeClosed('vscode_heartbeat_lost');
                    }
                } catch (e) {
                    // Network blip in the browser, not proof VS Code closed.
                }
            };

            this.vscodeWatchInterval = setInterval(check, this.vscodeWatchIntervalMs);
        },

        applyExtensionStatus(data) {
            if (!data) return;
            this.extensionOutdated = data.extension_outdated === true;
            this.extensionVersion = data.extension_version || null;
            this.extensionMinVersion = data.extension_min_version || '';
            if (data.extension_download_url) this.extensionDownloadUrl = data.extension_download_url;
        },

        async leaveLabAfterVsCodeClosed(reason) {
            if (this.leavingAfterVsCodeClosed) return;
            this.leavingAfterVsCodeClosed = true;
            await this.logTelemetry('vscode_closed_exit', { reason: reason, timestamp: new Date().toISOString() });
            this.stopProctoring();
            this.proctorStatus = 'disconnected';
            this.proctorStatusText = 'VS Code closed';
            // A finished attempt opens its results; one left open comes back to this page
            if (reason === 'session_ended') {
                this.showResults();
            } else {
                window.location.reload();
            }
        },

        showResults() {
            if (this.activeSessionId) {
                window.location.href = `/sessions/${this.activeSessionId}`;
            } else {
                window.location.reload();
            }
        },

        startContinuousProctoring() {
            if (this.proctorInterval) {
                clearInterval(this.proctorInterval);
            }
            this.proctorStatus = 'normal';
            this.proctorStatusText = 'Face Present (Normal)';
            this.absenceCount = 0;

            this.proctorInterval = setInterval(async () => {
                await this.runProctorCycle();
            }, 10000);
        },

        async runProctorCycle() {
            if (!this.browserProctorActive) return;

            if (!this.stream || !this.stream.getVideoTracks().some(t => t.readyState === 'live')) {
                this.proctorStatus = 'disconnected';
                this.proctorStatusText = 'Camera Disconnected';
                // Report a disconnect once, not on every cycle until the camera returns.
                if (!this.cameraDisconnectReported) {
                    this.cameraDisconnectReported = true;
                    await this.logTelemetry('camera_absence', {
                        reason: 'Webcam video track disconnected in browser',
                        timestamp: new Date().toISOString()
                    });
                }
                return;
            }
            this.cameraDisconnectReported = false;

            const video = this.$refs.proctorLiveVideo || this.$refs.videoEl;
            if (!video || video.readyState < 2) return;

            // Students who verified in low-light mode, or whose room has gone dark since,
            // get the low-light check instead of normal face detection.
            const grid = this.luminanceGrid(video);
            const avgLuminance = grid ? grid.reduce((a, b) => a + b, 0) / grid.length : 100;
            this.lowLightingDetected = avgLuminance < 75;
            const lowLight = this.isLowLightMode || this.lowLightingDetected;

            const detectedFaces = lowLight
                ? await this.detectFacesLowLight(video, grid)
                : await this.detectFacesNormal(video);

            await this.applyProctorResult(video, detectedFaces, lowLight);
        },

        async detectFacesNormal(video) {
            let detectedFaces = 0;

            // Strategy 1: SsdMobilenetv1 on raw video
            if (window.faceapi && this.modelLoaded && window.faceapi.nets.ssdMobilenetv1?.isLoaded) {
                try {
                    const detections = await window.faceapi.detectAllFaces(
                        video,
                        new window.faceapi.SsdMobilenetv1Options({ minConfidence: 0.15 })
                    );
                    detectedFaces = this.countFaces(detections);
                } catch (e) {}
            }

            // Strategy 2: TinyFaceDetector on raw video
            if (detectedFaces === 0 && window.faceapi && this.modelLoaded && window.faceapi.nets.tinyFaceDetector?.isLoaded) {
                try {
                    const tinyDetections = await window.faceapi.detectAllFaces(
                        video,
                        new window.faceapi.TinyFaceDetectorOptions({ inputSize: 416, scoreThreshold: 0.12 })
                    );
                    detectedFaces = this.countFaces(tinyDetections);
                } catch (e) {}
            }

            // Strategy 3: Contrast and brightness enhancement offscreen canvas (rescues dark/backlit faces)
            if (detectedFaces === 0 && window.faceapi && this.modelLoaded) {
                try {
                    const canvas = this.$refs.proctorCanvas || document.createElement('canvas');
                    canvas.width = video.videoWidth || 640;
                    canvas.height = video.videoHeight || 480;
                    const ctx = canvas.getContext('2d');
                    ctx.filter = 'brightness(1.8) contrast(1.5)';
                    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

                    if (window.faceapi.nets.tinyFaceDetector?.isLoaded) {
                        const boostedTiny = await window.faceapi.detectAllFaces(
                            canvas,
                            new window.faceapi.TinyFaceDetectorOptions({ inputSize: 416, scoreThreshold: 0.10 })
                        );
                        if (boostedTiny.length > 0) detectedFaces = this.countFaces(boostedTiny);
                    }

                    if (detectedFaces === 0 && window.faceapi.nets.ssdMobilenetv1?.isLoaded) {
                        const boostedSsd = await window.faceapi.detectAllFaces(
                            canvas,
                            new window.faceapi.SsdMobilenetv1Options({ minConfidence: 0.10 })
                        );
                        if (boostedSsd.length > 0) detectedFaces = this.countFaces(boostedSsd);
                    }
                } catch (e) {}
            }

            // Strategy 4: Native browser FaceDetector API
            if (detectedFaces === 0 && 'FaceDetector' in window) {
                try {
                    const detector = new window.FaceDetector({ fastMode: true });
                    const faces = await detector.detect(video);
                    detectedFaces = this.countFaces(faces);
                } catch (e) {}
            }

            return detectedFaces;
        },

        async detectFacesLowLight(video, grid) {
            // Boost the frame first: raw detection mostly misses faces in the dark
            if (window.faceapi && this.modelLoaded) {
                try {
                    const canvas = this.$refs.proctorCanvas || document.createElement('canvas');
                    canvas.width = video.videoWidth || 640;
                    canvas.height = video.videoHeight || 480;
                    const ctx = canvas.getContext('2d');
                    ctx.filter = 'brightness(2.4) contrast(1.6)';
                    ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                    ctx.filter = 'none';

                    let detections = [];
                    if (window.faceapi.nets.tinyFaceDetector?.isLoaded) {
                        detections = await window.faceapi.detectAllFaces(
                            canvas,
                            new window.faceapi.TinyFaceDetectorOptions({ inputSize: 416, scoreThreshold: 0.10 })
                        );
                    }
                    if (detections.length === 0 && window.faceapi.nets.ssdMobilenetv1?.isLoaded) {
                        detections = await window.faceapi.detectAllFaces(
                            canvas,
                            new window.faceapi.SsdMobilenetv1Options({ minConfidence: 0.10 })
                        );
                    }
                    if (detections.length > 0) return this.countFaces(detections);
                } catch (e) {}
            }

            if ('FaceDetector' in window) {
                try {
                    const faces = await new window.FaceDetector({ fastMode: true }).detect(video);
                    if (faces.length > 0) return this.countFaces(faces);
                } catch (e) {}
            }

            return this.checkLowLightPresence(grid) ? 1 : 0;
        },

        // How many people a set of detections represents. The thresholds above are low so a
        // dim or backlit student still counts as present, but that also picks up shadows,
        // posters and people far in the background. The largest face is the student; another
        // face only counts if it is confident, not a duplicate box of the student's face, and
        // close enough to the camera to be someone sitting with them.
        countFaces(detections) {
            const faces = (detections || []).map((d) => {
                const box = d.box || d.boundingBox || d.detection?.box || {};
                return {
                    score: d.score ?? d.classScore ?? d.detection?.score ?? 1,
                    x: box.x ?? 0, y: box.y ?? 0, w: box.width ?? 0, h: box.height ?? 0,
                };
            }).filter((f) => f.w > 0 && f.h > 0);

            if (faces.length === 0) return (detections || []).length > 0 ? 1 : 0;

            faces.sort((a, b) => b.w * b.h - a.w * a.h);
            const main = faces[0];
            const overlap = (a, b) => {
                const ix = Math.max(0, Math.min(a.x + a.w, b.x + b.w) - Math.max(a.x, b.x));
                const iy = Math.max(0, Math.min(a.y + a.h, b.y + b.h) - Math.max(a.y, b.y));
                return (ix * iy) / Math.min(a.w * a.h, b.w * b.h);
            };

            const others = faces.slice(1).filter((f) =>
                f.score >= 0.5
                && f.w * f.h >= main.w * main.h * 0.2
                && overlap(main, f) < 0.3
            );

            return 1 + others.length;
        },

        async applyProctorResult(video, detectedFaces, lowLight) {
            this.faceCount = detectedFaces;
            const nowTime = Date.now();

            if (detectedFaces < 2) this.multiFaceStreak = 0;

            if (detectedFaces === 1) {
                this.absenceCount = 0;
                this.proctorStatus = 'normal';
                this.proctorStatusText = lowLight ? 'Present (Low-light mode)' : 'Face Present (Normal)';
            } else if (detectedFaces === 0) {
                this.absenceCount++;
                this.proctorStatus = 'absence';
                this.proctorStatusText = `Face Searching (${this.absenceCount}/4 warning)`;

                if (this.absenceCount >= 4 && (nowTime - this.lastAbsenceAlertAt > 35000)) {
                    this.lastAbsenceAlertAt = nowTime;
                    const snapshot = this.captureSnapshot(video);
                    await this.logTelemetry('camera_absence', {
                        image_base64: snapshot,
                        face_count: 0,
                        consecutive_absences: this.absenceCount,
                        low_light: lowLight,
                        timestamp: new Date().toISOString()
                    });
                }
            } else {
                this.absenceCount = 0;
                this.proctorStatus = 'multiple_faces';
                this.proctorStatusText = `Multiple Faces Detected (${detectedFaces})`;
                this.multiFaceStreak++;

                // One frame can still catch a passer-by, so only flag a second person
                // who stays in view for two checks in a row (about 20 seconds).
                if (this.multiFaceStreak >= 2 && nowTime - this.lastMultiFaceAlertAt > 30000) {
                    this.lastMultiFaceAlertAt = nowTime;
                    const snapshot = this.captureSnapshot(video);
                    await this.logTelemetry('webcam_check', {
                        image_base64: snapshot,
                        face_count: detectedFaces,
                        low_light: lowLight,
                        timestamp: new Date().toISOString()
                    });
                }
            }
        },

        // 40x30 luminance values for the current frame, or null if the frame can't be read.
        luminanceGrid(video) {
            try {
                const canvas = document.createElement('canvas');
                canvas.width = 40;
                canvas.height = 30;
                const ctx = canvas.getContext('2d', { willReadFrequently: true });
                ctx.drawImage(video, 0, 0, 40, 30);
                const data = ctx.getImageData(0, 0, 40, 30).data;
                const grid = new Array(40 * 30);
                for (let i = 0; i < grid.length; i++) {
                    grid[i] = 0.299 * data[i * 4] + 0.587 * data[i * 4 + 1] + 0.114 * data[i * 4 + 2];
                }
                return grid;
            } catch (e) {
                return null;
            }
        },

        checkLowLightPresence(grid) {
            if (!grid) return false;

            let centerLum = 0, centerCount = 0;
            let borderLum = 0, borderCount = 0;
            let refDiff = 0;
            const ref = this.lowLightReference;

            for (let y = 0; y < 30; y++) {
                for (let x = 0; x < 40; x++) {
                    const i = y * 40 + x;
                    if (x >= 10 && x < 30 && y >= 5 && y < 25) {
                        centerLum += grid[i];
                        centerCount++;
                        if (ref) refDiff += Math.abs(grid[i] - ref[i]);
                    } else {
                        borderLum += grid[i];
                        borderCount++;
                    }
                }
            }

            const avgCenter = centerLum / centerCount;
            const avgBorder = borderLum / borderCount;

            // A covered or switched-off camera is never "present"
            if (avgCenter <= 4) return false;

            // The middle of the frame still looks like it did when the student verified
            if (ref && refDiff / centerCount < 14) return true;

            // Silhouette: head darker (backlit) or lighter (screen glow) than the room around it
            return Math.abs(avgBorder - avgCenter) > 6;
        },

        captureSnapshot(video) {
            try {
                const canvas = this.$refs.proctorCanvas || this.$refs.canvasEl || document.createElement('canvas');
                canvas.width = video.videoWidth || 640;
                canvas.height = video.videoHeight || 480;
                const ctx = canvas.getContext('2d');
                ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
                return canvas.toDataURL('image/jpeg', 0.65);
            } catch (e) {
                return null;
            }
        },

        startPingLoop() {
            if (this.pingInterval) {
                clearInterval(this.pingInterval);
            }
            this.sendPing();
            this.pingInterval = setInterval(() => {
                this.sendPing();
            }, 15000);
        },

        async sendPing() {
            if (!this.activeSessionId) return;
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';
            try {
                const res = await fetch(`/v1/sessions/${this.activeSessionId}/ping`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {})
                    },
                    body: JSON.stringify({
                        face_count: this.faceCount,
                        timestamp: new Date().toISOString()
                    })
                });

                if (res.ok) {
                    const data = await res.json();
                    this.pingCount++;
                    if (data && data.is_active === false) {
                        this.stopProctoring();
                        this.proctorStatus = 'disconnected';
                        this.proctorStatusText = 'Session Concluded';
                        this.showResults();
                    }
                }
            } catch (err) {
                console.warn('Ping heartbeat warning:', err);
            }
        },

        async logTelemetry(eventType, payload) {
            if (!this.activeSessionId) return;
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';
            try {
                await fetch(`/v1/sessions/${this.activeSessionId}/telemetry`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {})
                    },
                    body: JSON.stringify({
                        event_type: eventType,
                        payload: payload
                    })
                });
            } catch (err) {
                console.warn(`Failed to send telemetry event ${eventType}:`, err);
            }
        },

        reopenVsCode() {
            if (this.vscodeUrl) {
                window.location.href = this.vscodeUrl;
                // Restart detection so the student gets a fresh wait after installing the extension.
                this.pollForVsCodePing();
            }
        },

        stopProctoring() {
            clearTimeout(this.vscodePollInterval);
            this.vscodePollInterval = null;
            clearTimeout(this.vscodeDetectTimer);
            clearInterval(this.vscodeWatchInterval);
            this.vscodeWatchInterval = null;
            this.vscodeNotDetected = false;
            if (this.proctorInterval) {
                clearInterval(this.proctorInterval);
                this.proctorInterval = null;
            }
            if (this.pingInterval) {
                clearInterval(this.pingInterval);
                this.pingInterval = null;
            }
            if (this.stream) {
                this.stream.getTracks().forEach(t => t.stop());
                this.stream = null;
            }
            this.browserProctorActive = false;
            this.launchingVsCode = false;
        },

        closeGate() {
            if (!this.browserProctorActive && this.stream) {
                this.stream.getTracks().forEach(t => t.stop());
                this.stream = null;
            }
            clearTimeout(this.vscodePollInterval);
            this.vscodePollInterval = null;
            clearTimeout(this.vscodeDetectTimer);
            this.vscodeNotDetected = false;
            this.showModal = false;
            this.launchingVsCode = false;
        }
    };
}
window.preLabCameraGate = preLabCameraGate;
if (window.Alpine) {
    window.Alpine.data('preLabCameraGate', preLabCameraGate);
} else {
    document.addEventListener('alpine:init', () => {
        window.Alpine.data('preLabCameraGate', preLabCameraGate);
    });
}
</script>
@endsection

@extends('layouts.app')

@section('title', $laboratory->title)
@section('page_header', 'Laboratory Exercise Specifications')

@section('content')
@php
    $backUrl = route('classes.index');
    if ($laboratory->module) {
        $backUrl = route('modules.show', ['class_id' => $laboratory->module->class_id, 'module_id' => $laboratory->module->id]);
    }
@endphp
<div class="max-w-4xl mx-auto space-y-6">
    <div class="p-8 rounded-xl bg-[#171717] border border-[#2e2e2e]">
        <div class="flex items-center justify-between mb-6 flex-wrap gap-2">
            <div class="flex items-center gap-2 flex-wrap">
                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-mono uppercase tracking-wider {{ $laboratory->is_group_lab ? 'bg-[#141414] text-[#ededed] border border-[#2e2e2e]' : 'bg-[#141414] text-[#3ecf8e] border border-[#3ecf8e]/30' }}">
                    {{ $laboratory->is_group_lab ? 'Group Laboratory' : 'Individual Laboratory' }}
                </span>

                @if($laboratory->isLiveLab())
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-mono uppercase tracking-wider bg-[#3ecf8e]/10 text-[#3ecf8e] border border-[#3ecf8e]/30">
                        Live Lab (Shared Countdown)
                    </span>
                    @if($laboratory->isLiveNotStarted())
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-mono uppercase tracking-wider bg-amber-500/10 text-amber-400 border border-amber-500/30">
                            🔒 Locked / Not Started
                        </span>
                    @elseif($laboratory->isLiveActive())
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-mono uppercase tracking-wider bg-emerald-500/10 text-emerald-400 border border-emerald-500/30 animate-pulse">
                            🔴 Countdown Active ({{ max(1, (int) ceil($laboratory->getRemainingLiveSeconds() / 60)) }}m remaining)
                        </span>
                    @else
                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-mono uppercase tracking-wider bg-red-500/10 text-red-400 border border-red-500/30">
                            ⏹️ Closed / Expired
                        </span>
                    @endif
                @else
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-mono uppercase tracking-wider bg-[#141414] text-[#a3a3a3] border border-[#2e2e2e]">
                        Open Lab (Self-Paced)
                    </span>
                @endif
            </div>
            
            <div class="flex items-center text-[#888888] text-xs font-mono">
                <svg class="w-4 h-4 mr-1.5 text-[#3ecf8e]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                {{ $laboratory->isLiveLab() ? 'DURATION WINDOW' : 'TIME LIMIT' }}: {{ $laboratory->isLiveLab() ? ($laboratory->live_duration_minutes ?? $laboratory->time_limit ?? 60) : $laboratory->time_limit }} MIN
            </div>
        </div>

        <h1 class="text-2xl sm:text-3xl font-bold text-[#ededed] tracking-tight mb-4">{{ $laboratory->title }}</h1>
        
        <div class="prose prose-invert max-w-none text-[#a3a3a3] mb-8 leading-relaxed text-sm">
            <h3 class="text-xs font-mono uppercase font-bold tracking-wider text-[#ededed] mb-2">Instructions</h3>
            <p class="whitespace-pre-line">{{ $laboratory->description }}</p>
        </div>

        <!-- Starter Files Manifest Preview -->
        @php
            $starterFiles = $laboratory->getStarterFilesList();
        @endphp
        <div class="border-t border-[#232323] pt-6 mb-8" x-data="{ expandedFile: null }">
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-4">
                <div>
                    <h3 class="text-xs font-mono uppercase font-bold tracking-wider text-[#a3a3a3]">Starter Workspace Files</h3>
                    <p class="text-[11px] text-[#666666] mt-0.5">Automatically provisioned in your VS Code workspace or downloadable directly.</p>
                </div>
                <div class="flex items-center gap-2 flex-wrap">
                    <span class="text-[10px] font-mono text-[#3ecf8e] bg-[#141414] border border-[#3ecf8e]/30 px-2.5 py-1 rounded-full">
                        {{ count($starterFiles) }} {{ count($starterFiles) === 1 ? 'FILE' : 'FILES' }}
                    </span>
                    @if(count($starterFiles) > 0)
                        <a href="{{ route('laboratories.starter-files.download', $laboratory->id) }}" 
                           class="inline-flex items-center gap-1.5 px-3 py-1 rounded-[6px] bg-[#1a1a1a] hover:bg-[#262626] border border-[#2e2e2e] hover:border-[#3ecf8e]/50 text-xs font-mono text-[#ededed] transition-colors shadow-sm"
                           title="Download starter files directly">
                            <svg class="w-3.5 h-3.5 text-[#3ecf8e]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                            <span>Download Starter {{ count($starterFiles) > 1 ? 'Files (.zip)' : 'File' }}</span>
                        </a>
                    @endif
                </div>
            </div>

            <div class="space-y-3">
                @foreach($starterFiles as $idx => $sfile)
                    <div class="rounded-[6px] bg-[#141414] border border-[#2e2e2e] overflow-hidden">
                        <div class="p-3.5 flex items-center justify-between cursor-pointer hover:bg-[#1a1a1a] transition-colors"
                             @click="expandedFile = expandedFile === {{ $idx }} ? null : {{ $idx }}">
                            <div class="flex items-center space-x-3">
                                <svg class="w-4 h-4 text-[#3ecf8e]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                <span class="text-xs font-mono font-bold text-[#ededed]">{{ $sfile['name'] }}</span>
                            </div>
                            <div class="flex items-center space-x-2">
                                @if(!empty($sfile['is_primary']))
                                    <span class="px-2 py-0.5 text-[10px] font-mono rounded bg-[#3ecf8e]/10 text-[#3ecf8e] border border-[#3ecf8e]/20 font-bold">
                                        Primary (Auto-Open)
                                    </span>
                                @endif
                                @if(!empty($sfile['is_readonly']))
                                    <span class="px-2 py-0.5 text-[10px] font-mono rounded bg-[#2e2e2e] text-[#a3a3a3] border border-[#383838]">
                                        Read-Only
                                    </span>
                                @endif
                                <span class="text-[10px] font-mono text-[#888888]" x-text="expandedFile === {{ $idx }} ? '▲ Hide' : '▼ View Code'"></span>
                            </div>
                        </div>

                        <div x-show="expandedFile === {{ $idx }}" x-collapse style="display: none;" class="border-t border-[#232323] p-3 bg-[#0d0d0d]">
                            <div class="flex items-center justify-between pb-2 mb-2 border-b border-[#1f1f1f]">
                                <span class="text-[11px] font-mono text-[#666666]">{{ $sfile['name'] }}</span>
                                <button type="button"
                                        x-data="{ copied: false }"
                                        @click="navigator.clipboard.writeText({{ json_encode($sfile['content'] ?? '') }}).then(() => { copied = true; setTimeout(() => copied = false, 2000); })"
                                        class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded text-[11px] font-mono text-[#a3a3a3] hover:text-[#ededed] bg-[#171717] hover:bg-[#222222] border border-[#2e2e2e] transition-colors">
                                    <svg class="w-3 h-3 text-[#3ecf8e]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                                    <span x-text="copied ? 'Copied!' : 'Copy Code'"></span>
                                </button>
                            </div>
                            <pre class="text-xs font-mono text-[#a3a3a3] overflow-x-auto whitespace-pre"><code>{{ $sfile['content'] }}</code></pre>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        @if(!empty($laboratory->tasks_definition))
            <div class="border-t border-[#232323] pt-6 mb-8">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-xs font-mono uppercase font-bold tracking-wider text-[#a3a3a3]">Competency Tasks Checklist</h3>
                    <span class="text-[10px] font-mono text-[#666666]">VERIFICATION SUITE</span>
                </div>
                <div class="space-y-3">
                    @foreach($laboratory->tasks_definition as $task)
                        <div class="p-4 rounded-[6px] bg-[#141414] border border-[#2e2e2e] flex items-start space-x-3.5 hover:border-[#3ecf8e]/35 transition-colors">
                            <span class="h-6 w-6 rounded-[4px] bg-[#171717] flex items-center justify-center text-[#3ecf8e] font-mono font-bold text-xs flex-shrink-0 mt-0.5 border border-[#2e2e2e]">
                                {{ $task['id'] }}
                            </span>
                            <div class="flex-1">
                                <p class="text-xs sm:text-sm font-medium text-[#ededed]">{{ $task['task'] }}</p>
                                @if(!empty($task['command']) && (auth()->user()->role === 'admin' || auth()->user()->role === 'instructor'))
                                    <code class="text-[11px] bg-[#0f0f0f] border border-[#2e2e2e] text-[#3ecf8e] font-mono px-2 py-1 rounded mt-2 inline-block">
                                        Validation: {{ $task['command'] }}
                                    </code>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Team Collaboration & Live Metrics (for Group Labs) -->
        @if($laboratory->is_group_lab && $activeSession)
            <div class="border-t border-[#232323] pt-6 mb-8" 
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
                         setInterval(() => {
                             this.fetchChats();
                             this.fetchSessionStats();
                         }, 5000);
                     },
                     async fetchChats() {
                         try {
                             const res = await fetch(`/api/v1/sessions/${this.sessionId}/chat`);
                             const data = await res.json();
                             if (data.chats) this.messages = data.chats;
                         } catch (e) {}
                     },
                     async fetchSessionStats() {
                         try {
                             const res = await fetch(`/api/v1/sessions/${this.sessionId}`);
                             const data = await res.json();
                             if (data.code_contributions) this.contributions = data.code_contributions;
                             if (data.diff_stats) this.diffStats = data.diffStats;
                         } catch (e) {}
                     },
                     async sendMessage() {
                         if (!this.newMessage.trim()) return;
                         try {
                             const res = await fetch(`/api/v1/sessions/${this.sessionId}/chat`, {
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

        @if(auth()->user()->role === 'student')
            <div x-data="preLabCameraGate({{ $laboratory->id }}, {{ $activeSession ? $activeSession->id : 'null' }})">
                <!-- Persistent Live Browser Proctoring Monitor Card -->
                <div x-show="browserProctorActive" x-cloak class="p-5 sm:p-6 rounded-xl bg-[#141414] border-2 border-[#3ecf8e]/50 shadow-xl shadow-[#3ecf8e]/5 mb-6 transition-all">
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

                        <div class="flex items-center gap-2.5 shrink-0">
                            <button type="button" @click="reopenVsCode()" class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-[#3ecf8e] hover:bg-[#00c573] text-[#0f0f0f] text-xs font-bold transition shadow-sm">
                                <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M23.15 2.587L18.21.21a1.494 1.494 0 0 0-1.705.29l-9.46 8.63-4.12-3.128a.999.999 0 0 0-1.276.057L.327 7.261A1 1 0 0 0 .32 8.704l4.28 3.297-4.28 3.296a1 1 0 0 0 .007 1.443l1.322 1.203c.365.332.91.355 1.276.057l4.12-3.128 9.46 8.63c.47.43 1.15.56 1.705.29l4.94-2.377A1.5 1.5 0 0 0 24 19.985V4.015a1.5 1.5 0 0 0-.85-1.428zM18 17.57l-7.464-5.57L18 6.43v11.14z"/>
                                </svg>
                                <span>Switch to VS Code Workspace</span>
                            </button>
                        </div>
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
                                <span class="text-amber-400">⚠️ Keep this tab open while coding</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- VS Code Integration & Setup Card -->
                <div class="border-t border-[#232323] pt-6 mb-6">
                    <div class="p-5 rounded-[6px] bg-[#141414] border border-[#2e2e2e]">
                        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                            <div class="flex items-start space-x-3.5">
                                <div class="p-2 rounded-[6px] bg-[#171717] border border-[#2e2e2e] text-[#3ecf8e] flex-shrink-0 mt-0.5">
                                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor">
                                        <path d="M23.15 2.587L18.21.21a1.494 1.494 0 0 0-1.705.29l-9.46 8.63-4.12-3.128a.999.999 0 0 0-1.276.057L.327 7.261A1 1 0 0 0 .32 8.704l4.28 3.297-4.28 3.296a1 1 0 0 0 .007 1.443l1.322 1.203c.365.332.91.355 1.276.057l4.12-3.128 9.46 8.63c.47.43 1.15.56 1.705.29l4.94-2.377A1.5 1.5 0 0 0 24 19.985V4.015a1.5 1.5 0 0 0-.85-1.428zM18 17.57l-7.464-5.57L18 6.43v11.14z"/>
                                    </svg>
                                </div>
                                <div>
                                    <h4 class="text-sm font-bold text-[#ededed] flex items-center gap-2">
                                        Certicode Labs VS Code Extension
                                        <span class="px-2 py-0.5 text-[10px] font-mono uppercase tracking-wide rounded-full bg-[#171717] text-[#3ecf8e] border border-[#3ecf8e]/30">Official IDE</span>
                                    </h4>
                                    <p class="text-xs text-[#888888] mt-1 leading-relaxed">
                                        Complete tasks with live timer synchronization, AI progress checks, and automated rubric evaluations directly inside Visual Studio Code.
                                    </p>
                                </div>
                            </div>

                            <div class="flex items-center gap-2.5 flex-shrink-0">
                                <a href="{{ asset('downloads/certicode-labs.vsix') }}" download class="inline-flex items-center px-3.5 py-2 rounded-[6px] border border-[#2e2e2e] bg-[#171717] hover:bg-[#222222] hover:border-[#383838] text-xs font-semibold text-[#ededed] transition-colors">
                                    <svg class="w-3.5 h-3.5 mr-1.5 text-[#3ecf8e]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                                    Download Extension (.vsix)
                                </a>
                            </div>
                        </div>

                        <!-- Quick Instructions Accordion -->
                        <div class="mt-4 pt-3 border-t border-[#232323] flex flex-col md:flex-row gap-4 text-xs font-mono text-[#888888]">
                            <div class="flex items-center space-x-2">
                                <span class="w-4 h-4 rounded-[4px] bg-[#171717] border border-[#2e2e2e] text-[#ededed] font-bold flex items-center justify-center text-[10px]">1</span>
                                <span>Install: VS Code &rarr; Extensions (<kbd class="px-1 py-0.5 rounded bg-[#171717] text-[10px] border border-[#2e2e2e] text-[#ededed]">Ctrl+Shift+X</kbd>) &rarr; <strong class="text-[#ededed]">Install from VSIX</strong></span>
                            </div>
                            <div class="flex items-center space-x-2">
                                <span class="w-4 h-4 rounded-[4px] bg-[#171717] border border-[#2e2e2e] text-[#ededed] font-bold flex items-center justify-center text-[10px]">2</span>
                                <span>Launch: Connect automatically via <code class="text-[#3ecf8e]">vscode://</code> or click extension in sidebar</span>
                            </div>
                        </div>

                        <div class="mt-3 p-3 rounded-[6px] bg-[#f8fafc] dark:bg-[#101010] border border-[#e2e8f0] dark:border-[#262626] text-[11px] font-mono text-[#334155] dark:text-[#a3a3a3] flex items-start gap-2.5">
                            <svg class="w-4 h-4 text-[#059669] dark:text-[#3ecf8e] flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            <span><strong class="text-[#0f172a] dark:text-[#ededed]">Workspace Tip:</strong> To persist your code on disk while working in VS Code, open an empty folder (<em class="text-[#0f172a] dark:text-[#ededed]">File &rarr; Open Folder</em>) before launching, or click any starter file in the CertiCode sidebar or use the <strong class="text-[#059669] dark:text-[#3ecf8e]">Download Starter File</strong> button above.</span>
                        </div>

                        @if($activeSession)
                            <div class="mt-3 pt-3 border-t border-[#232323] text-[11px] font-mono text-[#888888] flex flex-wrap items-center gap-x-4 gap-y-1">
                                <span class="text-[#666666]">Manual session fallback:</span>
                                <span>Session ID: <strong class="text-[#3ecf8e]">{{ $activeSession->id }}</strong></span>
                                <span>Endpoint: <strong class="text-[#ededed]">{{ request()->getSchemeAndHttpHost() }}</strong></span>
                            </div>
                        @endif
                    </div>
                </div>

                <div class="border-t border-[#232323] pt-6 flex justify-between items-center flex-wrap gap-4">
                    <a href="{{ $backUrl }}" class="px-4 py-2.5 border border-[#2e2e2e] text-xs font-mono uppercase tracking-wider rounded-[6px] text-[#a3a3a3] bg-[#171717] hover:bg-[#222222] hover:text-[#ededed] transition-colors">
                        &larr; Back to Module
                    </a>

                @if($laboratory->isLiveLab() && $laboratory->isLiveNotStarted())
                    <div class="flex items-center gap-3">
                        <span class="text-xs font-mono text-amber-400">🔒 Waiting for instructor to open session</span>
                        <button type="button" disabled class="inline-flex items-center px-6 py-2.5 rounded-full bg-[#262626] text-xs font-semibold text-[#666666] cursor-not-allowed border border-[#333]">
                            Lab Locked &rarr;
                        </button>
                    </div>
                @elseif($laboratory->isLiveLab() && $laboratory->isLiveClosed())
                    <div class="flex items-center gap-3">
                        <span class="text-xs font-mono text-red-400">⏱️ Live Countdown Expired</span>
                        <button type="button" disabled class="inline-flex items-center px-6 py-2.5 rounded-full bg-[#262626] text-xs font-semibold text-[#666666] cursor-not-allowed border border-[#333]">
                            Session Closed &rarr;
                        </button>
                    </div>
                @else
                    <form id="start-lab-form" action="{{ route('laboratories.start', $laboratory->id) }}" method="POST">
                        @csrf
                        <input type="hidden" name="camera_verified" :value="cameraVerified ? 1 : 0">
                        <button type="button" 
                                @click="handleStartClick()"
                                class="inline-flex items-center px-6 py-2.5 rounded-full bg-[#3ecf8e] text-xs font-semibold text-[#0f0f0f] hover:bg-[#00c573] transition shadow-sm cursor-pointer">
                            <span x-show="!browserProctorActive">{{ $activeSession ? 'Resume Lab in VS Code' : 'Start Lab in VS Code' }} &rarr;</span>
                            <span x-show="browserProctorActive" style="display: none;">Switch to VS Code Workspace &rarr;</span>
                        </button>
                    </form>

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
                                To ensure academic integrity, CertiCode Labs requires camera permission and facial presence verification using SsdMobilenetv1 before unlocking your workspace.
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
                                    <span class="text-xs font-mono text-cyan-300">Loading SsdMobilenetv1 Model...</span>
                                    <span class="text-[11px] text-[#666666] mt-1">Self-hosted client-side verification</span>
                                </div>

                                <div x-show="status === 'denied'" class="absolute inset-0 bg-red-950/90 border border-red-500/50 flex flex-col items-center justify-center p-4 text-center">
                                    <svg class="w-8 h-8 text-red-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                    <span class="text-xs font-bold text-red-200 uppercase tracking-wide">Camera Access Denied (Hard Block)</span>
                                    <span class="text-[11px] text-red-300/90 mt-1 max-w-xs leading-relaxed" x-text="errorMessage"></span>
                                </div>

                                <div x-show="status === 'analyzing'" class="absolute bottom-2 left-2 right-2 bg-black/80 backdrop-blur-sm px-3 py-2 rounded text-[11px] font-mono text-cyan-300 flex items-center justify-between border border-cyan-500/30">
                                    <span class="flex items-center gap-2">
                                        <span class="w-2 h-2 rounded-full bg-cyan-400 animate-ping"></span>
                                        Running SsdMobilenetv1 Face Detection...
                                    </span>
                                    <span class="text-xs text-[#a3a3a3]" x-text="`Attempt ${attempts + 1} of ${maxAttempts}`"></span>
                                </div>

                                <div x-show="status === 'failed'" class="absolute bottom-2 left-2 right-2 bg-amber-950/95 border border-amber-500/60 px-3 py-2 rounded text-[11px] font-mono text-amber-200 flex items-center justify-between">
                                    <span x-text="errorMessage"></span>
                                    <button type="button" @click="runAiPresenceValidation()" class="underline font-bold text-amber-400 hover:text-white ml-2">Retry</button>
                                </div>

                                <div x-show="status === 'hard_blocked'" class="absolute inset-0 bg-red-950/95 border border-red-500/80 flex flex-col items-center justify-center p-4 text-center">
                                    <svg class="w-10 h-10 text-red-400 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path></svg>
                                    <span class="text-xs font-bold text-red-200 uppercase tracking-wide">Verification Failed (Hard Block)</span>
                                    <span class="text-[11px] text-red-300/90 mt-1.5 max-w-sm leading-relaxed" x-text="errorMessage"></span>
                                    <span class="text-[10px] font-mono text-red-400 mt-2">Incident logged to instructor integrity logs.</span>
                                </div>

                                <div x-show="status === 'verified'" class="absolute inset-0 bg-emerald-950/90 border border-emerald-500/60 flex flex-col items-center justify-center p-4 text-center">
                                    <div class="w-10 h-10 rounded-full bg-emerald-500/20 text-emerald-400 flex items-center justify-center text-xl font-bold mb-2">✓</div>
                                    <span class="text-xs font-bold text-emerald-200">AI Presence Verified (1 Face Detected)</span>
                                    <span class="text-[11px] text-emerald-300/80 mt-1">Unlocking workspace and launching VS Code...</span>
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
                                            class="px-4 py-2 rounded-lg bg-[#3ecf8e] text-[#0f0f0f] text-xs font-bold hover:bg-[#00c573] transition">
                                        <span x-text="`Retry Face Check (${attempts + 1}/${maxAttempts})`"></span>
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
                @endif
            </div>
        </div>
        @else
            <!-- Instructor actions & Live Lab lifecycle controls (Feature 9) -->
            <div class="border-t border-[#232323] pt-6 flex justify-between items-center flex-wrap gap-3">
                <a href="{{ $backUrl }}" class="px-4 py-2.5 border border-[#2e2e2e] text-xs font-mono uppercase tracking-wider rounded-[6px] text-[#a3a3a3] bg-[#171717] hover:bg-[#222222] hover:text-[#ededed] transition-colors">
                    &larr; Back to Course
                </a>

                <div class="flex items-center gap-3 flex-wrap">
                    @if($laboratory->isLiveLab())
                        @if($laboratory->isLiveNotStarted())
                            <form action="{{ route('laboratories.open-live', $laboratory->id) }}" method="POST" class="inline-flex items-center gap-2">
                                @csrf
                                <div class="flex items-center gap-1.5 bg-[#141414] border border-[#2e2e2e] rounded-full px-3 py-1.5">
                                    <span class="text-[11px] font-mono text-[#888]">Window:</span>
                                    <input type="number" name="duration_minutes" value="{{ $laboratory->live_duration_minutes ?? $laboratory->time_limit ?? 60 }}" min="1" max="600" class="w-14 bg-transparent text-xs font-mono text-[#ededed] focus:outline-none" title="Duration in minutes">
                                    <span class="text-[11px] font-mono text-[#888]">min</span>
                                </div>
                                <button type="submit" class="inline-flex items-center px-5 py-2.5 rounded-full bg-[#3ecf8e] text-xs font-semibold text-[#0f0f0f] hover:bg-[#00c573] transition shadow-sm">
                                    ▶️ Open Live Lab &rarr;
                                </button>
                            </form>
                        @elseif($laboratory->isLiveActive())
                            <form action="{{ route('laboratories.end-live', $laboratory->id) }}" method="POST" onsubmit="return confirm('End this live lab now? All in-progress student workspaces will be auto-submitted and assessed.');">
                                @csrf
                                <button type="submit" class="inline-flex items-center px-4 py-2.5 rounded-full bg-red-600/20 hover:bg-red-600/30 text-red-400 border border-red-500/30 text-xs font-semibold transition">
                                    ⏹️ End Live Lab (Auto-Submit All)
                                </button>
                            </form>
                        @else
                            @if($laboratory->getRemainingLiveSeconds() > 0)
                                <form action="{{ route('laboratories.reopen-live', $laboratory->id) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="inline-flex items-center px-4 py-2.5 rounded-full bg-amber-500/20 hover:bg-amber-500/30 text-amber-300 border border-amber-500/30 text-xs font-semibold transition" title="Carries forward remaining time only">
                                        🔄 Reopen Live Lab ({{ max(1, (int) ceil($laboratory->getRemainingLiveSeconds() / 60)) }}m left)
                                    </button>
                                </form>
                            @else
                                <form action="{{ route('laboratories.reopen-live', $laboratory->id) }}" method="POST" class="inline-flex items-center gap-2">
                                    @csrf
                                    <input type="number" name="extend_minutes" value="15" min="1" max="180" class="w-16 px-2.5 py-1.5 bg-[#141414] border border-[#2e2e2e] text-xs font-mono rounded text-[#ededed]" title="Extend duration in minutes">
                                    <button type="submit" class="inline-flex items-center px-4 py-2.5 rounded-full bg-[#1e1e1e] hover:bg-[#282828] text-xs text-[#ededed] border border-[#333] transition">
                                        🔄 Extend & Reopen (+min)
                                    </button>
                                </form>
                            @endif
                        @endif
                    @endif

                    <a href="{{ route('instructor.monitoring.show', $laboratory->id) }}" class="inline-flex items-center px-5 py-2.5 rounded-full bg-slate-800 hover:bg-slate-700 text-xs font-semibold text-white border border-slate-700 transition">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse mr-2"></span>
                        Live Student Monitoring &rarr;
                    </a>
                    <a href="{{ route('laboratories.edit', $laboratory->id) }}" class="inline-flex items-center px-5 py-2.5 rounded-full bg-[#3ecf8e] text-xs font-semibold text-[#0f0f0f] hover:bg-[#00c573] transition">
                        Edit Specifications &rarr;
                    </a>
                </div>
            </div>
        @endif
    </div>
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

        // Continuous in-browser proctoring & heartbeat states
        browserProctorActive: false,
        proctorStatus: 'idle', // 'idle', 'normal', 'absence', 'multiple_faces', 'disconnected'
        proctorStatusText: 'Face Present (Normal)',
        vscodeUrl: '',
        pingCount: 0,
        absenceCount: 0,
        lastAbsenceAlertAt: 0,
        lastMultiFaceAlertAt: 0,
        proctorInterval: null,
        pingInterval: null,

        init() {
            // Log focus / tab switch telemetry when in active proctor mode
            document.addEventListener('visibilitychange', () => {
                if (this.browserProctorActive && this.activeSessionId) {
                    if (document.hidden) {
                        this.logTelemetry('tab_switch', { event: 'tab_hidden', timestamp: new Date().toISOString() });
                    } else {
                        this.logTelemetry('tab_switch', { event: 'tab_visible', timestamp: new Date().toISOString() });
                    }
                }
            });

            // Prevent accidental tab closing during live proctored lab
            window.addEventListener('beforeunload', (e) => {
                if (this.browserProctorActive) {
                    e.preventDefault();
                    e.returnValue = 'Live camera proctoring is active for your laboratory session. Leaving or closing this tab will disconnect camera proctoring and notify your instructor.';
                    return e.returnValue;
                }
            });
        },

        handleStartClick() {
            if (this.browserProctorActive && this.vscodeUrl) {
                window.location.href = this.vscodeUrl;
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
                if (window.faceapi && window.faceapi.nets && window.faceapi.nets.ssdMobilenetv1) {
                    await window.faceapi.nets.ssdMobilenetv1.loadFromUri('/models');
                    this.modelLoaded = true;
                }
            } catch (err) {
                console.error('Failed to load SsdMobilenetv1 model from /models:', err);
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
            const ctx = canvas.getContext('2d');
            ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
            const imageBase64 = canvas.toDataURL('image/jpeg', 0.7);

            this.status = 'analyzing';

            let detectedFaces = 0;
            if (window.faceapi && this.modelLoaded) {
                try {
                    const detections = await window.faceapi.detectAllFaces(
                        video,
                        new window.faceapi.SsdMobilenetv1Options({ minConfidence: 0.5 })
                    );
                    detectedFaces = detections.length;
                } catch (e) {
                    console.error('face-api SsdMobilenetv1 detection error:', e);
                    if ('FaceDetector' in window) {
                        try {
                            const detector = new window.FaceDetector({ fastMode: false });
                            const faces = await detector.detect(video);
                            detectedFaces = faces.length;
                        } catch (err) {
                            detectedFaces = 0;
                        }
                    }
                }
            } else if ('FaceDetector' in window) {
                try {
                    const detector = new window.FaceDetector({ fastMode: false });
                    const faces = await detector.detect(video);
                    detectedFaces = faces.length;
                } catch (e) {
                    detectedFaces = 0;
                }
            }

            this.faceCount = detectedFaces;

            if (detectedFaces === 1) {
                const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';
                const headers = {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {})
                };
                const verifyPayload = JSON.stringify({
                    status: 'granted',
                    face_count: 1,
                    image_base64: imageBase64
                });

                const endpoints = this.activeSessionId 
                    ? [`/api/v1/sessions/${this.activeSessionId}/verify-camera`, `/v1/sessions/${this.activeSessionId}/verify-camera`]
                    : [`/api/v1/labs/${labId}/verify-camera`, `/v1/labs/${labId}/verify-camera`];

                for (const url of endpoints) {
                    try {
                        const res = await fetch(url, {
                            method: 'POST',
                            headers: headers,
                            body: verifyPayload
                        });
                        if (res.ok) break;
                    } catch (e) {
                        console.warn(`Verify endpoint ${url} network warning:`, e);
                    }
                }

                this.status = 'verified';
                this.cameraVerified = true;
                setTimeout(() => {
                    this.launchLab();
                }, 1000);
            } else {
                this.attempts++;
                if (detectedFaces > 1) {
                    this.errorMessage = `Multiple faces detected (${detectedFaces}). Only one person is permitted in the webcam frame.`;
                } else {
                    this.errorMessage = `No face detected. Please reposition yourself directly in front of the camera with adequate lighting.`;
                }

                if (this.attempts >= this.maxAttempts) {
                    this.status = 'hard_blocked';
                    this.errorMessage = `Pre-lab facial verification failed (${this.maxAttempts} of ${this.maxAttempts} attempts). No face was detected. Please contact your instructor. Workspace remains locked.`;

                    // Report failure snapshot to backend
                    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';
                    const headers = {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {})
                    };

                    try {
                        if (this.activeSessionId) {
                            await fetch(`/api/v1/sessions/${this.activeSessionId}/telemetry`, {
                                method: 'POST',
                                headers: headers,
                                body: JSON.stringify({
                                    event_type: 'prelab_verification_failed',
                                    payload: {
                                        attempts: this.attempts,
                                        face_count: detectedFaces,
                                        image_base64: imageBase64,
                                        timestamp: new Date().toISOString()
                                    }
                                })
                            });
                        }

                        const endpoints = this.activeSessionId 
                            ? [`/api/v1/sessions/${this.activeSessionId}/verify-camera`, `/v1/sessions/${this.activeSessionId}/verify-camera`]
                            : [`/api/v1/labs/${labId}/verify-camera`, `/v1/labs/${labId}/verify-camera`];

                        for (const url of endpoints) {
                            try {
                                const res = await fetch(url, {
                                    method: 'POST',
                                    headers: headers,
                                    body: JSON.stringify({
                                        status: 'failed',
                                        face_count: detectedFaces,
                                        image_base64: imageBase64
                                    })
                                });
                                if (res.ok) break;
                            } catch (e) {}
                        }
                    } catch (e) {
                        console.error('Failed to log pre-lab verification failure', e);
                    }
                } else {
                    this.status = 'failed';
                }
            }
        },

        async launchLab() {
            if (!this.cameraVerified) {
                console.warn('Cannot launch lab workspace without verified camera presence.');
                return;
            }
            this.status = 'verified';
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '{{ csrf_token() }}';

            try {
                const res = await fetch(`{{ route('laboratories.start', $laboratory->id) }}`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {})
                    },
                    body: JSON.stringify({ camera_verified: 1 })
                });

                if (res.ok) {
                    const data = await res.json();
                    if (data && data.vscode_url) {
                        this.activeSessionId = data.session_id || this.activeSessionId;
                        this.vscodeUrl = data.vscode_url;
                        this.browserProctorActive = true;
                        this.showModal = false;

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
                await this.logTelemetry('camera_absence', {
                    reason: 'Webcam video track disconnected in browser',
                    timestamp: new Date().toISOString()
                });
                return;
            }

            const video = this.$refs.proctorLiveVideo || this.$refs.videoEl;
            if (!video || video.readyState < 2) return;

            let detectedFaces = 0;
            if (window.faceapi && this.modelLoaded) {
                try {
                    const detections = await window.faceapi.detectAllFaces(
                        video,
                        new window.faceapi.SsdMobilenetv1Options({ minConfidence: 0.5 })
                    );
                    detectedFaces = detections.length;
                } catch (e) {
                    if ('FaceDetector' in window) {
                        try {
                            const detector = new window.FaceDetector({ fastMode: false });
                            const faces = await detector.detect(video);
                            detectedFaces = faces.length;
                        } catch (err) {
                            detectedFaces = 1;
                        }
                    } else {
                        detectedFaces = 1;
                    }
                }
            } else if ('FaceDetector' in window) {
                try {
                    const detector = new window.FaceDetector({ fastMode: false });
                    const faces = await detector.detect(video);
                    detectedFaces = faces.length;
                } catch (e) {
                    detectedFaces = 1;
                }
            } else {
                detectedFaces = 1;
            }

            this.faceCount = detectedFaces;
            const nowTime = Date.now();

            if (detectedFaces === 1) {
                this.absenceCount = 0;
                this.proctorStatus = 'normal';
                this.proctorStatusText = 'Face Present (Normal)';
            } else if (detectedFaces === 0) {
                this.absenceCount++;
                this.proctorStatus = 'absence';
                this.proctorStatusText = `No Face Detected (${this.absenceCount}x warning)`;

                if (this.absenceCount >= 2 && (nowTime - this.lastAbsenceAlertAt > 25000)) {
                    this.lastAbsenceAlertAt = nowTime;
                    const snapshot = this.captureSnapshot(video);
                    await this.logTelemetry('camera_absence', {
                        image_base64: snapshot,
                        face_count: 0,
                        consecutive_absences: this.absenceCount,
                        timestamp: new Date().toISOString()
                    });
                }
            } else {
                this.absenceCount = 0;
                this.proctorStatus = 'multiple_faces';
                this.proctorStatusText = `Multiple Faces Detected (${detectedFaces})`;

                if (nowTime - this.lastMultiFaceAlertAt > 30000) {
                    this.lastMultiFaceAlertAt = nowTime;
                    const snapshot = this.captureSnapshot(video);
                    await this.logTelemetry('webcam_check', {
                        image_base64: snapshot,
                        face_count: detectedFaces,
                        timestamp: new Date().toISOString()
                    });
                }
            }
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
                const res = await fetch(`/api/v1/sessions/${this.activeSessionId}/ping`, {
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
                        alert(`Session Notice: ${data.message || 'The lab session has concluded.'}`);
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
                await fetch(`/api/v1/sessions/${this.activeSessionId}/telemetry`, {
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
            }
        },

        stopProctoring() {
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
        },

        closeGate() {
            if (!this.browserProctorActive && this.stream) {
                this.stream.getTracks().forEach(t => t.stop());
                this.stream = null;
            }
            this.showModal = false;
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

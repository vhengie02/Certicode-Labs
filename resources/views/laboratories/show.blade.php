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
        <div class="flex items-center justify-between mb-6">
            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[11px] font-mono uppercase tracking-wider {{ $laboratory->is_group_lab ? 'bg-[#141414] text-[#ededed] border border-[#2e2e2e]' : 'bg-[#141414] text-[#3ecf8e] border border-[#3ecf8e]/30' }}">
                {{ $laboratory->is_group_lab ? 'Group Laboratory' : 'Individual Laboratory' }}
            </span>
            
            <div class="flex items-center text-[#888888] text-xs font-mono">
                <svg class="w-4 h-4 mr-1.5 text-[#3ecf8e]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                TIME LIMIT: {{ $laboratory->time_limit }} MIN
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
                            <textarea x-model="codeSnippet" placeholder="// Paste code snippet here..." rows="3"
                                class="w-full px-3 py-1.5 bg-[#0e0e0e] border border-[#2e2e2e] rounded text-xs font-mono text-[#ededed] focus:outline-none focus:border-[#3ecf8e]"></textarea>
                        </template>
                        <div class="flex items-center space-x-2">
                            <button type="button" @click="showSnippetInput = !showSnippetInput" 
                                class="p-2 rounded bg-[#171717] border border-[#2e2e2e] text-[#888888] hover:text-[#3ecf8e] text-xs font-mono" title="Attach Code Snippet">
                                &lt;/&gt;
                            </button>
                            <input type="text" x-model="newMessage" placeholder="Type a message to teammates..." 
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

                    <div class="mt-3 p-3 rounded-[6px] bg-[#101010] border border-[#262626] text-[11px] font-mono text-[#a3a3a3] flex items-start gap-2.5">
                        <svg class="w-4 h-4 text-[#3ecf8e] flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <span><strong class="text-[#ededed]">Workspace Tip:</strong> To persist your code on disk while working in VS Code, open an empty folder (<em class="text-[#ededed]">File &rarr; Open Folder</em>) before launching, or click any starter file in the CertiCode sidebar or use the <strong class="text-[#3ecf8e]">Download Starter File</strong> button above.</span>
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

            <div class="border-t border-[#232323] pt-6 flex justify-between items-center">
                <a href="{{ $backUrl }}" class="px-4 py-2.5 border border-[#2e2e2e] text-xs font-mono uppercase tracking-wider rounded-[6px] text-[#a3a3a3] bg-[#171717] hover:bg-[#222222] hover:text-[#ededed] transition-colors">
                    &larr; Back to Module
                </a>

                @if($activeSession)
                    <form action="{{ route('laboratories.start', $laboratory->id) }}" method="POST">
                        @csrf
                        <button type="submit" class="inline-flex items-center px-6 py-2.5 rounded-full bg-[#3ecf8e] text-xs font-semibold text-[#0f0f0f] hover:bg-[#00c573] transition">
                            Resume Lab in VS Code &rarr;
                        </button>
                    </form>
                @else
                    <form action="{{ route('laboratories.start', $laboratory->id) }}" method="POST">
                        @csrf
                        <button type="submit" class="inline-flex items-center px-6 py-2.5 rounded-full bg-[#3ecf8e] text-xs font-semibold text-[#0f0f0f] hover:bg-[#00c573] transition">
                            Start Lab in VS Code &rarr;
                        </button>
                    </form>
                @endif
            </div>
        @else
            <!-- Instructor edit action -->
            <div class="border-t border-[#232323] pt-6 flex justify-between items-center flex-wrap gap-3">
                <a href="{{ $backUrl }}" class="px-4 py-2.5 border border-[#2e2e2e] text-xs font-mono uppercase tracking-wider rounded-[6px] text-[#a3a3a3] bg-[#171717] hover:bg-[#222222] hover:text-[#ededed] transition-colors">
                    &larr; Back to Course
                </a>
                <div class="flex items-center gap-3">
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
@endsection

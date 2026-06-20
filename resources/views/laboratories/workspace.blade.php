@extends('layouts.app')

@section('title', 'Laboratory Workspace')
@section('page_header')
    Active Session Workspace - {{ $session->laboratory->title }}
@endsection

@section('content')
<div class="h-[calc(100vh-8.5rem)] flex flex-col lg:flex-row gap-6 overflow-hidden -mt-4">
    <!-- Left Sidebar: Lab instructions & checklist -->
    <div class="w-full lg:w-80 flex flex-col glass-panel rounded-2xl border border-slate-800 p-5 overflow-y-auto shrink-0">
        <div class="mb-4">
            <span class="text-xs font-bold uppercase tracking-wider text-indigo-400">Workspace Details</span>
            <h2 class="text-xl font-bold text-white mt-1">{{ $session->laboratory->title }}</h2>
        </div>

        <!-- Timer -->
        <div class="glass-card p-4 rounded-xl border border-slate-800/80 text-center mb-6">
            <span class="text-xs font-semibold text-slate-400 block mb-1">Time Remaining</span>
            <span id="countdown-timer" class="text-3xl font-mono font-extrabold text-indigo-400 tracking-wider">
                00:00:00
            </span>
        </div>

        <!-- Tasks list checklist -->
        <div class="flex-1 space-y-4">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-500 block">Competency Tasks</span>
            <div class="space-y-2.5">
                @foreach($session->laboratory->tasks_definition as $task)
                    <div class="flex items-start p-3 rounded-xl bg-slate-900/50 border border-slate-800/60">
                        <input type="checkbox" id="task-chk-{{ $task['id'] }}" disabled
                               class="mt-1 h-4 w-4 text-indigo-600 bg-slate-900 border-slate-700 rounded focus:ring-indigo-500">
                        <label for="task-chk-{{ $task['id'] }}" class="ml-2.5 text-xs font-medium text-slate-300">
                            {{ $task['task'] }}
                        </label>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="mt-6 pt-4 border-t border-slate-800/50">
            <a href="{{ route('classes.index') }}" class="block text-center w-full py-2.5 border border-slate-700 text-sm font-semibold rounded-xl text-rose-400 bg-rose-500/5 hover:bg-rose-500/10 transition-colors">
                Abandon & Exit Lab
            </a>
        </div>
    </div>

    <!-- Center Panel: Code Editor & Web Terminal -->
    <div class="flex-1 flex flex-col gap-6 overflow-hidden">
        <!-- Text Code Editor -->
        <div class="flex-1 glass-panel rounded-2xl border border-slate-800 overflow-hidden flex flex-col min-h-0">
            <div class="px-5 py-3 border-b border-slate-800/80 bg-slate-900/40 flex items-center justify-between">
                <div class="flex items-center space-x-2">
                    <span class="w-3 h-3 rounded-full bg-rose-500"></span>
                    <span class="w-3 h-3 rounded-full bg-amber-500"></span>
                    <span class="w-3 h-3 rounded-full bg-emerald-500"></span>
                    <span class="text-xs font-semibold text-slate-400 pl-2">editor.sh - Interactive Bash script file</span>
                </div>

                <div class="flex items-center space-x-2">
                    <select id="editor-lang" class="bg-slate-800 border border-slate-700 text-slate-300 text-xs font-semibold rounded-lg px-2 py-1 focus:outline-none">
                        <option value="bash">Bash Script</option>
                        <option value="python">Python 3</option>
                        <option value="sql">PostgreSQL / SQL</option>
                    </select>

                    <button id="btn-execute" onclick="executeEditorCode()" class="inline-flex items-center px-3.5 py-1 border border-transparent text-xs font-bold rounded-lg text-white bg-indigo-600 hover:bg-indigo-700 transition-colors">
                        Execute Code
                    </button>
                </div>
            </div>

            <textarea id="code-editor" class="flex-1 p-5 bg-slate-950 font-mono text-sm text-slate-200 border-none outline-none resize-none focus:ring-0 leading-relaxed" 
                      placeholder="# Write your program or bash scripts here..."></textarea>
        </div>

        <!-- Terminal panel -->
        <div class="h-64 glass-panel rounded-2xl border border-slate-800 overflow-hidden flex flex-col shrink-0">
            <div class="px-5 py-2.5 border-b border-slate-800/80 bg-slate-900/60 flex items-center justify-between">
                <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Simulation PTY CLI Terminal</span>
                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-medium bg-emerald-500/10 text-emerald-400 border border-emerald-500/20">
                    Online
                </span>
            </div>

            <!-- Terminal Buffer -->
            <div id="terminal-buffer" class="flex-1 p-4 bg-slate-950 font-mono text-xs text-emerald-400 overflow-y-auto select-text leading-5">
                <div class="text-slate-400">Certicode Labs virtual environment shell. Type commands to verify.</div>
                <div>student@certicode-sandbox:~$ </div>
            </div>

            <!-- Terminal Command prompt input -->
            <div class="px-4 py-2 border-t border-slate-800 bg-slate-950 flex items-center">
                <span class="font-mono text-xs text-indigo-400 mr-2 shrink-0">student@certicode-sandbox:~$</span>
                <input type="text" id="terminal-input" onkeydown="handleTerminalCommand(event)" 
                       class="flex-1 bg-transparent border-none outline-none font-mono text-xs text-slate-200 focus:ring-0 p-0"
                       placeholder="Enter terminal command (e.g. ls -la, mkdir /var/www) or hit enter...">
            </div>
        </div>
    </div>

    <!-- Right Sidebar: AI Presence and anomaly monitoring panel -->
    <div class="w-full lg:w-72 flex flex-col glass-panel rounded-2xl border border-slate-800 p-5 overflow-y-auto shrink-0 gap-6">
        <div>
            <span class="text-xs font-bold uppercase tracking-wider text-rose-400">AI Integrity Core</span>
            <h3 class="text-lg font-bold text-white mt-1">Presence Detector</h3>
        </div>

        <!-- Webcam Feed component -->
        <div class="relative w-full aspect-video rounded-xl overflow-hidden bg-slate-950 border border-slate-800 shadow-inner group">
            <video id="webcam-stream" class="w-full h-full object-cover scale-x-[-1]" autoplay playsinline muted></video>
            <div id="camera-overlay" class="absolute inset-0 bg-slate-900/90 flex flex-col items-center justify-center p-4 text-center">
                <svg class="w-8 h-8 text-indigo-500 mb-2 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 10l4.553-2.276A1 1 0 0121 8.618v6.764a1 1 0 01-1.447.894L15 14M5 18h8a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"></path></svg>
                <span class="text-xs font-semibold text-slate-300">Camera Access Required</span>
                <button onclick="requestCameraAccess()" class="mt-2.5 px-3 py-1 bg-indigo-600 hover:bg-indigo-700 text-[10px] font-bold rounded-lg text-white transition-colors">
                    Grant Permission
                </button>
            </div>
        </div>

        <!-- AI Behavior Alert streams -->
        <div class="flex-1 space-y-4">
            <span class="text-xs font-bold uppercase tracking-wider text-slate-500 block">AI Stream Logs</span>
            <div id="ai-alert-stream" class="space-y-2 max-h-48 overflow-y-auto text-[11px] font-mono leading-relaxed text-slate-400">
                <div class="text-indigo-400">[SYSTEM] Session telemetry logging active.</div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script>
    const sessionId = "{{ $session->id }}";
    let timerDuration = {{ $session->laboratory->time_limit * 60 }};
    let tabSwitchCount = 0;

    // Countdown Timer logic
    function startTimer() {
        const timerText = document.getElementById('countdown-timer');
        const interval = setInterval(() => {
            if (timerDuration <= 0) {
                clearInterval(interval);
                timerText.textContent = "SESSION EXPIRED";
                timerText.classList.replace('text-indigo-400', 'text-rose-500');
                return;
            }

            timerDuration--;
            const hours = String(Math.floor(timerDuration / 3600)).padStart(2, '0');
            const minutes = String(Math.floor((timerDuration % 3600) / 60)).padStart(2, '0');
            const seconds = String(timerDuration % 60).padStart(2, '0');
            timerText.textContent = `${hours}:${minutes}:${seconds}`;
        }, 1000);
    }
    startTimer();

    // Webcam integration logic
    async function requestCameraAccess() {
        const video = document.getElementById('webcam-stream');
        const overlay = document.getElementById('camera-overlay');
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ video: true });
            video.srcObject = stream;
            overlay.classList.add('hidden');
            logEvent('[WEBCAM] Camera stream accessed successfully.');
            // Send webcam check telemetry payload
            postTelemetry('webcam_check', { face_count: 1 });
        } catch (error) {
            console.error('Camera access failed:', error);
            logEvent('[WEBCAM ERROR] Camera permission denied.', 'rose-400');
            postTelemetry('webcam_check', { face_count: 0 });
        }
    }

    // Telemetry AJAX sender
    function postTelemetry(eventType, payload = {}) {
        fetch(`/api/v1/sessions/${sessionId}/telemetry`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ event_type: eventType, payload: payload })
        })
        .then(res => res.json())
        .then(data => {
            if (eventType === 'tab_switch') {
                logEvent(`[ANOMALY] Tab switched. Logs saved to database.`, 'amber-400');
            }
        });
    }

    // Capture tab switching anomalies using Visibility API
    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            tabSwitchCount++;
            postTelemetry('tab_switch', { switch_count: tabSwitchCount });
        }
    });

    // Helper to log stream logs inside sidebar
    function logEvent(message, textColorClass = 'indigo-400') {
        const stream = document.getElementById('ai-alert-stream');
        const log = document.createElement('div');
        const now = new Date().toLocaleTimeString();
        log.className = `text-${textColorClass}`;
        log.textContent = `[${now}] ${message}`;
        stream.appendChild(log);
        stream.scrollTop = stream.scrollHeight;
    }

    // Simulated Terminal interface processing
    function handleTerminalCommand(event) {
        if (event.key !== 'Enter') return;

        const input = document.getElementById('terminal-input');
        const cmd = input.value.trim();
        if (!cmd) return;

        input.value = '';
        appendTerminalOutput(`student@certicode-sandbox:~$ ${cmd}`);

        // Post executing command to backend Sandbox api
        fetch(`/api/v1/sessions/${sessionId}/execute`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ code: cmd, language: 'bash' })
        })
        .then(res => res.json())
        .then(data => {
            if (data.errors) {
                appendTerminalOutput(data.errors, 'rose-400');
            } else {
                appendTerminalOutput(data.output, 'slate-200');
            }
            logEvent(`[TERMINAL] Executed: "${cmd.substring(0, 15)}..."`);
        });
    }

    // Code Editor Execution trigger
    function executeEditorCode() {
        const code = document.getElementById('code-editor').value;
        const lang = document.getElementById('editor-lang').value;
        const btn = document.getElementById('btn-execute');

        btn.disabled = true;
        btn.textContent = 'Executing...';

        appendTerminalOutput(`\n[COMPILE] Triggering compilation review on editor code...`);

        fetch(`/api/v1/sessions/${sessionId}/execute`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': '{{ csrf_token() }}'
            },
            body: JSON.stringify({ code: code, language: lang })
        })
        .then(res => res.json())
        .then(data => {
            btn.disabled = false;
            btn.textContent = 'Execute Code';
            
            if (data.errors) {
                appendTerminalOutput(`Compilation Error:\n${data.errors}`, 'rose-400');
            } else {
                appendTerminalOutput(data.output, 'emerald-400');
            }
            logEvent(`[EDITOR] Ran program file (${lang}).`);
        });
    }

    function appendTerminalOutput(text, textColorClass = 'emerald-400') {
        const buffer = document.getElementById('terminal-buffer');
        const outputLine = document.createElement('div');
        outputLine.className = `text-${textColorClass} whitespace-pre-line`;
        outputLine.textContent = text;
        buffer.appendChild(outputLine);
        buffer.scrollTop = buffer.scrollHeight;
    }
</script>
@endsection

@extends('layouts.app')

@section('title', $laboratory->title)
@section('page_header', 'Laboratory Exercise Specifications')

@section('content')
@php
    $backUrl = route('classes.index');
    if ($laboratory->module_id) {
        $module = \App\Models\Module::find($laboratory->module_id);
        if ($module) {
            $backUrl = route('modules.show', ['class_id' => $module->class_id, 'module_id' => $module->id]);
        }
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
                            <span>Launch: Connect automatically via <code class="text-[#3ecf8e]">vscode://</code></span>
                        </div>
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
            <div class="border-t border-[#232323] pt-6 flex justify-between items-center">
                <a href="{{ $backUrl }}" class="px-4 py-2.5 border border-[#2e2e2e] text-xs font-mono uppercase tracking-wider rounded-[6px] text-[#a3a3a3] bg-[#171717] hover:bg-[#222222] hover:text-[#ededed] transition-colors">
                    &larr; Back to Course
                </a>
                <a href="{{ route('laboratories.edit', $laboratory->id) }}" class="inline-flex items-center px-5 py-2.5 rounded-full bg-[#3ecf8e] text-xs font-semibold text-[#0f0f0f] hover:bg-[#00c573] transition">
                    Edit Specifications &rarr;
                </a>
            </div>
        @endif
    </div>
</div>
@endsection

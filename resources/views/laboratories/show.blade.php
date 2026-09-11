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
    <div class="glass-panel p-8 rounded-2xl border border-slate-800">
        <div class="flex items-center justify-between mb-6">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold {{ $laboratory->is_group_lab ? 'bg-indigo-500/10 text-indigo-400 border border-indigo-500/20' : 'bg-blue-500/10 text-blue-400 border border-blue-500/20' }}">
                {{ $laboratory->is_group_lab ? 'Group Laboratory' : 'Individual Laboratory' }}
            </span>
            
            <div class="flex items-center text-slate-400 text-sm font-semibold">
                <svg class="w-4 h-4 mr-1.5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                Time Limit: {{ $laboratory->time_limit }} minutes
            </div>
        </div>

        <h1 class="text-3xl font-extrabold text-white tracking-tight mb-4">{{ $laboratory->title }}</h1>
        
        <div class="prose prose-invert max-w-none text-slate-300 mb-8 leading-relaxed">
            <h3 class="text-lg font-bold text-white mb-2">Instructions:</h3>
            <p class="whitespace-pre-line">{{ $laboratory->description }}</p>
        </div>

        @if(!empty($laboratory->tasks_definition))
            <div class="border-t border-slate-800/80 pt-6 mb-8">
                <h3 class="text-sm font-bold uppercase tracking-wider text-blue-400 mb-4">Competency Tasks Checklist</h3>
                <div class="space-y-3">
                    @foreach($laboratory->tasks_definition as $task)
                        <div class="glass-card p-4 rounded-xl border border-slate-800/50 flex items-start space-x-3">
                            <span class="h-6 w-6 rounded-lg bg-blue-500/10 flex items-center justify-center text-blue-400 font-bold text-xs flex-shrink-0 mt-0.5 border border-blue-500/15">
                                {{ $task['id'] }}
                            </span>
                            <div>
                                <p class="text-sm font-medium text-slate-200">{{ $task['task'] }}</p>
                                @if(!empty($task['command']) && (auth()->user()->role === 'admin' || auth()->user()->role === 'instructor'))
                                    <code class="text-xs bg-slate-900 border border-slate-800 text-blue-400 font-mono px-2 py-1 rounded mt-1.5 inline-block">
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
            <div class="border-t border-slate-800/80 pt-6 mb-6">
                <div class="glass-card p-5 rounded-xl border border-blue-500/20 bg-gradient-to-r from-blue-950/30 to-indigo-950/20">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                        <div class="flex items-start space-x-3">
                            <div class="p-2.5 rounded-lg bg-blue-500/10 border border-blue-500/20 text-blue-400 flex-shrink-0 mt-0.5">
                                <svg class="w-6 h-6" viewBox="0 0 24 24" fill="currentColor">
                                    <path d="M23.15 2.587L18.21.21a1.494 1.494 0 0 0-1.705.29l-9.46 8.63-4.12-3.128a.999.999 0 0 0-1.276.057L.327 7.261A1 1 0 0 0 .32 8.704l4.28 3.297-4.28 3.296a1 1 0 0 0 .007 1.443l1.322 1.203c.365.332.91.355 1.276.057l4.12-3.128 9.46 8.63c.47.43 1.15.56 1.705.29l4.94-2.377A1.5 1.5 0 0 0 24 19.985V4.015a1.5 1.5 0 0 0-.85-1.428zM18 17.57l-7.464-5.57L18 6.43v11.14z"/>
                                </svg>
                            </div>
                            <div>
                                <h4 class="text-sm font-bold text-white flex items-center gap-2">
                                    CertiCode Labs VS Code Extension
                                    <span class="px-2 py-0.5 text-[10px] font-semibold tracking-wide uppercase rounded-full bg-blue-500/20 text-blue-300 border border-blue-500/30">Official IDE</span>
                                </h4>
                                <p class="text-xs text-slate-400 mt-1 leading-relaxed">
                                    Complete tasks with live timer sync, AI progress checks, and automated rubric evaluations directly inside Visual Studio Code.
                                </p>
                            </div>
                        </div>

                        <div class="flex items-center gap-2.5 flex-shrink-0">
                            <a href="{{ asset('downloads/certicode-labs.vsix') }}" download class="inline-flex items-center px-3.5 py-2 rounded-lg border border-slate-700 bg-slate-800 hover:bg-slate-700 text-xs font-semibold text-slate-200 transition-colors shadow-sm">
                                <svg class="w-4 h-4 mr-1.5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                                Download Extension (.vsix)
                            </a>
                        </div>
                    </div>

                    <!-- Quick Instructions Accordion -->
                    <div class="mt-4 pt-3 border-t border-slate-800/60 flex flex-col md:flex-row gap-4 text-xs text-slate-400">
                        <div class="flex items-center space-x-2">
                            <span class="w-5 h-5 rounded-full bg-slate-800 border border-slate-700 text-slate-300 font-bold flex items-center justify-center text-[10px]">1</span>
                            <span>Install: VS Code &rarr; Extensions (<kbd class="px-1 py-0.5 rounded bg-slate-800 text-[10px] border border-slate-700">Ctrl+Shift+X</kbd>) &rarr; <strong class="text-slate-300">···</strong> &rarr; <strong class="text-slate-300">Install from VSIX</strong></span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <span class="w-5 h-5 rounded-full bg-slate-800 border border-slate-700 text-slate-300 font-bold flex items-center justify-center text-[10px]">2</span>
                            <span>Launch: Click the button below to connect automatically via <code class="text-blue-400 font-mono">vscode://</code></span>
                        </div>
                    </div>

                    @if($activeSession)
                        <div class="mt-3 pt-3 border-t border-slate-800/60 text-[11px] text-slate-400 flex flex-wrap items-center gap-x-4 gap-y-1">
                            <span class="text-slate-500">Manual connect fallback:</span>
                            <span>Session ID: <strong class="text-white font-mono">{{ $activeSession->id }}</strong></span>
                            <span>Endpoint: <strong class="text-white font-mono">{{ request()->getSchemeAndHttpHost() }}</strong></span>
                        </div>
                    @endif
                </div>
            </div>

            <div class="border-t border-slate-800/80 pt-6 flex justify-between items-center">
                <a href="{{ $backUrl }}" class="px-5 py-2.5 border border-slate-700 text-sm font-semibold rounded-xl text-slate-300 bg-slate-800 hover:bg-slate-700 transition-colors">
                    Back to Module
                </a>

                @if($activeSession)
                    <form action="{{ route('laboratories.start', $laboratory->id) }}" method="POST">
                        @csrf
                        <button type="submit" class="inline-flex items-center px-6 py-3 border border-transparent text-sm font-semibold rounded-xl text-white bg-green-600 hover:bg-green-500 transition-all shadow-lg shadow-green-500/20">
                            Resume Lab in VS Code
                            <svg class="w-4 h-4 ml-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 9l3 3m0 0l-3 3m3-3H8m13 0a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        </button>
                    </form>
                @else
                    <form action="{{ route('laboratories.start', $laboratory->id) }}" method="POST">
                        @csrf
                        <button type="submit" class="inline-flex items-center px-6 py-3 border border-transparent text-sm font-semibold rounded-xl text-white bg-green-600 hover:bg-green-500 transition-all shadow-lg shadow-green-500/20">
                            Start Lab in VS Code
                            <svg class="w-4 h-4 ml-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path></svg>
                        </button>
                    </form>
                @endif
            </div>
        @else
            <!-- Instructor edit action -->
            <div class="border-t border-slate-800/80 pt-6 flex justify-between items-center">
                <a href="{{ $backUrl }}" class="px-5 py-2.5 border border-slate-700 text-sm font-semibold rounded-xl text-slate-300 bg-slate-800 hover:bg-slate-700 transition-colors">
                    Back to Course
                </a>
                <a href="{{ route('laboratories.edit', $laboratory->id) }}" class="inline-flex items-center px-5 py-2.5 border border-transparent text-sm font-semibold rounded-xl text-white bg-green-600 hover:bg-green-500 transition-colors shadow-lg shadow-green-500/20">
                    Edit Specifications
                </a>
            </div>
        @endif
    </div>
</div>
@endsection

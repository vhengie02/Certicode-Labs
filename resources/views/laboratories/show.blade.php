@extends('layouts.app')

@section('title', $laboratory->title)
@section('page_header', 'Laboratory Exercise Specifications')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <div class="glass-panel p-8 rounded-2xl border border-slate-800">
        <div class="flex items-center justify-between mb-6">
            <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-semibold {{ $laboratory->is_group_lab ? 'bg-indigo-500/10 text-indigo-400 border border-indigo-500/20' : 'bg-blue-500/10 text-blue-400 border border-blue-500/20' }}">
                {{ $laboratory->is_group_lab ? 'Group Laboratory' : 'Individual Laboratory' }}
            </span>
            
            <div class="flex items-center text-slate-400 text-sm font-semibold">
                <svg class="w-4 h-4 mr-1.5 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
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
                <h3 class="text-sm font-bold uppercase tracking-wider text-indigo-400 mb-4">Competency Tasks Checklist</h3>
                <div class="space-y-3">
                    @foreach($laboratory->tasks_definition as $task)
                        <div class="glass-card p-4 rounded-xl border border-slate-800/50 flex items-start space-x-3">
                            <span class="h-6 w-6 rounded-lg bg-indigo-500/10 flex items-center justify-center text-indigo-400 font-bold text-xs flex-shrink-0 mt-0.5 border border-indigo-500/15">
                                {{ $task['id'] }}
                            </span>
                            <div>
                                <p class="text-sm font-medium text-slate-200">{{ $task['task'] }}</p>
                                @if(!empty($task['command']) && (auth()->user()->role === 'admin' || auth()->user()->role === 'instructor'))
                                    <code class="text-xs bg-slate-900 border border-slate-800 text-indigo-400 font-mono px-2 py-1 rounded mt-1.5 inline-block">
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
            <div class="border-t border-slate-800/80 pt-6 flex justify-between items-center">
                <a href="{{ route('laboratories.index') }}" class="px-5 py-2.5 border border-slate-700 text-sm font-semibold rounded-xl text-slate-300 bg-slate-800 hover:bg-slate-700 transition-colors">
                    Back to Catalog
                </a>

                @if($activeSession)
                    <a href="{{ route('sessions.show', $activeSession->id) }}" class="inline-flex items-center px-6 py-3 border border-transparent text-sm font-semibold rounded-xl text-white bg-indigo-600 hover:bg-indigo-700 transition-colors shadow-lg shadow-indigo-600/30">
                        Resume Lab Workspace
                        <svg class="w-4 h-4 ml-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 9l3 3m0 0l-3 3m3-3H8m13 0a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </a>
                @else
                    <form action="{{ route('laboratories.start', $laboratory->id) }}" method="POST">
                        @csrf
                        <button type="submit" class="inline-flex items-center px-6 py-3 border border-transparent text-sm font-semibold rounded-xl text-white bg-gradient-to-r from-indigo-600 to-violet-600 hover:from-indigo-700 hover:to-violet-700 transition-all shadow-lg shadow-indigo-600/30">
                            Initialize & Start Lab
                            <svg class="w-4 h-4 ml-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.752 11.168l-3.197-2.132A1 1 0 0010 9.87v4.263a1 1 0 001.555.832l3.197-2.132a1 1 0 000-1.664z"></path></svg>
                        </button>
                    </form>
                @endif
            </div>
        @else
            <!-- Instructor edit action -->
            <div class="border-t border-slate-800/80 pt-6 flex justify-between items-center">
                <a href="{{ route('laboratories.index') }}" class="px-5 py-2.5 border border-slate-700 text-sm font-semibold rounded-xl text-slate-300 bg-slate-800 hover:bg-slate-700 transition-colors">
                    Back to Catalog
                </a>
                <a href="{{ route('laboratories.edit', $laboratory->id) }}" class="inline-flex items-center px-5 py-2.5 border border-transparent text-sm font-semibold rounded-xl text-white bg-indigo-600 hover:bg-indigo-700 transition-colors shadow-lg shadow-indigo-600/30">
                    Edit Specifications
                </a>
            </div>
        @endif
    </div>
</div>
@endsection

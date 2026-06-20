@extends('layouts.app')

@section('title', $module->title)
@section('page_header')
    {{ $class->name }} &gt; {{ $module->title }}
@endsection

@section('content')
<div class="h-[calc(100vh-8.5rem)] flex flex-col lg:flex-row gap-6 overflow-hidden -mt-4">
    
    <!-- LEFT PANEL: Modules Navigation Rail -->
    <div class="w-full lg:w-72 flex flex-col bg-[#1C2333] rounded-xl border border-slate-800 p-4 overflow-y-auto shrink-0 z-10">
        <div class="mb-4 pb-3 border-b border-slate-800/80">
            <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">Course Directory</span>
            <a href="{{ route('classes.show', $class->id) }}" class="text-sm font-bold text-white hover:text-blue-500 transition block mt-1 leading-tight">&larr; {{ $class->name }}</a>
        </div>

        <div class="flex-1 space-y-3 overflow-y-auto">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Modules list</span>
            <div class="space-y-1">
                @foreach($class->modules as $mod)
                    <a href="{{ route('modules.show', [$class->id, $mod->id]) }}" 
                       class="group flex items-center px-2 py-1.5 text-xs font-semibold rounded transition {{ $mod->id === $module->id ? 'bg-blue-600/15 border-l-2 border-blue-500 text-white' : 'text-slate-400 hover:text-white hover:bg-slate-800/40' }}">
                        <svg class="w-3.5 h-3.5 mr-2 shrink-0 {{ $mod->id === $module->id ? 'text-blue-500' : 'text-slate-500 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"></path></svg>
                        <span class="truncate">{{ $mod->title }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    </div>

    <!-- MAIN CENTER CONTENT: Module Lesson Material & Sandbox challenges -->
    <div class="flex-1 flex flex-col overflow-y-auto gap-6 focus:outline-none">
        
        <!-- Module Lesson Content Card -->
        <div class="glass-panel p-8 rounded-xl border border-slate-800 space-y-6">
            <div>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Theoretical Lesson</span>
                <h1 class="text-2xl font-extrabold text-white mt-1">{{ $module->title }}</h1>
                <p class="text-xs text-slate-500 mt-1 italic">{{ $module->description ?? 'No summary available.' }}</p>
            </div>

            <!-- Reading Content (Preserving whitespace-pre-line) -->
            <div class="prose prose-invert max-w-none text-slate-300 text-sm leading-relaxed whitespace-pre-line border-t border-slate-800/60 pt-6">
                {{ $module->content }}
            </div>
        </div>

        <!-- Associated Coding Challenges / Laboratories -->
        <div class="glass-panel p-6 rounded-xl border border-slate-800 space-y-4">
            <div>
                <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Competency Tasks</span>
                <h3 class="text-base font-bold text-white mt-0.5">Interactive Laboratory Coding Challenges</h3>
                <p class="text-xs text-slate-500 mt-1">Complete these challenges in the interactive coding terminal environment to pass the module.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 border-t border-slate-800/60 pt-4">
                @forelse($module->laboratories as $lab)
                    <div class="p-4 bg-slate-900 border border-slate-800 rounded-lg flex flex-col justify-between">
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <span class="px-2 py-0.5 rounded text-[10px] font-semibold {{ $lab->is_group_lab ? 'bg-indigo-500/10 text-indigo-400 border border-indigo-500/20' : 'bg-blue-500/10 text-blue-400 border border-blue-500/20' }}">
                                    {{ $lab->is_group_lab ? 'Group Lab' : 'Individual Lab' }}
                                </span>
                                <span class="text-slate-500 text-xs font-mono">{{ $lab->time_limit }} min</span>
                            </div>
                            <h4 class="font-bold text-white text-sm">{{ $lab->title }}</h4>
                            <p class="text-xs text-slate-400 mt-1.5 line-clamp-2 leading-relaxed">{{ $lab->description }}</p>
                        </div>

                        <div class="mt-4 pt-3 border-t border-slate-800/40 flex items-center justify-between">
                            <a href="{{ route('laboratories.show', $lab->id) }}" class="text-xs font-bold text-blue-500 hover:text-blue-400 transition-colors">
                                View Instructions &rarr;
                            </a>

                            @if(auth()->user()->role === 'student')
                                @php
                                    $activeSession = $lab->labSessions()
                                        ->where('user_id', auth()->id())
                                        ->where('status', 'in_progress')
                                        ->first();
                                @endphp

                                @if($activeSession)
                                    <a href="{{ route('sessions.show', $activeSession->id) }}" class="px-3 py-1.5 bg-green-600 hover:bg-green-500 text-xs font-bold rounded-lg text-white transition-colors">
                                        Resume Lab
                                    </a>
                                @else
                                    <form action="{{ route('laboratories.start', $lab->id) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="px-3 py-1.5 bg-green-600 hover:bg-green-500 text-xs font-bold rounded-lg text-white transition-colors">
                                            Start Lab
                                        </button>
                                    </form>
                                @endif
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="col-span-full py-6 text-center text-xs text-slate-500">
                        No coding challenges (laboratories) are currently assigned to this module.
                    </div>
                @endforelse
            </div>
        </div>

    </div>
</div>
@endsection

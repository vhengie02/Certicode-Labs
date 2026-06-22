@extends('layouts.app')

@section('title', $module->title)
@section('page_header')
    {{ $class->name }} &gt; {{ $module->title }}
@endsection

@section('content')
<div class="h-[calc(100vh-8.5rem)] flex flex-col lg:flex-row gap-6 overflow-hidden -mt-4">
    
    <!-- LEFT PANEL: NetAcad-Style Course outline & Resources tabs -->
    <div class="w-full lg:w-80 flex flex-col bg-[#161B22] rounded-xl border border-slate-800 shrink-0 overflow-hidden">
        <!-- Tabs Header -->
        <div class="flex border-b border-slate-800 bg-slate-950">
            <button id="tab-outline-btn" onclick="switchTab('outline')" class="flex-1 py-3 text-xs font-bold uppercase tracking-wider text-center border-b-2 border-indigo-500 text-white transition-all">
                Course Outline
            </button>
            <button id="tab-resources-btn" onclick="switchTab('resources')" class="flex-1 py-3 text-xs font-bold uppercase tracking-wider text-center border-b-2 border-transparent text-slate-400 hover:text-white transition-all">
                Resources ({{ $module->attachments->count() }})
            </button>
        </div>

        <!-- Tab 1: Course Outline with Search -->
        <div id="tab-outline-content" class="flex-1 flex flex-col p-4 overflow-y-auto space-y-4">
            <div class="relative">
                <input type="text" id="search-outline" oninput="filterOutline()" placeholder="Search course outline..." 
                       class="w-full pl-8 pr-3 py-1.5 bg-slate-950 border border-slate-800 rounded-lg text-xs text-white placeholder-slate-500 focus:outline-none focus:border-indigo-500">
                <div class="absolute left-2.5 top-2.5 text-slate-500">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
            </div>

            <div class="flex-1 overflow-y-auto space-y-3">
                <div class="pb-2 border-b border-slate-850">
                    <a href="{{ route('classes.show', $class->id) }}" class="text-xs font-semibold text-indigo-400 hover:underline flex items-center">
                        &larr; Back to Class Main Page
                    </a>
                </div>

                <div class="space-y-1" id="outline-list">
                    @foreach($class->modules->where('parent_id', null)->sortBy('order_index') as $mod)
                        <div class="outline-item space-y-1">
                            <a href="{{ route('modules.show', [$class->id, $mod->id]) }}" 
                               class="group flex items-center justify-between px-2 py-2 text-xs font-semibold rounded transition {{ $mod->id === $module->id ? 'bg-indigo-600/15 border-l-2 border-indigo-500 text-white' : 'text-slate-400 hover:text-white hover:bg-slate-800/40' }}">
                                <span class="flex items-center truncate">
                                    <svg class="w-3.5 h-3.5 mr-2 shrink-0 {{ $mod->id === $module->id ? 'text-indigo-400' : 'text-slate-500 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"></path></svg>
                                    <span class="truncate">{{ $mod->title }}</span>
                                </span>
                                @if(auth()->user()->role === 'student')
                                    @php
                                        $progress = $mod->getStudentProgress(auth()->user());
                                    @endphp
                                    @if($progress)
                                        <span class="px-1 py-0.2 rounded text-[8px] bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 font-mono shrink-0 ml-1">
                                            {{ $progress['completed'] }}/{{ $progress['total'] }}
                                        </span>
                                    @endif
                                @endif
                            </a>

                            <!-- Sub-modules -->
                            @if($mod->children->isNotEmpty())
                                <div class="pl-4 border-l border-slate-800 space-y-1 mt-1">
                                    @foreach($mod->children->sortBy('order_index') as $subMod)
                                        <a href="{{ route('modules.show', [$class->id, $subMod->id]) }}" 
                                           class="group flex items-center justify-between px-2 py-1.5 text-xs font-medium rounded transition {{ $subMod->id === $module->id ? 'bg-indigo-600/15 border-l-2 border-indigo-500 text-white' : 'text-slate-400 hover:text-white hover:bg-slate-800/40' }}">
                                            <span class="flex items-center truncate">
                                                <svg class="w-3 h-3 mr-1.5 shrink-0 {{ $subMod->id === $module->id ? 'text-indigo-400' : 'text-slate-500 group-hover:text-white' }}" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                                                <span class="truncate">{{ $subMod->title }}</span>
                                            </span>
                                            @if(auth()->user()->role === 'student')
                                                @php
                                                    $subProgress = $subMod->getStudentProgress(auth()->user());
                                                @endphp
                                                @if($subProgress)
                                                    <span class="px-1 py-0.2 rounded text-[8px] bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 font-mono shrink-0 ml-1">
                                                        {{ $subProgress['completed'] }}/{{ $subProgress['total'] }}
                                                    </span>
                                                @endif
                                            @endif
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Tab 2: Resources Download Area -->
        <div id="tab-resources-content" class="flex-1 p-4 overflow-y-auto space-y-4 hidden">
            <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">Downloadable Files</span>
            <div class="space-y-2">
                @forelse($module->attachments as $attachment)
                    <div class="p-3 bg-slate-900/50 border border-slate-800 rounded-lg flex flex-col space-y-2">
                        <div class="flex items-start space-x-2">
                            <svg class="w-4 h-4 text-indigo-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                            <div class="overflow-hidden">
                                <span class="block text-xs font-semibold text-white truncate" title="{{ $attachment->file_name }}">
                                    {{ $attachment->file_name }}
                                </span>
                                <span class="block text-[10px] text-slate-500">
                                    {{ number_format($attachment->file_size / (1024 * 1024), 2) }} MB
                                </span>
                            </div>
                        </div>
                        <a href="{{ route('attachments.download', $attachment->id) }}" class="w-full text-center py-1.5 bg-slate-800 hover:bg-slate-750 text-[10px] font-bold text-slate-200 rounded-md transition-colors block border border-slate-700">
                            Download File
                        </a>
                    </div>
                @empty
                    <div class="p-4 text-center text-xs text-slate-500 border border-slate-800 rounded-lg">
                        No downloadable resources uploaded for this module.
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- MAIN CENTER CONTENT: Module Lesson Material & Sandbox challenges -->
    <div class="flex-1 flex flex-col overflow-y-auto gap-6 focus:outline-none">
        
        <!-- Module Lesson Content Card -->
        <div class="glass-panel p-8 rounded-xl border border-slate-800 space-y-6 relative overflow-hidden">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_right,rgba(37,99,235,0.03),transparent)] pointer-events-none"></div>
            
            <div class="flex items-center justify-between border-b border-slate-800 pb-4">
                <div>
                    <span class="text-[10px] font-bold text-indigo-400 uppercase tracking-wider block">Theoretical Lesson</span>
                    <h1 class="text-2xl font-extrabold text-white mt-1">{{ $module->title }}</h1>
                    @if($module->description)
                        <p class="text-xs text-slate-400 mt-1 italic">{{ $module->description }}</p>
                    @endif
                </div>

                @if(auth()->user()->role === 'admin' || auth()->user()->role === 'instructor')
                    <div class="shrink-0">
                        <a href="{{ route('modules.edit', [$class->id, $module->id]) }}" class="px-3.5 py-1.5 bg-slate-800 hover:bg-slate-750 border border-slate-700 text-xs font-bold text-slate-300 hover:text-white rounded-lg transition-colors">
                            Edit Content
                        </a>
                    </div>
                @endif
            </div>

            <!-- Reading Content (Renders Rich HTML and custom tags) -->
            <div class="prose prose-invert max-w-none text-slate-200 text-sm leading-relaxed border-t border-transparent pt-2">
                {!! $module->content !!}
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
                    <div class="p-4 bg-slate-900 border border-slate-800 rounded-lg flex flex-col justify-between hover:border-slate-750 transition-colors">
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

        <!-- Next / Previous Lesson Navigation -->
        @php
            $allModules = $class->modules->sortBy('order_index')->values();
            $currentIndex = $allModules->search(fn($m) => $m->id === $module->id);
            $prevModule = $currentIndex > 0 ? $allModules[$currentIndex - 1] : null;
            $nextModule = $currentIndex < $allModules->count() - 1 ? $allModules[$currentIndex + 1] : null;
        @endphp
        
        <div class="flex items-center justify-between pt-4 border-t border-slate-800/80 mt-2 shrink-0">
            @if($prevModule)
                <a href="{{ route('modules.show', [$class->id, $prevModule->id]) }}" class="px-4 py-2 border border-slate-800 hover:border-slate-700 text-xs font-bold rounded-lg text-slate-300 hover:text-white transition">
                    &larr; Previous: {{ $prevModule->title }}
                </a>
            @else
                <div></div>
            @endif

            @if($nextModule)
                <a href="{{ route('modules.show', [$class->id, $nextModule->id]) }}" class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 text-xs font-bold rounded-lg text-white transition shadow-lg shadow-indigo-600/10">
                    Next: {{ $nextModule->title }} &rarr;
                </a>
            @else
                <a href="{{ route('classes.show', $class->id) }}" class="px-4 py-2 bg-green-600 hover:bg-green-500 text-xs font-bold rounded-lg text-white transition shadow-lg shadow-green-600/10">
                    Complete Course Syllabus
                </a>
            @endif
        </div>

    </div>
</div>
@endsection

@section('scripts')
<script>
    // Tab switcher logic
    function switchTab(tab) {
        const outlineBtn = document.getElementById('tab-outline-btn');
        const resourcesBtn = document.getElementById('tab-resources-btn');
        const outlineContent = document.getElementById('tab-outline-content');
        const resourcesContent = document.getElementById('tab-resources-content');

        if (tab === 'outline') {
            outlineBtn.classList.add('border-indigo-500', 'text-white');
            outlineBtn.classList.remove('border-transparent', 'text-slate-400');
            resourcesBtn.classList.remove('border-indigo-500', 'text-white');
            resourcesBtn.classList.add('border-transparent', 'text-slate-400');

            outlineContent.classList.remove('hidden');
            resourcesContent.classList.add('hidden');
        } else {
            resourcesBtn.classList.add('border-indigo-500', 'text-white');
            resourcesBtn.classList.remove('border-transparent', 'text-slate-400');
            outlineBtn.classList.remove('border-indigo-500', 'text-white');
            outlineBtn.classList.add('border-transparent', 'text-slate-400');

            resourcesContent.classList.remove('hidden');
            outlineContent.classList.add('hidden');
        }
    }

    // Client-side quick filter of outline links
    function filterOutline() {
        const searchInput = document.getElementById('search-outline');
        const query = searchInput.value.toLowerCase().trim();
        const items = document.querySelectorAll('#outline-list .outline-item');

        items.forEach(item => {
            const text = item.textContent.toLowerCase();
            if (text.includes(query)) {
                item.style.display = 'block';
            } else {
                item.style.display = 'none';
            }
        });
    }
</script>
@endsection

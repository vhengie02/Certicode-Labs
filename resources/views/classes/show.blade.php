@extends('layouts.app')

@section('title', $class->name)
@section('page_header')
    {{ $class->name }}
@endsection

@section('content')
<!-- Master Layout Split (Left Modules Tree + Main Grid) -->
<div class="h-[calc(100vh-8.5rem)] flex flex-col lg:flex-row gap-6 overflow-hidden -mt-4">
    
    <!-- LEFT PANEL: NetAcad Course Modules Navigation Tree -->
    <div class="w-full lg:w-72 flex flex-col bg-[#1C2333] rounded-xl border border-slate-800 p-4 overflow-y-auto shrink-0 z-10">
        <div class="mb-4 pb-3 border-b border-slate-800/80">
            <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">Course Outline</span>
            <h2 class="text-base font-bold text-white mt-1">{{ $class->name }}</h2>
            <span class="text-xs text-slate-400 mt-1 block">Instructor: {{ $class->instructor->name }}</span>
        </div>

        <!-- Modules Tree -->
        <div class="flex-1 space-y-3 overflow-y-auto">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Modules & Tasks</span>
            
            @forelse($class->modules as $mod)
                <!-- Module Node -->
                <div class="space-y-1.5">
                    <div class="flex items-center justify-between text-xs font-bold text-white px-2 py-1 bg-slate-900 border border-slate-800 rounded">
                        <span class="truncate flex items-center">
                            <svg class="w-3.5 h-3.5 mr-1.5 text-blue-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"></path></svg>
                            {{ $mod->title }}
                        </span>
                    </div>
                    
                    <div class="pl-3 space-y-1">
                        <!-- 1. Lesson Reading materials -->
                        <a href="{{ route('modules.show', [$class->id, $mod->id]) }}" class="flex items-center px-2 py-1 text-xs text-slate-400 hover:text-white rounded hover:bg-slate-800/40 transition">
                            <svg class="w-3.5 h-3.5 mr-1.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                            Study Module Lessons
                        </a>

                        <!-- 2. Coding Challenge / Laboratories inside module -->
                        @forelse($mod->laboratories as $lab)
                            <a href="{{ route('laboratories.show', $lab->id) }}" class="flex items-center justify-between px-2 py-1 text-xs text-slate-400 hover:text-white rounded hover:bg-slate-800/40 transition">
                                <span class="flex items-center truncate">
                                    <svg class="w-3.5 h-3.5 mr-1.5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path></svg>
                                    Lab: {{ $lab->title }}
                                </span>
                            </a>
                        @empty
                            <span class="text-[10px] text-slate-500 italic block pl-5">No laboratory exercises</span>
                        @endforelse
                    </div>
                </div>
            @empty
                <div class="p-4 rounded-lg bg-slate-900 border border-slate-800 text-center text-xs text-slate-500">
                    No course modules uploaded.
                </div>
            @endforelse
        </div>

        <!-- Instructor Module Addition Controls -->
        @if(auth()->user()->role === 'admin' || auth()->user()->role === 'instructor')
            <div class="mt-4 pt-4 border-t border-slate-800/80 space-y-2 shrink-0">
                <button onclick="document.getElementById('module-create-modal').classList.remove('hidden')" class="w-full py-2 bg-slate-900 hover:bg-slate-850 text-xs font-bold rounded-lg text-white border border-slate-800 transition">
                    + Add New Module
                </button>
                <a href="{{ route('laboratories.create', $class->id) }}" class="block text-center py-2 bg-green-600 hover:bg-green-500 text-xs font-bold rounded-lg text-white transition shadow-lg shadow-green-500/20">
                    + Add Lab Exercise
                </a>
            </div>
        @endif
    </div>

    <!-- MAIN CENTER PANELS: Overview & Roster -->
    <div class="flex-1 flex flex-col overflow-y-auto gap-6 focus:outline-none">
        
        <!-- Class Specification Card -->
        <div class="glass-panel p-6 rounded-xl border border-slate-800 space-y-4">
            <div class="flex items-center justify-between">
                <span class="px-2.5 py-0.5 rounded text-xs font-mono bg-slate-950 border border-slate-800 text-slate-400">
                    Class Join Code: {{ $class->code }}
                </span>
                <span class="text-xs text-slate-500">Created: {{ $class->created_at->format('M d, Y') }}</span>
            </div>
            
            <h1 class="text-xl font-bold text-white">{{ $class->name }}</h1>
            <p class="text-xs text-slate-400 leading-relaxed">{{ $class->description ?? 'No course syllabus description provided.' }}</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <!-- Invite Students Section -->
            @if(auth()->user()->role === 'admin' || auth()->user()->role === 'instructor')
                <div class="glass-panel p-6 rounded-xl border border-slate-800 space-y-4">
                    <div>
                        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Invite Roster</span>
                        <h3 class="text-sm font-bold text-white mt-0.5">Invite Student by Email</h3>
                    </div>
                    <form action="{{ route('classes.invite', $class->id) }}" method="POST" class="space-y-3">
                        @csrf
                        <div>
                            <label for="invite-email" class="block text-[10px] font-bold text-slate-400 uppercase tracking-wider mb-1.5">Student Gmail Address</label>
                            <input type="email" name="email" id="invite-email" required placeholder="student@gmail.com" 
                                   class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-xs text-white placeholder-slate-600 focus:outline-none focus:border-blue-500">
                        </div>
                        <button type="submit" class="w-full py-2 bg-green-600 hover:bg-green-500 text-xs font-bold rounded-lg text-white transition-colors shadow-lg shadow-green-500/20">
                            Send Invitation
                        </button>
                    </form>
                    <p class="text-[10px] text-slate-500 leading-relaxed">
                        Note: Once invited, this class will automatically populate inside the student's homepage dashboard upon their registration.
                    </p>
                </div>
            @endif

            <!-- Class Roster / Enrolled Student List -->
            <div class="glass-panel p-6 rounded-xl border border-slate-800 space-y-4 flex-1">
                <div>
                    <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Enrolled Roster</span>
                    <h3 class="text-sm font-bold text-white mt-0.5">Enrolled Student Directory</h3>
                </div>
                <div class="space-y-3 max-h-48 overflow-y-auto divide-y divide-slate-800/60">
                    @forelse($class->students as $student)
                        <div class="flex items-center justify-between text-xs py-2 first:pt-0">
                            <div class="flex items-center space-x-2">
                                <div class="h-6 w-6 rounded-full bg-slate-800 flex items-center justify-center text-[10px] text-indigo-400 border border-slate-700 font-bold">
                                    {{ strtoupper(substr($student->name, 0, 2)) }}
                                </div>
                                <span class="text-white font-medium">{{ $student->name }}</span>
                            </div>
                            <span class="px-2 py-0.5 rounded text-[10px] {{ $student->pivot->status === 'enrolled' ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20' : 'bg-amber-500/10 text-amber-400 border border-amber-500/20' }}">
                                {{ $student->pivot->status }}
                            </span>
                        </div>
                    @empty
                        <div class="p-3 text-center text-xs text-slate-500">
                            No students enrolled in this class roster yet.
                        </div>
                    @endforelse
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Modal: Create Module Form (Hidden by default) -->
<div id="module-create-modal" class="hidden fixed inset-0 bg-black/60 flex items-center justify-center z-50 p-4">
    <div class="bg-slate-900 border border-slate-800 w-full max-w-lg rounded-xl overflow-hidden shadow-2xl">
        <div class="px-6 py-4 border-b border-slate-800 bg-slate-950 flex items-center justify-between">
            <h3 class="font-bold text-white text-sm">Add New Learning Module</h3>
            <button onclick="document.getElementById('module-create-modal').classList.add('hidden')" class="text-slate-400 hover:text-white transition">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
            </button>
        </div>
        <form action="{{ route('modules.store', $class->id) }}" method="POST" class="p-6 space-y-4 text-left">
            @csrf
            <div>
                <label for="module-title" class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Module Title</label>
                <input type="text" name="title" id="module-title" required placeholder="e.g. Module 1: Variables & Operations" 
                       class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white focus:outline-none focus:border-blue-500">
            </div>
            <div>
                <label for="module-desc" class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Brief Summary</label>
                <input type="text" name="description" id="module-desc" placeholder="Overview of module content" 
                       class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white focus:outline-none focus:border-blue-500">
            </div>
            <div>
                <label for="module-content" class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Lesson Readings & Materials</label>
                <textarea name="content" id="module-content" required rows="6" placeholder="Write theoretical lessons, documentation, and sample guides for students here..." 
                          class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white focus:outline-none focus:border-blue-500 font-sans"></textarea>
            </div>
            <div>
                <label for="module-order" class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Order Index</label>
                <input type="number" name="order_index" id="module-order" required value="1" min="0" 
                       class="w-32 px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white focus:outline-none focus:border-blue-500">
            </div>

            <div class="flex justify-end space-x-3 pt-4 border-t border-slate-800/80">
                <button type="button" onclick="document.getElementById('module-create-modal').classList.add('hidden')" class="px-4 py-2 border border-slate-800 text-xs font-semibold rounded-lg text-slate-300 bg-slate-900 hover:bg-slate-800 transition">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-green-600 hover:bg-green-500 text-xs font-semibold rounded-lg text-white transition shadow-lg shadow-green-500/20">Save Module</button>
            </div>
        </form>
    </div>
</div>
@endsection

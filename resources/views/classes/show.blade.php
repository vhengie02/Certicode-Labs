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
            
            @forelse($class->modules->where('parent_id', null)->sortBy('order_index') as $mod)
                <!-- Module Node -->
                <div class="space-y-1.5">
                    <div class="flex items-center justify-between text-xs font-bold text-white px-2 py-1 bg-slate-900 border border-slate-800 rounded">
                        <span class="truncate flex items-center">
                            <svg class="w-3.5 h-3.5 mr-1.5 text-blue-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"></path></svg>
                            {{ $mod->title }}
                        </span>
                        @if(auth()->user()->role === 'student')
                            @php
                                $progress = $mod->getStudentProgress(auth()->user());
                            @endphp
                            @if($progress)
                                <span class="px-1.5 py-0.5 rounded text-[9px] bg-emerald-500/10 text-emerald-400 border border-emerald-500/20 font-mono shrink-0 ml-1.5">
                                    {{ $progress['completed'] }}/{{ $progress['total'] }} Done
                                </span>
                            @endif
                        @endif
                    </div>
                    
                    <div class="pl-3 space-y-1">
                        <!-- 1. Lesson Reading materials -->
                        <a href="{{ route('modules.show', [$class->id, $mod->id]) }}" class="flex items-center px-2 py-1 text-xs text-slate-400 hover:text-white rounded hover:bg-slate-800/40 transition">
                            <svg class="w-3.5 h-3.5 mr-1.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                            Study Module Lessons
                        </a>

                        <!-- 2. Coding Challenge / Laboratories inside module -->
                        @foreach($mod->laboratories as $lab)
                            @php
                                $isCompleted = false;
                                if (auth()->user()->role === 'student') {
                                    $isCompleted = $lab->labSessions->where('user_id', auth()->id())->where('status', 'completed')->isNotEmpty();
                                }
                            @endphp
                            <a href="{{ route('laboratories.show', $lab->id) }}" class="flex items-center justify-between px-2 py-1 text-xs text-slate-400 hover:text-white rounded hover:bg-slate-800/40 transition">
                                <span class="flex items-center truncate">
                                    <svg class="w-3.5 h-3.5 mr-1.5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path></svg>
                                    Lab: {{ $lab->title }}
                                </span>
                                @if(auth()->user()->role === 'student')
                                    @if($isCompleted)
                                        <svg class="w-3.5 h-3.5 text-emerald-500 shrink-0 ml-1.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                                    @else
                                        <span class="w-2.5 h-2.5 rounded-full border border-slate-600 shrink-0 ml-1.5"></span>
                                    @endif
                                @endif
                            </a>
                        @endforeach

                        <!-- Sub-Modules under this parent module -->
                        @if($mod->children->isNotEmpty())
                            <div class="pl-3 border-l border-slate-800 space-y-1.5 mt-2">
                                @foreach($mod->children->sortBy('order_index') as $subMod)
                                    <div class="space-y-1">
                                        <div class="flex items-center justify-between text-[11px] font-bold text-slate-300 px-2 py-0.5 bg-slate-900/40 border border-slate-800 rounded">
                                            <span class="truncate flex items-center">
                                                <svg class="w-3.5 h-3.5 mr-1 text-blue-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                                                {{ $subMod->title }}
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
                                        </div>
                                        <div class="pl-2 space-y-1">
                                            <a href="{{ route('modules.show', [$class->id, $subMod->id]) }}" class="flex items-center px-2 py-0.5 text-xs text-slate-400 hover:text-white rounded hover:bg-slate-800/40 transition">
                                                <svg class="w-3.5 h-3.5 mr-1.5 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                                                Study Lesson
                                            </a>
                                            @foreach($subMod->laboratories as $subLab)
                                                @php
                                                    $subLabCompleted = false;
                                                    if (auth()->user()->role === 'student') {
                                                        $subLabCompleted = $subLab->labSessions->where('user_id', auth()->id())->where('status', 'completed')->isNotEmpty();
                                                    }
                                                @endphp
                                                <a href="{{ route('laboratories.show', $subLab->id) }}" class="flex items-center justify-between px-2 py-0.5 text-xs text-slate-450 hover:text-white rounded hover:bg-slate-800/40 transition">
                                                    <span class="flex items-center truncate">
                                                        <svg class="w-3 h-3 mr-1 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path></svg>
                                                        Lab: {{ $subLab->title }}
                                                    </span>
                                                    @if(auth()->user()->role === 'student')
                                                        @if($subLabCompleted)
                                                            <svg class="w-3 h-3 text-emerald-500 shrink-0 ml-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path></svg>
                                                        @else
                                                            <span class="w-2 h-2 rounded-full border border-slate-600 shrink-0 ml-1"></span>
                                                        @endif
                                                    @endif
                                                </a>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @endif
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
                <a href="{{ route('modules.create', $class->id) }}" class="block text-center w-full py-2 bg-slate-900 hover:bg-slate-850 text-xs font-bold rounded-lg text-white border border-slate-800 transition">
                    + Add New Module
                </a>
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

        <!-- Course Content & Analytics Overview Card -->
        <div class="glass-panel p-6 rounded-xl border border-slate-800 space-y-4">
            <div>
                <span class="text-[10px] font-bold text-indigo-400 uppercase tracking-wider block">Course Content & Activity</span>
                <h3 class="text-sm font-bold text-white mt-0.5">Lessons & Labs Engagement</h3>
            </div>
            
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-800">
                    <thead>
                        <tr>
                            <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Item Title</th>
                            <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Type</th>
                            @if(auth()->user()->role !== 'student')
                                <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Views</th>
                                <th class="px-4 py-2.5 text-left text-xs font-semibold text-slate-400 uppercase tracking-wider">Submissions</th>
                            @endif
                            @if(auth()->user()->role === 'admin' || auth()->user()->role === 'instructor')
                                <th class="px-4 py-2.5 text-right text-xs font-semibold text-slate-400 uppercase tracking-wider">Actions</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800 bg-transparent text-slate-300">
                        @forelse($class->modules->where('parent_id', null)->sortBy('order_index') as $mod)
                            <!-- Module Row -->
                            <tr class="bg-slate-900/20 hover:bg-slate-900/40 transition-colors">
                                <td class="px-4 py-3 whitespace-nowrap text-xs font-semibold text-white">
                                    <a href="{{ route('modules.show', [$class->id, $mod->id]) }}" class="hover:text-blue-400 flex items-center">
                                        <svg class="w-3.5 h-3.5 mr-1.5 text-blue-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                                        {{ $mod->title }}
                                    </a>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap text-[10px] uppercase font-mono text-blue-400">Module</td>
                                @if(auth()->user()->role !== 'student')
                                    <td class="px-4 py-3 whitespace-nowrap text-xs font-mono text-slate-400">{{ $mod->views_count }} views</td>
                                    <td class="px-4 py-3 whitespace-nowrap text-xs text-slate-500 font-mono">-</td>
                                @endif
                                @if(auth()->user()->role === 'admin' || auth()->user()->role === 'instructor')
                                    <td class="px-4 py-3 whitespace-nowrap text-right text-xs space-x-2">
                                        <a href="{{ route('modules.edit', [$class->id, $mod->id]) }}" class="text-indigo-400 hover:text-indigo-300 font-semibold">Edit</a>
                                        <form action="{{ route('modules.destroy', [$class->id, $mod->id]) }}" method="POST" class="inline">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" onclick="return confirm('Are you sure you want to delete this module and its attachments?')" class="text-rose-400 hover:text-rose-350 font-semibold">Delete</button>
                                        </form>
                                    </td>
                                @endif
                            </tr>
                            
                            <!-- Lab Rows under this module -->
                            @foreach($mod->laboratories as $lab)
                                <tr class="hover:bg-slate-900/10 transition-colors">
                                    <td class="px-4 py-2.5 whitespace-nowrap text-xs text-slate-300 pl-8">
                                        <a href="{{ route('laboratories.show', $lab->id) }}" class="hover:text-emerald-400 flex items-center">
                                            <svg class="w-3.5 h-3.5 mr-1.5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path></svg>
                                            Lab: {{ $lab->title }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-2.5 whitespace-nowrap text-[10px] uppercase font-mono text-emerald-400 pl-4">Lab</td>
                                    @if(auth()->user()->role !== 'student')
                                        <td class="px-4 py-2.5 whitespace-nowrap text-xs font-mono text-slate-400">{{ $lab->views_count }} views</td>
                                        <td class="px-4 py-2.5 whitespace-nowrap text-xs font-mono text-emerald-400 font-semibold">
                                            {{ $lab->labSessions->where('status', 'completed')->count() }} completed
                                        </td>
                                    @endif
                                    @if(auth()->user()->role === 'admin' || auth()->user()->role === 'instructor')
                                        <td class="px-4 py-2.5 whitespace-nowrap text-right text-xs space-x-2">
                                            <a href="{{ route('laboratories.edit', $lab->id) }}" class="text-indigo-400 hover:text-indigo-300 font-semibold">Edit</a>
                                            <form action="{{ route('laboratories.destroy', $lab->id) }}" method="POST" class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" onclick="return confirm('Are you sure you want to delete this laboratory?')" class="text-rose-400 hover:text-rose-350 font-semibold">Delete</button>
                                            </form>
                                        </td>
                                    @endif
                                </tr>
                            @endforeach

                            <!-- Sub-modules under this parent module -->
                            @foreach($mod->children->sortBy('order_index') as $subMod)
                                <tr class="bg-slate-900/10 hover:bg-slate-900/30 transition-colors border-l-2 border-indigo-500/30">
                                    <td class="px-4 py-2.5 whitespace-nowrap text-xs font-medium text-slate-300 pl-8">
                                        <a href="{{ route('modules.show', [$class->id, $subMod->id]) }}" class="hover:text-blue-400 flex items-center">
                                            <svg class="w-3.5 h-3.5 mr-1.5 text-blue-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                                            Sub-Module: {{ $subMod->title }}
                                        </a>
                                    </td>
                                    <td class="px-4 py-2.5 whitespace-nowrap text-[10px] uppercase font-mono text-indigo-400/80 pl-4">Sub-Module</td>
                                    @if(auth()->user()->role !== 'student')
                                        <td class="px-4 py-2.5 whitespace-nowrap text-xs font-mono text-slate-400">{{ $subMod->views_count }} views</td>
                                        <td class="px-4 py-2.5 whitespace-nowrap text-xs text-slate-500 font-mono">-</td>
                                    @endif
                                    @if(auth()->user()->role === 'admin' || auth()->user()->role === 'instructor')
                                        <td class="px-4 py-2.5 whitespace-nowrap text-right text-xs space-x-2">
                                            <a href="{{ route('modules.edit', [$class->id, $subMod->id]) }}" class="text-indigo-400 hover:text-indigo-300 font-semibold">Edit</a>
                                            <form action="{{ route('modules.destroy', [$class->id, $subMod->id]) }}" method="POST" class="inline">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" onclick="return confirm('Are you sure you want to delete this sub-module and its attachments?')" class="text-rose-400 hover:text-rose-350 font-semibold">Delete</button>
                                            </form>
                                        </td>
                                    @endif
                                </tr>

                                <!-- Lab Rows under this sub-module -->
                                @foreach($subMod->laboratories as $subLab)
                                    <tr class="hover:bg-slate-900/10 transition-colors border-l-2 border-indigo-500/30">
                                        <td class="px-4 py-2 whitespace-nowrap text-xs text-slate-350 pl-14">
                                            <a href="{{ route('laboratories.show', $subLab->id) }}" class="hover:text-emerald-400 flex items-center">
                                                <svg class="w-3.5 h-3.5 mr-1.5 text-emerald-500 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"></path></svg>
                                                Lab: {{ $subLab->title }}
                                            </a>
                                        </td>
                                        <td class="px-4 py-2 whitespace-nowrap text-[10px] uppercase font-mono text-emerald-400/80 pl-4">Lab</td>
                                        @if(auth()->user()->role !== 'student')
                                            <td class="px-4 py-2 whitespace-nowrap text-xs font-mono text-slate-400">{{ $subLab->views_count }} views</td>
                                            <td class="px-4 py-2 whitespace-nowrap text-xs font-mono text-emerald-400 font-semibold">
                                                {{ $subLab->labSessions->where('status', 'completed')->count() }} completed
                                            </td>
                                        @endif
                                        @if(auth()->user()->role === 'admin' || auth()->user()->role === 'instructor')
                                            <td class="px-4 py-2 whitespace-nowrap text-right text-xs space-x-2">
                                                <a href="{{ route('laboratories.edit', $subLab->id) }}" class="text-indigo-400 hover:text-indigo-300 font-semibold">Edit</a>
                                                <form action="{{ route('laboratories.destroy', $subLab->id) }}" method="POST" class="inline">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" onclick="return confirm('Are you sure you want to delete this laboratory?')" class="text-rose-400 hover:text-rose-350 font-semibold">Delete</button>
                                                </form>
                                            </td>
                                        @endif
                                    </tr>
                                @endforeach
                            @endforeach
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-6 text-center text-xs text-slate-500">
                                    No modules uploaded in this class yet.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
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

@endsection

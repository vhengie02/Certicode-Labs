@extends('layouts.app')

@section('title', 'Classes')
@section('page_header', 'Classes Directory')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between space-y-4 md:space-y-0">
        <div>
            <h1 class="text-2xl font-extrabold text-white tracking-tight">Your Classes</h1>
            <p class="text-sm text-slate-400 mt-1">Access lessons, reading modules, and associated laboratory coding challenges.</p>
        </div>

        @if(auth()->user()->role === 'student')
            <!-- Student: Join Class by Code -->
            <form action="{{ route('classes.join') }}" method="POST" class="flex space-x-2 shrink-0">
                @csrf
                <input type="text" name="code" required placeholder="Enter Class Code (e.g. CLASS-XYZ)" 
                       class="px-4 py-2 bg-slate-900 border border-slate-800 rounded-lg text-xs text-white placeholder-slate-500 uppercase focus:outline-none focus:border-blue-500">
                <button type="submit" class="px-4 py-2 text-xs font-semibold rounded-lg text-white bg-green-600 hover:bg-green-500 transition-colors shadow-lg shadow-green-500/20">
                    Join Class
                </button>
            </form>
        @else
            <!-- Instructor / Admin: Create Class -->
            <a href="{{ route('classes.create') }}" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-semibold rounded-lg text-white bg-green-600 hover:bg-green-500 transition-colors shadow-lg shadow-green-500/20">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Create Class
            </a>
        @endif
    </div>

    <!-- Invitations Section (Student Only) -->
    @if(auth()->user()->role === 'student' && $invitedClasses->isNotEmpty())
        <div class="p-6 bg-green-600/10 border border-green-500/20 rounded-xl">
            <h2 class="text-sm font-bold text-green-400 uppercase tracking-wider mb-3 flex items-center">
                <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 19v-8.93a2 2 0 01.89-1.664l8-5.333a2 2 0 012.22 0l8 5.333A2 2 0 0121 10.07V19M3 19a2 2 0 002 2h14a2 2 0 002-2M3 19l6.75-4.5M21 19l-6.75-4.5M3 10l6.75 4.5M21 10l-6.75 4.5m0 0l-2.25-1.5a2 2 0 00-2.22 0l-2.25 1.5"></path></svg>
                Class Invitations from Teachers
            </h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                @foreach($invitedClasses as $class)
                    <div class="p-4 bg-slate-900 border border-slate-800 rounded-lg flex items-center justify-between">
                        <div>
                            <h3 class="font-bold text-white text-sm">{{ $class->name }}</h3>
                            <p class="text-xs text-slate-400 mt-1">Instructor: {{ $class->instructor->name }}</p>
                        </div>
                        <form action="{{ route('classes.invite-accept', $class->id) }}" method="POST">
                            @csrf
                            <button type="submit" class="px-3.5 py-1.5 bg-green-600 hover:bg-green-500 text-xs font-bold rounded-lg text-white transition-colors">
                                Accept invitation
                            </button>
                        </form>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    <!-- Classes Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($classes as $class)
            <div class="glass-card rounded-lg p-6 flex flex-col justify-between border border-slate-800">
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-mono bg-slate-800 text-slate-400 border border-slate-800">
                            {{ $class->code }}
                        </span>
                        
                        <div class="flex items-center text-slate-400 text-xs font-medium">
                            <svg class="w-4 h-4 mr-1 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                            {{ $class->modules()->count() }} Modules
                        </div>
                    </div>

                    <h3 class="text-base font-bold text-white mb-2">{{ $class->name }}</h3>
                    <p class="text-xs text-slate-400 line-clamp-3 mb-4 leading-relaxed">{{ $class->description ?? 'No description provided.' }}</p>

                    <div class="mt-4 pt-4 border-t border-slate-800/60">
                        <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500 block mb-2">Teacher</span>
                        <div class="flex items-center space-x-2 text-xs">
                            <div class="h-6 w-6 rounded-full bg-slate-800 flex items-center justify-center text-indigo-400 border border-slate-700 font-bold">
                                {{ strtoupper(substr($class->instructor->name ?? 'T', 0, 2)) }}
                            </div>
                            <span class="text-slate-300 font-medium">{{ $class->instructor->name ?? 'Unknown Teacher' }}</span>
                        </div>
                    </div>
                </div>

                <div class="mt-6 pt-4 border-t border-slate-800/60 flex items-center justify-between">
                    <a href="{{ route('classes.show', $class->id) }}" class="inline-flex items-center text-xs font-semibold text-blue-500 hover:text-blue-400 transition-colors">
                        Enter Class Room &rarr;
                    </a>

                    @if(auth()->user()->role === 'admin' || auth()->user()->role === 'instructor')
                        <div class="flex space-x-2">
                            <a href="{{ route('classes.edit', $class->id) }}" class="p-1.5 rounded bg-slate-800 border border-slate-800 text-slate-400 hover:text-white transition-all">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                            </a>
                            <form action="{{ route('classes.destroy', $class->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this class?');" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="p-1.5 rounded bg-rose-500/5 border border-rose-500/10 text-rose-400 hover:text-white hover:bg-rose-600 transition-all">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </button>
                            </form>
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="col-span-full glass-panel p-12 text-center rounded-lg">
                <svg class="w-10 h-10 text-slate-500 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                <h3 class="text-base font-bold text-white">No Classes Found</h3>
                <p class="text-xs text-slate-400 mt-1">You are not enrolled in any classes yet.</p>
            </div>
        @endforelse
    </div>
</div>
@endsection

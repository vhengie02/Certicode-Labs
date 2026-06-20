@extends('layouts.app')

@section('title', 'Laboratories')
@section('page_header', 'Laboratory Exercises Catalog')

@section('content')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-extrabold text-white tracking-tight">Available Laboratories</h1>
            <p class="text-sm text-slate-400 mt-1">Select a lab session to practice your technical skills in a sandboxed environment.</p>
        </div>

        @if(auth()->user()->role === 'admin' || auth()->user()->role === 'instructor')
            <a href="{{ route('laboratories.create') }}" class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-semibold rounded-xl text-white bg-indigo-600 hover:bg-indigo-700 transition-colors shadow-lg shadow-indigo-600/30">
                <svg class="w-4 h-4 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Create Laboratory
            </a>
        @endif
    </div>

    <!-- Laboratories Cards Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
        @forelse($laboratories as $lab)
            <div class="glass-card rounded-2xl p-6 flex flex-col justify-between border border-slate-800/80">
                <div>
                    <div class="flex items-center justify-between mb-4">
                        <span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-semibold {{ $lab->is_group_lab ? 'bg-indigo-500/10 text-indigo-400 border border-indigo-500/20' : 'bg-blue-500/10 text-blue-400 border border-blue-500/20' }}">
                            {{ $lab->is_group_lab ? 'Group Lab' : 'Individual Lab' }}
                        </span>
                        
                        <div class="flex items-center text-slate-400 text-xs font-medium">
                            <svg class="w-4 h-4 mr-1 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                            {{ $lab->time_limit }} min
                        </div>
                    </div>

                    <h3 class="text-lg font-bold text-white mb-2">{{ $lab->title }}</h3>
                    <p class="text-sm text-slate-400 line-clamp-3 mb-4 leading-relaxed">{{ $lab->description }}</p>

                    @if(!empty($lab->tasks_definition))
                        <div class="mb-4">
                            <span class="text-xs font-bold uppercase tracking-wider text-slate-500 block mb-2">Checklist Tasks</span>
                            <ul class="space-y-1.5">
                                @foreach(array_slice($lab->tasks_definition, 0, 3) as $task)
                                    <li class="flex items-start text-xs text-slate-300">
                                        <svg class="w-3.5 h-3.5 text-emerald-400 mr-2 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                        <span class="truncate">{{ $task['task'] }}</span>
                                    </li>
                                @endforeach
                                @if(count($lab->tasks_definition) > 3)
                                    <li class="text-xs text-indigo-400 font-semibold pl-5">+ {{ count($lab->tasks_definition) - 3 }} more tasks</li>
                                @endif
                            </ul>
                        </div>
                    @endif
                </div>

                <div class="mt-6 pt-4 border-t border-slate-800/60 flex items-center justify-between">
                    <a href="{{ route('laboratories.show', $lab->id) }}" class="inline-flex items-center text-sm font-semibold text-indigo-400 hover:text-indigo-300 transition-colors">
                        View Details &rarr;
                    </a>

                    @if(auth()->user()->role === 'admin' || auth()->user()->role === 'instructor')
                        <div class="flex space-x-2">
                            <a href="{{ route('laboratories.edit', $lab->id) }}" class="p-1.5 rounded-lg border border-slate-700 text-slate-400 hover:text-white hover:bg-slate-800 transition-all">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                            </a>
                            <form action="{{ route('laboratories.destroy', $lab->id) }}" method="POST" onsubmit="return confirm('Are you sure you want to delete this laboratory?');" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="p-1.5 rounded-lg border border-rose-500/20 text-rose-400 hover:text-white hover:bg-rose-600 transition-all">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </button>
                            </form>
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="col-span-full glass-panel p-12 text-center rounded-2xl">
                <svg class="w-12 h-12 text-slate-600 mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                <h3 class="text-lg font-bold text-white">No Laboratories Created Yet</h3>
                <p class="text-sm text-slate-400 mt-2">Get started by creating a new laboratory exercise.</p>
            </div>
        @endforelse
    </div>
</div>
@endsection

@extends('layouts.app')

@section('title', 'Classes')
@section('page_header', 'Classes Directory')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col md:flex-row md:items-center md:justify-between space-y-4 md:space-y-0">
        <div>
            <h1 class="text-2xl font-bold text-[#ededed] tracking-tight">Your Classes</h1>
            <p class="text-xs text-[#888888] mt-1 font-mono">Access modules, readings, and verified coding laboratory challenges.</p>
        </div>

        @if(auth()->user()->role === 'student')
            <!-- Student: classes come from the instructor's roster -->
            <p id="class-roster-note" class="max-w-sm text-xs text-[#888888] md:text-right">
                Your instructor adds you to their class roster with <span class="text-[#ededed] font-mono break-all">{{ auth()->user()->email }}</span>. Missing a class? Ask them to add that email.
            </p>
        @else
            <!-- Instructor / Admin: Create Class -->
            <a href="{{ route('classes.create') }}" class="inline-flex items-center px-5 py-2 rounded-full text-xs font-semibold text-[#0f0f0f] bg-[#3ecf8e] hover:bg-[#00c573] transition-colors shadow-none">
                <svg class="w-3.5 h-3.5 mr-1.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path></svg>
                Create Class
            </a>
        @endif
    </div>

    <!-- Classes Grid -->
    <div class="motion-rows grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        @forelse($classes as $class)
            <div class="relative overflow-hidden rounded-xl p-6 flex flex-col justify-between bg-[#171717] border border-[#2e2e2e] hover:border-[#3ecf8e]/35 transition-colors">
                @if ($class->card_image_path)
                    <img src="{{ $class->cardImageUrl() }}" alt="" class="class-card-photo">
                @else
                    <x-class-pattern :pattern="$class->cardPattern()" :id="'class-pattern-'.$class->id" />
                @endif

                <div class="relative">
                    <div class="flex items-center justify-end mb-16">
                        <div class="flex items-center px-2 py-1 rounded-full bg-[#141414] border border-[#2e2e2e] text-[#888888] text-xs font-mono">
                            <svg class="w-3.5 h-3.5 mr-1 text-[#3ecf8e]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                            {{ $class->modules_count ?? $class->modules()->count() }} Modules
                        </div>
                    </div>

                    <h3 class="text-base font-bold text-[#ededed] mb-2">{{ $class->name }}</h3>
                    <p class="text-xs text-[#888888] line-clamp-3 mb-4 leading-relaxed">{{ $class->description ?? 'No description provided.' }}</p>

                    <div class="mt-4 pt-4 border-t border-[#232323]">
                        <span class="text-[10px] font-mono uppercase tracking-wider text-[#666666] block mb-2">Teacher</span>
                        <div class="flex items-center space-x-2 text-xs">
                            <div class="h-6 w-6 rounded-[4px] bg-[#141414] flex items-center justify-center text-[10px] text-[#3ecf8e] border border-[#2e2e2e] font-mono font-bold">
                                {{ strtoupper(substr($class->instructor->name ?? 'T', 0, 2)) }}
                            </div>
                            <span class="text-[#a3a3a3] font-medium">{{ $class->instructor->name ?? 'Unknown Teacher' }}</span>
                        </div>
                    </div>
                </div>

                <div class="relative mt-6 pt-4 border-t border-[#232323] flex items-center justify-between">
                    <a href="{{ route('classes.show', $class->id) }}" class="inline-flex items-center text-xs font-semibold text-[#3ecf8e] hover:text-[#00c573] transition-colors">
                        Enter Classroom &rarr;
                    </a>

                    @if(auth()->user()->role === 'admin' || auth()->user()->role === 'instructor')
                        <div class="flex space-x-2">
                            <a href="{{ route('classes.edit', $class->id) }}" class="p-1.5 rounded-[4px] bg-[#141414] border border-[#2e2e2e] text-[#888888] hover:text-[#ededed] hover:border-[#383838] transition-colors">
                                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                            </a>
                            <form action="{{ route('classes.destroy', $class->id) }}" method="POST" data-confirm-title="Delete “{{ $class->name }}”?" data-confirm="This can't be undone." data-confirm-action="Delete class" class="inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="p-1.5 rounded-[4px] bg-[#141414] border border-red-500/20 text-red-400 hover:text-red-300 hover:border-red-500/40 transition-colors">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                </button>
                            </form>
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="col-span-full p-12 text-center rounded-xl bg-[#171717] border border-[#2e2e2e]">
                <svg class="w-10 h-10 text-[#666666] mx-auto mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"></path></svg>
                <h3 class="text-base font-bold text-[#ededed]">No Classes Found</h3>
                <p class="text-xs text-[#888888] font-mono mt-1">{{ auth()->user()->role === 'student' ? 'You are not on any class roster yet. Your instructor adds you.' : 'You have no classes yet.' }}</p>
            </div>
        @endforelse
    </div>
</div>
@endsection

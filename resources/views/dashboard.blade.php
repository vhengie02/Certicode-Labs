@extends('layouts.app')

@section('title', 'Dashboard')

@php
    $user = auth()->user();
    $role = $user->role;
    // First name for the greeting, skipping titles such as "Dr." or "Prof."
    $nameParts = preg_split('/\s+/', trim($user->name)) ?: [];
    while (count($nameParts) > 1 && preg_match('/^(dr|prof|professor|mr|mrs|ms|miss|mx|sir|engr|atty)\.?$/i', $nameParts[0])) {
        array_shift($nameParts);
    }
    $firstName = $nameParts[0] ?? $user->name;
    $intro = [
        'student' => 'Pick up where you left off, or check on your certificates.',
        'instructor' => "Here's what's happening across your classes.",
        'admin' => 'Platform overview and people waiting for approval.',
    ][$role] ?? '';
@endphp

@section('content')
<div class="space-y-8">
    {{-- Rendered immediately: needs nothing beyond the signed-in user --}}
    <header class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-5">
        <div>
            <p class="font-mono text-[11px] uppercase tracking-[0.16em] text-[#888888]">{{ $role }} dashboard</p>
            <h1 class="cc-display mt-2 text-3xl sm:text-4xl font-bold text-[#ededed]">
                Welcome back, <span class="cc-serif text-[#3ecf8e] text-[1.08em]">{{ $firstName }}.</span>
            </h1>
            <p class="mt-2 text-[15px] text-[#a3a3a3]">{{ $intro }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2.5">
            @if ($role === 'admin')
                <a href="{{ route('admin.instructor-requests.index') }}" class="inline-flex items-center gap-2 h-10 px-4 rounded-lg bg-[#3ecf8e] text-sm font-semibold text-[#06150e] hover:bg-[#00c573] transition-colors">Review requests <span aria-hidden="true">&rarr;</span></a>
                <a href="{{ route('students.index') }}" class="inline-flex items-center h-10 px-4 rounded-lg border border-[#2e2e2e] bg-[#171717] text-sm font-medium text-[#ededed] hover:border-[#383838] transition-colors">Student directory</a>
            @elseif ($role === 'instructor')
                <a href="{{ route('classes.create') }}" class="inline-flex items-center gap-2 h-10 px-4 rounded-lg bg-[#3ecf8e] text-sm font-semibold text-[#06150e] hover:bg-[#00c573] transition-colors">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" d="M12 5v14M5 12h14"/></svg>
                    New class
                </a>
                <a href="{{ route('classes.index') }}" class="inline-flex items-center h-10 px-4 rounded-lg border border-[#2e2e2e] bg-[#171717] text-sm font-medium text-[#ededed] hover:border-[#383838] transition-colors">All classes</a>
            @else
                <a href="{{ route('classes.index') }}" class="inline-flex items-center gap-2 h-10 px-4 rounded-lg bg-[#3ecf8e] text-sm font-semibold text-[#06150e] hover:bg-[#00c573] transition-colors">My classes <span aria-hidden="true">&rarr;</span></a>
            @endif
        </div>
    </header>

    {{-- Filled in by the browser from dashboard.sections; the skeleton mirrors its layout --}}
    <div data-lazy-src="{{ route('dashboard.sections') }}" aria-busy="true" aria-live="polite">
        <span class="sr-only">Loading your dashboard…</span>
        <div class="space-y-8" aria-hidden="true">
            <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4">
                @for ($i = 0; $i < 4; $i++)
                    <div class="rounded-xl border border-[#2e2e2e] bg-[#171717] p-5">
                        <div class="skeleton-pulse h-3 w-20"></div>
                        <div class="skeleton-pulse h-8 w-14 mt-4"></div>
                        <div class="skeleton-pulse h-3 w-24 mt-3"></div>
                    </div>
                @endfor
            </div>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-4">
                <div class="lg:col-span-2 rounded-xl border border-[#2e2e2e] bg-[#171717] p-5">
                    <div class="skeleton-pulse h-4 w-40"></div>
                    @for ($i = 0; $i < 4; $i++)
                        <div class="flex items-center gap-4 mt-5">
                            <div class="skeleton-pulse h-9 w-9 !rounded-full shrink-0"></div>
                            <div class="flex-1">
                                <div class="skeleton-pulse h-3.5 w-1/2"></div>
                                <div class="skeleton-pulse h-3 w-1/3 mt-2"></div>
                            </div>
                            <div class="skeleton-pulse h-6 w-16 hidden sm:block"></div>
                        </div>
                    @endfor
                </div>
                <div class="rounded-xl border border-[#2e2e2e] bg-[#171717] p-5">
                    <div class="skeleton-pulse h-4 w-28"></div>
                    @for ($i = 0; $i < 3; $i++)
                        <div class="skeleton-pulse h-14 w-full mt-4"></div>
                    @endfor
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

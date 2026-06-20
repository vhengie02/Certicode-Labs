@extends('layouts.app')

@section('title', 'Class Invitation')
@section('page_header', 'Invitation Pending')

@section('content')
<div class="max-w-md mx-auto py-12">
    <div class="glass-panel rounded-lg p-8 border border-slate-800 text-center space-y-6">
        <div class="w-16 h-16 rounded-full bg-green-600/10 border border-green-500/20 flex items-center justify-center text-green-400 mx-auto">
            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 19v-8.93a2 2 0 01.89-1.664l8-5.333a2 2 0 012.22 0l8 5.333A2 2 0 0121 10.07V19M3 19a2 2 0 002 2h14a2 2 0 002-2M3 19l6.75-4.5M21 19l-6.75-4.5M3 10l6.75 4.5M21 10l-6.75 4.5m0 0l-2.25-1.5a2 2 0 00-2.22 0l-2.25 1.5"></path>
            </svg>
        </div>

        <div>
            <h2 class="text-xl font-bold text-white leading-tight">Class Invitation Received</h2>
            <p class="text-xs text-slate-400 mt-2">
                Your instructor has invited you to join the class <strong class="text-slate-300">{{ $class->name }}</strong>.
            </p>
        </div>

        <div class="p-4 bg-slate-900 border border-slate-800 rounded-lg text-left text-xs space-y-1.5">
            <div class="flex justify-between"><span class="text-slate-500">Instructor:</span><span class="text-slate-300 font-semibold">{{ $class->instructor->name }}</span></div>
            <div class="flex justify-between"><span class="text-slate-500">Enrolment Code:</span><span class="text-slate-300 font-mono">{{ $class->code }}</span></div>
        </div>

        <div class="flex space-x-3 pt-4 border-t border-slate-800/80">
            <a href="{{ route('classes.index') }}" class="flex-1 py-2.5 border border-slate-800 text-xs font-semibold rounded-lg text-slate-300 bg-slate-900 hover:bg-slate-800 transition-colors">
                Decline
            </a>
            <form action="{{ route('classes.invite-accept', $class->id) }}" method="POST" class="flex-1">
                @csrf
                <button type="submit" class="w-full py-2.5 border border-transparent text-xs font-semibold rounded-lg text-white bg-green-600 hover:bg-green-500 transition-colors shadow-lg shadow-green-500/20">
                    Accept & Enroll
                </button>
            </form>
        </div>
    </div>
</div>
@endsection

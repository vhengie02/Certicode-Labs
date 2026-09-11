@extends('layouts.app')

@section('title', 'Class Invitation')
@section('page_header', 'Invitation Pending')

@section('content')
<div class="max-w-md mx-auto py-12">
    <div class="rounded-xl p-8 bg-[#171717] border border-[#2e2e2e] text-center space-y-6">
        <div class="w-14 h-14 rounded-full bg-[#141414] border border-[#2e2e2e] flex items-center justify-center text-[#3ecf8e] mx-auto">
            <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 19v-8.93a2 2 0 01.89-1.664l8-5.333a2 2 0 012.22 0l8 5.333A2 2 0 0121 10.07V19M3 19a2 2 0 002 2h14a2 2 0 002-2M3 19l6.75-4.5M21 19l-6.75-4.5M3 10l6.75 4.5M21 10l-6.75 4.5m0 0l-2.25-1.5a2 2 0 00-2.22 0l-2.25 1.5"></path>
            </svg>
        </div>

        <div>
            <span class="text-[10px] font-mono uppercase tracking-wider text-[#3ecf8e] block mb-1">Roster Invitation</span>
            <h2 class="text-xl font-bold text-[#ededed] leading-tight">Class Invitation Received</h2>
            <p class="text-xs text-[#888888] mt-2 leading-relaxed">
                Your instructor has invited you to join the class <strong class="text-[#ededed]">{{ $class->name }}</strong>.
            </p>
        </div>

        <div class="p-4 bg-[#141414] border border-[#2e2e2e] rounded-[6px] text-left text-xs font-mono space-y-1.5">
            <div class="flex justify-between"><span class="text-[#666666]">Instructor:</span><span class="text-[#ededed]">{{ $class->instructor->name }}</span></div>
            <div class="flex justify-between"><span class="text-[#666666]">Enrolment Code:</span><span class="text-[#3ecf8e]">{{ $class->code }}</span></div>
        </div>

        <div class="flex items-center space-x-3 pt-4 border-t border-[#232323]">
            <a href="{{ route('classes.index') }}" class="flex-1 py-2 border border-[#2e2e2e] text-xs font-mono font-medium rounded-[6px] text-[#a3a3a3] bg-[#171717] hover:bg-[#222222] hover:text-[#ededed] transition-colors">
                Decline
            </a>
            <form action="{{ route('classes.invite-accept', $class->id) }}" method="POST" class="flex-1">
                @csrf
                <button type="submit" class="w-full py-2 border border-transparent text-xs font-semibold rounded-full text-[#0f0f0f] bg-[#3ecf8e] hover:bg-[#00c573] transition-colors shadow-none">
                    Accept & Enroll &rarr;
                </button>
            </form>
        </div>
    </div>
</div>
@endsection

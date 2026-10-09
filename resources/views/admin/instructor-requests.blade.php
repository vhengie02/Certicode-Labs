@extends('layouts.app')

@section('title', 'Instructor requests')
@section('page_header', 'Admin')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col sm:flex-row sm:items-end sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold tracking-tight text-[#ededed]">Instructor requests</h1>
            <p class="text-sm text-[#a3a3a3] mt-1 max-w-2xl">
                People who signed up as instructors. They use Certicode as students until you approve them,
                because instructors can see their students' sessions, telemetry and grades.
            </p>
        </div>
        <span class="inline-flex items-center gap-2 self-start sm:self-auto px-3 py-1 rounded-full border border-[#2e2e2e] bg-[#141414] text-xs font-mono text-[#a3a3a3] whitespace-nowrap">
            <span class="w-1.5 h-1.5 rounded-full {{ $pending->isEmpty() ? 'bg-[#555555]' : 'bg-[#3ecf8e]' }}" aria-hidden="true"></span>
            {{ $pending->count() }} pending
        </span>
    </div>

    @if ($pending->isEmpty())
        <div class="rounded-xl border border-dashed border-[#2e2e2e] bg-[#171717] px-6 py-16 flex flex-col items-center text-center">
            <div class="h-11 w-11 rounded-full border border-[#2e2e2e] bg-[#141414] flex items-center justify-center text-[#3ecf8e]" aria-hidden="true">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
            </div>
            <p class="text-sm font-semibold text-[#ededed] mt-4">You're all caught up</p>
            <p class="text-sm text-[#888888] mt-1 max-w-sm">New instructor sign-ups will appear here, and you'll get a notification when one arrives.</p>
        </div>
    @else
        <ul class="divide-y divide-[#2e2e2e] rounded-xl border border-[#2e2e2e] bg-[#171717]" role="list">
            @foreach ($pending as $user)
                <li class="flex flex-col sm:flex-row sm:items-center gap-4 px-5 py-4">
                    <div class="h-10 w-10 rounded-full bg-[#3ecf8e]/10 border border-[#3ecf8e]/25 flex items-center justify-center text-xs font-bold text-[#3ecf8e] shrink-0" aria-hidden="true">
                        {{ strtoupper(\Illuminate\Support\Str::substr($user->name, 0, 2)) }}
                    </div>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-[#ededed] truncate">{{ $user->name }}</p>
                        <p class="text-sm text-[#a3a3a3] truncate">{{ $user->email }}</p>
                        <p class="text-xs text-[#888888] mt-1">
                            Requested {{ $user->instructor_requested_at->diffForHumans() }}
                            <span aria-hidden="true">&middot;</span>
                            account created {{ $user->created_at?->toFormattedDateString() }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <form method="POST" action="{{ route('admin.instructor-requests.decline', $user) }}">
                            @csrf
                            <button type="submit" class="px-4 py-2 rounded-lg border border-[#2e2e2e] text-sm font-medium text-[#ededed] hover:border-red-400/50 hover:text-red-300 transition-colors">
                                Decline
                            </button>
                        </form>
                        <form method="POST" action="{{ route('admin.instructor-requests.approve', $user) }}">
                            @csrf
                            <button type="submit" class="px-4 py-2 rounded-lg bg-[#3ecf8e] text-sm font-semibold text-[#06150e] hover:bg-[#52dba0] transition-colors">
                                Approve
                            </button>
                        </form>
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
@endsection

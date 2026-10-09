@extends('layouts.app')

@section('title', 'Instructor requests')
@section('page_header', 'Admin')

@section('content')
<div class="max-w-4xl space-y-6">
    <div>
        <h1 class="text-2xl font-bold tracking-tight">Instructor requests</h1>
        <p class="text-sm text-[#a3a3a3] mt-1">
            People who signed up as instructors. They use Certicode as students until you approve them,
            because instructors can see every student's sessions, telemetry and grades.
        </p>
    </div>

    @if ($pending->isEmpty())
        <div class="rounded-xl border border-[#2e2e2e] bg-[#171717] px-6 py-12 text-center">
            <p class="text-sm font-medium">No pending requests</p>
            <p class="text-sm text-[#888888] mt-1">New instructor sign-ups will appear here, and you'll get a notification.</p>
        </div>
    @else
        <ul class="divide-y divide-[#2e2e2e] rounded-xl border border-[#2e2e2e] bg-[#171717]" role="list">
            @foreach ($pending as $user)
                <li class="flex flex-col sm:flex-row sm:items-center gap-4 px-5 py-4">
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold truncate">{{ $user->name }}</p>
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

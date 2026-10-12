{{-- Tabs shared by Admin → Users and Admin → Instructor requests. Expects $pendingRequests. --}}
<nav class="monitor-tabs flex items-center gap-1.5 overflow-x-auto border-b border-[#2e2e2e] pb-px" aria-label="Users sections">
    @foreach ([
        ['All users', route('admin.users.index'), request()->routeIs('admin.users.*'), null],
        ['Instructor requests', route('admin.instructor-requests.index'), request()->routeIs('admin.instructor-requests.*'), $pendingRequests ?? 0],
    ] as [$label, $url, $active, $badge])
        <a href="{{ $url }}" @if($active) aria-current="page" @endif
           class="shrink-0 relative inline-flex items-center gap-2 px-3 py-2.5 text-sm font-medium transition-colors {{ $active ? 'text-[#ededed] monitor-tab-active' : 'text-[#888888] hover:text-[#ededed]' }}">
            {{ $label }}
            @if ($badge)
                <span class="min-w-5 h-5 px-1.5 rounded-full bg-[#3ecf8e] text-[11px] font-bold text-[#06150e] inline-flex items-center justify-center">{{ $badge }}</span>
            @endif
        </a>
    @endforeach
</nav>

@extends('layouts.app')

@section('title', 'Users')

@php
    $roleLabels = ['student' => 'Students', 'instructor' => 'Instructors', 'admin' => 'Admins'];
    $total = array_sum($counts->all());
@endphp

@section('content')
<div class="space-y-6">
    <x-page-header title="Users" subtitle="Every account on Certicode. Change roles, edit details or remove accounts." />

    @include('admin._users-tabs')

    {{-- Filters --}}
    <form method="GET" action="{{ route('admin.users.index') }}" class="flex flex-col sm:flex-row gap-3 sm:items-center">
        <div class="flex flex-wrap items-center gap-1.5" role="group" aria-label="Filter by role">
            <a href="{{ route('admin.users.index', array_filter(['q' => $search ?: null])) }}"
               class="ui-badge {{ !$role ? 'ui-badge-brand' : '' }} !py-1 !px-3 text-[13px]" @if(!$role) aria-current="true" @endif>All {{ $total }}</a>
            @foreach ($roleLabels as $key => $label)
                <a href="{{ route('admin.users.index', array_filter(['role' => $key, 'q' => $search ?: null])) }}"
                   class="ui-badge {{ $role === $key ? 'ui-badge-brand' : '' }} !py-1 !px-3 text-[13px]" @if($role === $key) aria-current="true" @endif>{{ $label }} {{ $counts[$key] ?? 0 }}</a>
            @endforeach
        </div>
        <div class="flex gap-2 sm:ml-auto">
            @if ($role) <input type="hidden" name="role" value="{{ $role }}"> @endif
            <label for="user-search" class="sr-only">Search users</label>
            <input type="search" id="user-search" name="q" value="{{ $search }}" placeholder="Search name, email or username" class="ui-input sm:w-72">
            <button type="submit" class="ui-btn ui-btn-secondary shrink-0">Search</button>
        </div>
    </form>

    <section class="ui-card overflow-hidden" aria-label="Users">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-[#232323] text-left font-mono text-[11px] uppercase tracking-[0.12em] text-[#888888]">
                        <th scope="col" class="px-5 py-3 font-medium">Name</th>
                        <th scope="col" class="px-5 py-3 font-medium">Role</th>
                        <th scope="col" class="px-5 py-3 font-medium">Activity</th>
                        <th scope="col" class="px-5 py-3 font-medium">Joined</th>
                        <th scope="col" class="px-5 py-3 font-medium text-right"><span class="sr-only">Actions</span></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#232323]">
                    @forelse ($users as $u)
                        @php $isSelf = $u->is(auth()->user()); @endphp
                        <tr>
                            <td class="px-5 py-3">
                                <div class="flex items-center gap-3 min-w-0">
                                    <span class="h-8 w-8 shrink-0 rounded-full bg-[#3ecf8e]/10 border border-[#3ecf8e]/25 flex items-center justify-center text-[11px] font-semibold text-[#3ecf8e]" aria-hidden="true">{{ strtoupper(\Illuminate\Support\Str::substr($u->name, 0, 2)) }}</span>
                                    <span class="min-w-0">
                                        <span class="block truncate font-medium text-[#ededed]">{{ $u->name }} @if($isSelf)<span class="text-xs font-normal text-[#888888]">(you)</span>@endif</span>
                                        <span class="block truncate text-xs text-[#888888]">{{ $u->email }}</span>
                                    </span>
                                </div>
                            </td>
                            <td class="px-5 py-3 whitespace-nowrap">
                                @if ($isSelf)
                                    <span class="ui-badge ui-badge-brand">{{ ucfirst($u->role) }}</span>
                                @else
                                    <form method="POST" action="{{ route('admin.users.role', $u) }}" class="flex items-center gap-2">
                                        @csrf
                                        @method('PATCH')
                                        <label for="role-{{ $u->id }}" class="sr-only">Role for {{ $u->name }}</label>
                                        <select id="role-{{ $u->id }}" name="role" class="ui-input !min-h-0 !h-8 !py-0 !w-auto text-[13px]" onchange="this.form.requestSubmit()">
                                            @foreach (['student', 'instructor', 'admin'] as $r)
                                                <option value="{{ $r }}" @selected($u->role === $r)>{{ ucfirst($r) }}</option>
                                            @endforeach
                                        </select>
                                        <noscript><button type="submit" class="ui-btn ui-btn-secondary ui-btn-sm">Save</button></noscript>
                                    </form>
                                @endif
                                @if ($u->instructor_requested_at)
                                    <a href="{{ route('admin.instructor-requests.index') }}" class="mt-1 inline-block text-xs text-amber-300 hover:underline">Wants instructor access</a>
                                @endif
                            </td>
                            <td class="px-5 py-3 whitespace-nowrap text-xs text-[#a3a3a3]">
                                @if ($u->role === 'student')
                                    {{ $u->lab_sessions_count }} {{ \Illuminate\Support\Str::plural('lab session', $u->lab_sessions_count) }}
                                @else
                                    {{ $u->instructed_classes_count }} {{ \Illuminate\Support\Str::plural('class', $u->instructed_classes_count) }}
                                @endif
                            </td>
                            <td class="px-5 py-3 whitespace-nowrap text-xs text-[#888888]">{{ $u->created_at?->format('M j, Y') }}</td>
                            <td class="px-5 py-3 whitespace-nowrap text-right">
                                <div class="inline-flex items-center gap-1">
                                    <a href="{{ route('profiles.edit', $u->id) }}" class="ui-btn ui-btn-ghost ui-btn-sm">Edit</a>
                                    @unless ($isSelf)
                                        <form method="POST" action="{{ route('admin.users.destroy', $u) }}"
                                              onsubmit="return confirm('Delete {{ addslashes($u->name) }}\'s account? Their lab sessions, grades and certificates are deleted too. This can\'t be undone.');">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="ui-btn ui-btn-ghost ui-btn-sm hover:!text-red-400">Delete</button>
                                        </form>
                                    @endunless
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-12 text-center text-sm text-[#888888]">No users match {{ $search ? '“' . $search . '”' : 'this filter' }}.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($users->hasPages())
        <div class="flex items-center justify-between text-sm text-[#888888]">
            <span>Showing {{ $users->firstItem() }}–{{ $users->lastItem() }} of {{ $users->total() }}</span>
            <div class="flex gap-2">
                @if ($users->previousPageUrl()) <a href="{{ $users->previousPageUrl() }}" class="ui-btn ui-btn-secondary ui-btn-sm">Previous</a> @endif
                @if ($users->nextPageUrl()) <a href="{{ $users->nextPageUrl() }}" class="ui-btn ui-btn-secondary ui-btn-sm">Next</a> @endif
            </div>
        </div>
    @endif
</div>
@endsection

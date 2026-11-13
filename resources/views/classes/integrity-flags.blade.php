@extends('layouts.app')

@section('title', 'Integrity flags')

@section('content')
<div class="space-y-6">
    <header>
        <a href="{{ route('dashboard') }}" class="inline-flex items-center gap-1.5 text-sm text-[#888888] hover:text-[#ededed] transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
            Dashboard
        </a>
        <h1 class="cc-display mt-2 text-3xl font-bold text-[#ededed]">Integrity flags</h1>
        <p class="mt-1 text-[15px] text-[#a3a3a3]">Every flag raised across your classes, newest first.</p>
    </header>

    <div class="flex flex-wrap items-center gap-1.5" role="group" aria-label="Filter by status">
        @foreach (['' => 'All', 'open' => 'Open', 'resolved' => 'Resolved'] as $key => $label)
            @php $isCurrent = ($status ?? '') === $key; @endphp
            <a href="{{ route('anomalies.index', array_filter(['status' => $key])) }}"
               class="ui-badge {{ $isCurrent ? 'ui-badge-brand' : '' }} !py-1 !px-3 text-[13px]" @if ($isCurrent) aria-current="true" @endif>{{ $label }} {{ number_format($counts[$key ?: 'all']) }}</a>
        @endforeach
    </div>

    <section class="rounded-xl border border-[#2e2e2e] bg-[#171717]" aria-label="Integrity flags">
        <div class="overflow-x-auto">
            <table class="min-w-full text-sm">
                <thead>
                    <tr class="border-b border-[#232323] text-left text-xs text-[#888888]">
                        <th scope="col" class="px-5 py-3 font-normal">Student</th>
                        <th scope="col" class="px-5 py-3 font-normal">Lab</th>
                        <th scope="col" class="px-5 py-3 font-normal">What happened</th>
                        <th scope="col" class="px-5 py-3 font-normal">Severity</th>
                        <th scope="col" class="px-5 py-3 font-normal">When</th>
                        <th scope="col" class="px-5 py-3 font-normal text-right">Status</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#232323]">
                    @forelse ($flags as $flag)
                        @php
                            $flagSession = $flag->labSession;
                            $lab = $flagSession?->laboratory;
                            $class = $lab?->module?->schoolClass;
                            $severityStyle = [
                                'high' => 'bg-red-500/10 text-red-400 border-red-500/20',
                                'medium' => 'bg-yellow-500/10 text-yellow-400 border-yellow-500/20',
                            ][$flag->severity] ?? 'bg-sky-500/10 text-sky-400 border-sky-500/20';
                        @endphp
                        <tr class="{{ $flag->resolved ? '' : 'bg-amber-400/[0.03]' }}">
                            <td class="px-5 py-3 whitespace-nowrap font-medium text-[#ededed]">{{ $flagSession?->user?->name ?? 'Unknown' }}</td>
                            <td class="px-5 py-3 whitespace-nowrap">
                                @if ($lab && $class)
                                    <a href="{{ route('classes.telemetry', ['class_id' => $class->id, 'lab' => $lab->id]) }}" class="block text-[#a3a3a3] hover:text-[#3ecf8e] hover:underline underline-offset-2">{{ $lab->title }}</a>
                                    <span class="block text-xs text-[#888888]">{{ $class->name }}</span>
                                @else
                                    <span class="text-[#888888]">Removed lab</span>
                                @endif
                            </td>
                            <td class="px-5 py-3 max-w-xs">
                                <span class="block text-sm text-[#ededed]">{{ ucfirst(str_replace('_', ' ', $flag->type)) }}</span>
                                <span class="block text-xs text-[#888888] truncate" title="{{ $flag->description }}">{{ $flag->description }}</span>
                            </td>
                            <td class="px-5 py-3 whitespace-nowrap">
                                <span class="px-2 py-0.5 rounded-md border text-xs {{ $severityStyle }}">{{ ucfirst($flag->severity) }}</span>
                            </td>
                            <td class="px-5 py-3 whitespace-nowrap text-xs text-[#888888]" title="{{ $flag->created_at }}">{{ $flag->created_at?->diffForHumans() }}</td>
                            <td class="px-5 py-3 whitespace-nowrap text-right">
                                @if ($flag->resolved)
                                    <span class="inline-flex items-center gap-1 text-xs text-[#888888]">
                                        <svg class="w-3.5 h-3.5 text-[#3ecf8e]" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                        Resolved
                                    </span>
                                @else
                                    <form action="{{ route('anomalies.resolve', $flag->id) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" class="h-8 px-3 rounded-lg border border-[#2e2e2e] text-xs font-medium text-[#ededed] hover:border-[#3ecf8e]/40 transition-colors">Resolve</button>
                                    </form>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-5 py-10 text-center text-sm text-[#888888]">
                                {{ ['open' => 'No open flags. Everything has been reviewed.', 'resolved' => 'No resolved flags.'][$status] ?? "No flags. When a student's session trips a proctoring rule, it shows up here." }}
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>

    @if ($flags->hasPages())
        <div class="flex items-center justify-between text-sm text-[#888888]">
            <span>Showing {{ $flags->firstItem() }}–{{ $flags->lastItem() }} of {{ $flags->total() }}</span>
            <div class="flex gap-2">
                @if ($flags->previousPageUrl()) <a href="{{ $flags->previousPageUrl() }}" class="ui-btn ui-btn-secondary ui-btn-sm">Previous</a> @endif
                @if ($flags->nextPageUrl()) <a href="{{ $flags->nextPageUrl() }}" class="ui-btn ui-btn-secondary ui-btn-sm">Next</a> @endif
            </div>
        </div>
    @endif
</div>
@endsection

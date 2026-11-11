@extends('layouts.app')

@section('title', $module->title)

@php
    $user = auth()->user();
    $isStaff = $user->canManageClass($class);
    $lesson = $module->safeContent();
    $labs = $module->laboratories;
    $labsDone = $labs->filter(fn ($lab) => isset($completedLabIds[$lab->id]))->count();
    $topModules = $class->modules->where('parent_id', null)->sortBy('order_index');

    $allModules = $class->modules->sortBy('order_index')->values();
    $currentIndex = $allModules->search(fn ($m) => $m->id === $module->id);
    $prevModule = $currentIndex > 0 ? $allModules[$currentIndex - 1] : null;
    $nextModule = $currentIndex < $allModules->count() - 1 ? $allModules[$currentIndex + 1] : null;
@endphp

@section('content')
<div class="space-y-6">
    <x-page-header :back="route('classes.show', $class->id)" :back-label="$class->name" :title="$module->title"
                   :subtitle="$module->description ?: null">
        @if ($isStaff)
            <a href="{{ route('modules.edit', [$class->id, $module->id]) }}" class="ui-btn ui-btn-secondary">Edit module</a>
        @endif
    </x-page-header>

    <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_340px] gap-6 items-start">
        <div class="min-w-0 space-y-6">
            {{-- Lesson --}}
            <section class="ui-card p-5" aria-label="Lesson">
                @if (trim($lesson) !== '')
                    <div class="prose dark:prose-invert max-w-none break-words prose-a:text-[#059669] dark:prose-a:text-[#3ecf8e] prose-code:before:content-none prose-code:after:content-none">
                        {!! $lesson !!}
                    </div>
                @else
                    <p class="py-8 text-center text-sm text-[#888888]">This module has no lesson content yet.</p>
                @endif
            </section>

            {{-- Labs --}}
            <section class="ui-card" aria-labelledby="labs-heading">
                <div class="px-5 pt-5 pb-3 border-b border-[#232323]">
                    <h2 id="labs-heading" class="ui-card-title">Labs</h2>
                    @if ($labs->isNotEmpty())
                        <p class="ui-card-subtitle">
                            @if ($isStaff)
                                {{ $labs->count() }} {{ \Illuminate\Support\Str::plural('lab', $labs->count()) }} in this module
                            @else
                                {{ $labsDone }} of {{ $labs->count() }} done
                            @endif
                        </p>
                    @endif
                </div>

                @if ($labs->isEmpty())
                    <p class="px-5 py-10 text-center text-sm text-[#888888]">This module has no labs yet.</p>
                @else
                    <ul role="list">
                        @foreach ($labs as $lab)
                            @php
                                $isDone = !$isStaff && isset($completedLabIds[$lab->id]);
                                $isInProgress = !$isStaff && isset($activeLabIds[$lab->id]);
                                $isLiveNow = $lab->isLiveLab() && $lab->isLiveActive();
                                $studentStatus = !$isStaff ? $lab->studentStatus($isDone) : null;
                                // A live lab can only be started during its window
                                $canStart = !$isStaff && !$isDone && (!$lab->isLiveLab() || $isLiveNow);
                                $actionLabel = $isInProgress ? 'Continue lab' : ($canStart ? 'Start lab' : 'View lab');
                            @endphp
                            <li class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:gap-6 {{ $loop->first ? '' : 'border-t border-[#232323]' }}">
                                <div class="flex min-w-0 gap-3">
                                    <span class="mt-0.5 shrink-0">
                                        @if ($isStaff)
                                            <svg class="w-4 h-4 text-[#3ecf8e]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
                                        @elseif ($isDone)
                                            <span class="flex h-4 w-4 items-center justify-center rounded-full bg-[#3ecf8e] text-[#06150e]" role="img" aria-label="Done">
                                                <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="3.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                            </span>
                                        @else
                                            <span class="block h-4 w-4 rounded-full border border-[#444444]" role="img" aria-label="Not done yet"></span>
                                        @endif
                                    </span>
                                    <div class="min-w-0">
                                        <h3 class="text-sm font-medium text-[#ededed]">{{ $lab->title }}</h3>
                                        @if ($lab->description)
                                            <p class="mt-1 text-sm leading-relaxed text-[#a3a3a3] line-clamp-2">{{ $lab->description }}</p>
                                        @endif
                                        <div class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1.5 text-xs text-[#888888]">
                                            <span>{{ $lab->is_group_lab ? 'Group lab' : 'Individual lab' }}</span>
                                            @if ($lab->time_limit)
                                                <span class="font-mono">{{ $lab->time_limit }} min</span>
                                            @endif
                                            @if ($studentStatus)
                                                <span class="ui-badge {{ $studentStatus['class'] }} text-[11px]">
                                                    @if ($isLiveNow)<span class="h-1.5 w-1.5 rounded-full bg-[#3ecf8e] animate-pulse" aria-hidden="true"></span>@endif
                                                    {{ $studentStatus['label'] }}
                                                </span>
                                            @elseif ($isStaff && $lab->isLiveLab())
                                                <span class="ui-badge {{ $isLiveNow ? 'ui-badge-brand' : '' }} text-[11px]">
                                                    @if ($isLiveNow)<span class="h-1.5 w-1.5 rounded-full bg-[#3ecf8e] animate-pulse" aria-hidden="true"></span>@endif
                                                    {{ $isLiveNow ? 'Live now' : 'Live lab' }}
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                                <a href="{{ route('laboratories.show', $lab->id) }}"
                                   class="ui-btn ui-btn-sm {{ $actionLabel === 'View lab' ? 'ui-btn-secondary' : 'ui-btn-primary' }} shrink-0 self-start sm:self-center"
                                   aria-label="{{ $actionLabel }}: {{ $lab->title }}">{{ $actionLabel }}</a>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>

            {{-- Previous and next module --}}
            <nav class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between" aria-label="Modules in this class">
                @if ($prevModule)
                    <a href="{{ route('modules.show', [$class->id, $prevModule->id]) }}" class="ui-btn ui-btn-secondary min-w-0 max-w-full sm:max-w-[48%]">
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/></svg>
                        <span class="truncate">Previous: {{ $prevModule->title }}</span>
                    </a>
                @endif

                @if ($nextModule)
                    <a href="{{ route('modules.show', [$class->id, $nextModule->id]) }}" class="ui-btn ui-btn-secondary min-w-0 max-w-full sm:max-w-[48%] sm:ml-auto">
                        <span class="truncate">Next: {{ $nextModule->title }}</span>
                        <svg class="w-4 h-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/></svg>
                    </a>
                @else
                    <a href="{{ route('classes.show', $class->id) }}" class="ui-btn ui-btn-secondary sm:ml-auto">Back to class</a>
                @endif
            </nav>
        </div>

        {{-- Sidebar --}}
        <aside class="min-w-0 space-y-6 lg:sticky lg:top-2 lg:max-h-[calc(100vh-7rem)] lg:overflow-y-auto">
            <section class="ui-card" aria-labelledby="outline-heading" x-data="{ query: '' }">
                <div class="px-5 pt-5">
                    <h2 id="outline-heading" class="ui-card-title">Course outline</h2>
                </div>
                <div class="px-5 pt-3">
                    <label for="outline-search" class="sr-only">Search the course outline</label>
                    <input type="search" id="outline-search" x-model="query" placeholder="Search outline" autocomplete="off" class="ui-input">
                </div>
                <ul class="line-sidebar line-sidebar--compact px-5 pt-4 pb-5" role="list" x-data="lineSidebar">
                    @foreach ($topModules as $item)
                        @include('classes._module-outline-item', ['item' => $item, 'number' => str_pad($loop->iteration, 2, '0', STR_PAD_LEFT)])
                    @endforeach
                </ul>
            </section>

            @if ($module->attachments->isNotEmpty())
                <section class="ui-card" aria-labelledby="resources-heading">
                    <div class="px-5 pt-5">
                        <h2 id="resources-heading" class="ui-card-title">Resources</h2>
                    </div>
                    <ul class="px-2 pt-2 pb-3" role="list">
                        @foreach ($module->attachments as $attachment)
                            <li class="flex items-center gap-3 rounded-lg px-3 py-2">
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm text-[#ededed]" title="{{ $attachment->file_name }}">{{ $attachment->file_name }}</span>
                                    <span class="block text-xs text-[#888888]">{{ number_format($attachment->file_size / (1024 * 1024), 2) }} MB</span>
                                </span>
                                <a href="{{ route('attachments.download', $attachment->id) }}" class="ui-btn ui-btn-ghost ui-btn-sm !px-2 shrink-0" aria-label="Download {{ $attachment->file_name }}" title="Download">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v12m0 0l-4-4m4 4l4-4M5 20h14"/></svg>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </aside>
    </div>
</div>
@endsection

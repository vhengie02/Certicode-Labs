{{--
    One module in the class syllabus, with its labs and sub-modules (rendered recursively) as a
    branch of the .line-sidebar tree.
    Expects: $module, $class, $isStaff, $completedLabIds, $number (its index, like 01 or 01.2).
    Students never see engagement numbers here (no view or submission counts).
--}}
@php
    $studentProgress = !$isStaff ? $module->getStudentProgress(auth()->user(), $completedLabIds ?? null) : null;
    $labs = $module->laboratories;
    $children = $module->children->sortBy('order_index');
@endphp
<li class="line-sidebar__item">
    <div class="line-sidebar__row group">
        <span class="line-sidebar__marker" aria-hidden="true"></span>
        <a href="{{ route('modules.show', [$class->id, $module->id]) }}" class="line-sidebar__link line-sidebar__link--module">
            <span class="line-sidebar__label">
                <span class="line-sidebar__index" aria-hidden="true">{{ $number }}</span>
                <span class="line-sidebar__text">{{ $module->title }}</span>
            </span>
        </a>
        <span class="line-sidebar__meta">
            {{ $labs->count() }} {{ \Illuminate\Support\Str::plural('lab', $labs->count()) }}
            @if ($children->isNotEmpty())
                &middot; {{ $children->count() }} {{ \Illuminate\Support\Str::plural('section', $children->count()) }}
            @endif
            @if ($isStaff)
                &middot; {{ number_format($module->views_count) }} views
            @endif
        </span>

        @if ($studentProgress && $studentProgress['total'] > 0)
            <span class="ui-badge {{ $studentProgress['completed'] >= $studentProgress['total'] ? 'ui-badge-brand' : '' }} shrink-0 font-mono">
                {{ $studentProgress['completed'] }}/{{ $studentProgress['total'] }}
            </span>
        @endif

        @if ($isStaff)
            <div class="line-sidebar__actions opacity-100 sm:opacity-0 sm:group-hover:opacity-100 sm:group-focus-within:opacity-100 transition-opacity">
                <a href="{{ route('modules.edit', [$class->id, $module->id]) }}" class="ui-btn ui-btn-ghost ui-btn-sm !px-2" aria-label="Edit {{ $module->title }}" title="Edit"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 20h4L19 9l-4-4L4 16v4zM14 6l4 4"/></svg></a>
                @php $labsInside = count($module->getAllLaboratoryIds()); @endphp
                <form action="{{ route('modules.destroy', [$class->id, $module->id]) }}" method="POST"
                      onsubmit="return confirm('Delete the module “{{ addslashes($module->title) }}” and its sections and attachments? This can\'t be undone.');">
                    @csrf
                    @method('DELETE')
                    @if ($labsInside > 0)
                        {{-- Deleting would detach the labs, so it's blocked until they're moved or deleted --}}
                        <button type="button" disabled class="ui-btn ui-btn-ghost ui-btn-sm !px-2"
                                aria-label="Can't delete {{ $module->title }}: move or delete its {{ $labsInside }} {{ \Illuminate\Support\Str::plural('lab', $labsInside) }} first"
                                title="Move or delete its {{ $labsInside }} {{ \Illuminate\Support\Str::plural('lab', $labsInside) }} first"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12M9 7V4h6v3m-7 0l1 13h6l1-13"/></svg></button>
                    @else
                        <button type="submit" class="ui-btn ui-btn-ghost ui-btn-sm !px-2 hover:!text-red-400" aria-label="Delete {{ $module->title }}" title="Delete"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12M9 7V4h6v3m-7 0l1 13h6l1-13"/></svg></button>
                    @endif
                </form>
            </div>
        @endif
    </div>

    @if ($labs->isNotEmpty() || $children->isNotEmpty())
        <ul class="line-sidebar__branch" role="list">
            @foreach ($labs as $lab)
                @php
                    $isDone = !$isStaff && isset($completedLabIds[$lab->id]);
                    $isLiveNow = $lab->isLiveLab() && $lab->isLiveActive();
                @endphp
                <li class="line-sidebar__item">
                    <div class="line-sidebar__row group/lab">
                        <span class="line-sidebar__marker" aria-hidden="true"></span>
                        <a href="{{ route('laboratories.show', $lab->id) }}" class="line-sidebar__link">
                            <span class="line-sidebar__label">
                                <span class="line-sidebar__index" aria-hidden="true">{{ $number }}.{{ $loop->iteration }}</span>
                                @if (!$isStaff)
                                    @if ($isDone)
                                        <span class="flex h-4 w-4 shrink-0 items-center justify-center rounded-full bg-[#3ecf8e] text-[#06150e]" aria-label="Done">
                                            <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" stroke-width="3.5" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/></svg>
                                        </span>
                                    @else
                                        <span class="h-4 w-4 shrink-0 rounded-full border border-[#444444]" aria-label="Not done yet"></span>
                                    @endif
                                @else
                                    <svg class="w-4 h-4 shrink-0 text-[#3ecf8e]" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>
                                @endif
                                <span class="line-sidebar__text">{{ $lab->title }}</span>
                            </span>
                        </a>
                        @if ($lab->isLiveLab() || $isStaff)
                            <span class="line-sidebar__meta">
                                @if ($lab->isLiveLab())
                                    <span class="ui-badge {{ $isLiveNow ? 'ui-badge-brand' : '' }} shrink-0 text-[11px]">
                                        @if ($isLiveNow)<span class="h-1.5 w-1.5 rounded-full bg-[#3ecf8e] animate-pulse" aria-hidden="true"></span>@endif
                                        {{ $isLiveNow ? 'Live now' : 'Live lab' }}
                                    </span>
                                @endif
                                @if ($isStaff)
                                    <span>{{ number_format($lab->views_count) }} views &middot; {{ $lab->completed_count ?? 0 }} completed</span>
                                @endif
                            </span>
                        @endif
                        @if ($isStaff)
                            <div class="line-sidebar__actions opacity-100 sm:opacity-0 sm:group-hover/lab:opacity-100 sm:group-focus-within/lab:opacity-100 transition-opacity">
                                <a href="{{ route('laboratories.edit', $lab->id) }}" class="ui-btn ui-btn-ghost ui-btn-sm !px-2" aria-label="Edit {{ $lab->title }}" title="Edit"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 20h4L19 9l-4-4L4 16v4zM14 6l4 4"/></svg></a>
                                <form action="{{ route('laboratories.destroy', $lab->id) }}" method="POST"
                                      onsubmit="return confirm('Delete the lab “{{ addslashes($lab->title) }}”? Student sessions for it are deleted too.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="ui-btn ui-btn-ghost ui-btn-sm !px-2 hover:!text-red-400" aria-label="Delete {{ $lab->title }}" title="Delete"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12M9 7V4h6v3m-7 0l1 13h6l1-13"/></svg></button>
                                </form>
                            </div>
                        @endif
                    </div>
                </li>
            @endforeach

            @foreach ($children as $child)
                @include('classes._syllabus-module', ['module' => $child, 'number' => $number . '.' . ($labs->count() + $loop->iteration)])
            @endforeach
        </ul>
    @endif
</li>

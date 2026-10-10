{{--
    One module in the class syllabus, with its labs and sub-modules (rendered recursively).
    Expects: $module, $class, $isStaff, $completedLabIds, $depth (0 for top level).
    Students never see engagement numbers here (no view or submission counts).
--}}
@php
    $depth = $depth ?? 0;
    $studentProgress = !$isStaff ? $module->getStudentProgress(auth()->user(), $completedLabIds ?? null) : null;
    $labs = $module->laboratories;
    $children = $module->children->sortBy('order_index');
@endphp
<li class="{{ $depth === 0 ? 'border-b border-[#232323] last:border-b-0' : '' }}">
    <div class="group flex items-center gap-3 {{ $depth === 0 ? 'px-5 py-4' : 'pl-5 pr-5 py-2.5' }}">
        <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-[#2e2e2e] bg-[#141414] text-[#a3a3a3] {{ $depth > 0 ? 'h-7 w-7' : '' }}" aria-hidden="true">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.25v13M12 6.25C10.83 5.48 9.25 5 7.5 5S4.17 5.48 3 6.25v13C4.17 18.48 5.75 18 7.5 18s3.33.48 4.5 1.25m0-13C13.17 5.48 14.75 5 16.5 5s3.33.48 4.5 1.25v13C19.83 18.48 18.25 18 16.5 18s-3.33.48-4.5 1.25"/></svg>
        </span>
        <div class="min-w-0 flex-1">
            <a href="{{ route('modules.show', [$class->id, $module->id]) }}" class="block truncate {{ $depth === 0 ? 'text-[15px] font-semibold' : 'text-sm font-medium' }} text-[#ededed] hover:text-[#3ecf8e] transition-colors">{{ $module->title }}</a>
            <p class="text-xs text-[#888888] mt-0.5">
                {{ $labs->count() }} {{ \Illuminate\Support\Str::plural('lab', $labs->count()) }}
                @if ($children->isNotEmpty())
                    &middot; {{ $children->count() }} {{ \Illuminate\Support\Str::plural('section', $children->count()) }}
                @endif
                @if ($isStaff)
                    &middot; {{ number_format($module->views_count) }} views
                @endif
            </p>
        </div>

        @if ($studentProgress && $studentProgress['total'] > 0)
            <span class="ui-badge {{ $studentProgress['completed'] >= $studentProgress['total'] ? 'ui-badge-brand' : '' }} shrink-0 font-mono">
                {{ $studentProgress['completed'] }}/{{ $studentProgress['total'] }}
            </span>
        @endif

        @if ($isStaff)
            <div class="flex items-center gap-1 shrink-0 opacity-100 sm:opacity-0 sm:group-hover:opacity-100 sm:group-focus-within:opacity-100 transition-opacity">
                <a href="{{ route('modules.edit', [$class->id, $module->id]) }}" class="ui-btn ui-btn-ghost ui-btn-sm !px-2" aria-label="Edit {{ $module->title }}" title="Edit"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 20h4L19 9l-4-4L4 16v4zM14 6l4 4"/></svg></a>
                <form action="{{ route('modules.destroy', [$class->id, $module->id]) }}" method="POST"
                      onsubmit="return confirm('Delete the module “{{ addslashes($module->title) }}”? Its sections and attachments are deleted, and its labs are removed from the class so students can no longer open them.');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="ui-btn ui-btn-ghost ui-btn-sm !px-2 hover:!text-red-400" aria-label="Delete {{ $module->title }}" title="Delete"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12M9 7V4h6v3m-7 0l1 13h6l1-13"/></svg></button>
                </form>
            </div>
        @endif
    </div>

    @if ($labs->isNotEmpty())
        <ul class="pb-2 {{ $depth === 0 ? 'pl-[3.75rem] pr-5' : 'pl-[3.5rem] pr-5' }} space-y-1" role="list">
            @foreach ($labs as $lab)
                @php
                    $isDone = !$isStaff && isset($completedLabIds[$lab->id]);
                    $isLiveNow = $lab->isLiveLab() && $lab->isLiveActive();
                @endphp
                <li class="group/lab flex items-center gap-3 rounded-lg px-3 py-2 hover:bg-[#1c1c1c] transition-colors">
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
                    <a href="{{ route('laboratories.show', $lab->id) }}" class="min-w-0 flex-1 truncate text-sm text-[#d4d4d4] hover:text-[#3ecf8e] transition-colors">{{ $lab->title }}</a>
                    @if ($lab->isLiveLab())
                        <span class="ui-badge {{ $isLiveNow ? 'ui-badge-brand' : 'hidden sm:inline-flex' }} shrink-0 text-[11px]">
                            @if ($isLiveNow)<span class="h-1.5 w-1.5 rounded-full bg-[#3ecf8e] animate-pulse" aria-hidden="true"></span>@endif
                            {{ $isLiveNow ? 'Live now' : 'Live lab' }}
                        </span>
                    @endif
                    @if ($isStaff)
                        <span class="hidden md:inline text-xs text-[#888888] tabular-nums shrink-0">{{ number_format($lab->views_count) }} views &middot; {{ $lab->completed_count ?? 0 }} completed</span>
                        <div class="flex items-center gap-1 shrink-0 opacity-100 sm:opacity-0 sm:group-hover/lab:opacity-100 sm:group-focus-within/lab:opacity-100 transition-opacity">
                            <a href="{{ route('laboratories.edit', $lab->id) }}" class="ui-btn ui-btn-ghost ui-btn-sm !px-2" aria-label="Edit {{ $lab->title }}" title="Edit"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M4 20h4L19 9l-4-4L4 16v4zM14 6l4 4"/></svg></a>
                            <form action="{{ route('laboratories.destroy', $lab->id) }}" method="POST"
                                  onsubmit="return confirm('Delete the lab “{{ addslashes($lab->title) }}”? Student sessions for it are deleted too.');">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="ui-btn ui-btn-ghost ui-btn-sm !px-2 hover:!text-red-400" aria-label="Delete {{ $lab->title }}" title="Delete"><svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12M9 7V4h6v3m-7 0l1 13h6l1-13"/></svg></button>
                            </form>
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif

    @if ($children->isNotEmpty())
        <ul class="pb-3 ml-9 border-l border-[#2e2e2e] {{ $depth === 0 ? 'ml-[2.25rem]' : '' }}" role="list">
            @foreach ($children as $child)
                @include('classes._syllabus-module', ['module' => $child, 'depth' => $depth + 1])
            @endforeach
        </ul>
    @endif
</li>

{{--
    One module in the module page's outline, with its sub-modules (rendered recursively) as a
    branch of the .line-sidebar tree. Numbers match the class syllabus.
    Expects: $item, $module (the page's module), $class, $isStaff, $completedLabIds, $number, and
    query from the outline's x-data.
--}}
@php
    $itemProgress = !$isStaff ? $item->getStudentProgress(auth()->user(), $completedLabIds ?? null) : null;
    $children = $item->children->sortBy('order_index');
@endphp
<li class="line-sidebar__item" x-show="!query || $el.textContent.toLowerCase().includes(query.toLowerCase())">
    <div class="line-sidebar__row">
        <span class="line-sidebar__marker" aria-hidden="true"></span>
        <a href="{{ route('modules.show', [$class->id, $item->id]) }}" class="line-sidebar__link line-sidebar__link--module"
           @if ($item->id === $module->id) aria-current="page" @endif>
            <span class="line-sidebar__label">
                <span class="line-sidebar__index" aria-hidden="true">{{ $number }}</span>
                <span class="line-sidebar__text">{{ $item->title }}</span>
            </span>
        </a>
        @if ($itemProgress && $itemProgress['total'] > 0)
            <span class="ui-badge {{ $itemProgress['completed'] >= $itemProgress['total'] ? 'ui-badge-brand' : '' }} shrink-0 font-mono text-[11px]">
                {{ $itemProgress['completed'] }}/{{ $itemProgress['total'] }}
            </span>
        @endif
    </div>

    @if ($children->isNotEmpty())
        <ul class="line-sidebar__branch" role="list">
            @foreach ($children as $child)
                @include('classes._module-outline-item', ['item' => $child, 'number' => $number . '.' . ($item->laboratories->count() + $loop->iteration)])
            @endforeach
        </ul>
    @endif
</li>

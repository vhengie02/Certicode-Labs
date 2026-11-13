{{-- Student checkboxes for a team form. $choices: students who may be picked; $checked: ids ticked; $idPrefix keeps ids unique per form. Optional: $showEmail (off for students, who don't see classmates' addresses), $emptyText. --}}
@if ($choices->isEmpty())
    <p class="ui-hint">{{ $emptyText ?? 'Every enrolled student is already in a team.' }}</p>
@else
    <div class="mt-1 max-h-72 overflow-y-auto rounded-lg border border-[#2e2e2e] divide-y divide-[#242424]">
        @foreach ($choices as $student)
            <label for="{{ $idPrefix }}-student-{{ $student->id }}" class="flex cursor-pointer items-center gap-3 px-3 py-2 hover:bg-[#1a1a1a]">
                <input id="{{ $idPrefix }}-student-{{ $student->id }}" type="checkbox" name="members[]" value="{{ $student->id }}" @checked(in_array($student->id, $checked, true))
                       class="h-4 w-4 rounded border-[#3a3a3a] bg-[#141414] text-[#3ecf8e] focus:ring-[#3ecf8e]/40">
                <span class="min-w-0">
                    <span class="block truncate text-sm text-[#ededed]">{{ $student->name }}</span>
                    @if ($showEmail ?? true)
                        <span class="block truncate text-xs text-[#888888]">{{ $student->email }}</span>
                    @endif
                </span>
            </label>
        @endforeach
    </div>
@endif

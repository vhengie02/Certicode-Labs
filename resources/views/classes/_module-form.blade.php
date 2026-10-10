{{-- Module fields, shared by module-create and module-edit. $module is null when creating. --}}
@php
    $module = $module ?? null;
    $parents = $class->modules->where('parent_id', null)->when($module, fn ($c) => $c->where('id', '!=', $module->id))->sortBy('order_index');
    $selectedParent = old('parent_id', $module?->parent_id);
    // The server clamps order_index to the end of the list, so a large number means "last"
    $positionOptions = $module
        ? [(string) $module->order_index => 'Keep its current position', '0' => 'Move to the top', '9999' => 'Move to the end']
        : ['9999' => 'At the end', '0' => 'At the top'];
    $selectedPosition = (string) old('order_index', $module ? $module->order_index : 9999);
@endphp

<div class="ui-card">
    <div class="ui-card-header">
        <h2 class="ui-card-title">Lesson</h2>
        <p class="ui-card-subtitle">What students read before they start the labs in this module.</p>
    </div>
    <div class="ui-card-body space-y-5">
        <div>
            <label for="title" class="ui-label">Title</label>
            <input type="text" name="title" id="title" required maxlength="255" @if(!$module) autofocus @endif
                   value="{{ old('title', $module?->title) }}" placeholder="e.g. Module 1: Variables and types"
                   class="ui-input" @error('title') aria-invalid="true" @enderror>
            @error('title') <p class="ui-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="description" class="ui-label">Summary <span class="ui-optional">(optional)</span></label>
            <input type="text" name="description" id="description" value="{{ old('description', $module?->description) }}"
                   placeholder="One line shown under the title in the syllabus" class="ui-input">
            @error('description') <p class="ui-error">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="module-content" class="ui-label">Lesson content</label>
            <div class="module-editor">
                <textarea name="content" id="module-content" rows="12" class="ui-input">{{ old('content', $module?->content) }}</textarea>
            </div>
            @error('content') <p class="ui-error">{{ $message }}</p> @enderror
        </div>
    </div>
</div>

<div class="ui-card">
    <div class="ui-card-header">
        <h2 class="ui-card-title">Files</h2>
        <p class="ui-card-subtitle">Slides, readings or datasets students can download. Up to 20 MB each.</p>
    </div>
    <div class="ui-card-body space-y-4">
        @if ($module && $module->attachments->isNotEmpty())
            <ul class="space-y-2" role="list">
                @foreach ($module->attachments as $attachment)
                    <li class="flex items-center gap-3 rounded-lg border border-[#2e2e2e] bg-[#141414] px-3.5 py-2.5">
                        <svg class="w-4 h-4 shrink-0 text-[#888888]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5z M14 3v5h5"/></svg>
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-sm text-[#ededed]">{{ $attachment->file_name }}</span>
                            <span class="block text-xs text-[#888888]">{{ number_format($attachment->file_size / 1024, 1) }} KB</span>
                        </span>
                        <label class="inline-flex items-center gap-2 text-sm text-[#a3a3a3] cursor-pointer select-none has-[:checked]:text-red-400">
                            <input type="checkbox" name="remove_attachments[]" value="{{ $attachment->id }}" class="h-4 w-4 rounded border-[#3a3a3a] bg-[#141414] text-red-500 focus:ring-red-500/40">
                            Remove
                        </label>
                    </li>
                @endforeach
            </ul>
        @endif

        <label for="attachments" class="file-drop flex flex-col items-center justify-center gap-1.5 rounded-xl border border-dashed border-[#3a3a3a] bg-[#141414] px-4 py-7 text-center cursor-pointer hover:border-[#3ecf8e]/50 transition-colors">
            <svg class="w-6 h-6 text-[#888888]" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M12 16V4m0 0l-4 4m4-4l4 4M4 16v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/></svg>
            <span class="text-sm text-[#ededed]"><span class="font-medium text-[#3ecf8e]">Choose files</span> or drop them here</span>
            <span class="file-drop-list text-xs text-[#888888]">{{ $module ? 'Adds to the files above' : 'Optional' }}</span>
            <input type="file" name="attachments[]" id="attachments" multiple class="sr-only">
        </label>
        @error('attachments.*') <p class="ui-error">{{ $message }}</p> @enderror
    </div>
</div>

<div class="ui-card">
    <div class="ui-card-header">
        <h2 class="ui-card-title">Placement</h2>
        <p class="ui-card-subtitle">Where the module appears in the syllabus.</p>
    </div>
    <div class="ui-card-body grid grid-cols-1 sm:grid-cols-2 gap-5">
        <div>
            <label for="parent_id" class="ui-label">Belongs to</label>
            <select name="parent_id" id="parent_id" class="ui-input">
                <option value="">Top level (its own module)</option>
                @foreach ($parents as $parent)
                    <option value="{{ $parent->id }}" @selected((string) $selectedParent === (string) $parent->id)>Inside “{{ $parent->title }}”</option>
                @endforeach
            </select>
            <p class="ui-hint">Put it inside another module to make it a section of that module.</p>
            @error('parent_id') <p class="ui-error">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="order_index" class="ui-label">Position</label>
            <select name="order_index" id="order_index" class="ui-input">
                @foreach ($positionOptions as $value => $label)
                    <option value="{{ $value }}" @selected($selectedPosition === (string) $value)>{{ $label }}</option>
                @endforeach
            </select>
            <p class="ui-hint">Among modules at the same level.</p>
        </div>
    </div>
</div>


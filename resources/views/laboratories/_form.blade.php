{{-- Lab fields, shared by laboratories/create and laboratories/edit. $laboratory is null when creating. --}}
@php
    $laboratory = $laboratory ?? null;

    // Restore what was typed after a validation error, otherwise the saved lab, otherwise defaults
    $tasks = old('tasks') ?? (!empty($laboratory?->tasks_definition) ? $laboratory->tasks_definition : [['task' => '', 'command' => '']]);
    $tasks = array_values(array_map(fn ($t) => ['task' => $t['task'] ?? '', 'command' => $t['command'] ?? ''], $tasks));

    $files = old('starter_files') ?? ($laboratory ? $laboratory->getStarterFilesList() : [[
        'name' => 'Main.java',
        'content' => "public class Main {\n    public static void main(String[] args) {\n        System.out.println(\"Hello, Certicode!\");\n    }\n}\n",
        'is_primary' => true,
        'is_readonly' => false,
    ]]);
    $files = array_values(array_map(fn ($f) => [
        'name' => $f['name'] ?? '',
        'content' => $f['content'] ?? '',
        'is_primary' => filter_var($f['is_primary'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'is_readonly' => filter_var($f['is_readonly'] ?? false, FILTER_VALIDATE_BOOLEAN),
    ], $files ?: []));

    $formState = [
        'availabilityMode' => old('availability_mode', $laboratory?->availability_mode ?? 'open'),
        'tasks' => $tasks,
        'starterFiles' => $files,
    ];

    // Module choices, with sections shown under their parent module
    $moduleOptions = [];
    foreach ($class->modules->where('parent_id', null)->sortBy('order_index') as $top) {
        $moduleOptions[$top->id] = $top->title;
        foreach ($class->modules->where('parent_id', $top->id)->sortBy('order_index') as $child) {
            $moduleOptions[$child->id] = $top->title . ' › ' . $child->title;
        }
    }
    $selectedModule = old('module_id', $laboratory?->module_id ?? request('module'));
@endphp

<div class="space-y-5" x-data="labForm({{ \Illuminate\Support\Js::from($formState) }})">
    {{-- Basics --}}
    <div class="ui-card">
        <div class="ui-card-header">
            <h2 class="ui-card-title">Basics</h2>
            <p class="ui-card-subtitle">What students see when they open the lab.</p>
        </div>
        <div class="ui-card-body space-y-5">
            <div class="grid grid-cols-1 md:grid-cols-[minmax(0,1fr)_260px] gap-5">
                <div>
                    <label for="title" class="ui-label">Title</label>
                    <input type="text" name="title" id="title" required maxlength="255" @if(!$laboratory) autofocus @endif
                           value="{{ old('title', $laboratory?->title) }}" placeholder="e.g. Lab 1.3: Loop bounds"
                           class="ui-input" @error('title') aria-invalid="true" @enderror>
                    @error('title') <p class="ui-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="module_id" class="ui-label">Module</label>
                    <select name="module_id" id="module_id" required class="ui-input" @error('module_id') aria-invalid="true" @enderror>
                        <option value="" disabled @selected(!$selectedModule)>Choose a module</option>
                        @foreach ($moduleOptions as $id => $label)
                            <option value="{{ $id }}" @selected((string) $selectedModule === (string) $id)>{{ $label }}</option>
                        @endforeach
                    </select>
                    @if (empty($moduleOptions))
                        <p class="ui-hint">This class has no modules yet. <a href="{{ route('modules.create', $class->id) }}" class="text-[#3ecf8e] hover:underline">Add one first</a>.</p>
                    @endif
                    @error('module_id') <p class="ui-error">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label for="description" class="ui-label">Instructions</label>
                <textarea name="description" id="description" rows="6" required
                          placeholder="What students must build, the expected input and output, and any rules."
                          class="ui-input" @error('description') aria-invalid="true" @enderror>{{ old('description', $laboratory?->description) }}</textarea>
                <p class="ui-hint">The AI grader reads these instructions too, so be specific about what counts as correct.</p>
                @error('description') <p class="ui-error">{{ $message }}</p> @enderror
            </div>
        </div>
    </div>

    {{-- How students take it --}}
    <div class="ui-card">
        <div class="ui-card-header">
            <h2 class="ui-card-title">How students take it</h2>
            <p class="ui-card-subtitle">Self-paced practice, or a timed session you run for the whole class.</p>
        </div>
        <div class="ui-card-body space-y-5">
            <fieldset>
                <legend class="sr-only">Availability</legend>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    @foreach ([
                        'open' => ['Open lab', 'Self-paced', 'Students start whenever they like, each with their own timer.'],
                        'live' => ['Live lab', 'Shared timer', 'Locked until you start it. Everyone shares one countdown, and work is submitted automatically when it ends.'],
                    ] as $value => [$label, $tag, $text])
                        <label class="relative flex cursor-pointer flex-col rounded-xl border p-4 transition-colors"
                               :class="availabilityMode === '{{ $value }}' ? 'border-[#3ecf8e] bg-[#3ecf8e]/[0.05]' : 'border-[#2e2e2e] bg-[#141414] hover:border-[#383838]'">
                            <span class="flex items-center justify-between gap-3">
                                <span class="flex items-center gap-2.5">
                                    <input type="radio" name="availability_mode" value="{{ $value }}" x-model="availabilityMode" class="h-4 w-4 border-[#3a3a3a] bg-[#141414] text-[#3ecf8e] focus:ring-[#3ecf8e]/40">
                                    <span class="text-sm font-semibold text-[#ededed]">{{ $label }}</span>
                                </span>
                                <span class="ui-badge text-[11px]" :class="availabilityMode === '{{ $value }}' && 'ui-badge-brand'">{{ $tag }}</span>
                            </span>
                            <span class="mt-2 pl-[1.6rem] text-[13px] leading-relaxed text-[#888888]">{{ $text }}</span>
                        </label>
                    @endforeach
                </div>
            </fieldset>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <div x-show="availabilityMode === 'live'" x-cloak>
                    <label for="live_duration_minutes" class="ui-label">Live session length</label>
                    <div class="relative">
                        <input type="number" name="live_duration_minutes" id="live_duration_minutes" min="1" max="600" inputmode="numeric"
                               value="{{ old('live_duration_minutes', $laboratory?->live_duration_minutes ?? $laboratory?->time_limit ?? 60) }}"
                               class="ui-input pr-14" @error('live_duration_minutes') aria-invalid="true" @enderror>
                        <span class="absolute inset-y-0 right-3 flex items-center text-sm text-[#888888] pointer-events-none" aria-hidden="true">min</span>
                    </div>
                    <p class="ui-hint">The shared countdown. You can change it when you start the lab.</p>
                    @error('live_duration_minutes') <p class="ui-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="time_limit" class="ui-label">Time per student</label>
                    <div class="relative">
                        <input type="number" name="time_limit" id="time_limit" required min="5" max="300" inputmode="numeric"
                               value="{{ old('time_limit', $laboratory?->time_limit ?? 60) }}"
                               class="ui-input pr-14" @error('time_limit') aria-invalid="true" @enderror>
                        <span class="absolute inset-y-0 right-3 flex items-center text-sm text-[#888888] pointer-events-none" aria-hidden="true">min</span>
                    </div>
                    <p class="ui-hint">How long each student's session can run, 5 to 300 minutes.</p>
                    @error('time_limit') <p class="ui-error">{{ $message }}</p> @enderror
                </div>
            </div>

            <label class="flex cursor-pointer items-start gap-3 rounded-xl border border-[#2e2e2e] bg-[#141414] p-4 hover:border-[#383838] transition-colors">
                <input id="is_group_lab" name="is_group_lab" type="checkbox" value="1" @checked(old('is_group_lab', $laboratory?->is_group_lab))
                       class="mt-0.5 h-4 w-4 rounded border-[#3a3a3a] bg-[#141414] text-[#3ecf8e] focus:ring-[#3ecf8e]/40">
                <span>
                    <span class="block text-sm font-medium text-[#ededed]">Group lab</span>
                    <span class="block mt-0.5 text-[13px] text-[#888888]">Students work in teams on one shared workspace, and you see each teammate's contribution.</span>
                </span>
            </label>
        </div>
    </div>

    {{-- Starter files --}}
    <div class="ui-card">
        <div class="ui-card-header flex items-start justify-between gap-4">
            <div>
                <h2 class="ui-card-title">Starter files</h2>
                <p class="ui-card-subtitle">Created in each student's VS Code workspace when they start the lab.</p>
            </div>
            <button type="button" @click="addFile()" class="ui-btn ui-btn-secondary ui-btn-sm shrink-0">Add file</button>
        </div>
        <div class="ui-card-body space-y-3">
            <template x-for="(file, index) in starterFiles" :key="index">
                <div class="rounded-xl border border-[#2e2e2e] bg-[#141414] overflow-hidden">
                    <div class="flex flex-wrap items-center gap-3 border-b border-[#232323] px-3 py-2.5">
                        <label class="sr-only" :for="`starter-name-${index}`">File name</label>
                        <input type="text" :id="`starter-name-${index}`" :name="`starter_files[${index}][name]`" x-model="file.name" required placeholder="Main.java"
                               class="ui-input ui-input-mono !min-h-0 !h-8 flex-1 min-w-[10rem] !bg-[#171717]">
                        <label class="inline-flex items-center gap-1.5 text-[13px] cursor-pointer select-none" :class="file.is_primary ? 'text-[#3ecf8e]' : 'text-[#888888]'">
                            <input type="radio" name="primary_file_selector" :checked="file.is_primary" @change="setPrimary(index)" class="h-3.5 w-3.5 border-[#3a3a3a] bg-[#141414] text-[#3ecf8e] focus:ring-[#3ecf8e]/40">
                            Opens first
                        </label>
                        <input type="hidden" :name="`starter_files[${index}][is_primary]`" :value="file.is_primary ? '1' : '0'">
                        <label class="inline-flex items-center gap-1.5 text-[13px] text-[#888888] cursor-pointer select-none">
                            <input type="checkbox" :name="`starter_files[${index}][is_readonly]`" value="1" x-model="file.is_readonly" class="h-3.5 w-3.5 rounded border-[#3a3a3a] bg-[#141414] text-[#3ecf8e] focus:ring-[#3ecf8e]/40">
                            Read-only
                        </label>
                        <button type="button" @click="removeFile(index)" :disabled="starterFiles.length <= 1"
                                class="ui-btn ui-btn-ghost ui-btn-sm !px-2 hover:!text-red-400" :aria-label="`Remove ${file.name || 'file'}`" title="Remove file">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12M9 7V4h6v3m-7 0l1 13h6l1-13"/></svg>
                        </button>
                    </div>
                    <label class="sr-only" :for="`starter-content-${index}`">File contents</label>
                    <textarea :id="`starter-content-${index}`" :name="`starter_files[${index}][content]`" x-model="file.content" rows="8" spellcheck="false"
                              placeholder="// Starter code students begin with"
                              class="block w-full resize-y border-0 bg-[#101010] px-4 py-3 font-mono text-[13px] leading-relaxed text-[#ededed] placeholder-[#555555] focus:outline-none focus:ring-0"></textarea>
                </div>
            </template>
            <p class="ui-hint">"Opens first" is the file VS Code shows when the lab starts. "Read-only" labels a file students shouldn't change in their sidebar; it doesn't block edits.</p>
        </div>
    </div>

    {{-- Tasks --}}
    <div class="ui-card">
        <div class="ui-card-header flex items-start justify-between gap-4">
            <div>
                <h2 class="ui-card-title">Tasks</h2>
                <p class="ui-card-subtitle">The checklist students work through. The grader marks each one as done or not.</p>
            </div>
            <button type="button" @click="addTask()" class="ui-btn ui-btn-secondary ui-btn-sm shrink-0">Add task</button>
        </div>
        <div class="ui-card-body">
            <ol class="space-y-3" role="list">
                <template x-for="(task, index) in tasks" :key="index">
                    <li class="flex gap-3 rounded-xl border border-[#2e2e2e] bg-[#141414] p-3.5">
                        <span class="mt-2 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-[#232323] font-mono text-xs text-[#a3a3a3]" x-text="index + 1" aria-hidden="true"></span>
                        <div class="grid min-w-0 flex-1 grid-cols-1 md:grid-cols-2 gap-3">
                            <div>
                                <label class="ui-label !text-xs !text-[#a3a3a3]" :for="`task-${index}`">Task</label>
                                <input type="text" :id="`task-${index}`" :name="`tasks[${index}][task]`" x-model="task.task" required maxlength="255"
                                       placeholder="e.g. Print the numbers 1 to 10 with a for loop" class="ui-input !bg-[#171717]">
                            </div>
                            <div>
                                <label class="ui-label !text-xs !text-[#a3a3a3]" :for="`check-${index}`">Check <span class="ui-optional">(optional)</span></label>
                                <input type="text" :id="`check-${index}`" :name="`tasks[${index}][command]`" x-model="task.command" maxlength="255"
                                       placeholder="e.g. for (int i" class="ui-input ui-input-mono !bg-[#171717]">
                            </div>
                        </div>
                        <button type="button" @click="removeTask(index)" :disabled="tasks.length <= 1"
                                class="ui-btn ui-btn-ghost ui-btn-sm !px-2 mt-6 hover:!text-red-400" :aria-label="`Remove task ${index + 1}`" title="Remove task">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M6 7h12M9 7V4h6v3m-7 0l1 13h6l1-13"/></svg>
                        </button>
                    </li>
                </template>
            </ol>
            <p class="ui-hint mt-3">
                A check is text that must appear in the student's code (case and spacing don't matter). Start it with
                <code class="font-mono text-[#a3a3a3]">regex:</code> to match a pattern instead. Leave it empty to let the grader judge the task from its description.
            </p>
            @error('tasks.*.task') <p class="ui-error">Every task needs a description.</p> @enderror
        </div>
    </div>

    {{-- Advanced --}}
    <details class="ui-card group" @if(old('github_repo_template', $laboratory?->github_repo_template)) open @endif>
        <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-6 py-4">
            <span>
                <span class="ui-card-title">Advanced</span>
                <span class="block ui-card-subtitle">GitHub template repository.</span>
            </span>
            <svg class="w-4 h-4 text-[#888888] transition-transform group-open:rotate-180" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"/></svg>
        </summary>
        <div class="px-6 pb-6">
            <label for="github_repo_template" class="ui-label">GitHub template <span class="ui-optional">(optional)</span></label>
            <input type="text" name="github_repo_template" id="github_repo_template" placeholder="owner/repository"
                   value="{{ old('github_repo_template', $laboratory?->github_repo_template) }}" class="ui-input ui-input-mono">
            <p class="ui-hint">Saved with the lab for reference. Student workspaces are created from the starter files above, not from this repository.</p>
            @error('github_repo_template') <p class="ui-error">{{ $message }}</p> @enderror
        </div>
    </details>
</div>

@extends('layouts.app')

@section('title', 'Edit Laboratory')
@section('page_header', 'Modify Coding Laboratory Specifications')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="rounded-xl p-8 bg-[#171717] border border-[#2e2e2e]">
        <div class="mb-6">
            <span class="text-[10px] font-mono uppercase tracking-wider text-[#3ecf8e] block">Class: {{ $class->name }}</span>
            <h2 class="text-lg font-bold text-[#ededed] mt-0.5">Modify Laboratory Specifications</h2>
        </div>

        <form action="{{ route('laboratories.update', $laboratory->id) }}" method="POST" class="space-y-6" 
              x-data="{ 
                  availabilityMode: '{{ old('availability_mode', $laboratory->availability_mode ?? 'open') }}',
                  tasks: {{ json_encode(!empty($laboratory->tasks_definition) ? $laboratory->tasks_definition : [['task' => '', 'command' => '']]) }},
                  starterFiles: {{ json_encode($laboratory->getStarterFilesList()) }},
                  setPrimary(idx) {
                      this.starterFiles.forEach((f, i) => f.is_primary = (i === idx));
                  },
                  addFile() {
                      this.starterFiles.push({ name: '', content: '', is_primary: false, is_readonly: false });
                  },
                  removeFile(idx) {
                      if (this.starterFiles.length > 1) {
                          const wasPrimary = this.starterFiles[idx].is_primary;
                          this.starterFiles.splice(idx, 1);
                          if (wasPrimary && this.starterFiles.length > 0) {
                              this.starterFiles[0].is_primary = true;
                          }
                      }
                  }
              }">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Title -->
                <div class="col-span-1 md:col-span-2">
                    <label for="title" class="block text-xs font-mono uppercase tracking-wider text-[#a3a3a3] mb-2">Laboratory Title</label>
                    <input type="text" name="title" id="title" required value="{{ old('title', $laboratory->title) }}"
                        class="w-full px-3.5 py-2.5 bg-[#141414] border border-[#2e2e2e] rounded-[6px] text-sm text-[#ededed] placeholder-[#666666] focus:outline-none focus:border-[#3ecf8e] focus:ring-1 focus:ring-[#3ecf8e] transition-colors">
                    @error('title') <p class="text-red-400 text-xs mt-1 font-mono">{{ $message }}</p> @enderror
                </div>

                <!-- Target Module Selection -->
                <div class="col-span-1 md:col-span-2">
                    <label for="module_id" class="block text-xs font-mono uppercase tracking-wider text-[#a3a3a3] mb-2">Select Target Class Module</label>
                    <select name="module_id" id="module_id" required 
                            class="w-full px-3.5 py-2.5 bg-[#141414] border border-[#2e2e2e] rounded-[6px] text-sm text-[#ededed] focus:outline-none focus:border-[#3ecf8e] focus:ring-1 focus:ring-[#3ecf8e] transition-colors">
                        <option value="" disabled selected class="bg-[#141414] text-[#888888]">Choose module...</option>
                        @foreach($class->modules as $mod)
                            <option value="{{ $mod->id }}" {{ old('module_id', $laboratory->module_id) == $mod->id ? 'selected' : '' }} class="bg-[#141414] text-[#ededed]">{{ $mod->title }}</option>
                        @endforeach
                    </select>
                    @error('module_id') <p class="text-red-400 text-xs mt-1 font-mono">{{ $message }}</p> @enderror
                </div>
            </div>

            <!-- Description -->
            <div>
                <label for="description" class="block text-xs font-mono uppercase tracking-wider text-[#a3a3a3] mb-2">Detailed Instructions & Problem Statement</label>
                <textarea name="description" id="description" rows="4" required
                    class="w-full px-3.5 py-2.5 bg-[#141414] border border-[#2e2e2e] rounded-[6px] text-sm text-[#ededed] placeholder-[#666666] focus:outline-none focus:border-[#3ecf8e] focus:ring-1 focus:ring-[#3ecf8e] transition-colors">{{ old('description', $laboratory->description) }}</textarea>
                @error('description') <p class="text-red-400 text-xs mt-1 font-mono">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Time Limit -->
                <div>
                    <label for="time_limit" class="block text-xs font-mono uppercase tracking-wider text-[#a3a3a3] mb-2">Time Limit (Minutes)</label>
                    <input type="number" name="time_limit" id="time_limit" required min="5" max="300" value="{{ old('time_limit', $laboratory->time_limit) }}" 
                        class="w-full px-3.5 py-2.5 bg-[#141414] border border-[#2e2e2e] rounded-[6px] text-sm text-[#ededed] focus:outline-none focus:border-[#3ecf8e] focus:ring-1 focus:ring-[#3ecf8e] transition-colors">
                    @error('time_limit') <p class="text-red-400 text-xs mt-1 font-mono">{{ $message }}</p> @enderror
                </div>

                <!-- GitHub Template -->
                <div>
                    <label for="github_repo_template" class="block text-xs font-mono uppercase tracking-wider text-[#a3a3a3] mb-2">GitHub Template Repository</label>
                    <input type="text" name="github_repo_template" id="github_repo_template" placeholder="owner/repository" value="{{ old('github_repo_template', $laboratory->github_repo_template) }}" 
                        class="w-full px-3.5 py-2.5 bg-[#141414] border border-[#2e2e2e] rounded-[6px] text-sm text-[#ededed] placeholder-[#666666] focus:outline-none focus:border-[#3ecf8e] focus:ring-1 focus:ring-[#3ecf8e] transition-colors">
                    @error('github_repo_template') <p class="text-red-400 text-xs mt-1 font-mono">{{ $message }}</p> @enderror
                </div>
            </div>

            <!-- Availability Mode Selection (Feature 9: Live Lab vs. Open Lab) -->
            <div class="col-span-1 md:col-span-2">
                <label class="block text-xs font-mono uppercase tracking-wider text-[#a3a3a3] mb-2">Lab Availability Mode</label>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <label class="relative flex flex-col p-4 rounded-[6px] border cursor-pointer transition-all"
                           :class="availabilityMode === 'open' ? 'border-[#3ecf8e] bg-[#3ecf8e]/5' : 'border-[#2e2e2e] bg-[#141414] hover:border-[#383838]'">
                        <div class="flex items-center justify-between mb-1.5">
                            <div class="flex items-center gap-2">
                                <input type="radio" name="availability_mode" value="open" x-model="availabilityMode" class="text-[#3ecf8e] focus:ring-[#3ecf8e]">
                                <span class="text-sm font-bold text-[#ededed]">Open Lab</span>
                            </div>
                            <span class="px-2 py-0.5 text-[10px] font-mono rounded bg-[#2e2e2e] text-[#a3a3a3]">Self-Paced</span>
                        </div>
                        <p class="text-xs text-[#888888] leading-relaxed">
                            No time gating. Students can start whenever they choose with independent session timers. Closes only when manually ended or when course completes.
                        </p>
                    </label>

                    <label class="relative flex flex-col p-4 rounded-[6px] border cursor-pointer transition-all"
                           :class="availabilityMode === 'live' ? 'border-[#3ecf8e] bg-[#3ecf8e]/5' : 'border-[#2e2e2e] bg-[#141414] hover:border-[#383838]'">
                        <div class="flex items-center justify-between mb-1.5">
                            <div class="flex items-center gap-2">
                                <input type="radio" name="availability_mode" value="live" x-model="availabilityMode" class="text-[#3ecf8e] focus:ring-[#3ecf8e]">
                                <span class="text-sm font-bold text-[#ededed]">Live Lab</span>
                            </div>
                            <span class="px-2 py-0.5 text-[10px] font-mono rounded bg-[#3ecf8e]/20 text-[#3ecf8e]">Shared Countdown</span>
                        </div>
                        <p class="text-xs text-[#888888] leading-relaxed">
                            Manual start trigger. Lab remains locked until instructor opens it. All students share the same countdown clock. Auto-closes when duration reaches zero.
                        </p>
                    </label>
                </div>
            </div>

            <!-- Live Lab Duration Window Configuration -->
            <div x-show="availabilityMode === 'live'" x-cloak class="p-4 rounded-[6px] bg-[#141414] border border-[#2e2e2e]">
                <label for="live_duration_minutes" class="block text-xs font-mono uppercase tracking-wider text-[#3ecf8e] mb-2">
                    Live Lab Window Duration (Minutes)
                </label>
                <div class="flex flex-col sm:flex-row sm:items-center gap-3">
                    <input type="number" name="live_duration_minutes" id="live_duration_minutes" min="1" max="600" value="{{ old('live_duration_minutes', $laboratory->live_duration_minutes ?? $laboratory->time_limit ?? 60) }}"
                           class="w-full sm:w-48 px-3.5 py-2.5 bg-[#0d0d0d] border border-[#2e2e2e] rounded-[6px] text-sm text-[#ededed] focus:outline-none focus:border-[#3ecf8e] font-mono">
                    <span class="text-xs text-[#888888]">Fixed duration window shared across all students upon manual launch.</span>
                </div>
            </div>

            <!-- Group lab checkbox -->
            <div class="flex items-center">
                <input id="is_group_lab" name="is_group_lab" type="checkbox" value="1" {{ old('is_group_lab', $laboratory->is_group_lab) ? 'checked' : '' }}
                    class="h-4 w-4 bg-[#141414] border-[#2e2e2e] text-[#3ecf8e] focus:ring-[#3ecf8e] rounded-[4px]">
                <label for="is_group_lab" class="ml-2.5 block text-xs font-mono uppercase tracking-wider text-[#ededed] cursor-pointer">
                    Enable Collaborative / Group Lab Activity
                </label>
            </div>

            <!-- Starter Files Manager (Multi-File Starter Boilerplate) -->
            <div class="border-t border-[#232323] pt-6">
                <div class="flex items-center justify-between mb-4">
                    <div>
                        <span class="block text-xs font-mono uppercase tracking-wider text-[#a3a3a3]">Auto-Generated Starter Files</span>
                        <p class="text-[11px] text-[#666666] mt-0.5">Files automatically created in student workspace when launched via VS Code extension.</p>
                    </div>
                    <button type="button" @click="addFile()" 
                        class="inline-flex items-center px-3 py-1.5 border border-[#2e2e2e] text-xs font-mono font-medium rounded-[6px] text-[#3ecf8e] bg-[#141414] hover:bg-[#202020] hover:border-[#3ecf8e]/35 transition-colors">
                        + Add Starter File
                    </button>
                </div>

                <div class="space-y-4">
                    <template x-for="(file, index) in starterFiles" :key="index">
                        <div class="p-4 rounded-[6px] bg-[#141414] border border-[#2e2e2e] space-y-3 relative">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-[#232323]">
                                <div class="flex-1">
                                    <label class="block text-[10px] font-mono uppercase tracking-wider text-[#888888]">File Name</label>
                                    <input type="text" :name="`starter_files[${index}][name]`" x-model="file.name" required placeholder="e.g. TaskManager.java"
                                        class="mt-1 block w-full px-3 py-1.5 bg-[#171717] border border-[#2e2e2e] rounded-[6px] text-xs text-[#ededed] font-mono focus:outline-none focus:border-[#3ecf8e]">
                                </div>
                                <div class="flex items-center space-x-4 pt-3 sm:pt-4">
                                    <label class="inline-flex items-center cursor-pointer text-xs font-mono text-[#a3a3a3]">
                                        <input type="radio" name="primary_file_selector" :checked="file.is_primary" @change="setPrimary(index)" class="text-[#3ecf8e] focus:ring-0">
                                        <span class="ml-1.5 text-[11px]" :class="file.is_primary ? 'text-[#3ecf8e] font-bold' : 'text-[#888888]'">Primary (Auto-Open)</span>
                                        <input type="hidden" :name="`starter_files[${index}][is_primary]`" :value="file.is_primary ? '1' : '0'">
                                    </label>
                                    <label class="inline-flex items-center cursor-pointer text-xs font-mono text-[#a3a3a3]">
                                        <input type="checkbox" :name="`starter_files[${index}][is_readonly]`" value="1" x-model="file.is_readonly" class="rounded bg-[#171717] border-[#2e2e2e] text-[#3ecf8e] focus:ring-0">
                                        <span class="ml-1.5 text-[11px] text-[#888888]">Read-Only</span>
                                    </label>
                                    <button type="button" @click="removeFile(index)" 
                                        class="p-1.5 text-red-400 hover:text-red-300 hover:bg-[#171717] rounded-[4px] transition-colors"
                                        :class="starterFiles.length <= 1 ? 'opacity-30 cursor-not-allowed' : ''" :disabled="starterFiles.length <= 1">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                </div>
                            </div>

                            <div>
                                <label class="block text-[10px] font-mono uppercase tracking-wider text-[#888888] mb-1">Starter Code / Boilerplate Content</label>
                                <textarea :name="`starter_files[${index}][content]`" x-model="file.content" rows="6" placeholder="// Write starter code and instructions here..."
                                    class="w-full px-3 py-2 bg-[#101010] border border-[#2e2e2e] rounded-[6px] text-xs font-mono text-[#ededed] placeholder-[#555555] focus:outline-none focus:border-[#3ecf8e]"></textarea>
                            </div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Dynamic Task List Definition (Alpine.js) -->
            <div class="border-t border-[#232323] pt-6">
                <div class="flex items-center justify-between mb-4">
                    <span class="block text-xs font-mono uppercase tracking-wider text-[#a3a3a3]">Competency Verification Checklist Tasks</span>
                    <button type="button" @click="tasks.push({ task: '', command: '' })" 
                        class="inline-flex items-center px-3 py-1.5 border border-[#2e2e2e] text-xs font-mono font-medium rounded-[6px] text-[#3ecf8e] bg-[#141414] hover:bg-[#202020] hover:border-[#3ecf8e]/35 transition-colors">
                        + Add Task
                    </button>
                </div>

                <div class="space-y-4">
                    <template x-for="(task, index) in tasks" :key="index">
                        <div class="p-4 rounded-[6px] bg-[#141414] border border-[#2e2e2e] flex items-start space-x-4 relative">
                            <div class="flex-grow grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[10px] font-mono uppercase tracking-wider text-[#888888]">Task Objective Description</label>
                                    <input type="text" :name="`tasks[${index}][task]`" x-model="task.task" required placeholder="e.g. Create a directory named '/var/www'"
                                        class="mt-1.5 block w-full px-3 py-2 bg-[#171717] border border-[#2e2e2e] rounded-[6px] text-xs text-[#ededed] focus:outline-none focus:border-[#3ecf8e]">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-mono uppercase tracking-wider text-[#888888]">Verification Shell Command (Validation)</label>
                                    <input type="text" :name="`tasks[${index}][command]`" x-model="task.command" placeholder="e.g. [ -d /var/www ]"
                                        class="mt-1.5 block w-full px-3 py-2 bg-[#171717] border border-[#2e2e2e] rounded-[6px] text-xs text-[#3ecf8e] focus:outline-none focus:border-[#3ecf8e] font-mono">
                                </div>
                            </div>
                            
                            <button type="button" @click="if(tasks.length > 1) tasks.splice(index, 1)" 
                                class="mt-5 p-2 text-red-400 hover:text-red-300 hover:bg-[#171717] rounded-[4px] transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                            </button>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Action buttons -->
            <div class="flex justify-end space-x-3 pt-6 border-t border-[#232323]">
                <a href="{{ route('classes.show', $class->id) }}" class="px-4 py-2 border border-[#2e2e2e] text-xs font-mono font-medium rounded-[6px] text-[#a3a3a3] bg-[#171717] hover:bg-[#222222] hover:text-[#ededed] transition-colors">
                    Cancel
                </a>
                <button type="submit" class="px-5 py-2 text-xs font-semibold rounded-full text-[#0f0f0f] bg-[#3ecf8e] hover:bg-[#00c573] transition-colors shadow-none">
                    Update Specifications &rarr;
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
@endsection

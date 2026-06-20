@extends('layouts.app')

@section('title', 'Edit Laboratory')
@section('page_header', 'Modify Coding Laboratory Specifications')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="glass-panel rounded-lg p-8 border border-slate-800">
        <div class="mb-6">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Class: {{ $class->name }}</span>
            <h2 class="text-base font-bold text-white mt-1">Modify Laboratory Specifications</h2>
        </div>

        <form action="{{ route('laboratories.update', $laboratory->id) }}" method="POST" class="space-y-6" 
              x-data="{ tasks: {{ json_encode(!empty($laboratory->tasks_definition) ? $laboratory->tasks_definition : [['task' => '', 'command' => '']]) }} }">
            @csrf
            @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Title -->
                <div class="col-span-1 md:col-span-2">
                    <label for="title" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Laboratory Title</label>
                    <input type="text" name="title" id="title" required value="{{ old('title', $laboratory->title) }}"
                        class="w-full px-4 py-3 bg-slate-900 border border-slate-800 rounded-lg text-sm text-white focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-colors">
                    @error('title') <p class="text-rose-400 text-xs mt-1 font-mono">{{ $message }}</p> @enderror
                </div>

                <!-- Target Module Selection -->
                <div class="col-span-1 md:col-span-2">
                    <label for="module_id" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Select Target Class Module</label>
                    <select name="module_id" id="module_id" required 
                            class="w-full px-4 py-3 bg-slate-900 border border-slate-800 rounded-lg text-sm text-slate-300 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-colors">
                        <option value="" disabled selected>Choose module...</option>
                        @foreach($class->modules as $mod)
                            <option value="{{ $mod->id }}" {{ old('module_id', $laboratory->module_id) == $mod->id ? 'selected' : '' }}>{{ $mod->title }}</option>
                        @endforeach
                    </select>
                    @error('module_id') <p class="text-rose-400 text-xs mt-1 font-mono">{{ $message }}</p> @enderror
                </div>
            </div>

            <!-- Description -->
            <div>
                <label for="description" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Detailed Instructions & Problem Statement</label>
                <textarea name="description" id="description" rows="4" required
                    class="w-full px-4 py-3 bg-slate-900 border border-slate-800 rounded-lg text-sm text-white focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-colors">{{ old('description', $laboratory->description) }}</textarea>
                @error('description') <p class="text-rose-400 text-xs mt-1 font-mono">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Time Limit -->
                <div>
                    <label for="time_limit" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Time Limit (Minutes)</label>
                    <input type="number" name="time_limit" id="time_limit" required min="5" max="300" value="{{ old('time_limit', $laboratory->time_limit) }}" 
                        class="w-full px-4 py-3 bg-slate-900 border border-slate-800 rounded-lg text-sm text-white focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-colors">
                    @error('time_limit') <p class="text-rose-400 text-xs mt-1 font-mono">{{ $message }}</p> @enderror
                </div>

                <!-- GitHub Template -->
                <div>
                    <label for="github_repo_template" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">GitHub Template Repository</label>
                    <input type="text" name="github_repo_template" id="github_repo_template" placeholder="owner/repository" value="{{ old('github_repo_template', $laboratory->github_repo_template) }}" 
                        class="w-full px-4 py-3 bg-slate-900 border border-slate-800 rounded-lg text-sm text-white focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-colors">
                    @error('github_repo_template') <p class="text-rose-400 text-xs mt-1 font-mono">{{ $message }}</p> @enderror
                </div>
            </div>

            <!-- Group lab checkbox -->
            <div class="flex items-center">
                <input id="is_group_lab" name="is_group_lab" type="checkbox" value="1" {{ old('is_group_lab', $laboratory->is_group_lab) ? 'checked' : '' }}
                    class="h-4 w-4 bg-slate-950 border-slate-800 text-blue-600 focus:ring-blue-500 rounded">
                <label for="is_group_lab" class="ml-2.5 block text-xs font-semibold text-slate-300 uppercase tracking-wider cursor-pointer">
                    Enable Collaborative / Group Lab Activity
                </label>
            </div>

            <!-- Dynamic Task List Definition (Alpine.js) -->
            <div class="border-t border-slate-800/80 pt-6">
                <div class="flex items-center justify-between mb-4">
                    <span class="block text-xs font-bold uppercase tracking-wider text-slate-400">Competency Verification Checklist Tasks</span>
                    <button type="button" @click="tasks.push({ task: '', command: '' })" 
                        class="inline-flex items-center px-3 py-1.5 border border-slate-800 text-xs font-semibold rounded-lg text-blue-500 bg-slate-900 hover:bg-slate-850 transition-colors">
                        + Add Task
                    </button>
                </div>

                <div class="space-y-4">
                    <template x-for="(task, index) in tasks" :key="index">
                        <div class="p-4 rounded-lg bg-slate-950 border border-slate-800 flex items-start space-x-4 relative">
                            <div class="flex-grow grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[10px] font-semibold text-slate-500 uppercase tracking-wider">Task Objective Description</label>
                                    <input type="text" :name="`tasks[${index}][task]`" x-model="task.task" required placeholder="e.g. Create a directory named '/var/www'"
                                        class="mt-1.5 block w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded-lg text-xs text-white focus:outline-none focus:border-blue-500">
                                </div>
                                <div>
                                    <label class="block text-[10px] font-semibold text-slate-500 uppercase tracking-wider">Verification Shell Command (Validation)</label>
                                    <input type="text" :name="`tasks[${index}][command]`" x-model="task.command" placeholder="e.g. [ -d /var/www ]"
                                        class="mt-1.5 block w-full px-3 py-2 bg-slate-900 border border-slate-800 rounded-lg text-xs text-white focus:outline-none focus:border-blue-500 font-mono">
                                </div>
                            </div>
                            
                            <button type="button" @click="if(tasks.length > 1) tasks.splice(index, 1)" 
                                class="mt-5 p-2 text-rose-450 hover:text-rose-450 hover:bg-slate-900 rounded-lg transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                            </button>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Action buttons -->
            <div class="flex justify-end space-x-3 pt-6 border-t border-slate-800/80">
                <a href="{{ route('classes.show', $class->id) }}" class="px-4 py-2 border border-slate-800 text-xs font-semibold rounded-lg text-slate-300 bg-slate-900 hover:bg-slate-855 transition-colors">
                    Cancel
                </a>
                <button type="submit" class="px-4 py-2 border border-transparent text-xs font-semibold rounded-lg text-white bg-green-600 hover:bg-green-500 transition-colors shadow-lg shadow-green-500/20">
                    Update Specifications
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
@endsection

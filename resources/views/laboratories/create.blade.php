@extends('layouts.app')

@section('title', 'Create Laboratory')
@section('page_header', 'Create Laboratory Exercise')

@section('content')
<div class="max-w-4xl mx-auto">
    <div class="glass-panel rounded-2xl p-8 border border-slate-800">
        <h2 class="text-xl font-extrabold text-white mb-6">New Laboratory Specifications</h2>

        <form action="{{ route('laboratories.store') }}" method="POST" class="space-y-6" x-data="{ tasks: [{ task: '', command: '' }] }">
            @csrf

            <!-- Title -->
            <div>
                <label for="title" class="block text-sm font-semibold text-slate-300">Laboratory Title</label>
                <input type="text" name="title" id="title" required value="{{ old('title') }}" 
                    class="mt-2 block w-full px-4 py-3 bg-slate-900 border border-slate-700 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                @error('title') <p class="text-rose-400 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <!-- Description -->
            <div>
                <label for="description" class="block text-sm font-semibold text-slate-300">Detailed Description & Instructions</label>
                <textarea name="description" id="description" rows="4" required
                    class="mt-2 block w-full px-4 py-3 bg-slate-900 border border-slate-700 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">{{ old('description') }}</textarea>
                @error('description') <p class="text-rose-400 text-xs mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Time Limit -->
                <div>
                    <label for="time_limit" class="block text-sm font-semibold text-slate-300">Time Limit (Minutes)</label>
                    <input type="number" name="time_limit" id="time_limit" required min="5" max="300" value="{{ old('time_limit', 60) }}" 
                        class="mt-2 block w-full px-4 py-3 bg-slate-900 border border-slate-700 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                    @error('time_limit') <p class="text-rose-400 text-xs mt-1">{{ $message }}</p> @enderror
                </div>

                <!-- GitHub Template -->
                <div>
                    <label for="github_repo_template" class="block text-sm font-semibold text-slate-300">GitHub Template Repository</label>
                    <input type="text" name="github_repo_template" id="github_repo_template" placeholder="owner/repo" value="{{ old('github_repo_template') }}" 
                        class="mt-2 block w-full px-4 py-3 bg-slate-900 border border-slate-700 rounded-xl text-white focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent transition-all">
                    @error('github_repo_template') <p class="text-rose-400 text-xs mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <!-- Group lab checkbox -->
            <div class="flex items-center">
                <input id="is_group_lab" name="is_group_lab" type="checkbox" value="1" {{ old('is_group_lab') ? 'checked' : '' }}
                    class="h-4 w-4 bg-slate-900 border-slate-700 text-indigo-600 focus:ring-indigo-500 rounded">
                <label for="is_group_lab" class="ml-2.5 block text-sm font-semibold text-slate-300">
                    Enable Collaborative / Group Lab Activity
                </label>
            </div>

            <!-- Dynamic Task List Definition (Alpine.js) -->
            <div class="border-t border-slate-800/80 pt-6">
                <div class="flex items-center justify-between mb-4">
                    <span class="block text-sm font-bold uppercase tracking-wider text-indigo-400">Competency Verification Checklist Tasks</span>
                    <button type="button" @click="tasks.push({ task: '', command: '' })" 
                        class="inline-flex items-center px-3 py-1.5 border border-indigo-500/20 text-xs font-semibold rounded-lg text-indigo-400 bg-indigo-500/5 hover:bg-indigo-500/10 transition-colors">
                        + Add Task
                    </button>
                </div>

                <div class="space-y-4">
                    <template x-for="(task, index) in tasks" :key="index">
                        <div class="glass-panel p-4 rounded-xl border border-slate-800 flex items-start space-x-4 relative">
                            <div class="flex-grow grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-slate-400">Task Objective Description</label>
                                    <input type="text" :name="`tasks[${index}][task]`" x-model="task.task" required placeholder="e.g. Create a directory named '/var/www'"
                                        class="mt-1.5 block w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-slate-400">Verification Shell Command (Validation)</label>
                                    <input type="text" :name="`tasks[${index}][command]`" x-model="task.command" placeholder="e.g. [ -d /var/www ]"
                                        class="mt-1.5 block w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-sm text-white focus:outline-none focus:ring-2 focus:ring-indigo-500">
                                </div>
                            </div>
                            
                            <button type="button" @click="if(tasks.length > 1) tasks.splice(index, 1)" 
                                class="mt-5 p-2 text-rose-400 hover:text-rose-300 hover:bg-slate-800 rounded-lg transition-colors">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                            </button>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Action buttons -->
            <div class="flex justify-end space-x-3 pt-6 border-t border-slate-800/80">
                <a href="{{ route('laboratories.index') }}" class="px-5 py-2.5 border border-slate-700 text-sm font-semibold rounded-xl text-slate-300 bg-slate-800 hover:bg-slate-700 transition-colors">
                    Cancel
                </a>
                <button type="submit" class="px-5 py-2.5 border border-transparent text-sm font-semibold rounded-xl text-white bg-indigo-600 hover:bg-indigo-700 transition-colors shadow-lg shadow-indigo-600/30">
                    Save Specifications
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@section('scripts')
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
@endsection

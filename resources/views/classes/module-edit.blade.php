@extends('layouts.app')

@section('title', 'Edit Module - ' . $module->title)
@section('page_header')
    Edit Module Specification
@endsection

@section('content')
<div class="max-w-3xl mx-auto glass-panel p-8 rounded-xl border border-slate-800 space-y-6">
    <div>
        <h2 class="text-xl font-bold text-white">Modify Learning Module</h2>
        <p class="text-xs text-slate-400 mt-1">Update title, syllabus details, theoretical lessons, and associated downloadable files.</p>
    </div>

    <form action="{{ route('modules.update', [$class->id, $module->id]) }}" method="POST" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @method('PUT')

        <!-- Title -->
        <div>
            <label for="title" class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Module Title</label>
            <input type="text" name="title" id="title" required value="{{ old('title', $module->title) }}" placeholder="e.g. Module 1: Variables & Operations" 
                   class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white focus:outline-none focus:border-blue-500">
        </div>

        <!-- Brief summary -->
        <div>
            <label for="description" class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Brief Summary</label>
            <input type="text" name="description" id="description" value="{{ old('description', $module->description) }}" placeholder="Overview of module content" 
                   class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white focus:outline-none focus:border-blue-500">
        </div>

        <!-- Content with CKEditor -->
        <div>
            <label for="module-content" class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Lesson Readings & Materials</label>
            <div class="text-slate-900">
                <textarea name="content" id="module-content" rows="12" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white focus:outline-none focus:border-blue-500 font-sans">{{ old('content', $module->content) }}</textarea>
            </div>
        </div>

        <!-- Current File attachments list with deletion checkboxes -->
        @if($module->attachments->count() > 0)
            <div class="p-4 bg-slate-900/40 border border-slate-850 rounded-xl space-y-3">
                <span class="block text-xs font-bold text-slate-400 uppercase tracking-wider">Current Attachments</span>
                <div class="space-y-2">
                    @foreach($module->attachments as $attachment)
                        <div class="flex items-center justify-between p-2 bg-slate-950 rounded-lg border border-slate-850 text-xs">
                            <span class="text-white truncate flex items-center">
                                <svg class="w-3.5 h-3.5 mr-1.5 text-indigo-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                {{ $attachment->file_name }} ({{ number_format($attachment->file_size / 1024, 1) }} KB)
                            </span>
                            <label class="flex items-center text-rose-400 cursor-pointer select-none">
                                <input type="checkbox" name="remove_attachments[]" value="{{ $attachment->id }}" class="mr-1.5 h-3.5 w-3.5 rounded text-rose-600 bg-slate-900 border-slate-700 focus:ring-rose-500">
                                Remove File
                            </label>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Upload new file attachments -->
        <div>
            <label for="attachments" class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Upload Additional Files / Resources</label>
            <input type="file" name="attachments[]" id="attachments" multiple
                   class="w-full text-xs text-slate-400 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-slate-800 file:text-white hover:file:bg-slate-750">
        </div>

        <!-- Parent Module (Sub-module setting) -->
        <div>
            <label for="parent_id" class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Parent Module (Optional)</label>
            <select name="parent_id" id="parent_id" 
                    class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white focus:outline-none focus:border-blue-500">
                <option value="">-- None (Make it a main module) --</option>
                @foreach($class->modules->where('parent_id', null) as $parentMod)
                    @if($parentMod->id !== $module->id)
                        <option value="{{ $parentMod->id }}" {{ old('parent_id', $module->parent_id) == $parentMod->id ? 'selected' : '' }}>
                            {{ $parentMod->title }}
                        </option>
                    @endif
                @endforeach
            </select>
        </div>

        <!-- Order index -->
        <div>
            <label for="order_index" class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Order Index</label>
            <input type="number" name="order_index" id="order_index" required value="{{ old('order_index', $module->order_index) }}" min="0" 
                   class="w-32 px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white focus:outline-none focus:border-blue-500">
        </div>

        <!-- Actions -->
        <div class="flex justify-end space-x-3 pt-4 border-t border-slate-800/80">
            <a href="{{ route('modules.show', [$class->id, $module->id]) }}" class="px-4 py-2 border border-slate-800 text-xs font-semibold rounded-lg text-slate-300 bg-slate-900 hover:bg-slate-800 transition">Cancel</a>
            <button type="submit" class="px-4 py-2 bg-green-600 hover:bg-green-500 text-xs font-semibold rounded-lg text-white transition shadow-lg shadow-green-500/20">Update Module</button>
        </div>
    </form>
</div>
@endsection

@section('scripts')
<script src="https://cdn.ckeditor.com/ckeditor5/36.0.1/classic/ckeditor.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        ClassicEditor
            .create(document.querySelector('#module-content'), {
                toolbar: ['heading', '|', 'bold', 'italic', 'link', 'bulletedList', 'numberedList', 'blockQuote', 'insertTable', 'undo', 'redo']
            })
            .catch(error => {
                console.error(error);
            });
    });
</script>
@endsection

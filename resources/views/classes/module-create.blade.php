@extends('layouts.app')

@section('title', 'Add Module - ' . $class->name)
@section('page_header')
    Create Module Specification
@endsection

@section('content')
<div class="max-w-3xl mx-auto glass-panel p-8 rounded-xl border border-slate-800 space-y-6">
    <div>
        <h2 class="text-xl font-bold text-white">Add New Learning Module</h2>
        <p class="text-xs text-slate-400 mt-1">Specify title, summary, theoretical lessons, and associated downloadable files for the class.</p>
    </div>

    <form action="{{ route('modules.store', $class->id) }}" method="POST" enctype="multipart/form-data" class="space-y-6">
        @csrf

        <!-- Title -->
        <div>
            <label for="title" class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Module Title</label>
            <input type="text" name="title" id="title" required value="{{ old('title') }}" placeholder="e.g. Module 1: Variables & Operations" 
                   class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white focus:outline-none focus:border-blue-500">
        </div>

        <!-- Brief summary -->
        <div>
            <label for="description" class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Brief Summary</label>
            <input type="text" name="description" id="description" value="{{ old('description') }}" placeholder="Overview of module content" 
                   class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white focus:outline-none focus:border-blue-500">
        </div>

        <!-- Content with CKEditor -->
        <div>
            <label for="module-content" class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Lesson Readings & Materials</label>
            <div class="text-slate-900">
                <textarea name="content" id="module-content" rows="12" class="w-full px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white focus:outline-none focus:border-blue-500 font-sans">{{ old('content') }}</textarea>
            </div>
        </div>

        <!-- Upload file attachments -->
        <div>
            <label for="attachments" class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Upload Files / Resources (Optional)</label>
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
                    <option value="{{ $parentMod->id }}" {{ old('parent_id') == $parentMod->id ? 'selected' : '' }}>
                        {{ $parentMod->title }}
                    </option>
                @endforeach
            </select>
        </div>

        <!-- Order index -->
        <div>
            <label for="order_index" class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Order Index</label>
            <input type="number" name="order_index" id="order_index" required value="1" min="0" 
                   class="w-32 px-3 py-2 bg-slate-950 border border-slate-800 rounded-lg text-sm text-white focus:outline-none focus:border-blue-500">
        </div>

        <!-- Actions -->
        <div class="flex justify-end space-x-3 pt-4 border-t border-slate-800/80">
            <a href="{{ route('classes.show', $class->id) }}" class="px-4 py-2 border border-slate-800 text-xs font-semibold rounded-lg text-slate-300 bg-slate-900 hover:bg-slate-800 transition">Cancel</a>
            <button type="submit" class="px-4 py-2 bg-green-600 hover:bg-green-500 text-xs font-semibold rounded-lg text-white transition shadow-lg shadow-green-500/20">Save Module</button>
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

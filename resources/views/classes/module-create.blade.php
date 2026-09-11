@extends('layouts.app')

@section('title', 'Add Module - ' . $class->name)
@section('page_header')
    Create Module Specification
@endsection

@section('content')
<div class="max-w-3xl mx-auto p-8 rounded-xl bg-[#171717] border border-[#2e2e2e] space-y-6">
    <div>
        <span class="text-[10px] font-mono uppercase tracking-wider text-[#3ecf8e] block">Curriculum Builder</span>
        <h2 class="text-xl font-bold text-[#ededed] mt-0.5">Add New Learning Module</h2>
        <p class="text-xs text-[#888888] mt-1 font-mono">Specify title, summary, theoretical lessons, and associated downloadable files for the class.</p>
    </div>

    <form action="{{ route('modules.store', $class->id) }}" method="POST" enctype="multipart/form-data" class="space-y-6">
        @csrf

        <!-- Title -->
        <div>
            <label for="title" class="block text-xs font-mono uppercase tracking-wider text-[#a3a3a3] mb-2">Module Title</label>
            <input type="text" name="title" id="title" required value="{{ old('title') }}" placeholder="e.g. Module 1: Variables & Operations" 
                   class="w-full px-3.5 py-2.5 bg-[#141414] border border-[#2e2e2e] rounded-[6px] text-sm text-[#ededed] placeholder-[#666666] focus:outline-none focus:border-[#3ecf8e] focus:ring-1 focus:ring-[#3ecf8e] transition-colors">
        </div>

        <!-- Brief summary -->
        <div>
            <label for="description" class="block text-xs font-mono uppercase tracking-wider text-[#a3a3a3] mb-2">Brief Summary</label>
            <input type="text" name="description" id="description" value="{{ old('description') }}" placeholder="Overview of module content" 
                   class="w-full px-3.5 py-2.5 bg-[#141414] border border-[#2e2e2e] rounded-[6px] text-sm text-[#ededed] placeholder-[#666666] focus:outline-none focus:border-[#3ecf8e] focus:ring-1 focus:ring-[#3ecf8e] transition-colors">
        </div>

        <!-- Content with CKEditor -->
        <div>
            <label for="module-content" class="block text-xs font-mono uppercase tracking-wider text-[#a3a3a3] mb-2">Lesson Readings & Materials</label>
            <div class="text-slate-900">
                <textarea name="content" id="module-content" rows="12" class="w-full px-3.5 py-2.5 bg-[#141414] border border-[#2e2e2e] rounded-[6px] text-sm text-[#ededed] placeholder-[#666666] focus:outline-none focus:border-[#3ecf8e] focus:ring-1 focus:ring-[#3ecf8e] transition-colors">{{ old('content') }}</textarea>
            </div>
        </div>

        <!-- Upload file attachments -->
        <div>
            <label for="attachments" class="block text-xs font-mono uppercase tracking-wider text-[#a3a3a3] mb-2">Upload Files / Resources (Optional)</label>
            <input type="file" name="attachments[]" id="attachments" multiple
                   class="w-full text-xs text-[#888888] font-mono file:mr-4 file:py-2 file:px-4 file:rounded-[6px] file:border file:border-[#2e2e2e] file:text-xs file:font-medium file:bg-[#141414] file:text-[#ededed] hover:file:bg-[#202020]">
        </div>

        <!-- Parent Module (Sub-module setting) -->
        <div>
            <label for="parent_id" class="block text-xs font-mono uppercase tracking-wider text-[#a3a3a3] mb-2">Parent Module (Optional)</label>
            <select name="parent_id" id="parent_id" 
                    class="w-full px-3.5 py-2.5 bg-[#141414] border border-[#2e2e2e] rounded-[6px] text-sm text-[#ededed] focus:outline-none focus:border-[#3ecf8e] focus:ring-1 focus:ring-[#3ecf8e] transition-colors">
                <option value="" class="bg-[#141414] text-[#888888]">-- None (Make it a main module) --</option>
                @foreach($class->modules->where('parent_id', null) as $parentMod)
                    <option value="{{ $parentMod->id }}" {{ old('parent_id') == $parentMod->id ? 'selected' : '' }} class="bg-[#141414] text-[#ededed]">
                        {{ $parentMod->title }}
                    </option>
                @endforeach
            </select>
        </div>

        <!-- Order index -->
        <div>
            <label for="order_index" class="block text-xs font-mono uppercase tracking-wider text-[#a3a3a3] mb-2">Order Index</label>
            <input type="number" name="order_index" id="order_index" required value="1" min="0" 
                   class="w-32 px-3.5 py-2.5 bg-[#141414] border border-[#2e2e2e] rounded-[6px] text-sm text-[#ededed] focus:outline-none focus:border-[#3ecf8e] focus:ring-1 focus:ring-[#3ecf8e] transition-colors">
        </div>

        <!-- Actions -->
        <div class="flex justify-end space-x-3 pt-4 border-t border-[#232323]">
            <a href="{{ route('classes.show', $class->id) }}" class="px-4 py-2 border border-[#2e2e2e] text-xs font-mono font-medium rounded-[6px] text-[#a3a3a3] bg-[#171717] hover:bg-[#222222] hover:text-[#ededed] transition">Cancel</a>
            <button type="submit" class="px-5 py-2 bg-[#3ecf8e] hover:bg-[#00c573] text-xs font-semibold rounded-full text-[#0f0f0f] transition shadow-none">Save Module &rarr;</button>
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

@extends('layouts.app')

@section('title', 'Edit Class')
@section('page_header', 'Modify Class Room')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="rounded-xl p-8 bg-[#171717] border border-[#2e2e2e]">
        <div class="mb-6">
            <span class="text-[10px] font-mono uppercase tracking-wider text-[#3ecf8e] block">Class Definition</span>
            <h2 class="text-lg font-bold text-[#ededed] mt-0.5">Modify Class Specifications</h2>
        </div>

        <form action="{{ route('classes.update', $class->id) }}" method="POST" class="space-y-6">
            @csrf
            @method('PUT')

            <!-- Class Name -->
            <div>
                <label for="name" class="block text-xs font-mono uppercase tracking-wider text-[#a3a3a3] mb-2">Class Title / Name</label>
                <input type="text" name="name" id="name" required value="{{ old('name', $class->name) }}"
                    class="w-full px-3.5 py-2.5 bg-[#141414] border border-[#2e2e2e] rounded-[6px] text-sm text-[#ededed] placeholder-[#666666] focus:outline-none focus:border-[#3ecf8e] focus:ring-1 focus:ring-[#3ecf8e] transition-colors">
                @error('name') <p class="text-red-400 text-xs mt-1 font-mono">{{ $message }}</p> @enderror
            </div>

            <!-- Description -->
            <div>
                <label for="description" class="block text-xs font-mono uppercase tracking-wider text-[#a3a3a3] mb-2">Class Description</label>
                <textarea name="description" id="description" rows="4"
                    class="w-full px-3.5 py-2.5 bg-[#141414] border border-[#2e2e2e] rounded-[6px] text-sm text-[#ededed] placeholder-[#666666] focus:outline-none focus:border-[#3ecf8e] focus:ring-1 focus:ring-[#3ecf8e] transition-colors">{{ old('description', $class->description) }}</textarea>
                @error('description') <p class="text-red-400 text-xs mt-1 font-mono">{{ $message }}</p> @enderror
            </div>

            <!-- Action buttons -->
            <div class="flex justify-end space-x-3 pt-6 border-t border-[#232323]">
                <a href="{{ route('classes.show', $class->id) }}" class="px-4 py-2 border border-[#2e2e2e] text-xs font-mono font-medium rounded-[6px] text-[#a3a3a3] bg-[#171717] hover:bg-[#222222] hover:text-[#ededed] transition-colors">
                    Cancel
                </a>
                <button type="submit" class="px-5 py-2 text-xs font-semibold rounded-full text-[#0f0f0f] bg-[#3ecf8e] hover:bg-[#00c573] transition-colors shadow-none">
                    Save Changes &rarr;
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

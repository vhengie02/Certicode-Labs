@extends('layouts.app')

@section('title', 'Create Class')
@section('page_header', 'Create Class Room')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="rounded-xl p-8 bg-[#171717] border border-[#2e2e2e]">
        <div class="mb-6">
            <span class="text-[10px] font-mono uppercase tracking-wider text-[#3ecf8e] block">Class Definition</span>
            <h2 class="text-lg font-bold text-[#ededed] mt-0.5">New Class Specifications</h2>
        </div>

        <form action="{{ route('classes.store') }}" method="POST" class="space-y-6">
            @csrf

            <!-- Class Name -->
            <div>
                <label for="name" class="block text-xs font-mono uppercase tracking-wider text-[#a3a3a3] mb-2">Class Title / Name</label>
                <input type="text" name="name" id="name" required value="{{ old('name') }}" placeholder="e.g. CS101: Systems Programming with Python"
                    class="w-full px-3.5 py-2.5 bg-[#141414] border border-[#2e2e2e] rounded-[6px] text-sm text-[#ededed] placeholder-[#666666] focus:outline-none focus:border-[#3ecf8e] focus:ring-1 focus:ring-[#3ecf8e] transition-colors">
                @error('name') <p class="text-red-400 text-xs mt-1 font-mono">{{ $message }}</p> @enderror
            </div>

            <!-- Description -->
            <div>
                <label for="description" class="block text-xs font-mono uppercase tracking-wider text-[#a3a3a3] mb-2">Class Description</label>
                <textarea name="description" id="description" rows="4" placeholder="Brief details about class materials and criteria..."
                    class="w-full px-3.5 py-2.5 bg-[#141414] border border-[#2e2e2e] rounded-[6px] text-sm text-[#ededed] placeholder-[#666666] focus:outline-none focus:border-[#3ecf8e] focus:ring-1 focus:ring-[#3ecf8e] transition-colors">{{ old('description') }}</textarea>
                @error('description') <p class="text-red-400 text-xs mt-1 font-mono">{{ $message }}</p> @enderror
            </div>

            <!-- Passing Threshold & Scheduled End Date -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div>
                    <label for="passing_threshold" class="block text-xs font-mono uppercase tracking-wider text-[#a3a3a3] mb-2">Passing Threshold (%)</label>
                    <input type="number" name="passing_threshold" id="passing_threshold" min="1" max="100" value="{{ old('passing_threshold', 75) }}"
                        class="w-full px-3.5 py-2.5 bg-[#141414] border border-[#2e2e2e] rounded-[6px] text-sm text-[#ededed] placeholder-[#666666] focus:outline-none focus:border-[#3ecf8e] focus:ring-1 focus:ring-[#3ecf8e] transition-colors">
                    <span class="text-[10px] text-[#888888] mt-1 block">Completion % required for diploma certificate.</span>
                    @error('passing_threshold') <p class="text-red-400 text-xs mt-1 font-mono">{{ $message }}</p> @enderror
                </div>

                <div>
                    <label for="scheduled_end_date" class="block text-xs font-mono uppercase tracking-wider text-[#a3a3a3] mb-2">Scheduled End Date (Optional)</label>
                    <input type="datetime-local" name="scheduled_end_date" id="scheduled_end_date" value="{{ old('scheduled_end_date') }}"
                        class="w-full px-3.5 py-2.5 bg-[#141414] border border-[#2e2e2e] rounded-[6px] text-sm text-[#ededed] focus:outline-none focus:border-[#3ecf8e] focus:ring-1 focus:ring-[#3ecf8e] transition-colors">
                    <span class="text-[10px] text-[#888888] mt-1 block">Automatic cutoff date for all lab submissions.</span>
                    @error('scheduled_end_date') <p class="text-red-400 text-xs mt-1 font-mono">{{ $message }}</p> @enderror
                </div>
            </div>

            <!-- Action buttons -->
            <div class="flex justify-end space-x-3 pt-6 border-t border-[#232323]">
                <a href="{{ route('classes.index') }}" class="px-4 py-2 border border-[#2e2e2e] text-xs font-mono font-medium rounded-[6px] text-[#a3a3a3] bg-[#171717] hover:bg-[#222222] hover:text-[#ededed] transition-colors">
                    Cancel
                </a>
                <button type="submit" class="px-5 py-2 text-xs font-semibold rounded-full text-[#0f0f0f] bg-[#3ecf8e] hover:bg-[#00c573] transition-colors shadow-none">
                    Create Class Room &rarr;
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

@extends('layouts.app')

@section('title', 'Create Class')
@section('page_header', 'Create Class Room')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="glass-panel rounded-lg p-8 border border-slate-800">
        <h2 class="text-lg font-bold text-white mb-6 uppercase tracking-wider text-slate-400">New Class Specifications</h2>

        <form action="{{ route('classes.store') }}" method="POST" class="space-y-6">
            @csrf

            <!-- Class Name -->
            <div>
                <label for="name" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Class Title / Name</label>
                <input type="text" name="name" id="name" required value="{{ old('name') }}" placeholder="e.g. CS101: Introduction to Python"
                    class="w-full px-4 py-3 bg-slate-900 border border-slate-800 rounded-lg text-sm text-white focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-colors">
                @error('name') <p class="text-rose-400 text-xs mt-1 font-mono">{{ $message }}</p> @enderror
            </div>

            <!-- Description -->
            <div>
                <label for="description" class="block text-xs font-semibold text-slate-300 uppercase tracking-wider mb-2">Class Description</label>
                <textarea name="description" id="description" rows="4" placeholder="Brief details about class materials and criteria..."
                    class="w-full px-4 py-3 bg-slate-900 border border-slate-800 rounded-lg text-sm text-white focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-colors">{{ old('description') }}</textarea>
                @error('description') <p class="text-rose-400 text-xs mt-1 font-mono">{{ $message }}</p> @enderror
            </div>

            <!-- Action buttons -->
            <div class="flex justify-end space-x-3 pt-6 border-t border-slate-800/80">
                <a href="{{ route('classes.index') }}" class="px-4 py-2 border border-slate-800 text-xs font-semibold rounded-lg text-slate-300 bg-slate-900 hover:bg-slate-850 transition-colors">
                    Cancel
                </a>
                <button type="submit" class="px-4 py-2 border border-transparent text-xs font-semibold rounded-lg text-white bg-green-600 hover:bg-green-500 transition-colors shadow-lg shadow-green-500/20">
                    Create Class Room
                </button>
            </div>
        </form>
    </div>
</div>
@endsection

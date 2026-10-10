@extends('layouts.app')

@section('title', 'New module · ' . $class->name)

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <x-page-header :back="route('classes.show', $class->id)" :back-label="$class->name" title="New module"
                   subtitle="A module is one lesson in the syllabus. Add labs to it once it's saved." />

    <form action="{{ route('modules.store', $class->id) }}" method="POST" enctype="multipart/form-data" class="space-y-5">
        @csrf
        @include('classes._module-form')

        <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2.5 pt-1">
            <a href="{{ route('classes.show', $class->id) }}" class="ui-btn ui-btn-ghost">Cancel</a>
            <button type="submit" class="ui-btn ui-btn-primary">Create module</button>
        </div>
    </form>
</div>
@endsection

@section('scripts')
    @include('classes._module-form-scripts')
@endsection

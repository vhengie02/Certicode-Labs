@extends('layouts.app')

@section('title', 'Edit ' . $module->title)

@section('content')
<div class="max-w-3xl mx-auto space-y-6">
    <x-page-header :back="route('modules.show', [$class->id, $module->id])" :back-label="$module->title" title="Edit module" />

    <form action="{{ route('modules.update', [$class->id, $module->id]) }}" method="POST" enctype="multipart/form-data" class="space-y-5">
        @csrf
        @method('PUT')
        @include('classes._module-form', ['module' => $module])

        <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2.5 pt-1">
            <a href="{{ route('modules.show', [$class->id, $module->id]) }}" class="ui-btn ui-btn-ghost">Cancel</a>
            <button type="submit" class="ui-btn ui-btn-primary">Save changes</button>
        </div>
    </form>
</div>
@endsection

@section('scripts')
    @include('classes._module-form-scripts')
@endsection

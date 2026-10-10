@extends('layouts.app')

@section('title', 'New lab · ' . $class->name)

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <x-page-header :back="route('classes.show', $class->id)" :back-label="$class->name" title="New lab"
                   subtitle="A coding exercise students open in VS Code. They're graded against your tasks." />

    <form action="{{ route('laboratories.store') }}" method="POST" class="space-y-5">
        @csrf
        <input type="hidden" name="class_id" value="{{ $class->id }}">
        @include('laboratories._form')

        <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2.5 pt-1">
            <a href="{{ route('classes.show', $class->id) }}" class="ui-btn ui-btn-ghost">Cancel</a>
            <button type="submit" class="ui-btn ui-btn-primary">Create lab</button>
        </div>
    </form>
</div>
@endsection

@section('scripts')
    @include('laboratories._form-scripts')
@endsection

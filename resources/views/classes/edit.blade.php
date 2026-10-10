@extends('layouts.app')

@section('title', 'Edit ' . $class->name)

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    <x-page-header :back="route('classes.show', $class->id)" :back-label="$class->name" title="Class settings" />

    <form action="{{ route('classes.update', $class->id) }}" method="POST" class="space-y-5">
        @csrf
        @method('PUT')
        @include('classes._form', ['class' => $class])

        <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2.5 pt-1">
            <a href="{{ route('classes.show', $class->id) }}" class="ui-btn ui-btn-ghost">Cancel</a>
            <button type="submit" class="ui-btn ui-btn-primary">Save changes</button>
        </div>
    </form>
</div>
@endsection

@extends('layouts.app')

@section('title', 'Edit ' . $laboratory->title)

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <x-page-header :back="route('laboratories.show', $laboratory->id)" :back-label="$laboratory->title" title="Edit lab" />

    <form action="{{ route('laboratories.update', $laboratory->id) }}" method="POST" class="space-y-5">
        @csrf
        @method('PUT')
        @include('laboratories._form', ['laboratory' => $laboratory])

        <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2.5 pt-1">
            <a href="{{ route('classes.show', $class->id) }}" class="ui-btn ui-btn-ghost">Cancel</a>
            <button type="submit" class="ui-btn ui-btn-primary">Save changes</button>
        </div>
    </form>
</div>
@endsection

@section('scripts')
    @include('laboratories._form-scripts')
@endsection

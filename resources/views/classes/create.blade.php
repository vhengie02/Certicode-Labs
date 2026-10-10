@extends('layouts.app')

@section('title', 'New class')

@section('content')
<div class="max-w-2xl mx-auto space-y-6">
    <x-page-header :back="route('classes.index')" back-label="Classes" title="New class"
                   subtitle="You'll get a join code to share with students as soon as it's created." />

    <form action="{{ route('classes.store') }}" method="POST" class="space-y-5">
        @csrf
        @include('classes._form')

        <div class="flex flex-col-reverse sm:flex-row sm:justify-end gap-2.5 pt-1">
            <a href="{{ route('classes.index') }}" class="ui-btn ui-btn-ghost">Cancel</a>
            <button type="submit" class="ui-btn ui-btn-primary">Create class</button>
        </div>
    </form>
</div>
@endsection

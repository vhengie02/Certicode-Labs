@extends('layouts.app')

@section('title', ($lab->title ?? 'Lab').' results')

@section('content')
<div class="space-y-6">
    <x-page-header :back="route('laboratories.show', $lab->id)" :back-label="$lab->title" eyebrow="Lab results" :title="$lab->title" />

    <section class="ui-card px-5 py-14 text-center" aria-labelledby="grading-heading" aria-live="polite">
        <span class="mx-auto flex h-10 w-10 items-center justify-center rounded-full bg-[#3ecf8e]/15 text-[#3ecf8e]" aria-hidden="true">
            <svg class="w-5 h-5 animate-spin motion-reduce:animate-none" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 3a9 9 0 1 0 9 9"/></svg>
        </span>
        <h2 id="grading-heading" class="mt-4 text-sm font-medium text-[#ededed]">Grading your submission</h2>
        <p class="mt-1 text-sm text-[#888888] max-w-sm mx-auto">This takes up to a minute. The page updates on its own once the grade is in.</p>
    </section>
</div>
@endsection

@section('scripts')
<script>
    setTimeout(() => window.location.reload(), 5000);
</script>
@endsection

@extends('layouts.app')

@section('title', 'Competencies')

@section('content')
<div class="space-y-6">
    <x-page-header title="Competencies"
                   subtitle="The skills Certicode certifies. Instructors tag each lab with the competencies it assesses, and a student's score for a competency is the average of their best grades on those labs." />

    <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_340px] gap-6 items-start">
        <section class="ui-card overflow-hidden" aria-labelledby="list-heading">
            <div class="flex items-baseline justify-between px-5 pt-5 pb-3 border-b border-[#232323]">
                <h2 id="list-heading" class="ui-card-title">Catalogue</h2>
                <span class="text-xs text-[#888888]">{{ $competencies->count() }} {{ \Illuminate\Support\Str::plural('competency', $competencies->count()) }}</span>
            </div>
            <ul class="divide-y divide-[#232323]" role="list">
                @forelse ($competencies as $c)
                    <li class="px-5 py-4">
                        <details class="group">
                            <summary class="flex cursor-pointer list-none items-start gap-4">
                                <span class="mt-0.5 shrink-0 rounded-md border border-[#2e2e2e] bg-[#141414] px-2 py-0.5 font-mono text-xs text-[#3ecf8e]">{{ $c->code }}</span>
                                <span class="min-w-0 flex-1">
                                    <span class="block font-medium text-[#ededed]">{{ $c->name }}</span>
                                    @if ($c->description)
                                        <span class="mt-0.5 block text-[13px] text-[#888888] line-clamp-2">{{ $c->description }}</span>
                                    @endif
                                    <span class="mt-1.5 block text-xs text-[#888888]">
                                        {{ $c->laboratories_count }} {{ \Illuminate\Support\Str::plural('lab', $c->laboratories_count) }}
                                        &middot; {{ $c->student_competencies_count }} {{ \Illuminate\Support\Str::plural('student', $c->student_competencies_count) }} scored
                                        @if ($c->student_competencies_avg_score_achieved !== null)
                                            &middot; average {{ round($c->student_competencies_avg_score_achieved) }}%
                                        @endif
                                    </span>
                                </span>
                                <span class="ui-btn ui-btn-ghost ui-btn-sm shrink-0">Edit</span>
                            </summary>

                            <form method="POST" action="{{ route('admin.competencies.update', $c) }}" class="mt-4 grid grid-cols-1 sm:grid-cols-[160px_minmax(0,1fr)] gap-3">
                                @csrf
                                @method('PUT')
                                <div>
                                    <label for="code-{{ $c->id }}" class="ui-label">Code</label>
                                    <input id="code-{{ $c->id }}" name="code" value="{{ $c->code }}" required maxlength="40" class="ui-input ui-input-mono uppercase">
                                </div>
                                <div>
                                    <label for="name-{{ $c->id }}" class="ui-label">Name</label>
                                    <input id="name-{{ $c->id }}" name="name" value="{{ $c->name }}" required maxlength="255" class="ui-input">
                                </div>
                                <div class="sm:col-span-2">
                                    <label for="desc-{{ $c->id }}" class="ui-label">Description <span class="ui-optional">(optional)</span></label>
                                    <textarea id="desc-{{ $c->id }}" name="description" rows="2" class="ui-input">{{ $c->description }}</textarea>
                                </div>
                                <div class="sm:col-span-2 flex flex-wrap items-center justify-between gap-2">
                                    <button type="submit" form="delete-competency-{{ $c->id }}" class="ui-btn ui-btn-ghost ui-btn-sm hover:!text-red-400">Delete</button>
                                    <button type="submit" class="ui-btn ui-btn-primary ui-btn-sm">Save</button>
                                </div>
                            </form>
                            <form id="delete-competency-{{ $c->id }}" method="POST" action="{{ route('admin.competencies.destroy', $c) }}" class="hidden"
                                  onsubmit="return confirm('Delete “{{ addslashes($c->name) }}”? It is removed from {{ $c->laboratories_count }} lab(s) and from {{ $c->student_competencies_count }} student score(s).');">
                                @csrf
                                @method('DELETE')
                            </form>
                        </details>
                    </li>
                @empty
                    <li class="px-5 py-12 text-center">
                        <p class="text-sm font-medium text-[#ededed]">No competencies yet</p>
                        <p class="mt-1 text-sm text-[#888888]">Add the first one on the right, then tag labs with it.</p>
                    </li>
                @endforelse
            </ul>
        </section>

        <section class="ui-card" aria-labelledby="add-heading">
            <div class="ui-card-header">
                <h2 id="add-heading" class="ui-card-title">Add a competency</h2>
                <p class="ui-card-subtitle">Use a short code that stays stable, like JAVA-OOP-01.</p>
            </div>
            <form method="POST" action="{{ route('admin.competencies.store') }}" class="ui-card-body space-y-4">
                @csrf
                <div>
                    <label for="new-code" class="ui-label">Code</label>
                    <input id="new-code" name="code" value="{{ old('code') }}" required maxlength="40" placeholder="JAVA-OOP-01" class="ui-input ui-input-mono uppercase" @error('code') aria-invalid="true" @enderror>
                    @error('code') <p class="ui-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="new-name" class="ui-label">Name</label>
                    <input id="new-name" name="name" value="{{ old('name') }}" required maxlength="255" placeholder="Object-oriented design in Java" class="ui-input" @error('name') aria-invalid="true" @enderror>
                    @error('name') <p class="ui-error">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="new-desc" class="ui-label">Description <span class="ui-optional">(optional)</span></label>
                    <textarea id="new-desc" name="description" rows="3" placeholder="What a student can do once they have it." class="ui-input">{{ old('description') }}</textarea>
                </div>
                <button type="submit" class="ui-btn ui-btn-primary w-full">Add competency</button>
            </form>
        </section>
    </div>
</div>
@endsection

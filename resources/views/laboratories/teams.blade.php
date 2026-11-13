@extends('layouts.app')

@section('title', 'Teams · ' . $laboratory->title)

@section('content')
@php
    // Which form the last failed submit came from, so its errors and input show only there
    $failedForm = $errors->any() ? old('form') : null;
    $checkedFor = fn (string $form, array $default) => $failedForm === $form ? array_map('intval', (array) old('members', [])) : $default;
@endphp
<div class="max-w-5xl mx-auto space-y-6">
    <x-page-header :back="route('laboratories.show', $laboratory->id)" :back-label="$laboratory->title" title="Teams"
                   subtitle="Put students into teams. Teammates share chat and see each other's contributions. A student without a team works alone." />

    @unless ($laboratory->is_group_lab)
        <div class="rounded-xl border border-amber-400/30 bg-amber-400/[0.06] px-4 py-3 text-sm text-amber-300">
            This lab is not a group lab, so teams have no effect. Turn on "Group lab" in
            <a href="{{ route('laboratories.edit', $laboratory->id) }}" class="underline underline-offset-2">Edit lab</a> to use them.
        </div>
    @endunless

    @if ($laboratory->is_group_lab)
        <div class="ui-card ui-card-body flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div class="min-w-0">
                <p class="text-sm font-medium text-[#ededed]">
                    @if ($laboratory->team_size)
                        Up to {{ $laboratory->team_size }} per team
                    @else
                        No team size set
                    @endif
                    &middot;
                    {{ ['students' => 'Students pick their teammates', 'random' => 'Randomized'][$laboratory->team_formation] ?? 'You assign teams' }}
                </p>
                <p class="mt-0.5 text-[13px] text-[#888888]">
                    @if ($laboratory->team_size)
                        Randomize puts students without a team into open seats first, then into new teams.
                    @else
                        Set a team size in <a href="{{ route('laboratories.edit', $laboratory->id) }}" class="underline underline-offset-2">Edit lab</a> to randomize teams.
                    @endif
                </p>
            </div>
            @if ($laboratory->team_size)
                <div class="flex items-center gap-2 shrink-0">
                    @if ($canReshuffle)
                        <form action="{{ route('laboratories.teams.randomize', $laboratory->id) }}" method="POST"
                              data-confirm-title="Draw all teams again?" data-confirm="Every team is deleted and all enrolled students are drawn into new ones." data-confirm-action="Draw again">
                            @csrf
                            <input type="hidden" name="reshuffle" value="1">
                            <button type="submit" class="ui-btn ui-btn-secondary ui-btn-sm">Draw all again</button>
                        </form>
                    @endif
                    <form action="{{ route('laboratories.teams.randomize', $laboratory->id) }}" method="POST">
                        @csrf
                        <button type="submit" class="ui-btn ui-btn-primary ui-btn-sm" @disabled($unassigned->isEmpty())>Randomize {{ $unassigned->count() }} {{ \Illuminate\Support\Str::plural('student', $unassigned->count()) }}</button>
                    </form>
                </div>
            @endif
        </div>
    @endif

    <div class="grid gap-6 lg:grid-cols-[minmax(0,22rem)_minmax(0,1fr)] items-start">
        {{-- New team --}}
        <form action="{{ route('laboratories.teams.store', $laboratory->id) }}" method="POST" class="ui-card">
            @csrf
            <input type="hidden" name="form" value="new">
            <div class="ui-card-header">
                <h2 class="ui-card-title">New team</h2>
                <p class="ui-card-subtitle">{{ $unassigned->count() }} of {{ $students->count() }} {{ \Illuminate\Support\Str::plural('student', $students->count()) }} without a team</p>
            </div>
            <div class="ui-card-body space-y-4">
                <div>
                    <label for="new-team-name" class="ui-label">Team name</label>
                    <input id="new-team-name" name="name" type="text" maxlength="100" required class="ui-input"
                           value="{{ $failedForm === 'new' ? old('name') : 'Team ' . ($teams->count() + 1) }}">
                    @if ($failedForm === 'new') @error('name') <p class="ui-error">{{ $message }}</p> @enderror @endif
                </div>
                <fieldset>
                    <legend class="ui-label">Members</legend>
                    @include('laboratories._team-members', ['choices' => $unassigned, 'checked' => $checkedFor('new', []), 'idPrefix' => 'new'])
                    @if ($failedForm === 'new')
                        @foreach ($errors->get('members*') as $messages)
                            @foreach ((array) $messages as $message) <p class="ui-error">{{ $message }}</p> @endforeach
                        @endforeach
                    @endif
                </fieldset>
                <button type="submit" class="ui-btn ui-btn-primary w-full" @disabled($unassigned->isEmpty())>Create team</button>
            </div>
        </form>

        {{-- Existing teams --}}
        <div class="space-y-4">
            @forelse ($teams as $team)
                @php
                    $form = 'team-' . $team->id;
                    $choices = $team->members->concat($unassigned)->sortBy('name')->values();
                @endphp
                <div class="ui-card" x-data="{ editing: {{ $failedForm === $form ? 'true' : 'false' }} }">
                    <div class="ui-card-header flex items-start justify-between gap-4">
                        <div class="min-w-0">
                            <h2 class="ui-card-title truncate">{{ $team->name }}</h2>
                            <p class="ui-card-subtitle">{{ $team->members->count() }}{{ $laboratory->team_size ? ' of ' . $laboratory->team_size : '' }} &middot; {{ $team->members->pluck('name')->join(', ') ?: 'No members' }}</p>
                            <p class="ui-card-subtitle">{{ $team->leader ? 'Leader: ' . $team->leader->name : 'No leader yet. The first teammate to open the lab leads.' }}</p>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <button type="button" class="ui-btn ui-btn-secondary ui-btn-sm" @click="editing = !editing" x-text="editing ? 'Close' : 'Edit'">Edit</button>
                            <form action="{{ route('laboratories.teams.destroy', [$laboratory->id, $team->id]) }}" method="POST"
                                  data-confirm-title="Delete {{ $team->name }}?" data-confirm="Its students will work alone. Attempts already made keep their work." data-confirm-action="Delete team">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="ui-btn ui-btn-ghost ui-btn-sm text-red-400">Delete</button>
                            </form>
                        </div>
                    </div>
                    <form x-show="editing" x-cloak action="{{ route('laboratories.teams.update', [$laboratory->id, $team->id]) }}" method="POST" class="ui-card-body space-y-4 border-t border-[#242424]">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="form" value="{{ $form }}">
                        <div>
                            <label for="{{ $form }}-name" class="ui-label">Team name</label>
                            <input id="{{ $form }}-name" name="name" type="text" maxlength="100" required class="ui-input"
                                   value="{{ $failedForm === $form ? old('name') : $team->name }}">
                            @if ($failedForm === $form) @error('name') <p class="ui-error">{{ $message }}</p> @enderror @endif
                        </div>
                        <fieldset>
                            <legend class="ui-label">Members</legend>
                            @include('laboratories._team-members', ['choices' => $choices, 'checked' => $checkedFor($form, $team->members->pluck('id')->all()), 'idPrefix' => $form])
                            @if ($failedForm === $form)
                                @foreach ($errors->get('members*') as $messages)
                                    @foreach ((array) $messages as $message) <p class="ui-error">{{ $message }}</p> @endforeach
                                @endforeach
                            @endif
                        </fieldset>
                        <div class="flex justify-end">
                            <button type="submit" class="ui-btn ui-btn-primary">Save team</button>
                        </div>
                    </form>
                    @if ($team->members->isNotEmpty())
                        {{-- Only the leader can submit the lab, so this stays open while the lab runs --}}
                        <form x-show="editing" x-cloak action="{{ route('laboratories.teams.leader', [$laboratory->id, $team->id]) }}" method="POST" class="ui-card-body flex flex-wrap items-end gap-3 border-t border-[#242424]">
                            @csrf
                            @method('PUT')
                            <input type="hidden" name="form" value="{{ $form }}">
                            <div class="min-w-0 flex-1">
                                <label for="{{ $form }}-leader" class="ui-label">Team leader</label>
                                <select id="{{ $form }}-leader" name="leader_id" class="ui-input" required>
                                    @unless ($team->leader_id) <option value="" selected disabled>Pick a leader</option> @endunless
                                    @foreach ($team->members as $member)
                                        <option value="{{ $member->id }}" @selected($team->leader_id === $member->id)>{{ $member->name }}</option>
                                    @endforeach
                                </select>
                                <p class="ui-hint">Only the leader can submit the lab for the team. You can change the leader at any time.</p>
                                @if ($failedForm === $form) @error('leader_id') <p class="ui-error">{{ $message }}</p> @enderror @endif
                            </div>
                            <button type="submit" class="ui-btn ui-btn-secondary">Set leader</button>
                        </form>
                    @endif
                </div>
            @empty
                <div class="ui-card ui-card-body text-sm text-[#888888]">
                    No teams yet. Create one to group students for this lab.
                </div>
            @endforelse
        </div>
    </div>
</div>
@endsection

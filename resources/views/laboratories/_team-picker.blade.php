{{-- A student's team on a lab where students pick their teammates. $teamPicker comes from LabTeams::pickerFor. --}}
@php
    ['team' => $team, 'classmates' => $classmates, 'seats' => $seats, 'locked' => $locked] = $teamPicker;
    $limit = $laboratory->team_size;
@endphp
<div class="ui-card">
    <div class="ui-card-header flex items-start justify-between gap-4">
        <div class="min-w-0">
            @if ($team)
                <h2 class="ui-card-title truncate">{{ $team->name }}</h2>
                <p class="ui-card-subtitle">{{ $team->members->count() }}{{ $limit ? ' of ' . $limit : '' }} &middot; {{ $team->members->pluck('name')->join(', ') }}</p>
            @elseif ($locked)
                <h2 class="ui-card-title">No team</h2>
                <p class="ui-card-subtitle">You started this lab without a team, so you work alone. Your instructor can still add you to one.</p>
            @else
                <h2 class="ui-card-title">Pick your teammates</h2>
                <p class="ui-card-subtitle">If you start the lab without a team, you work alone.</p>
            @endif
        </div>
        @if ($team && ! $locked)
            <form action="{{ route('laboratories.my-team.destroy', $laboratory->id) }}" method="POST" class="shrink-0"
                  data-confirm-title="Leave {{ $team->name }}?" data-confirm="You can pick new teammates or be picked again until a teammate starts the lab." data-confirm-action="Leave team">
                @csrf
                @method('DELETE')
                <button type="submit" class="ui-btn ui-btn-ghost ui-btn-sm text-red-400">Leave team</button>
            </form>
        @endif
    </div>

    @if ($locked)
        @if ($team)
            <p class="ui-card-body text-sm text-[#888888] border-t border-[#242424]">This team is locked because a member started the lab. Ask your instructor to change it.</p>
        @endif
    @elseif ($seats === 0)
        <p class="ui-card-body text-sm text-[#888888] border-t border-[#242424]">Your team is full.</p>
    @else
        <form action="{{ route('laboratories.my-team.store', $laboratory->id) }}" method="POST" class="ui-card-body space-y-4 border-t border-[#242424]">
            @csrf
            <fieldset>
                <legend class="ui-label">{{ $seats ? 'Pick up to ' . $seats . ' ' . \Illuminate\Support\Str::plural('classmate', $seats) : 'Pick classmates' }}</legend>
                @include('laboratories._team-members', [
                    'choices' => $classmates,
                    'checked' => array_map('intval', (array) old('members', [])),
                    'idPrefix' => 'pick',
                    'showEmail' => false,
                    'emptyText' => 'No classmates are free right now.',
                ])
                <p class="ui-hint">Anyone you pick joins right away and gets a notification. They can leave.</p>
                @foreach ($errors->get('members*') as $messages)
                    @foreach ((array) $messages as $message) <p class="ui-error">{{ $message }}</p> @endforeach
                @endforeach
            </fieldset>
            <button type="submit" class="ui-btn ui-btn-primary" @disabled($classmates->isEmpty())>{{ $team ? 'Add to team' : 'Create team' }}</button>
        </form>
    @endif
    @if ($locked && $errors->has('members'))
        <p class="ui-card-body ui-error">{{ $errors->first('members') }}</p>
    @endif
</div>

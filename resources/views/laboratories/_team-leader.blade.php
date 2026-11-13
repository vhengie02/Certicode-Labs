{{-- A student's team on a group lab: who leads it and who wants whom as leader. $myTeam has its members loaded. --}}
@php
    $leaderVotes = app(\App\Services\LabTeams::class)->leaderVotes($myTeam);
    $votesFor = array_count_values($leaderVotes);
    $iLead = $myTeam->leader_id === $user->id;
    $myVote = $leaderVotes[$user->id] ?? null;
@endphp
<div class="ui-card">
    <div class="ui-card-header">
        <h2 class="ui-card-title">Team leader</h2>
        <p class="ui-card-subtitle">
            @if ($iLead)
                You lead {{ $myTeam->name }}. Only you can submit the lab for the team. You can hand the lead to a teammate.
            @elseif ($myTeam->leader_id)
                Only the leader can submit the lab for the team. If your leader is away, vote for a new one: they lead once more than half of the other teammates agree.
            @else
                {{ $myTeam->name }} has no leader yet. The first teammate to open the lab leads, or pick one now.
            @endif
        </p>
    </div>
    <ul class="ui-card-body divide-y divide-[#242424] border-t border-[#242424] !py-0">
        @foreach ($myTeam->members->sortBy('name') as $member)
            @php $leads = $myTeam->leader_id === $member->id; @endphp
            <li class="flex items-center justify-between gap-3 py-3">
                <div class="min-w-0">
                    <span class="text-sm text-[#ededed]">{{ $member->name }}{{ $member->id === $user->id ? ' (you)' : '' }}</span>
                    @if ($leads) <span class="ui-badge ml-2">Leader</span> @endif
                    @if (! $leads && ($votesFor[$member->id] ?? 0))
                        <span class="ml-2 text-xs text-[#888888]">{{ $votesFor[$member->id] }} {{ \Illuminate\Support\Str::plural('vote', $votesFor[$member->id]) }}</span>
                    @endif
                </div>
                @if (! $leads && ($iLead ? $member->id !== $user->id : true))
                    <form action="{{ route('laboratories.my-team.leader', $laboratory->id) }}" method="POST" class="shrink-0"
                          @if ($iLead) data-confirm-title="Make {{ $member->name }} the leader?" data-confirm="Only {{ $member->name }} will be able to submit the lab for the team." data-confirm-action="Make leader" @endif>
                        @csrf
                        <input type="hidden" name="candidate_id" value="{{ $member->id }}">
                        <button type="submit" class="ui-btn ui-btn-secondary ui-btn-sm" @disabled($myVote === $member->id)>
                            {{ $iLead || ! $myTeam->leader_id ? 'Make leader' : ($myVote === $member->id ? 'Voted' : 'Vote as leader') }}
                        </button>
                    </form>
                @endif
            </li>
        @endforeach
    </ul>
    @error('leader') <p class="ui-card-body ui-error">{{ $message }}</p> @enderror
</div>

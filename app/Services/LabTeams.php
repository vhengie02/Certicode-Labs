<?php

namespace App\Services;

use App\Events\ChatMessageSent;
use App\Models\Group;
use App\Models\Laboratory;
use App\Models\LabSession;
use App\Models\LabSessionChat;
use App\Models\TeamDoc;
use App\Models\User;
use App\Notifications\ClassActivityNotification;
use Closure;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Forming the teams of a group lab. Instructors, students and the random draw can all change
 * teams at once, and a student belongs in at most one team per lab, so every change runs
 * inside locked().
 */
class LabTeams
{
    /**
     * Run a team change with the lab row locked, so two changes on one lab can't both see a
     * student as free and place them twice.
     */
    public function locked(Laboratory $laboratory, Closure $change): mixed
    {
        return DB::transaction(function () use ($laboratory, $change) {
            Laboratory::whereKey($laboratory->id)->lockForUpdate()->first();

            return $change();
        });
    }

    /**
     * @return Collection<int, User>
     */
    public function enrolledStudents(Laboratory $laboratory): Collection
    {
        return $laboratory->module->schoolClass->students()
            ->wherePivot('status', 'enrolled')
            ->orderBy('name')
            ->get(['users.id', 'users.name', 'users.email']);
    }

    /**
     * @return Collection<int, User>
     */
    public function studentsWithoutTeam(Laboratory $laboratory): Collection
    {
        $placed = $laboratory->groups()->with('members:id')->get()->flatMap->members->pluck('id');

        return $this->enrolledStudents($laboratory)->whereNotIn('id', $placed)->values();
    }

    /**
     * What a student needs to form a team on the lab page.
     *
     * @return array{team: ?Group, classmates: Collection, seats: ?int, locked: bool}
     */
    public function pickerFor(Laboratory $laboratory, User $student): array
    {
        $team = $laboratory->teamOf($student)?->load('members:id,name');
        $roster = $team ? $team->members->pluck('id')->all() : [$student->id];
        $free = $this->studentsWithoutTeam($laboratory)->where('id', '!=', $student->id);

        return [
            'team' => $team,
            'classmates' => $free->whereNotIn('id', $this->startedAmong($laboratory, $free->pluck('id')->all()))->values(),
            'seats' => $laboratory->team_size ? max(0, $laboratory->team_size - count($roster)) : null,
            'locked' => $this->startedAmong($laboratory, $roster)->isNotEmpty(),
        ];
    }

    /** A team name this lab doesn't use yet: the next "Team N". */
    public function nextTeamName(Laboratory $laboratory): string
    {
        $taken = $laboratory->groups()->pluck('name')->all();
        $number = count($taken) + 1;
        while (in_array("Team {$number}", $taken, true)) {
            $number++;
        }

        return "Team {$number}";
    }

    /**
     * Put every student without a team into one at random. Open seats in existing teams fill
     * first, smallest team first. The rest split evenly into new teams, so 10 students in
     * teams of 3 make 3, 3, 2 and 2 rather than leaving one student alone.
     *
     * @return int How many students were placed
     */
    public function placeUnassigned(Laboratory $laboratory): int
    {
        return $this->locked($laboratory, function () use ($laboratory) {
            $waiting = $this->studentsWithoutTeam($laboratory)->shuffle()->pluck('id');
            $placed = $waiting->count();
            $size = $laboratory->team_size;

            if ($placed === 0 || ! $size) {
                return 0;
            }

            $teams = $laboratory->groups()->withCount('members')->get();
            while ($waiting->isNotEmpty() && ($team = $teams->where('members_count', '<', $size)->sortBy('members_count')->first())) {
                $this->addMembers($laboratory, $team, [$waiting->shift()]);
                $team->members_count++;
            }

            $teamCount = (int) ceil($waiting->count() / $size);
            for ($left = $teamCount; $left > 0; $left--) {
                $members = $waiting->splice(0, (int) ceil($waiting->count() / $left))->all();
                $team = $laboratory->groups()->create(['name' => $this->nextTeamName($laboratory)]);
                $this->addMembers($laboratory, $team, $members);
            }

            return $placed;
        });
    }

    /**
     * Throw the teams away and draw them again. Only for teams with no attempts yet: deleting
     * a team unlinks its attempts, and teammates would lose sight of their finished work.
     *
     * @return bool False when an attempt already belongs to a team, and nothing changed
     */
    public function reshuffle(Laboratory $laboratory): bool
    {
        return $this->locked($laboratory, function () use ($laboratory) {
            if ($this->teamsHaveAttempts($laboratory)) {
                return false;
            }

            $laboratory->groups()->delete();
            $this->placeUnassigned($laboratory);

            return true;
        });
    }

    public function teamsHaveAttempts(Laboratory $laboratory): bool
    {
        return LabSession::where('lab_id', $laboratory->id)->whereNotNull('group_id')->exists();
    }

    /** Students who started this lab, at any time. Students can't change team after that. */
    public function startedAmong(Laboratory $laboratory, array $userIds): Collection
    {
        return LabSession::where('lab_id', $laboratory->id)->whereIn('user_id', $userIds)->distinct()->pluck('user_id');
    }

    /**
     * The team's leader. A team without one gets the teammate who is opening the lab, so a
     * team is never stuck with nobody able to submit. Null only when the team has no leader
     * and the opener isn't one of its members.
     */
    public function ensureLeader(Group $team, User $opener): ?User
    {
        // Asked on every read of an attempt, by every student, every few seconds: a team that
        // has a leader (nearly always) is answered without locking the lab row
        if ($team->leader_id && ($leader = User::find($team->leader_id))) {
            return $leader;
        }

        return $this->locked($team->laboratory, function () use ($team, $opener) {
            $team->refresh();
            if (! $team->leader && $this->isMember($team, $opener)) {
                $this->assignLeader($team, $opener);
            }

            return $team->leader;
        });
    }

    /** Make a member the leader, and tell the team. Votes start over. */
    public function setLeader(Group $team, User $leader): void
    {
        $this->locked($team->laboratory, fn () => $this->assignLeader($team, $leader));

        $this->announceLeader($team, $leader);
    }

    /** The change itself, for callers that hold the lab lock. Nobody is told here. */
    private function assignLeader(Group $team, User $leader): void
    {
        if (! $this->isMember($team, $leader)) {
            throw ValidationException::withMessages(['leader' => "{$leader->name} is not in {$team->name}."]);
        }

        $team->update(['leader_id' => $leader->id]);
        $team->setRelation('leader', $leader);
        $this->teamRows($team)->update(['leader_vote' => null]);
    }

    /**
     * Tell the team who leads now. Always after the lab lock is released: a broadcast or an
     * email can be slow, and a failed one must not undo the change.
     */
    private function announceLeader(Group $team, User $leader): void
    {
        $this->notice($team, "{$leader->name} is now the team leader.");
        $this->tellTeam($team, 'New team leader', "{$leader->name} now leads {$team->name} on the lab '{$team->laboratory->title}'. Only the team leader can submit the lab.");
    }

    /**
     * A teammate picks a leader. The leader hands over at once. Anyone else casts a vote, and
     * the candidate leads once more than half of the teammates other than the leader want
     * them: the vote exists for when the leader is gone, so the leader isn't counted.
     *
     * @return bool Whether the leader changed
     */
    public function chooseLeader(Group $team, User $actor, User $candidate): bool
    {
        $changed = $this->castLeaderChoice($team, $actor, $candidate);

        if ($changed) {
            $this->announceLeader($team, $candidate);
        } elseif ($team->leader_id !== $candidate->id) {
            $this->notice($team, "{$actor->name} voted for {$candidate->name} as team leader.");
        }

        return $changed;
    }

    private function castLeaderChoice(Group $team, User $actor, User $candidate): bool
    {
        return $this->locked($team->laboratory, function () use ($team, $actor, $candidate) {
            $team->refresh();
            foreach ([$actor, $candidate] as $person) {
                if (! $this->isMember($team, $person)) {
                    throw ValidationException::withMessages(['leader' => "{$person->name} is not in {$team->name}."]);
                }
            }

            if ($team->leader_id === $candidate->id) {
                return false;
            }
            if ($team->leader_id === null || $team->leader_id === $actor->id) {
                $this->assignLeader($team, $candidate);

                return true;
            }

            $this->teamRows($team)->where('user_id', $actor->id)->update(['leader_vote' => $candidate->id]);

            $voters = $this->teamRows($team)->where('user_id', '!=', $team->leader_id);
            $inFavour = (clone $voters)->where('leader_vote', $candidate->id)->count();
            if ($inFavour * 2 <= $voters->count()) {
                return false;
            }

            $this->assignLeader($team, $candidate);

            return true;
        });
    }

    /**
     * @return array<int, int> Voter id to the teammate they want as leader
     */
    public function leaderVotes(Group $team): array
    {
        return $this->teamRows($team)->whereNotNull('leader_vote')->orderBy('user_id')
            ->pluck('leader_vote', 'user_id')->map(fn ($id) => (int) $id)->all();
    }

    /**
     * After members leave a team: it loses its leader if they led it, and the votes for them
     * no longer count. Their own votes went with their membership.
     */
    public function forgetMembers(Group $team, array $removedIds): void
    {
        if (! $removedIds) {
            return;
        }
        // The leader may have changed since the caller loaded the team
        $team->refresh();
        if (in_array($team->leader_id, $removedIds, true)) {
            $team->update(['leader_id' => null]);
        }
        $this->teamRows($team)->whereIn('leader_vote', $removedIds)->update(['leader_vote' => null]);
    }

    /**
     * Tell the team something in its chat: a teammate joined or left, the leader changed. The
     * line is kept on an attempt that is open (the one of the student it is about, if they
     * have one), so a team with nobody in the lab gets no line. Never fails the caller.
     */
    public function notice(Group $team, string $message, ?User $about = null): void
    {
        try {
            $open = LabSession::where('group_id', $team->id)->where('lab_id', $team->lab_id)->where('status', 'in_progress');
            $session = ($about ? (clone $open)->where('user_id', $about->id)->first() : null) ?? $open->oldest('id')->first();
            if (! $session) {
                return;
            }

            $chat = LabSessionChat::create([
                'lab_session_id' => $session->id,
                'user_id' => $about?->id,
                'user_name' => $about?->name ?? 'CertiCode Labs',
                'avatar_color' => LabSessionChat::AVATAR_COLORS[($about?->id ?? 0) % count(LabSessionChat::AVATAR_COLORS)],
                'message' => $message,
                'is_system' => true,
            ]);

            foreach ($session->teamSessionIds() as $teamSessionId) {
                event(new ChatMessageSent($teamSessionId, $chat->toPayload()));
            }
        } catch (\Throwable $e) {
            Log::warning("Failed to post a team notice to team {$team->id}: ".$e->getMessage());
        }
    }

    /** Notify every member of the team. A failed send never breaks the caller. */
    private function tellTeam(Group $team, string $title, string $message): void
    {
        foreach ($team->members()->get() as $member) {
            try {
                $member->notify(new ClassActivityNotification($title, $message, route('laboratories.show', $team->lab_id), 'lab'));
            } catch (\Throwable $e) {
                Log::warning("Failed to deliver team notification to student {$member->id}: ".$e->getMessage());
            }
        }
    }

    private function isMember(Group $team, User $user): bool
    {
        return $this->teamRows($team)->where('user_id', $user->id)->exists();
    }

    private function teamRows(Group $team): Builder
    {
        return DB::table('group_members')->where('group_id', $team->id);
    }

    public function addMembers(Laboratory $laboratory, Group $team, array $userIds): void
    {
        $team->members()->attach($userIds);
        $this->moveOpenAttempts($laboratory, $team, $userIds, []);
    }

    /**
     * Attempts already under way follow the team change, so chat and contributions match the
     * new roster. Finished attempts keep the team they were done in.
     */
    public function moveOpenAttempts(Laboratory $laboratory, Group $team, array $addedIds, array $removedIds): void
    {
        $open = fn () => LabSession::where('lab_id', $laboratory->id)->where('status', 'in_progress');

        if ($addedIds) {
            // They join the attempt the team is working on, and so share its clock. A team
            // with none under way begins one at the earliest of their starts.
            $joining = $open()->whereIn('user_id', $addedIds)->get();
            $teamStart = $laboratory->openTeamAttemptStart($team) ?? $joining->pluck('started_at')->filter()->min();
            $joining->each->update(['group_id' => $team->id, 'team_started_at' => $teamStart]);
        }
        if ($removedIds) {
            $open()->whereIn('user_id', $removedIds)->where('group_id', $team->id)->update(['group_id' => null, 'team_started_at' => null]);
            // Whoever left still has the team's channel and key: replace them
            TeamDoc::where('group_id', $team->id)->where('lab_id', $laboratory->id)->get()->each->rotate();
        }
    }
}

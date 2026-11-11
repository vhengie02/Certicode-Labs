<?php

namespace App\Services;

use App\Models\Group;
use App\Models\Laboratory;
use App\Models\LabSession;
use App\Models\User;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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
            $open()->whereIn('user_id', $addedIds)->update(['group_id' => $team->id]);
        }
        if ($removedIds) {
            $open()->whereIn('user_id', $removedIds)->where('group_id', $team->id)->update(['group_id' => null]);
        }
    }
}

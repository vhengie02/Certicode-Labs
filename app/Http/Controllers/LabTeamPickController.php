<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Laboratory;
use App\Models\User;
use App\Notifications\ClassActivityNotification;
use App\Services\LabTeams;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Students form their own teams on labs set to "Students pick". A picked classmate joins right
 * away and is told so, and anyone can leave. Teams lock once a member has started the lab, so
 * nobody walks away from an attempt that is under way; the instructor can still move people.
 */
class LabTeamPickController extends Controller
{
    use Concerns\AuthorizesClassAccess;

    public function __construct(private LabTeams $labTeams) {}

    /** Add classmates to the student's team, starting a team if they have none. */
    public function store(Request $request, int $id)
    {
        $laboratory = $this->pickableLab($id);
        $student = $request->user();

        $picked = array_map('intval', $request->validate([
            'members' => 'required|array|min:1',
            'members.*' => 'integer|distinct',
        ], [
            'members.required' => 'Pick at least one classmate.',
        ])['members']);

        $team = $this->labTeams->locked($laboratory, function () use ($laboratory, $student, $picked) {
            $team = $laboratory->teamOf($student);
            $roster = $team ? $team->members()->pluck('users.id')->all() : [$student->id];
            $free = $this->labTeams->studentsWithoutTeam($laboratory)->pluck('id')->all();

            if (in_array($student->id, $picked, true) || array_diff($picked, $free)) {
                $this->refuse('Someone you picked is no longer free. Check the list and try again.');
            }
            if ($laboratory->team_size && count($roster) + count($picked) > $laboratory->team_size) {
                $this->refuse("Teams on this lab have at most {$laboratory->team_size} people.");
            }
            if ($this->labTeams->startedAmong($laboratory, [...$roster, ...$picked])->isNotEmpty()) {
                $this->refuse('Teams lock once a member starts the lab. Ask your instructor to change this team.');
            }

            if (! $team) {
                // Whoever starts the team leads it until the team chooses otherwise
                $team = $laboratory->groups()->create(['name' => $this->labTeams->nextTeamName($laboratory), 'leader_id' => $student->id]);
                $team->members()->attach($student->id);
            }
            $team->members()->attach($picked);

            return $team;
        });

        $this->tellPicked($laboratory, $team, $student, $picked);

        return redirect()->route('laboratories.show', $laboratory->id)->with('success', "Teammates added to {$team->name}.");
    }

    /** Leave the team. A team with nobody left is removed. */
    public function destroy(Request $request, int $id)
    {
        $laboratory = $this->pickableLab($id);
        $student = $request->user();

        $this->labTeams->locked($laboratory, function () use ($laboratory, $student) {
            $team = $laboratory->teamOf($student);
            if (! $team) {
                return;
            }

            $roster = $team->members()->pluck('users.id')->all();
            if ($this->labTeams->startedAmong($laboratory, $roster)->isNotEmpty()) {
                $this->refuse('Teams lock once a member starts the lab. Ask your instructor to change this team.');
            }

            $team->members()->detach($student->id);
            $this->labTeams->forgetMembers($team, [$student->id]);
            if (count($roster) === 1) {
                $team->delete();
            }
        });

        return redirect()->route('laboratories.show', $laboratory->id)->with('success', 'You left the team.');
    }

    /**
     * Choose the team's leader from the lab page. The leader hands over; anyone else votes.
     * Open on every group lab, however its teams were formed.
     */
    public function chooseLeader(Request $request, int $id)
    {
        $laboratory = Laboratory::with('module.schoolClass')->findOrFail($id);
        $this->authorizeLabViewer($laboratory);
        $student = $request->user();
        $team = $laboratory->teamOf($student);
        abort_unless($student->role === 'student' && $team, 403);

        $candidate = User::findOrFail((int) $request->validate(['candidate_id' => 'required|integer'])['candidate_id']);
        $changed = $this->labTeams->chooseLeader($team, $student, $candidate);

        return redirect()->route('laboratories.show', $laboratory->id)->with('success', $changed
            ? "{$candidate->name} now leads {$team->name}."
            : "Your vote for {$candidate->name} is in. They lead once more than half of your other teammates agree.");
    }

    private function pickableLab(int $id): Laboratory
    {
        $laboratory = Laboratory::with('module.schoolClass')->findOrFail($id);
        $this->authorizeLabViewer($laboratory);
        abort_unless(auth()->user()->role === 'student' && $laboratory->studentsPickTeams(), 403);

        return $laboratory;
    }

    private function refuse(string $message): never
    {
        throw ValidationException::withMessages(['members' => $message]);
    }

    private function tellPicked(Laboratory $laboratory, Group $team, User $student, array $picked): void
    {
        foreach (User::whereKey($picked)->get() as $classmate) {
            try {
                $classmate->notify(new ClassActivityNotification(
                    "You're in {$team->name}",
                    "{$student->name} added you to {$team->name} for the lab '{$laboratory->title}'. You can leave the team from the lab page.",
                    route('laboratories.show', $laboratory->id),
                    'lab'
                ));
            } catch (\Throwable $e) {
                Log::warning("Failed to deliver team notification to student {$classmate->id}: ".$e->getMessage());
            }
        }
    }
}

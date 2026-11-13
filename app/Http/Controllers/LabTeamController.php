<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Laboratory;
use App\Models\User;
use App\Services\LabTeams;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Instructors put the students of a group lab into teams, by hand or at random. A student is
 * in at most one team per lab; their attempts carry the team (group_id) so teammates share chat
 * and contributions. The team size limits students and the random draw, not the instructor, who
 * may need one bigger team for a class that doesn't divide evenly.
 */
class LabTeamController extends Controller
{
    use Concerns\AuthorizesClassAccess;

    public function __construct(private LabTeams $labTeams) {}

    public function index(int $id)
    {
        $laboratory = $this->managedLab($id);

        $teams = $laboratory->groups()->with(['leader:id,name', 'members' => fn ($q) => $q->orderBy('name')])->orderBy('name')->get();
        $students = $this->labTeams->enrolledStudents($laboratory);

        return view('laboratories.teams', [
            'laboratory' => $laboratory,
            'teams' => $teams,
            'students' => $students,
            'unassigned' => $students->whereNotIn('id', $teams->flatMap->members->pluck('id'))->values(),
            'canReshuffle' => $teams->isNotEmpty() && ! $this->labTeams->teamsHaveAttempts($laboratory),
        ]);
    }

    public function store(Request $request, int $id)
    {
        $laboratory = $this->managedLab($id);

        $name = $this->labTeams->locked($laboratory, function () use ($request, $laboratory) {
            [$name, $memberIds] = $this->validatedTeam($request, $laboratory);

            $team = $laboratory->groups()->create(['name' => $name]);
            $this->labTeams->addMembers($laboratory, $team, $memberIds);

            return $name;
        });

        return redirect()->route('laboratories.teams.index', $laboratory->id)->with('success', "Team \"{$name}\" created.");
    }

    public function update(Request $request, int $id, int $teamId)
    {
        $laboratory = $this->managedLab($id);
        $team = $laboratory->groups()->findOrFail($teamId);

        $name = $this->labTeams->locked($laboratory, function () use ($request, $laboratory, $team) {
            [$name, $memberIds] = $this->validatedTeam($request, $laboratory, $team);

            $before = $team->members()->pluck('users.id')->all();
            $team->update(['name' => $name]);
            $team->members()->sync($memberIds);
            $this->labTeams->forgetMembers($team, array_values(array_diff($before, $memberIds)));
            $this->labTeams->moveOpenAttempts($laboratory, $team, array_diff($memberIds, $before), array_diff($before, $memberIds));

            return $name;
        });

        return redirect()->route('laboratories.teams.index', $laboratory->id)->with('success', "Team \"{$name}\" saved.");
    }

    /**
     * Name the team's leader, the only member who can submit the lab. Allowed at any time, so
     * a team whose leader is away during the lab isn't stuck.
     */
    public function setLeader(Request $request, int $id, int $teamId)
    {
        $laboratory = $this->managedLab($id);
        $team = $laboratory->groups()->findOrFail($teamId);

        $data = $request->validate([
            'leader_id' => ['required', 'integer', Rule::exists('group_members', 'user_id')->where('group_id', $team->id)],
        ], [
            'leader_id.exists' => 'The leader must be a member of the team.',
        ]);

        $leader = User::findOrFail($data['leader_id']);
        $this->labTeams->setLeader($team, $leader);

        return redirect()->route('laboratories.teams.index', $laboratory->id)->with('success', "{$leader->name} now leads {$team->name}.");
    }

    public function destroy(int $id, int $teamId)
    {
        $laboratory = $this->managedLab($id);
        $team = $laboratory->groups()->findOrFail($teamId);

        // Attempts keep their work; the database unlinks them from the team (nullOnDelete)
        $team->delete();

        return redirect()->route('laboratories.teams.index', $laboratory->id)->with('success', "Team \"{$team->name}\" deleted.");
    }

    /**
     * Draw students into teams at random: those without a team, or everyone with "reshuffle".
     */
    public function randomize(Request $request, int $id)
    {
        $laboratory = $this->managedLab($id);
        $back = redirect()->route('laboratories.teams.index', $laboratory->id);

        if (! $laboratory->team_size) {
            return $back->with('error', 'Set a team size in Edit lab first.');
        }

        if ($request->boolean('reshuffle')) {
            return $this->labTeams->reshuffle($laboratory)
                ? $back->with('success', 'Teams drawn again.')
                : $back->with('error', 'A team has already started this lab, so teams can no longer be drawn again.');
        }

        $placed = $this->labTeams->placeUnassigned($laboratory);

        return $back->with('success', $placed.' '.Str::plural('student', $placed).' drawn into teams.');
    }

    private function managedLab(int $id): Laboratory
    {
        $laboratory = Laboratory::with('module.schoolClass')->findOrFail($id);
        $this->authorizeLabManager($laboratory);
        abort_unless($laboratory->module?->schoolClass, 404);

        return $laboratory;
    }

    /**
     * @return array{0: string, 1: array<int, int>}
     */
    private function validatedTeam(Request $request, Laboratory $laboratory, ?Group $team = null): array
    {
        $enrolledIds = $this->labTeams->enrolledStudents($laboratory)->pluck('id')->all();

        $data = $request->validate([
            'name' => [
                'required', 'string', 'max:100',
                Rule::unique('groups', 'name')->where('lab_id', $laboratory->id)->ignore($team?->id),
            ],
            'members' => 'required|array|min:1',
            'members.*' => ['integer', 'distinct', Rule::in($enrolledIds)],
        ], [
            'name.unique' => 'Another team on this lab already has that name.',
            'members.required' => 'Pick at least one student.',
            'members.*.in' => 'Only students enrolled in this class can join a team.',
        ]);

        $memberIds = array_map('intval', $data['members']);

        $taken = $laboratory->groups()
            ->when($team, fn ($q) => $q->whereKeyNot($team->id))
            ->with(['members' => fn ($q) => $q->whereIn('users.id', $memberIds)])
            ->get()
            ->flatMap(fn ($other) => $other->members->map(fn ($m) => "{$m->name} is already in {$other->name}."));
        if ($taken->isNotEmpty()) {
            throw ValidationException::withMessages(['members' => $taken->all()]);
        }

        return [trim($data['name']), $memberIds];
    }
}

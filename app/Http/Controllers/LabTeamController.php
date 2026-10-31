<?php

namespace App\Http\Controllers;

use App\Models\Group;
use App\Models\Laboratory;
use App\Models\LabSession;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Instructors put the students of a group lab into teams. A student is in at most one team
 * per lab; their attempts carry the team (group_id) so teammates share chat and contributions.
 */
class LabTeamController extends Controller
{
    use Concerns\AuthorizesClassAccess;

    public function index(int $id)
    {
        $laboratory = $this->managedLab($id);

        $teams = $laboratory->groups()->with(['members' => fn ($q) => $q->orderBy('name')])->orderBy('name')->get();
        $students = $this->enrolledStudents($laboratory);
        $teamOfStudent = $teams->flatMap(fn ($team) => $team->members->mapWithKeys(fn ($m) => [$m->id => $team->id]));

        return view('laboratories.teams', [
            'laboratory' => $laboratory,
            'teams' => $teams,
            'students' => $students,
            'teamOfStudent' => $teamOfStudent,
            'unassigned' => $students->reject(fn ($s) => $teamOfStudent->has($s->id))->values(),
        ]);
    }

    public function store(Request $request, int $id)
    {
        $laboratory = $this->managedLab($id);
        [$name, $memberIds] = $this->validatedTeam($request, $laboratory);

        $team = $laboratory->groups()->create(['name' => $name]);
        $team->members()->attach($memberIds);
        $this->moveOpenAttempts($laboratory, $team, $memberIds, []);

        return redirect()->route('laboratories.teams.index', $laboratory->id)->with('success', "Team \"{$name}\" created.");
    }

    public function update(Request $request, int $id, int $teamId)
    {
        $laboratory = $this->managedLab($id);
        $team = $laboratory->groups()->findOrFail($teamId);
        [$name, $memberIds] = $this->validatedTeam($request, $laboratory, $team);

        $before = $team->members()->pluck('users.id')->all();
        $team->update(['name' => $name]);
        $team->members()->sync($memberIds);
        $this->moveOpenAttempts($laboratory, $team, array_diff($memberIds, $before), array_diff($before, $memberIds));

        return redirect()->route('laboratories.teams.index', $laboratory->id)->with('success', "Team \"{$name}\" saved.");
    }

    public function destroy(int $id, int $teamId)
    {
        $laboratory = $this->managedLab($id);
        $team = $laboratory->groups()->findOrFail($teamId);

        // Attempts keep their work; the database unlinks them from the team (nullOnDelete)
        $team->delete();

        return redirect()->route('laboratories.teams.index', $laboratory->id)->with('success', "Team \"{$team->name}\" deleted.");
    }

    private function managedLab(int $id): Laboratory
    {
        $laboratory = Laboratory::with('module.schoolClass')->findOrFail($id);
        $this->authorizeLabManager($laboratory);
        abort_unless($laboratory->module?->schoolClass, 404);

        return $laboratory;
    }

    private function enrolledStudents(Laboratory $laboratory)
    {
        return $laboratory->module->schoolClass->students()
            ->wherePivot('status', 'enrolled')
            ->orderBy('name')
            ->get(['users.id', 'users.name', 'users.email']);
    }

    /**
     * @return array{0: string, 1: array<int, int>}
     */
    private function validatedTeam(Request $request, Laboratory $laboratory, ?Group $team = null): array
    {
        $enrolledIds = $this->enrolledStudents($laboratory)->pluck('id')->all();

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

    /**
     * Attempts already under way follow the team change, so chat and contributions match the
     * new roster. Finished attempts keep the team they were done in.
     */
    private function moveOpenAttempts(Laboratory $laboratory, Group $team, array $addedIds, array $removedIds): void
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

<?php

namespace App\Http\Controllers;

use App\Models\Laboratory;
use App\Models\LabSession;
use App\Models\Module;
use App\Models\SchoolClass;
use App\Models\StudentCompetency;
use App\Notifications\ClassActivityNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class LaboratoryController extends Controller
{
    use Concerns\AuthorizesClassAccess;

    /**
     * Display a listing of the laboratories.
     */
    public function index()
    {
        return redirect()->route('classes.index');
    }

    /**
     * Show the form for creating a new laboratory.
     */
    public function create(?int $class_id = null)
    {
        $this->authorizeAdminOrInstructor();

        if (! $class_id) {
            return redirect()->route('classes.index')->with('error', 'Select a class to add a laboratory.');
        }

        $class = SchoolClass::with('modules')->findOrFail($class_id);
        $this->authorizeClassManager($class);

        return view('laboratories.create', compact('class'));
    }

    /**
     * Store a newly created laboratory in storage.
     */
    public function store(Request $request)
    {
        $this->authorizeAdminOrInstructor();

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'github_repo_template' => 'nullable|string|max:255',
            'time_limit' => 'required|integer|min:5|max:300',
            'max_attempts' => 'nullable|integer|min:0|max:10',
            'is_group_lab' => 'boolean',
            'availability_mode' => 'nullable|in:open,live',
            'live_duration_minutes' => 'nullable|integer|min:1|max:600',
            'module_id' => 'required|exists:modules,id',
            'tasks' => 'nullable|array',
            'tasks.*.task' => 'required|string|max:255',
            'tasks.*.command' => 'nullable|string|max:255',
            'starter_files' => 'nullable|array',
            'starter_files.*.name' => 'nullable|string|max:255',
            'starter_files.*.content' => 'nullable|string',
            'competencies' => 'nullable|array',
            'competencies.*' => 'integer|exists:competencies,id',
        ]);

        // Convert key-value tasks to JSON structures
        $tasksDefinition = [];
        if (! empty($validated['tasks'])) {
            foreach ($validated['tasks'] as $index => $taskData) {
                $tasksDefinition[] = [
                    'id' => $index + 1,
                    'task' => $taskData['task'],
                    'command' => $taskData['command'] ?? '',
                ];
            }
        }

        // Process starter files manifest
        $starterFiles = [];
        if (! empty($request->input('starter_files')) && is_array($request->input('starter_files'))) {
            $hasPrimary = false;
            foreach ($request->input('starter_files') as $file) {
                if (! empty($file['name'])) {
                    $isPrimary = ! empty($file['is_primary']);
                    if ($isPrimary) {
                        $hasPrimary = true;
                    }
                    $starterFiles[] = [
                        'name' => trim($file['name']),
                        'content' => $file['content'] ?? '',
                        'is_primary' => $isPrimary,
                        'is_readonly' => ! empty($file['is_readonly']),
                    ];
                }
            }
            if (! $hasPrimary && count($starterFiles) > 0) {
                $starterFiles[0]['is_primary'] = true;
            }
        }

        $module = Module::findOrFail($validated['module_id']);
        $this->authorizeModuleManager($module);

        $availabilityMode = $validated['availability_mode'] ?? 'open';
        $liveDuration = $validated['live_duration_minutes'] ?? $validated['time_limit'];

        $lab = Laboratory::create([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'github_repo_template' => $validated['github_repo_template'] ?? null,
            'time_limit' => $validated['time_limit'],
            'max_attempts' => $validated['max_attempts'] ?? 1,
            'is_group_lab' => $request->has('is_group_lab'),
            'availability_mode' => $availabilityMode,
            'live_duration_minutes' => $availabilityMode === 'live' ? $liveDuration : null,
            'live_status' => $availabilityMode === 'live' ? 'not_started' : 'active',
            'module_id' => $validated['module_id'],
            'tasks_definition' => $tasksDefinition,
            'starter_files' => ! empty($starterFiles) ? $starterFiles : null,
        ]);

        $lab->competencies()->sync($validated['competencies'] ?? []);

        // Send notifications to enrolled students
        $class = SchoolClass::findOrFail($module->class_id);
        $students = $class->students()->wherePivot('status', 'enrolled')->get();
        foreach ($students as $student) {
            try {
                $student->notify(new ClassActivityNotification(
                    "New Lab Challenge: {$lab->title}",
                    "A new laboratory exercise '{$lab->title}' has been added to module '{$module->title}' in {$class->name}.",
                    route('modules.show', [$class->id, $module->id]),
                    'lab'
                ));
            } catch (\Throwable $e) {
                Log::warning("Failed to deliver lab notification to student {$student->id}: ".$e->getMessage());
            }
        }

        return redirect()->route('classes.show', $module->class_id)->with('success', 'Laboratory created successfully.');
    }

    /**
     * Display the specified laboratory.
     */
    public function show(Laboratory $laboratory)
    {
        $this->authorizeLabViewer($laboratory);
        $laboratory->recordViewBy(auth()->user());
        $laboratory->load(['module.schoolClass']);

        // Check if student already has a session (stale ones are closed first)
        LabSession::autoExpireStaleSessions($laboratory->id, auth()->id());
        $activeSession = $laboratory->labSessions()
            ->where('user_id', auth()->id())
            ->where('status', 'in_progress')
            ->first();

        $user = auth()->user();
        $attempts = $user->role === 'student'
            ? ['limit' => $laboratory->attemptLimit(), 'used' => $laboratory->attemptsUsedBy($user), 'canStart' => $laboratory->canStartNewAttempt($user)]
            : null;

        // The student's finished attempts (their own and their team's), newest first, each
        // linking to its results page
        $results = collect();
        $attemptNumbers = collect();
        if ($user->role === 'student') {
            $results = $laboratory->labSessions()
                ->where(fn ($q) => $q->where('user_id', $user->id)
                    ->orWhereIn('group_id', $user->groups()->select('groups.id')))
                ->where('status', '!=', 'in_progress')
                ->orderByDesc('id')
                ->get(['id', 'lab_id', 'user_id', 'group_id', 'status', 'performance_score', 'instructor_grade_override', 'started_at', 'ended_at']);
            // Attempt N counts every attempt the student started, as the attempt limit does
            $attemptNumbers = $laboratory->labSessions()->where('user_id', $user->id)
                ->orderBy('id')->pluck('id')->flip()->map(fn ($index) => $index + 1);
        }

        return view('laboratories.show', compact('laboratory', 'activeSession', 'attempts', 'results', 'attemptNumbers'));
    }

    /**
     * Show the form for editing the specified laboratory.
     */
    public function edit(Laboratory $laboratory)
    {
        $this->authorizeAdminOrInstructor();

        $module = Module::findOrFail($laboratory->module_id);
        $class = SchoolClass::with('modules')->findOrFail($module->class_id);
        $this->authorizeClassManager($class);

        return view('laboratories.edit', compact('laboratory', 'class'));
    }

    /**
     * Update the specified laboratory in storage.
     */
    public function update(Request $request, Laboratory $laboratory)
    {
        $this->authorizeAdminOrInstructor();
        $this->authorizeLabManager($laboratory);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'github_repo_template' => 'nullable|string|max:255',
            'time_limit' => 'required|integer|min:5|max:300',
            'max_attempts' => 'nullable|integer|min:0|max:10',
            'is_group_lab' => 'boolean',
            'availability_mode' => 'nullable|in:open,live',
            'live_duration_minutes' => 'nullable|integer|min:1|max:600',
            'module_id' => 'required|exists:modules,id',
            'tasks' => 'nullable|array',
            'tasks.*.task' => 'required|string|max:255',
            'tasks.*.command' => 'nullable|string|max:255',
            'starter_files' => 'nullable|array',
            'starter_files.*.name' => 'nullable|string|max:255',
            'starter_files.*.content' => 'nullable|string',
            'competencies' => 'nullable|array',
            'competencies.*' => 'integer|exists:competencies,id',
        ]);

        $tasksDefinition = [];
        if (! empty($validated['tasks'])) {
            foreach ($validated['tasks'] as $index => $taskData) {
                $tasksDefinition[] = [
                    'id' => $index + 1,
                    'task' => $taskData['task'],
                    'command' => $taskData['command'] ?? '',
                ];
            }
        }

        // Process starter files manifest
        $starterFiles = [];
        if (! empty($request->input('starter_files')) && is_array($request->input('starter_files'))) {
            $hasPrimary = false;
            foreach ($request->input('starter_files') as $file) {
                if (! empty($file['name'])) {
                    $isPrimary = ! empty($file['is_primary']);
                    if ($isPrimary) {
                        $hasPrimary = true;
                    }
                    $starterFiles[] = [
                        'name' => trim($file['name']),
                        'content' => $file['content'] ?? '',
                        'is_primary' => $isPrimary,
                        'is_readonly' => ! empty($file['is_readonly']),
                    ];
                }
            }
            if (! $hasPrimary && count($starterFiles) > 0) {
                $starterFiles[0]['is_primary'] = true;
            }
        }

        $module = Module::findOrFail($validated['module_id']);
        // Moving the lab is only allowed into a module of a class you also manage
        $this->authorizeModuleManager($module);

        $availabilityMode = $validated['availability_mode'] ?? $laboratory->availability_mode ?? 'open';
        $liveDuration = $validated['live_duration_minutes'] ?? $laboratory->live_duration_minutes ?? $validated['time_limit'];

        $updateData = [
            'title' => $validated['title'],
            'description' => $validated['description'],
            // No longer on the form; keep whatever was saved before
            'github_repo_template' => $validated['github_repo_template'] ?? $laboratory->github_repo_template,
            'time_limit' => $validated['time_limit'],
            'max_attempts' => $validated['max_attempts'] ?? $laboratory->max_attempts ?? 1,
            'is_group_lab' => $request->has('is_group_lab'),
            'availability_mode' => $availabilityMode,
            'live_duration_minutes' => $availabilityMode === 'live' ? $liveDuration : null,
            'module_id' => $validated['module_id'],
            'tasks_definition' => $tasksDefinition,
            'starter_files' => ! empty($starterFiles) ? $starterFiles : null,
        ];

        if ($availabilityMode === 'live' && empty($laboratory->live_status)) {
            $updateData['live_status'] = 'not_started';
        }

        $laboratory->update($updateData);
        $synced = $laboratory->competencies()->sync($validated['competencies'] ?? []);
        // Students who already finished the lab gain or lose credit for the changed competencies
        StudentCompetency::refreshForLabCompetencies($laboratory, array_merge($synced['attached'], $synced['detached']));

        return redirect()->route('classes.show', $module->class_id)->with('success', 'Laboratory updated successfully.');
    }

    /**
     * Remove the specified laboratory from storage.
     */
    public function destroy(Laboratory $laboratory)
    {
        $this->authorizeAdminOrInstructor();
        $this->authorizeLabManager($laboratory);

        $classId = null;
        if ($laboratory->module_id) {
            $module = Module::find($laboratory->module_id);
            if ($module) {
                $classId = $module->class_id;
            }
        }

        $laboratory->delete();

        if ($classId) {
            return redirect()->route('classes.show', $classId)->with('success', 'Laboratory deleted successfully.');
        }

        return redirect()->route('classes.index')->with('success', 'Laboratory deleted successfully.');
    }

    /**
     * Helper to restrict actions to Admins and Instructors.
     */
    protected function authorizeAdminOrInstructor()
    {
        $role = auth()->user()->role ?? 'student';
        if ($role !== 'admin' && $role !== 'instructor') {
            abort(403, 'Unauthorized action.');
        }
    }

    /**
     * Manually trigger and open a Live Lab with a fixed duration window (Feature 9).
     */
    public function openLive(Request $request, int $id)
    {
        $this->authorizeAdminOrInstructor();
        $laboratory = Laboratory::findOrFail($id);
        $this->authorizeLabManager($laboratory);

        $duration = $request->input('duration_minutes') ? (int) $request->input('duration_minutes') : null;
        $laboratory->openLive($duration);

        $mins = (int) ($laboratory->getLiveTotalDurationSeconds() / 60);

        return back()->with('success', "Live Lab opened! The {$mins}-minute countdown has started for all students.");
    }

    /**
     * Manually end a Live Lab immediately, auto-submitting all active sessions (Feature 9 & 7A).
     */
    public function endLive(Request $request, int $id)
    {
        $this->authorizeAdminOrInstructor();
        $laboratory = Laboratory::findOrFail($id);
        $this->authorizeLabManager($laboratory);

        $laboratory->closeLive();

        return back()->with('success', 'Live Lab ended. All active student sessions have been auto-submitted and closed.');
    }

    /**
     * Reopen a closed Live Lab, carrying forward only the remaining time from the original countdown (Feature 9).
     */
    public function reopenLive(Request $request, int $id)
    {
        $this->authorizeAdminOrInstructor();
        $laboratory = Laboratory::findOrFail($id);
        $this->authorizeLabManager($laboratory);

        $extendMinutes = $request->input('extend_minutes') ?? $request->input('add_minutes');
        $extendMinutes = $extendMinutes ? (int) $extendMinutes : null;
        $success = $laboratory->reopenLive($extendMinutes);

        if (! $success) {
            return back()->with('error', 'Cannot reopen Live Lab: The countdown duration has already fully expired. Live Labs do not grant a fresh duration.');
        }

        $remainingMin = max(1, (int) ceil($laboratory->getRemainingLiveSeconds() / 60));

        return back()->with('success', "Live Lab reopened! Countdown resumed with {$remainingMin} minute(s) remaining.");
    }
}

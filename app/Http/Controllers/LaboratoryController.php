<?php

namespace App\Http\Controllers;

use App\Models\Laboratory;
use Illuminate\Http\Request;

class LaboratoryController extends Controller
{
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

        if (!$class_id) {
            return redirect()->route('classes.index')->with('error', 'Select a class to add a laboratory.');
        }

        $class = \App\Models\SchoolClass::with('modules')->findOrFail($class_id);
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
            'is_group_lab' => 'boolean',
            'module_id' => 'required|exists:modules,id',
            'tasks' => 'nullable|array',
            'tasks.*.task' => 'required|string|max:255',
            'tasks.*.command' => 'nullable|string|max:255',
            'starter_files' => 'nullable|array',
            'starter_files.*.name' => 'nullable|string|max:255',
            'starter_files.*.content' => 'nullable|string',
        ]);

        // Convert key-value tasks to JSON structures
        $tasksDefinition = [];
        if (!empty($validated['tasks'])) {
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
        if (!empty($request->input('starter_files')) && is_array($request->input('starter_files'))) {
            $hasPrimary = false;
            foreach ($request->input('starter_files') as $file) {
                if (!empty($file['name'])) {
                    $isPrimary = !empty($file['is_primary']);
                    if ($isPrimary) {
                        $hasPrimary = true;
                    }
                    $starterFiles[] = [
                        'name' => trim($file['name']),
                        'content' => $file['content'] ?? '',
                        'is_primary' => $isPrimary,
                        'is_readonly' => !empty($file['is_readonly']),
                    ];
                }
            }
            if (!$hasPrimary && count($starterFiles) > 0) {
                $starterFiles[0]['is_primary'] = true;
            }
        }

        $module = \App\Models\Module::findOrFail($validated['module_id']);

        $lab = Laboratory::create([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'github_repo_template' => $validated['github_repo_template'] ?? null,
            'time_limit' => $validated['time_limit'],
            'is_group_lab' => $request->has('is_group_lab'),
            'module_id' => $validated['module_id'],
            'tasks_definition' => $tasksDefinition,
            'starter_files' => !empty($starterFiles) ? $starterFiles : null,
        ]);

        // Send notifications to enrolled students
        $class = \App\Models\SchoolClass::findOrFail($module->class_id);
        $students = $class->students()->wherePivot('status', 'enrolled')->get();
        foreach ($students as $student) {
            $student->notify(new \App\Notifications\ClassActivityNotification(
                "New Lab Challenge: {$lab->title}",
                "A new laboratory exercise '{$lab->title}' has been added to module '{$module->title}' in {$class->name}.",
                route('modules.show', [$class->id, $module->id]),
                'lab'
            ));
        }

        return redirect()->route('classes.show', $module->class_id)->with('success', 'Laboratory created successfully.');
    }

    /**
     * Display the specified laboratory.
     */
    public function show(Laboratory $laboratory)
    {
        $this->recordUniqueView($laboratory);

        // Check if student already has a session
        $activeSession = $laboratory->labSessions()
            ->where('user_id', auth()->id())
            ->where('status', 'in_progress')
            ->first();

        return view('laboratories.show', compact('laboratory', 'activeSession'));
    }

    /**
     * Show the form for editing the specified laboratory.
     */
    public function edit(Laboratory $laboratory)
    {
        $this->authorizeAdminOrInstructor();

        $module = \App\Models\Module::findOrFail($laboratory->module_id);
        $class = \App\Models\SchoolClass::with('modules')->findOrFail($module->class_id);

        return view('laboratories.edit', compact('laboratory', 'class'));
    }

    /**
     * Update the specified laboratory in storage.
     */
    public function update(Request $request, Laboratory $laboratory)
    {
        $this->authorizeAdminOrInstructor();

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'required|string',
            'github_repo_template' => 'nullable|string|max:255',
            'time_limit' => 'required|integer|min:5|max:300',
            'is_group_lab' => 'boolean',
            'module_id' => 'required|exists:modules,id',
            'tasks' => 'nullable|array',
            'tasks.*.task' => 'required|string|max:255',
            'tasks.*.command' => 'nullable|string|max:255',
            'starter_files' => 'nullable|array',
            'starter_files.*.name' => 'nullable|string|max:255',
            'starter_files.*.content' => 'nullable|string',
        ]);

        $tasksDefinition = [];
        if (!empty($validated['tasks'])) {
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
        if (!empty($request->input('starter_files')) && is_array($request->input('starter_files'))) {
            $hasPrimary = false;
            foreach ($request->input('starter_files') as $file) {
                if (!empty($file['name'])) {
                    $isPrimary = !empty($file['is_primary']);
                    if ($isPrimary) {
                        $hasPrimary = true;
                    }
                    $starterFiles[] = [
                        'name' => trim($file['name']),
                        'content' => $file['content'] ?? '',
                        'is_primary' => $isPrimary,
                        'is_readonly' => !empty($file['is_readonly']),
                    ];
                }
            }
            if (!$hasPrimary && count($starterFiles) > 0) {
                $starterFiles[0]['is_primary'] = true;
            }
        }

        $module = \App\Models\Module::findOrFail($validated['module_id']);

        $laboratory->update([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'github_repo_template' => $validated['github_repo_template'],
            'time_limit' => $validated['time_limit'],
            'is_group_lab' => $request->has('is_group_lab'),
            'module_id' => $validated['module_id'],
            'tasks_definition' => $tasksDefinition,
            'starter_files' => !empty($starterFiles) ? $starterFiles : null,
        ]);

        return redirect()->route('classes.show', $module->class_id)->with('success', 'Laboratory updated successfully.');
    }

    /**
     * Remove the specified laboratory from storage.
     */
    public function destroy(Laboratory $laboratory)
    {
        $this->authorizeAdminOrInstructor();

        $classId = null;
        if ($laboratory->module_id) {
            $module = \App\Models\Module::find($laboratory->module_id);
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
     * Start a new laboratory session for a student and redirect to VS Code.
     */
    public function startSession(int $id)
    {
        $laboratory = Laboratory::findOrFail($id);
        /** @var \App\Models\User|null $user */
        $user = auth()->user();

        $this->recordUniqueView($laboratory);

        // Find existing in-progress session
        $session = \App\Models\LabSession::where('lab_id', $laboratory->id)
            ->where('user_id', $user ? $user->id : null)
            ->where('status', 'in_progress')
            ->latest()
            ->first();

        // If session exists but has exceeded its time limit, mark it abandoned and start fresh
        if ($session && $laboratory->time_limit > 0) {
            $elapsed = $session->started_at ? (int) $session->started_at->diffInSeconds(now(), true) : 0;
            if ($elapsed >= ($laboratory->time_limit * 60)) {
                $session->update([
                    'status' => 'abandoned',
                    'ended_at' => now(),
                ]);
                $session = null;
            }
        }

        if (!$session) {
            $session = \App\Models\LabSession::create([
                'lab_id' => $laboratory->id,
                'user_id' => $user ? $user->id : null,
                'status' => 'in_progress',
                'started_at' => now(),
                'performance_score' => 0.0,
            ]);
        }

        $backendUrl = request()->getSchemeAndHttpHost();
        $vscodeUrl = "vscode://certicode.certicode-labs/connect?sessionId={$session->id}&backendUrl=" . urlencode($backendUrl);

        return redirect($vscodeUrl);
    }

    /**
     * Complete and grade the laboratory workspace session.
     */
    public function completeSession(int $id)
    {
        $session = \App\Models\LabSession::findOrFail($id);

        // Check ownership
        if (auth()->id() !== $session->user_id && auth()->user()->role === 'student') {
            abort(403, 'Unauthorized.');
        }

        $session->update([
            'status' => 'completed',
            'ended_at' => now(),
        ]);

        // Log completion in telemetry
        \App\Models\TelemetryLog::create([
            'lab_session_id' => $session->id,
            'event_type' => 'session_completed',
            'payload' => [
                'final_score' => $session->performance_score,
                'completed_tasks_count' => count($session->completed_tasks ?? []),
            ],
        ]);

        // Find associated class to redirect back
        $lab = $session->laboratory;
        $classId = null;
        if ($lab->module_id) {
            $module = \App\Models\Module::find($lab->module_id);
            if ($module) {
                $classId = $module->class_id;
            }
        }

        $redirect = $classId ? redirect()->route('classes.show', $classId) : redirect()->route('classes.index');
        return $redirect->with('success', "Laboratory session completed successfully! Final Performance Score: {$session->performance_score}%.");
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
     * Record a unique view of the laboratory for students.
     */
    private function recordUniqueView(Laboratory $laboratory)
    {
        $user = auth()->user();
        if ($user && $user->role === 'student') {
            $alreadyViewed = \App\Models\LaboratoryView::where('laboratory_id', $laboratory->id)
                ->where('user_id', $user->id)
                ->exists();
            if (!$alreadyViewed) {
                \App\Models\LaboratoryView::create([
                    'laboratory_id' => $laboratory->id,
                    'user_id' => $user->id,
                ]);
                $laboratory->increment('views_count');
            }
        }
    }
}

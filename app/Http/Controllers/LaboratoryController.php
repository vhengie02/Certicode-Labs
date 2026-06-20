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
        $laboratories = Laboratory::latest()->get();
        return view('laboratories.index', compact('laboratories'));
    }

    /**
     * Show the form for creating a new laboratory.
     */
    public function create()
    {
        $this->authorizeAdminOrInstructor();

        return view('laboratories.create');
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
            'tasks' => 'nullable|array',
            'tasks.*.task' => 'required|string|max:255',
            'tasks.*.command' => 'nullable|string|max:255',
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

        Laboratory::create([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'github_repo_template' => $validated['github_repo_template'],
            'time_limit' => $validated['time_limit'],
            'is_group_lab' => $request->has('is_group_lab'),
            'tasks_definition' => $tasksDefinition,
        ]);

        return redirect()->route('laboratories.index')->with('success', 'Laboratory created successfully.');
    }

    /**
     * Display the specified laboratory.
     */
    public function show(Laboratory $laboratory)
    {
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

        return view('laboratories.edit', compact('laboratory'));
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
            'tasks' => 'nullable|array',
            'tasks.*.task' => 'required|string|max:255',
            'tasks.*.command' => 'nullable|string|max:255',
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

        $laboratory->update([
            'title' => $validated['title'],
            'description' => $validated['description'],
            'github_repo_template' => $validated['github_repo_template'],
            'time_limit' => $validated['time_limit'],
            'is_group_lab' => $request->has('is_group_lab'),
            'tasks_definition' => $tasksDefinition,
        ]);

        return redirect()->route('laboratories.index')->with('success', 'Laboratory updated successfully.');
    }

    /**
     * Remove the specified laboratory from storage.
     */
    public function destroy(Laboratory $laboratory)
    {
        $this->authorizeAdminOrInstructor();

        $laboratory->delete();

        return redirect()->route('laboratories.index')->with('success', 'Laboratory deleted successfully.');
    }

    /**
     * Start a new laboratory session for a student.
     */
    public function startSession($id)
    {
        $laboratory = Laboratory::findOrFail($id);
        $user = auth()->user();

        // Create or find session
        $session = \App\Models\LabSession::firstOrCreate([
            'lab_id' => $laboratory->id,
            'user_id' => $user->id,
            'status' => 'in_progress',
        ], [
            'started_at' => now(),
            'performance_score' => 0.0,
        ]);

        return redirect()->route('sessions.show', $session->id)->with('success', 'Workspace session initialized.');
    }

    /**
     * Display the laboratory workspace session.
     */
    public function showWorkspace($id)
    {
        $session = \App\Models\LabSession::with('laboratory')->findOrFail($id);

        // A student can only view their own sessions, unless they are admin/instructor
        if (auth()->id() !== $session->user_id && auth()->user()->role === 'student') {
            abort(403, 'Unauthorized.');
        }

        return view('laboratories.workspace', compact('session'));
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
}

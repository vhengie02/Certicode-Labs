<?php

namespace App\Http\Controllers;

use App\Models\SchoolClass;
use App\Models\Module;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;

class ClassController extends Controller
{
    /**
     * Display a listing of classes.
     */
    public function index()
    {
        $user = Auth::user();
        if (!$user instanceof User) {
            return redirect()->route('login');
        }
        $invitedClasses = collect();

        if ($user->role === 'student') {
            $classes = $user->classes()->with('instructor')->withCount('modules')->latest()->get();
            $invitedClasses = $user->invitedClasses()->with('instructor')->withCount('modules')->latest()->get();
        } else {
            // Instructor / Admin
            $classes = SchoolClass::where('instructor_id', $user->id)
                ->with('instructor')
                ->withCount(['students', 'modules'])
                ->latest()
                ->get();
        }

        return view('classes.index', compact('classes', 'invitedClasses'));
    }

    /**
     * Show the form for creating a new class.
     */
    public function create()
    {
        $this->authorizeInstructor();
        return view('classes.create');
    }

    /**
     * Store a newly created class in database.
     */
    public function store(Request $request)
    {
        $this->authorizeInstructor();

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'passing_threshold' => 'nullable|integer|min:1|max:100',
            'scheduled_end_date' => 'nullable|date',
        ]);

        // Generate unique 8-character code: CLASS-XXXX
        $code = 'CLASS-' . strtoupper(Str::random(6));
        while (SchoolClass::where('code', $code)->exists()) {
            $code = 'CLASS-' . strtoupper(Str::random(6));
        }

        SchoolClass::create([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'code' => $code,
            'instructor_id' => (int) Auth::id(),
            'passing_threshold' => $validated['passing_threshold'] ?? 75,
            'scheduled_end_date' => $validated['scheduled_end_date'] ?? null,
            'status' => 'active',
        ]);

        return redirect()->route('classes.index')->with('success', 'Class created successfully with join code: ' . $code);
    }

    /**
     * Display the specified class details.
     */
    public function show(int $id)
    {
        $class = SchoolClass::with([
            'modules.laboratories.labSessions',
            'modules.children.laboratories.labSessions',
            'students',
            'instructor'
        ])->findOrFail($id);
        $user = Auth::user();
        if (!$user instanceof User) {
            return redirect()->route('login');
        }

        // Authorize student access
        if ($user->role === 'student') {
            $isEnrolled = $class->students()->where('student_id', $user->id)->wherePivot('status', 'enrolled')->exists();
            $isInvited = $class->students()->where('student_id', $user->id)->wherePivot('status', 'invited')->exists();
            
            if (!$isEnrolled && !$isInvited) {
                abort(403, 'You are not enrolled in this class.');
            }

            if ($isInvited) {
                return view('classes.invited', compact('class'));
            }
        }

        $completedLabIds = [];
        if ($user->role === 'student') {
            $completedLabIds = \App\Models\LabSession::where('user_id', $user->id)
                ->where('status', 'completed')
                ->pluck('lab_id', 'lab_id')
                ->toArray();
        }

        return view('classes.show', compact('class', 'completedLabIds'));
    }

    /**
     * Show form to edit class.
     */
    public function edit(int $id)
    {
        $this->authorizeInstructor();
        $class = SchoolClass::findOrFail($id);
        return view('classes.edit', compact('class'));
    }

    /**
     * Update class.
     */
    public function update(Request $request, int $id)
    {
        $this->authorizeInstructor();
        $class = SchoolClass::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'passing_threshold' => 'nullable|integer|min:1|max:100',
            'scheduled_end_date' => 'nullable|date',
            'status' => 'nullable|string|in:active,completed,closed',
        ]);

        $class->update($validated);

        return redirect()->route('classes.show', $class->id)->with('success', 'Class updated successfully.');
    }

    /**
     * Delete class.
     */
    public function destroy(int $id)
    {
        $this->authorizeInstructor();
        $class = SchoolClass::findOrFail($id);
        $class->delete();

        return redirect()->route('classes.index')->with('success', 'Class deleted successfully.');
    }

    /**
     * Join class using join code.
     */
    public function joinByCode(Request $request)
    {
        $request->validate([
            'code' => 'required|string|max:20',
        ]);

        $class = SchoolClass::where('code', strtoupper($request->code))->first();

        if (!$class) {
            return redirect()->back()->with('error', 'Invalid enrollment code. Please check with your instructor.');
        }

        $userId = Auth::id();
        if ($userId) {
            // Attach student
            $class->students()->syncWithoutDetaching([
                $userId => ['status' => 'enrolled']
            ]);

            // If they had an invitation, make sure it is updated to enrolled
            $class->students()->updateExistingPivot($userId, ['status' => 'enrolled']);
        }

        return redirect()->route('classes.show', $class->id)->with('success', 'Successfully enrolled in ' . $class->name);
    }

    /**
     * Invite student by Email (Gmail invite).
     */
    public function inviteStudent(Request $request, int $id)
    {
        $this->authorizeInstructor();
        $class = SchoolClass::findOrFail($id);

        $request->validate([
            'email' => 'required|email|max:255',
        ]);

        $student = User::where('email', $request->email)->first();

        if (!$student) {
            return redirect()->back()->with('error', 'Student email not found in Certicode system. Ask them to register first.');
        }

        // Enroll them as invited
        $class->students()->syncWithoutDetaching([
            $student->id => ['status' => 'invited']
        ]);

        // Send invite notification
        $student->notify(new \App\Notifications\ClassActivityNotification(
            "Invited to Class: {$class->name}",
            "You have been invited to join the class '{$class->name}' by {$class->instructor->name}.",
            route('classes.index'),
            'class'
        ));

        return redirect()->back()->with('success', 'Invitation successfully sent. The class will automatically appear in ' . $student->name . '\'s Classes tab.');
    }

    /**
     * Accept Class invitation.
     */
    public function acceptInvite(Request $request, int $class_id)
    {
        $class = SchoolClass::findOrFail($class_id);
        $student = Auth::user();
        if (!$student instanceof User) {
            return redirect()->route('login');
        }
        
        $class->students()->updateExistingPivot($student->id, [
            'status' => 'enrolled'
        ]);

        // Notify the instructor
        if ($class->instructor) {
            $class->instructor->notify(new \App\Notifications\ClassActivityNotification(
                "Student Joined Class: {$class->name}",
                "{$student->name} has accepted your invitation and joined the class '{$class->name}'.",
                route('classes.show', $class->id),
                'class'
            ));
        }

        return redirect()->route('classes.show', $class->id)->with('success', 'Invitation accepted. Welcome to ' . $class->name . '!');
    }

    /**
     * Show form to create a module.
     */
    public function createModule(int $class_id)
    {
        $this->authorizeInstructor();
        $class = SchoolClass::findOrFail($class_id);

        return view('classes.module-create', compact('class'));
    }

    /**
     * Store a Module inside a Class.
     */
    public function storeModule(Request $request, int $class_id)
    {
        $this->authorizeInstructor();
        $class = SchoolClass::findOrFail($class_id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'content' => 'required|string',
            'order_index' => 'integer',
            'parent_id' => 'nullable|exists:modules,id,class_id,' . $class->id,
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:20480', // Max 20MB
        ]);

        $parentId = $validated['parent_id'] ?? null;
        $existingCount = Module::where('class_id', $class->id)
            ->where('parent_id', $parentId)
            ->count();

        $orderIndex = isset($validated['order_index'])
            ? min(max(0, (int)$validated['order_index']), $existingCount)
            : $existingCount;

        $module = Module::create([
            'class_id' => $class->id,
            'parent_id' => $parentId,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'content' => $validated['content'],
            'order_index' => $orderIndex,
        ]);

        // Handle file attachments
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('module_attachments', 'public');
                $module->attachments()->create([
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'file_size' => $file->getSize(),
                ]);
            }
        }

        // Notify enrolled students
        $students = $class->students()->wherePivot('status', 'enrolled')->get();
        foreach ($students as $student) {
            $student->notify(new \App\Notifications\ClassActivityNotification(
                "New Module: {$module->title}",
                "A new module '{$module->title}' has been uploaded in {$class->name}.",
                route('modules.show', [$class->id, $module->id]),
                'module'
            ));
        }

        return redirect()->route('classes.show', $class->id)->with('success', 'Module created successfully.');
    }

    /**
     * Display a specific module.
     */
    public function showModule(int $class_id, int $module_id)
    {
        $class = SchoolClass::with([
            'modules.children.laboratories',
            'modules.laboratories'
        ])->findOrFail($class_id);
        $module = Module::with(['laboratories', 'attachments'])->findOrFail($module_id);
        $user = Auth::user();
        if (!$user instanceof User) {
            return redirect()->route('login');
        }

        // Check if student is enrolled in the class
        if ($user->role === 'student') {
            $isEnrolled = $class->students()->where('student_id', $user->id)->wherePivot('status', 'enrolled')->exists();
            if (!$isEnrolled) {
                abort(403, 'Unauthorized.');
            }

            // Record unique view and increment if first time
            $alreadyViewed = \App\Models\ModuleView::where('module_id', $module->id)
                ->where('user_id', $user->id)
                ->exists();
            if (!$alreadyViewed) {
                \App\Models\ModuleView::create([
                    'module_id' => $module->id,
                    'user_id' => $user->id,
                ]);
                $module->increment('views_count');
            }
        }

        return view('classes.module-show', compact('class', 'module'));
    }

    /**
     * Edit a specific module.
     */
    public function editModule(int $class_id, int $module_id)
    {
        $this->authorizeInstructor();
        $class = SchoolClass::findOrFail($class_id);
        $module = Module::with('attachments')->findOrFail($module_id);

        return view('classes.module-edit', compact('class', 'module'));
    }

    /**
     * Update a specific module.
     */
    public function updateModule(Request $request, int $class_id, int $module_id)
    {
        $this->authorizeInstructor();
        $class = SchoolClass::findOrFail($class_id);
        $module = Module::findOrFail($module_id);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'content' => 'required|string',
            'order_index' => 'integer',
            'parent_id' => 'nullable|exists:modules,id,class_id,' . $class->id . '|not_in:' . $module->id,
            'attachments' => 'nullable|array',
            'attachments.*' => 'file|max:20480',
            'remove_attachments' => 'nullable|array',
            'remove_attachments.*' => 'exists:module_attachments,id',
        ]);

        $parentId = $validated['parent_id'] ?? null;
        $existingCount = Module::where('class_id', $class->id)
            ->where('parent_id', $parentId)
            ->where('id', '!=', $module->id)
            ->count();

        $orderIndex = isset($validated['order_index'])
            ? min(max(0, (int)$validated['order_index']), $existingCount)
            : $existingCount;

        $module->update([
            'parent_id' => $parentId,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'content' => $validated['content'],
            'order_index' => $orderIndex,
        ]);

        // Remove marked attachments
        if (!empty($validated['remove_attachments'])) {
            foreach ($validated['remove_attachments'] as $attId) {
                $attachment = $module->attachments()->find($attId);
                if ($attachment) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($attachment->file_path);
                    $attachment->delete();
                }
            }
        }

        // Add new attachments
        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('module_attachments', 'public');
                $module->attachments()->create([
                    'file_name' => $file->getClientOriginalName(),
                    'file_path' => $path,
                    'file_size' => $file->getSize(),
                ]);
            }
        }

        return redirect()->route('modules.show', [$class->id, $module->id])->with('success', 'Module updated successfully.');
    }

    /**
     * Delete a specific module.
     */
    public function destroyModule(int $class_id, int $module_id)
    {
        $this->authorizeInstructor();
        $module = Module::findOrFail($module_id);

        // Delete associated files
        foreach ($module->attachments as $attachment) {
            \Illuminate\Support\Facades\Storage::disk('public')->delete($attachment->file_path);
        }

        $module->delete();

        return redirect()->route('classes.show', $class_id)->with('success', 'Module deleted successfully.');
    }

    /**
     * Download attachment.
     */
    public function downloadAttachment(int $id)
    {
        $attachment = \App\Models\ModuleAttachment::findOrFail($id);
        $module = $attachment->module;
        $class = $module->schoolClass;
        $user = Auth::user();
        if (!$user instanceof User) {
            return redirect()->route('login');
        }

        // Authorize access to attachment (must be enrolled in the class or instructor)
        if ($user->role === 'student') {
            $isEnrolled = $class->students()->where('student_id', $user->id)->wherePivot('status', 'enrolled')->exists();
            if (!$isEnrolled) {
                abort(403, 'Unauthorized.');
            }
        }

        if (!\Illuminate\Support\Facades\Storage::disk('public')->exists($attachment->file_path)) {
            abort(404, 'File not found.');
        }

        $filePath = (string) $attachment->file_path;
        $fileName = (string) $attachment->file_name;
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = \Illuminate\Support\Facades\Storage::disk('public');
        return $disk->download($filePath, $fileName);
    }

    /**
     * Display real-time telemetry and anomalies monitor dashboard.
     */
    public function telemetry(int $class_id)
    {
        $this->authorizeInstructor();
        $class = SchoolClass::findOrFail($class_id);

        $labIds = \App\Models\Laboratory::whereIn('module_id', function ($query) use ($class) {
            $query->select('id')->from('modules')->where('class_id', $class->id);
        })->pluck('id');

        $sessions = \App\Models\LabSession::with(['user', 'laboratory'])
            ->whereIn('lab_id', $labIds)
            ->latest()
            ->get();

        $anomalies = \App\Models\Anomaly::with(['labSession.user', 'labSession.laboratory'])
            ->whereIn('lab_session_id', $sessions->pluck('id'))
            ->latest()
            ->get();

        return view('classes.telemetry', compact('class', 'sessions', 'anomalies'));
    }

    /**
     * View detailed timeline for a specific student workspace session.
     */
    public function telemetryTimeline(int $id)
    {
        $this->authorizeInstructor();
        $session = \App\Models\LabSession::with(['user', 'laboratory.module.schoolClass'])->findOrFail($id);

        $logs = \App\Models\TelemetryLog::where('lab_session_id', $session->id)
            ->latest()
            ->get();

        $anomalies = \App\Models\Anomaly::where('lab_session_id', $session->id)
            ->latest()
            ->get();

        return view('classes.telemetry-timeline', compact('session', 'logs', 'anomalies'));
    }

    /**
     * Mark an anomaly as resolved.
     */
    public function resolveAnomaly(int $id)
    {
        $this->authorizeInstructor();
        $anomaly = \App\Models\Anomaly::findOrFail($id);
        $anomaly->update(['resolved' => true]);

        return back()->with('success', 'Anomaly marked as resolved.');
    }

    /**
     * Conclude the class course and issue certificates to students meeting passing threshold.
     */
    public function endClass(Request $request, int $id)
    {
        $this->authorizeInstructor();
        $class = SchoolClass::with(['students', 'modules.laboratories'])->findOrFail($id);

        $class->update([
            'status' => 'completed',
        ]);

        $threshold = $class->passing_threshold ?? 75;
        $certifiedCount = 0;

        foreach ($class->students as $student) {
            $progress = $class->getStudentProgress($student);
            if ($progress['percent'] >= $threshold && $progress['total'] > 0) {
                $existing = \App\Models\Certificate::where('user_id', $student->id)
                    ->where('class_id', $class->id)
                    ->first();

                if (!$existing) {
                    $code = 'CERT-' . strtoupper(Str::random(12));
                    while (\App\Models\Certificate::where('verification_code', $code)->exists()) {
                        $code = 'CERT-' . strtoupper(Str::random(12));
                    }

                    $cert = \App\Models\Certificate::create([
                        'user_id' => $student->id,
                        'class_id' => $class->id,
                        'verification_code' => $code,
                        'qr_code_path' => 'certificates/qr-' . $code . '.svg',
                        'issued_at' => now(),
                    ]);

                    $student->notify(new \App\Notifications\ClassActivityNotification(
                        "Course Completed: {$class->name}",
                        "Congratulations! You completed '{$class->name}' with {$progress['percent']}% (threshold: {$threshold}%) and earned your official certificate.",
                        route('certificates.show', $cert->id),
                        'certificate'
                    ));

                    $certifiedCount++;
                }
            }
        }

        return redirect()->route('classes.show', $class->id)
            ->with('success', "Course has been successfully concluded. {$certifiedCount} qualifying student(s) awarded certificates!");
    }

    /**
     * End an individual lab session and clear ephemeral state.
     */
    public function endSession(Request $request, int $id)
    {
        $this->authorizeInstructor();
        $session = \App\Models\LabSession::findOrFail($id);

        $session->update([
            'status' => 'completed',
            'ended_at' => $session->ended_at ?: now(),
            'closed_at' => now(),
        ]);

        // Clean ephemeral chat records
        \App\Models\LabSessionChat::where('lab_session_id', $session->id)->delete();

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Lab session ended and ephemeral state cleared.',
                'session' => $session,
            ]);
        }

        return back()->with('success', 'Lab session ended and ephemeral chat cleared.');
    }

    /**
     * Reopen an individual lab session.
     */
    public function reopenSession(Request $request, int $id)
    {
        $this->authorizeInstructor();
        $session = \App\Models\LabSession::findOrFail($id);

        $session->update([
            'status' => 'in_progress',
            'ended_at' => null,
            'closed_at' => null,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Lab session reopened.',
                'session' => $session,
            ]);
        }

        return back()->with('success', 'Lab session reopened.');
    }

    /**
     * Restrict helper.
     */
    protected function authorizeInstructor()
    {
        $user = Auth::user();
        if (!$user instanceof User) {
            abort(403, 'Unauthorized action.');
        }
        $role = $user->role;
        if ($role !== 'admin' && $role !== 'instructor') {
            abort(403, 'Unauthorized action.');
        }
    }
}

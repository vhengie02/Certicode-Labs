<?php

namespace App\Http\Controllers;

use App\Models\SchoolClass;
use App\Models\Module;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ClassController extends Controller
{
    /**
     * Display a listing of classes.
     */
    public function index()
    {
        $user = auth()->user();
        $invitedClasses = collect();

        if ($user->role === 'student') {
            $classes = $user->classes()->with('instructor')->latest()->get();
            $invitedClasses = $user->invitedClasses()->with('instructor')->latest()->get();
        } else {
            // Instructor / Admin
            $classes = SchoolClass::where('instructor_id', $user->id)
                ->withCount('students')
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
            'instructor_id' => auth()->id(),
        ]);

        return redirect()->route('classes.index')->with('success', 'Class created successfully with join code: ' . $code);
    }

    /**
     * Display the specified class details.
     */
    public function show($id)
    {
        $class = SchoolClass::with(['modules.laboratories.labSessions', 'students', 'instructor'])->findOrFail($id);
        $user = auth()->user();

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

        return view('classes.show', compact('class'));
    }

    /**
     * Show form to edit class.
     */
    public function edit($id)
    {
        $this->authorizeInstructor();
        $class = SchoolClass::findOrFail($id);
        return view('classes.edit', compact('class'));
    }

    /**
     * Update class.
     */
    public function update(Request $request, $id)
    {
        $this->authorizeInstructor();
        $class = SchoolClass::findOrFail($id);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $class->update($validated);

        return redirect()->route('classes.show', $class->id)->with('success', 'Class updated successfully.');
    }

    /**
     * Delete class.
     */
    public function destroy($id)
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

        // Attach student
        $class->students()->syncWithoutDetaching([
            auth()->id() => ['status' => 'enrolled']
        ]);

        // If they had an invitation, make sure it is updated to enrolled
        $class->students()->updateExistingPivot(auth()->id(), ['status' => 'enrolled']);

        return redirect()->route('classes.show', $class->id)->with('success', 'Successfully enrolled in ' . $class->name);
    }

    /**
     * Invite student by Email (Gmail invite).
     */
    public function inviteStudent(Request $request, $id)
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
    public function acceptInvite(Request $request, $class_id)
    {
        $class = SchoolClass::findOrFail($class_id);
        
        $class->students()->updateExistingPivot(auth()->id(), [
            'status' => 'enrolled'
        ]);

        return redirect()->route('classes.show', $class->id)->with('success', 'Invitation accepted. Welcome to ' . $class->name . '!');
    }

    /**
     * Show form to create a module.
     */
    public function createModule($class_id)
    {
        $this->authorizeInstructor();
        $class = SchoolClass::findOrFail($class_id);

        return view('classes.module-create', compact('class'));
    }

    /**
     * Store a Module inside a Class.
     */
    public function storeModule(Request $request, $class_id)
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

        $module = Module::create([
            'class_id' => $class->id,
            'parent_id' => $validated['parent_id'] ?? null,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'content' => $validated['content'],
            'order_index' => $validated['order_index'] ?? 0,
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
    public function showModule($class_id, $module_id)
    {
        $class = SchoolClass::with('modules')->findOrFail($class_id);
        $module = Module::with(['laboratories', 'attachments'])->findOrFail($module_id);
        $user = auth()->user();

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
    public function editModule($class_id, $module_id)
    {
        $this->authorizeInstructor();
        $class = SchoolClass::findOrFail($class_id);
        $module = Module::with('attachments')->findOrFail($module_id);

        return view('classes.module-edit', compact('class', 'module'));
    }

    /**
     * Update a specific module.
     */
    public function updateModule(Request $request, $class_id, $module_id)
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

        $module->update([
            'parent_id' => $validated['parent_id'] ?? null,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'content' => $validated['content'],
            'order_index' => $validated['order_index'] ?? 0,
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
    public function destroyModule($class_id, $module_id)
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
    public function downloadAttachment($id)
    {
        $attachment = \App\Models\ModuleAttachment::findOrFail($id);
        $module = $attachment->module;
        $class = $module->schoolClass;
        $user = auth()->user();

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

        return \Illuminate\Support\Facades\Storage::disk('public')->download($attachment->file_path, $attachment->file_name);
    }

    /**
     * Restrict helper.
     */
    protected function authorizeInstructor()
    {
        $role = auth()->user()->role ?? 'student';
        if ($role !== 'admin' && $role !== 'instructor') {
            abort(403, 'Unauthorized action.');
        }
    }
}

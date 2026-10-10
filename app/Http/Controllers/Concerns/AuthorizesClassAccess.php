<?php

namespace App\Http\Controllers\Concerns;

use App\Models\LabSession;
use App\Models\Laboratory;
use App\Models\Module;
use App\Models\SchoolClass;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Instructor actions must be limited to the instructor's own classes (admins can act on
 * any). Each helper resolves the class that owns the record and aborts with 403 otherwise.
 */
trait AuthorizesClassAccess
{
    protected function authorizeClassManager(SchoolClass|int|null $class): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User && $user->canManageClass($class), 403, 'You can only manage your own classes.');
    }

    protected function authorizeModuleManager(Module $module): void
    {
        $this->authorizeClassManager($module->class_id);
    }

    protected function authorizeLabManager(Laboratory $laboratory): void
    {
        $this->authorizeClassManager(Module::whereKey($laboratory->module_id)->value('class_id'));
    }

    /**
     * Viewing or starting a lab: students must be enrolled in its class; staff must manage it.
     */
    protected function authorizeLabViewer(Laboratory $laboratory): void
    {
        $user = Auth::user();
        abort_unless($user instanceof User, 403);

        $classId = Module::whereKey($laboratory->module_id)->value('class_id');
        if ($user->role === 'student') {
            $enrolled = $classId && SchoolClass::whereKey($classId)
                ->whereHas('students', fn ($q) => $q->where('users.id', $user->id)->where('class_student.status', 'enrolled'))
                ->exists();
            abort_unless($enrolled, 403, 'You are not enrolled in this class.');
            return;
        }

        $this->authorizeClassManager($classId);
    }

    protected function authorizeSessionManager(LabSession $session): void
    {
        $this->authorizeLabManager($session->laboratory()->firstOrFail());
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\ClassActivityNotification;
use Illuminate\Support\Facades\Log;

/**
 * Admin review of instructor access requests. People who sign up as instructors start as
 * students; approving promotes them, declining keeps them as students.
 */
class InstructorRequestController extends Controller
{
    public function index()
    {
        $pending = User::where('role', 'student')
            ->whereNotNull('instructor_requested_at')
            ->orderBy('instructor_requested_at')
            ->get();

        return view('admin.instructor-requests', compact('pending'));
    }

    public function approve(User $user)
    {
        if (!$user->hasPendingInstructorRequest()) {
            return back()->with('error', "{$user->name} has no pending instructor request.");
        }

        $user->forceFill(['role' => 'instructor', 'instructor_requested_at' => null])->save();
        $this->notifyUser($user, 'Instructor access approved', 'You can now create classes, run labs and grade submissions.', route('classes.index'));

        return back()->with('success', "{$user->name} is now an instructor.");
    }

    public function decline(User $user)
    {
        if (!$user->hasPendingInstructorRequest()) {
            return back()->with('error', "{$user->name} has no pending instructor request.");
        }

        $user->forceFill(['instructor_requested_at' => null])->save();
        $this->notifyUser($user, 'Instructor access not approved', 'Your account stays a student account. Contact your administrator if you think this is a mistake.', route('dashboard'));

        return back()->with('success', "Declined {$user->name}'s request. They remain a student.");
    }

    private function notifyUser(User $user, string $title, string $message, string $url): void
    {
        try {
            $user->notify(new ClassActivityNotification($title, $message, $url, 'info'));
        } catch (\Throwable $e) {
            Log::warning("Could not notify {$user->email} about their instructor request: " . $e->getMessage());
        }
    }
}

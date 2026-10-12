<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Models\User;
use App\Notifications\ClassActivityNotification;
use Illuminate\Http\Request;

/**
 * Admin → Users: every account in one place, with role changes and removal guarded so the
 * platform can't lose its last admin or cascade-delete an instructor's classes.
 */
class UserController extends Controller
{
    public const ROLES = ['student', 'instructor', 'admin'];

    public function index(Request $request)
    {
        $search = trim((string) $request->query('q', ''));
        $role = in_array($request->query('role'), self::ROLES, true) ? $request->query('role') : null;

        $users = User::query()
            ->when($role, fn ($q) => $q->where('role', $role))
            ->when($search !== '', function ($q) use ($search) {
                $like = '%' . mb_strtolower($search) . '%';
                $q->where(fn ($w) => $w->whereRaw('lower(name) like ?', [$like])
                    ->orWhereRaw('lower(email) like ?', [$like])
                    ->orWhereRaw('lower(username) like ?', [$like]));
            })
            ->withCount(['instructedClasses', 'labSessions'])
            ->orderByRaw("case role when 'admin' then 0 when 'instructor' then 1 else 2 end")
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        $counts = User::query()->selectRaw('role, count(*) as total')->groupBy('role')->pluck('total', 'role');
        $pendingRequests = User::where('role', 'student')->whereNotNull('instructor_requested_at')->count();

        return view('admin.users.index', compact('users', 'search', 'role', 'counts', 'pendingRequests'));
    }

    public function updateRole(Request $request, User $user)
    {
        $validated = $request->validate(['role' => 'required|in:' . implode(',', self::ROLES)]);
        $newRole = $validated['role'];

        if ($newRole === $user->role) {
            return back();
        }
        if ($user->is($request->user())) {
            return back()->with('error', "You can't change your own role. Ask another admin.");
        }
        if ($user->role === 'admin' && User::where('role', 'admin')->count() <= 1) {
            return back()->with('error', "{$user->name} is the only admin. Make someone else an admin first.");
        }
        if ($user->role === 'instructor' && $newRole === 'student' && $user->instructedClasses()->exists()) {
            return back()->with('error', "{$user->name} still teaches classes. Reassign them in each class's settings first.");
        }

        $user->forceFill(['role' => $newRole, 'instructor_requested_at' => null])->save();

        try {
            $user->notify(new ClassActivityNotification(
                'Your role changed',
                'An admin changed your Certicode role to ' . $newRole . '.',
                route('dashboard'),
                'info'
            ));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('Role change notification failed: ' . $e->getMessage());
        }

        return back()->with('success', "{$user->name} is now " . ($newRole === 'admin' ? 'an admin' : 'a ' . $newRole) . '.');
    }

    public function destroy(Request $request, User $user)
    {
        if ($user->is($request->user())) {
            return back()->with('error', "You can't delete your own account here.");
        }
        if ($user->role === 'admin' && User::where('role', 'admin')->count() <= 1) {
            return back()->with('error', "{$user->name} is the only admin and can't be deleted.");
        }
        // Classes cascade-delete with their instructor, taking every student's work with them
        $classCount = SchoolClass::where('instructor_id', $user->id)->count();
        if ($classCount > 0) {
            return back()->with('error', "{$user->name} teaches {$classCount} " . \Illuminate\Support\Str::plural('class', $classCount)
                . '. Reassign ' . ($classCount === 1 ? 'it' : 'them') . ' to another instructor before deleting this account.');
        }

        $name = $user->name;
        $user->delete();

        return redirect()->route('admin.users.index')->with('success', "{$name}'s account was deleted.");
    }
}

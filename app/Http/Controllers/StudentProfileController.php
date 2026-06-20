<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;

class StudentProfileController extends Controller
{
    /**
     * Display a listing of students (Instructors/Admins only).
     */
    public function index()
    {
        $role = auth()->user()->role ?? 'student';
        if ($role !== 'admin' && $role !== 'instructor') {
            abort(403, 'Unauthorized.');
        }

        // Show students
        $students = User::where('role', 'student')->latest()->get();
        return view('profiles.index', compact('students'));
    }

    /**
     * Show the profile edit form.
     */
    public function edit($id)
    {
        $user = User::findOrFail($id);

        // Access check: users can edit their own profiles; admins can edit anyone's profile.
        if (auth()->id() !== $user->id && auth()->user()->role !== 'admin') {
            abort(403, 'Unauthorized action.');
        }

        return view('profiles.edit', compact('user'));
    }

    /**
     * Update the user profile.
     */
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        if (auth()->id() !== $user->id && auth()->user()->role !== 'admin') {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'github_username' => 'nullable|string|max:255',
            'role' => 'required|string|in:student,instructor,admin',
        ]);

        // Restrict role modification to admin users only
        if (auth()->user()->role !== 'admin') {
            unset($validated['role']);
        }

        $user->update($validated);

        // Redirect appropriately
        if (auth()->user()->role === 'admin' || auth()->user()->role === 'instructor') {
            return redirect()->route('students.index')->with('success', 'Profile updated successfully.');
        }

        return redirect()->route('dashboard')->with('success', 'Profile updated successfully.');
    }

    /**
     * Remove the user (Admin only).
     */
    public function destroy($id)
    {
        if (auth()->user()->role !== 'admin') {
            abort(403, 'Unauthorized.');
        }

        $user = User::findOrFail($id);
        $user->delete();

        return redirect()->route('students.index')->with('success', 'User deleted successfully.');
    }
}

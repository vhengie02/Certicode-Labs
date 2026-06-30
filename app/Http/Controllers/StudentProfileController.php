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
        if ($role !== 'admin') {
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
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username,' . $user->id,
            'gender' => 'nullable|string|in:male,female,other',
            'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $user->id,
            'github_username' => 'nullable|string|max:255',
            'role' => 'required|string|in:student,instructor,admin',
        ]);

        // Restrict role modification to admin users only
        if (auth()->user()->role !== 'admin') {
            unset($validated['role']);
        }

        $validated['name'] = $validated['first_name'] . ' ' . $validated['last_name'];
        $user->update($validated);

        // Redirect appropriately
        if ($user->id === auth()->id()) {
            return redirect()->route('settings.show')->with('success', 'Profile updated successfully.');
        }

        if (auth()->user()->role === 'admin') {
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

<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Admin\UserController;
use App\Models\User;
use App\Rules\UniqueEmail;
use Illuminate\Http\Request;

class StudentProfileController extends Controller
{
    /**
     * Show the profile edit form.
     */
    public function edit(int $id)
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
    public function update(Request $request, int $id)
    {
        $user = User::findOrFail($id);

        if (auth()->id() !== $user->id && auth()->user()->role !== 'admin') {
            abort(403, 'Unauthorized action.');
        }

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username,'.$user->id,
            'gender' => 'nullable|string|in:male,female,other',
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255', new UniqueEmail(ignoreUserId: $user->id)],
            'github_username' => 'nullable|string|max:255',
        ]);

        // No role here: roles change only through Admin\UserController::updateRole, whose
        // checks (not your own role, not the last admin, not an instructor still teaching)
        // this form used to skip
        $validated['name'] = $validated['first_name'].' '.$validated['last_name'];
        $user->update($validated);

        // Redirect appropriately
        if ($user->id === auth()->id()) {
            return redirect()->route('settings.show')->with('success', 'Profile updated successfully.');
        }

        if (auth()->user()->role === 'admin') {
            return redirect()->route('admin.users.index')->with('success', 'Profile updated successfully.');
        }

        return redirect()->route('dashboard')->with('success', 'Profile updated successfully.');
    }

    /**
     * Remove the user (Admin only).
     */
    public function destroy(int $id)
    {
        if (auth()->user()->role !== 'admin') {
            abort(403, 'Unauthorized.');
        }

        // Same safeguards as Admin > Users (no self-delete, keep an admin, don't cascade classes)
        return app(UserController::class)->destroy(request(), User::findOrFail($id));
    }
}

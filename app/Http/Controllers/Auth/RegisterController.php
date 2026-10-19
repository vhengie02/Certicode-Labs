<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class RegisterController extends Controller
{
    /**
     * Display the registration form.
     */
    public function show()
    {
        return view('auth.register');
    }

    /**
     * Store a newly created user in the database.
     */
    public function store(Request $request)
    {
        if (!\App\Models\SystemSetting::read('registration_open')) {
            return redirect()->route('login')->withErrors(['email' => 'New sign-ups are closed right now. If you already have an account, sign in; otherwise ask your instructor or a Certicode admin.']);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'string', 'email', 'max:255', new \App\Rules\UniqueEmail(
                message: 'An account with this email already exists. Sign in instead, or use Continue with Google to get a sign-in code by email.',
            )],
            'password' => ['required', 'confirmed', Password::defaults()],
            'role' => 'required|string|in:student,instructor',
        ]);

        // Instructors see every student's work, so that role needs an admin's approval:
        // the account starts as a student with a pending request.
        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role' => 'student',
        ]);

        $requestedInstructor = $validated['role'] === 'instructor' && \App\Models\SystemSetting::read('instructor_requests_open');
        if ($requestedInstructor) {
            $user->requestInstructorAccess();
        }

        Auth::login($user);

        return redirect('/dashboard')->with('success', $requestedInstructor
            ? 'Account created. Your instructor access is waiting for an admin to approve it; until then you can use Certicode as a student.'
            : 'Account created successfully!');
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Hash;

class SettingsController extends Controller
{
    /**
     * Show settings dashboard.
     */
    public function show()
    {
        $user = auth()->user();
        return view('settings.index', compact('user'));
    }

    /**
     * Update notification settings.
     */
    public function updateNotifications(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'notify_class' => 'boolean',
            'notify_module' => 'boolean',
            'notify_lab' => 'boolean',
            'notify_certificate' => 'boolean',
        ]);

        // Default toggles to false if not present in request (since checkboxes are omitted when unchecked)
        $user->update([
            'notify_class' => $request->has('notify_class'),
            'notify_module' => $request->has('notify_module'),
            'notify_lab' => $request->has('notify_lab'),
            'notify_certificate' => $request->has('notify_certificate'),
            'notify_email_channel' => true,
        ]);

        return redirect()->route('settings.show')->with('success', 'Notification preferences updated successfully!');
    }

    /**
     * Generate and mock send a Gmail verification code.
     */
    public function sendGmailCode(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'gmail' => 'required|email|unique:users,gmail,' . $user->id,
        ]);

        if (!app()->runningUnitTests()) {
            $lastSent = session('gmail_code_sent_at');
            if ($lastSent && now()->diffInSeconds($lastSent) < 60) {
                $secondsLeft = 60 - now()->diffInSeconds($lastSent);
                return back()->withErrors(['code' => "Please wait {$secondsLeft} seconds before requesting a new code."]);
            }
        }

        // Generate 6-digit code
        $code = (string) rand(100000, 999999);

        // Store unverified Gmail and verification code
        $user->update([
            'gmail' => $request->gmail,
            'gmail_verification_code' => $code,
            'gmail_verified_at' => null,
        ]);

        session()->put('gmail_code_sent_at', now());

        // Log code and put it in session so user/tests can see the code easily
        Log::info("Gmail connection code for User ID {$user->id} ({$request->gmail}): {$code}");
        session()->put('gmail_code_debug', $code);

        $mailError = null;
        try {
            \Illuminate\Support\Facades\Mail::send('emails.generic', [
                'title' => 'Gmail Connection Code',
                'greeting' => 'Hello ' . ($user->name ?? 'developer') . ',',
                'messageLines' => [
                    'You requested to connect your Gmail account (' . $request->gmail . ') to your Certicode Labs profile.',
                    'Please use the verification code below to verify your email ownership.'
                ],
                'code' => $code,
            ], function ($message) use ($request) {
                $message->to($request->gmail)
                        ->subject('Certicode Labs: Gmail Connection Code');
            });
        } catch (\Exception $e) {
            Log::error("Failed to send verification email: " . $e->getMessage());
            $mailError = "Note: Real email delivery failed. (Error: {$e->getMessage()}). However, you can still complete verification using the code below for local testing.";
        }

        if ($mailError) {
            return redirect()->route('settings.show')
                ->with('warning', $mailError)
                ->with('success', 'Verification code (local testing): ' . $code);
        }

        return redirect()->route('settings.show')->with('success', 'A verification code has been successfully sent to ' . $request->gmail);
    }

    /**
     * Verify the 6-digit code.
     */
    public function verifyGmailCode(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'code' => 'required|string|size:6',
        ]);

        if ($request->code === $user->gmail_verification_code) {
            $user->update([
                'gmail_verified_at' => now(),
                'gmail_verification_code' => null,
            ]);

            session()->forget('gmail_code_debug');

            return redirect()->route('settings.show')->with('success', 'Gmail account connected successfully!');
        }

        return redirect()->route('settings.show')->withErrors(['code' => 'Incorrect verification code. Please try again.']);
    }

    /**
     * Disconnect Gmail account.
     */
    public function disconnectGmail()
    {
        $user = auth()->user();

        $user->update([
            'gmail' => null,
            'gmail_verified_at' => null,
            'gmail_verification_code' => null,
        ]);

        return redirect()->route('settings.show')->with('success', 'Gmail account disconnected successfully.');
    }

    /**
     * Disconnect GitHub account.
     */
    public function disconnectGithub()
    {
        $user = auth()->user();

        $user->update([
            'github_username' => null,
        ]);

        return redirect()->route('settings.show')->with('success', 'GitHub account disconnected successfully.');
    }

    /**
     * Update user profile information.
     */
    public function updateProfile(Request $request)
    {
        $user = auth()->user();

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username,' . $user->id,
            'gender' => 'nullable|string|in:male,female,other',
            'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $user->id,
        ]);

        $validated['name'] = $validated['first_name'] . ' ' . $validated['last_name'];
        $user->update($validated);

        return redirect()->route('settings.show')->with('success', 'Profile information updated successfully!');
    }

    /**
     * Update user password.
     */
    public function updatePassword(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', \Illuminate\Validation\Rules\Password::defaults()],
        ]);

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return redirect()->route('settings.show')->with('success', 'Password updated successfully!');
    }
}

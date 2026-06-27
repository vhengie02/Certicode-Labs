<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

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

        $validated = $request->validate([
            'notify_class' => 'boolean',
            'notify_module' => 'boolean',
            'notify_lab' => 'boolean',
            'notify_certificate' => 'boolean',
            'notify_email_channel' => 'boolean',
        ]);

        // Default toggles to false if not present in request (since checkboxes are omitted when unchecked)
        $user->update([
            'notify_class' => $request->has('notify_class'),
            'notify_module' => $request->has('notify_module'),
            'notify_lab' => $request->has('notify_lab'),
            'notify_certificate' => $request->has('notify_certificate'),
            'notify_email_channel' => $request->has('notify_email_channel'),
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

        // Generate 6-digit code
        $code = (string) rand(100000, 999999);

        // Store unverified Gmail and verification code
        $user->update([
            'gmail' => $request->gmail,
            'gmail_verification_code' => $code,
            'gmail_verified_at' => null,
        ]);

        // Log code and put it in session so user/tests can see the code easily
        Log::info("Gmail connection code for User ID {$user->id} ({$request->gmail}): {$code}");
        session()->put('gmail_code_debug', $code);

        $mailError = null;
        try {
            \Illuminate\Support\Facades\Mail::raw("Your Certicode Labs verification code to connect your Gmail is: {$code}", function ($message) use ($request) {
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
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
        ]);

        $user->update($validated);

        return redirect()->route('settings.show')->with('success', 'Profile information updated successfully!');
    }
}

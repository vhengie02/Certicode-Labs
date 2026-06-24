<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class LoginController extends Controller
{
    /**
     * Display the login form.
     */
    public function show()
    {
        return view('auth.login');
    }

    /**
     * Handle an authentication attempt.
     */
    public function store(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|string|email',
            'password' => 'required|string',
        ]);

        if (Auth::attempt($credentials, $request->filled('remember'))) {
            $request->session()->regenerate();

            return redirect()->intended('/dashboard')->with('success', 'Logged in successfully!');
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->onlyInput('email');
    }

    /**
     * Redirect to the mock Google OAuth page.
     */
    public function redirectToGoogle()
    {
        return view('auth.google');
    }

    /**
     * Process the submitted Google Gmail address and dispatch a verification code.
     */
    public function submitGoogleEmail(Request $request)
    {
        $request->validate([
            'gmail' => 'required|string|email',
        ]);

        $gmail = $request->gmail;
        $code = (string) rand(100000, 999999);

        // Save target gmail and verification code in session
        session()->put('google_auth_gmail', $gmail);
        session()->put('google_auth_code', $code);
        session()->put('gmail_code_debug', $code);

        Log::info("Google Sign-In/Up verification code for {$gmail}: {$code}");

        $mailError = null;
        try {
            \Illuminate\Support\Facades\Mail::raw("Your Certicode Labs Google verification code is: {$code}", function ($message) use ($gmail) {
                $message->to($gmail)
                        ->subject('Certicode Labs: Google Authentication Code');
            });
        } catch (\Exception $e) {
            Log::error("Failed to send Google verification email: " . $e->getMessage());
            $mailError = "Note: Real email delivery failed. (Error: {$e->getMessage()}). However, you can still complete verification using the code below for local testing.";
        }

        // Check if user exists with this verified Gmail
        $userExists = User::where('gmail', $gmail)
            ->whereNotNull('gmail_verified_at')
            ->exists();

        $redirectParams = $userExists ? [] : ['needs_role' => 1];

        if ($mailError) {
            return redirect()->route('auth.google.verify', $redirectParams)
                ->with('warning', $mailError)
                ->with('success', 'Verification code (local testing): ' . $code);
        }

        return redirect()->route('auth.google.verify', $redirectParams)
            ->with('success', 'A verification code has been successfully sent to ' . $gmail);
    }

    /**
     * Display the verification code prompt page.
     */
    public function showGoogleVerify()
    {
        if (!session()->has('google_auth_gmail')) {
            return redirect()->route('auth.google')->withErrors(['email' => 'Please enter your Gmail address first.']);
        }
        return view('auth.google-verify');
    }

    /**
     * Handle mock Google OAuth callback.
     */
    public function handleGoogleCallback(Request $request)
    {
        $request->validate([
            'code' => 'required|string|size:6',
            'role' => 'nullable|string|in:student,instructor',
        ]);

        $gmail = session('google_auth_gmail');
        $expectedCode = session('google_auth_code');

        if (!$gmail || !$expectedCode) {
            return redirect()->route('auth.google')->withErrors(['email' => 'Session expired. Please sign in again.']);
        }

        // Check verification code
        if ($request->code !== $expectedCode) {
            return back()->withErrors(['code' => 'Incorrect verification code. Please try again.']);
        }

        // 1. Check if user already exists with this verified Gmail
        $user = User::where('gmail', $gmail)
            ->whereNotNull('gmail_verified_at')
            ->first();

        if ($user) {
            Auth::login($user);
            $request->session()->regenerate();
            $this->clearAuthSessions();

            return redirect()->intended('/dashboard')->with('success', 'Logged in with Google successfully!');
        }

        // 2. Check if user exists with this email address but has no Gmail linked
        $emailUser = User::where('email', $gmail)->first();
        if ($emailUser) {
            $this->clearAuthSessions();
            return redirect()->route('login')->withErrors([
                'email' => 'This email address (' . $gmail . ') is already registered. Please log in with your password to link your Google account.',
            ]);
        }

        // 3. Register user if role is selected
        if ($request->filled('role')) {
            $name = ucwords(str_replace(['.', '_', '-'], ' ', explode('@', $gmail)[0]));
            
            $newUser = User::create([
                'name' => $name,
                'email' => $gmail,
                'gmail' => $gmail,
                'gmail_verified_at' => now(),
                'password' => Hash::make(Str::random(16)),
                'role' => $request->role,
            ]);

            Auth::login($newUser);
            $request->session()->regenerate();
            $this->clearAuthSessions();

            return redirect('/dashboard')->with('success', 'Account created and Google linked successfully!');
        }

        // If code is correct but role is missing for a new account (should not happen with required select, but just in case)
        return redirect()->route('auth.google.verify', ['needs_role' => 1])->withErrors(['role' => 'Please select a role to complete your registration.']);
    }

    /**
     * Clear temporary login/signup session variables.
     */
    protected function clearAuthSessions()
    {
        session()->forget([
            'google_auth_gmail',
            'google_auth_code',
            'gmail_code_debug',
        ]);
    }
}

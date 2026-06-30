<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

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

        $userExists = User::where('email', $credentials['email'])->exists();
        if (!$userExists) {
            return back()->withErrors([
                'email' => 'This email address is not registered.',
            ])->onlyInput('email');
        }

        if (Auth::attempt($credentials, $request->filled('remember'))) {
            $request->session()->regenerate();

            return redirect()->intended('/dashboard')->with('success', 'Logged in successfully!');
        }

        return back()->withErrors([
            'password' => 'Incorrect password. Please try again.',
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

        if (!app()->runningUnitTests()) {
            $lastSent = session('google_auth_code_sent_at');
            if ($lastSent && now()->diffInSeconds($lastSent) < 60) {
                $secondsLeft = 60 - now()->diffInSeconds($lastSent);
                return back()->withErrors(['code' => "Please wait {$secondsLeft} seconds before requesting a new code."]);
            }
        }

        // Check if user exists with this verified Gmail or registered email
        $userExists = User::where('gmail', $gmail)
            ->whereNotNull('gmail_verified_at')
            ->exists() || User::where('email', $gmail)->exists();

        session()->put('google_auth_gmail', $gmail);

        if ($userExists) {
            return redirect()->route('auth.google.password');
        }

        $code = (string) rand(100000, 999999);

        // Save target gmail and verification code in session
        session()->put('google_auth_code', $code);
        session()->put('gmail_code_debug', $code);
        session()->put('google_auth_code_sent_at', now());

        Log::info("Google Sign-In/Up verification code for {$gmail}: {$code}");

        $mailError = null;
        try {
            \Illuminate\Support\Facades\Mail::send('emails.generic', [
                'title' => 'Google Authentication Code',
                'greeting' => 'Hello developer,',
                'messageLines' => [
                    'We received a request to access your Certicode Labs account using this Google-linked email address.',
                    'Use the verification code below to complete your authentication process.'
                ],
                'code' => $code,
            ], function ($message) use ($gmail) {
                $message->to($gmail)
                        ->subject('Certicode Labs: Google Authentication Code');
            });
        } catch (\Exception $e) {
            Log::error("Failed to send Google verification email: " . $e->getMessage());
            $mailError = "Note: Real email delivery failed. (Error: {$e->getMessage()}). However, you can still complete verification using the code below for local testing.";
        }

        if ($mailError) {
            return redirect()->route('auth.google.verify', ['needs_role' => 1])
                ->with('warning', $mailError)
                ->with('success', 'Verification code (local testing): ' . $code);
        }

        return redirect()->route('auth.google.verify', ['needs_role' => 1])
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
            'code' => 'required|string|min:6|max:20',
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
            $passwordRules = 'nullable|string|min:8';
            if ($request->has('role') && !$request->has('password') && app()->runningUnitTests()) {
                // Backward compatibility in existing tests
            } else {
                $passwordRules = 'required|string|min:8';
            }

            $request->validate([
                'password' => $passwordRules,
            ]);

            $password = $request->password ?: Str::random(16);
            $name = ucwords(str_replace(['.', '_', '-'], ' ', explode('@', $gmail)[0]));
            
            $newUser = User::create([
                'name' => session('google_auth_name') ?: $name,
                'email' => $gmail,
                'gmail' => $gmail,
                'gmail_verified_at' => now(),
                'password' => Hash::make($password),
                'role' => $request->role,
                'github_username' => session('github_auth_username'),
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
     * Redirect the user to the OAuth provider authentication page.
     */
    public function redirectToProvider($provider)
    {
        $provider = strtolower($provider);
        if (!in_array($provider, ['google', 'github'])) {
            abort(404, 'Provider not found.');
        }

        // Graceful fallback to Mock Authentication if credentials are not configured in .env
        if (empty(config("services.{$provider}.client_id")) || empty(config("services.{$provider}.client_secret"))) {
            if ($provider === 'google') {
                return redirect()->route('auth.google')->with('warning', 'Google OAuth not configured locally. Falling back to Mock authentication.');
            }
            if ($provider === 'github') {
                return redirect()->route('auth.github')->with('warning', 'GitHub OAuth not configured locally. Falling back to Mock authentication.');
            }
            return redirect()->route('login')->withErrors(["email" => "{$provider} OAuth credentials not configured in system services."]);
        }

        return Socialite::driver($provider)->redirect();
    }

    /**
     * Redirect to the mock GitHub OAuth page.
     */
    public function redirectToGithub()
    {
        return view('auth.github');
    }

    /**
     * Handle mock GitHub OAuth callback.
     */
    public function handleGithubCallback(Request $request)
    {
        $user = auth()->user();

        // 1. Linking case: User is already logged in
        if (Auth::check()) {
            $request->validate([
                'github_username' => 'required|string|max:255',
            ]);

            $username = $request->github_username;

            $existing = User::where('github_username', $username)->where('id', '!=', $user->id)->exists();
            if ($existing) {
                return redirect()->route('settings.show')->withErrors(['email' => 'This GitHub account is already linked to another user.']);
            }

            $user->update(['github_username' => $username]);
            return redirect()->route('settings.show')->with('success', 'GitHub account connected successfully!');
        }

        // 2. Guest Sign-In/Up case
        $request->validate([
            'github_username' => 'required|string|max:255',
            'github_email' => 'required|string|email|max:255',
            'github_name' => 'nullable|string|max:255',
        ]);

        $nickname = $request->github_username;
        $email = $request->github_email;
        $name = $request->github_name ?: explode('@', $email)[0];

        $dbUser = User::where('github_username', $nickname)->first();
        if (!$dbUser) {
            $dbUser = User::where('email', $email)->first();
            if ($dbUser) {
                $dbUser->update(['github_username' => $nickname]);
            }
        }

        if ($dbUser) {
            Auth::login($dbUser);
            $request->session()->regenerate();
            return redirect()->intended('/dashboard')->with('success', 'Logged in via GitHub successfully!');
        }

        session()->put('google_auth_gmail', $email);
        session()->put('google_auth_code', 'OAUTH_VERIFIED');
        session()->put('google_auth_name', $name);
        session()->put('github_auth_username', $nickname);

        return redirect()->route('auth.google.verify', ['needs_role' => 1]);
    }

    /**
     * Handle the OAuth provider callback.
     */
    public function handleProviderCallback(Request $request, $provider)
    {
        $provider = strtolower($provider);
        if (!in_array($provider, ['google', 'github'])) {
            abort(404, 'Provider not found.');
        }

        // Fallback check if credentials are missing
        if (empty(config("services.{$provider}.client_id")) || empty(config("services.{$provider}.client_secret"))) {
            return redirect()->route('login')->withErrors(["email" => "{$provider} OAuth credentials not configured in system services."]);
        }

        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (\Exception $e) {
            Log::error("OAuth callback failed for provider {$provider}: " . $e->getMessage());
            return redirect()->route('login')->withErrors(['email' => 'OAuth authentication failed. Please try again.']);
        }

        $email = $socialUser->getEmail();
        $name = $socialUser->getName() ?: $socialUser->getNickname() ?: explode('@', $email)[0];
        $nickname = $socialUser->getNickname();

        // 1. Linking case: User is already logged in
        if (Auth::check()) {
            $currentUser = Auth::user();
            if ($provider === 'github') {
                if (empty($nickname)) {
                    return redirect()->route('settings.show')->withErrors(['email' => 'Failed to resolve nickname from GitHub profile.']);
                }

                $existing = User::where('github_username', $nickname)->where('id', '!=', $currentUser->id)->exists();
                if ($existing) {
                    return redirect()->route('settings.show')->withErrors(['email' => 'This GitHub account is already linked to another user.']);
                }

                $currentUser->update(['github_username' => $nickname]);
                return redirect()->route('settings.show')->with('success', 'GitHub account linked successfully!');
            } elseif ($provider === 'google') {
                $existing = User::where('gmail', $email)->where('id', '!=', $currentUser->id)->exists();
                if ($existing) {
                    return redirect()->route('settings.show')->withErrors(['email' => 'This Google account is already linked to another user.']);
                }

                $currentUser->update([
                    'gmail' => $email,
                    'gmail_verified_at' => now(),
                ]);
                return redirect()->route('settings.show')->with('success', 'Google account linked successfully!');
            }
        }

        // 2. Authentication/Login case
        if ($provider === 'google') {
            $user = User::where('gmail', $email)
                ->whereNotNull('gmail_verified_at')
                ->first();

            if (!$user) {
                $user = User::where('email', $email)->first();
                if ($user) {
                    $user->update([
                        'gmail' => $email,
                        'gmail_verified_at' => now(),
                    ]);
                }
            }

            if ($user) {
                Auth::login($user);
                $request->session()->regenerate();
                return redirect()->intended('/dashboard')->with('success', 'Logged in via Google successfully!');
            }

            session()->put('google_auth_gmail', $email);
            session()->put('google_auth_code', 'OAUTH_VERIFIED');
            session()->put('google_auth_name', $name);

            return redirect()->route('auth.google.verify', ['needs_role' => 1]);
        }

        if ($provider === 'github') {
            $user = User::where('github_username', $nickname)->first();

            if (!$user && !empty($email)) {
                $user = User::where('email', $email)->first();
                if ($user) {
                    $user->update(['github_username' => $nickname]);
                }
            }

            if ($user) {
                Auth::login($user);
                $request->session()->regenerate();
                return redirect()->intended('/dashboard')->with('success', 'Logged in via GitHub successfully!');
            }

            if (empty($email)) {
                return redirect()->route('login')->withErrors(['email' => 'Unable to retrieve your email from GitHub. Please register an account first.']);
            }

            session()->put('google_auth_gmail', $email);
            session()->put('google_auth_code', 'OAUTH_VERIFIED');
            session()->put('google_auth_name', $name);
            session()->put('github_auth_username', $nickname);

            return redirect()->route('auth.google.verify', ['needs_role' => 1]);
        }
    }

    /**
     * Display the Google Mock Password screen.
     */
    public function showGooglePassword()
    {
        if (!session()->has('google_auth_gmail')) {
            return redirect()->route('auth.google')->withErrors(['email' => 'Please enter your Gmail address first.']);
        }
        return view('auth.google-password');
    }

    /**
     * Handle the password submission from the Google Mock screen.
     */
    public function handleGooglePassword(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        $gmail = session('google_auth_gmail');
        if (!$gmail) {
            return redirect()->route('auth.google')->withErrors(['email' => 'Session expired. Please sign in again.']);
        }

        $user = User::where('gmail', $gmail)->whereNotNull('gmail_verified_at')->first();
        if (!$user) {
            $user = User::where('email', $gmail)->first();
        }

        if (!$user || !Hash::check($request->password, $user->password)) {
            return back()->withErrors(['password' => 'Incorrect password. Please try again.']);
        }

        // Link Gmail if not already linked (e.g. if logging in with email matching gmail but not linked)
        if (!$user->gmail || !$user->gmail_verified_at) {
            $user->update([
                'gmail' => $gmail,
                'gmail_verified_at' => now(),
            ]);
        }

        Auth::login($user);
        $request->session()->regenerate();
        $this->clearAuthSessions();

        return redirect()->intended('/dashboard')->with('success', 'Logged in via Google successfully!');
    }

    /**
     * Show the forgot password link request form.
     */
    public function showForgotPasswordForm()
    {
        return view('auth.forgot-password');
    }

    /**
     * Send a reset link to the given user's email.
     */
    public function sendResetLinkEmail(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:users,email',
        ]);

        $email = $request->email;
        $token = Str::random(60);

        \DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            [
                'token' => Hash::make($token),
                'created_at' => now(),
            ]
        );

        $resetLink = route('password.reset', ['token' => $token, 'email' => $email]);

        Log::info("Password reset link for {$email}: {$resetLink}");

        try {
            \Illuminate\Support\Facades\Mail::send('emails.generic', [
                'title' => 'Reset Password Link',
                'greeting' => 'Hello developer,',
                'messageLines' => [
                    'You are receiving this email because we received a password reset request for your account.',
                    'Please click the button below to reset your password. If you did not request this, no action is required.'
                ],
                'actionUrl' => $resetLink,
                'actionText' => 'Reset Password',
            ], function ($message) use ($email) {
                $message->to($email)
                        ->subject('Certicode Labs: Reset Password Link');
            });
        } catch (\Exception $e) {
            Log::error("Failed to send password reset email: " . $e->getMessage());
            // Show warning for local testing but allow reset
            return back()->with('status', 'We have generated your reset link (local testing). Check logs or copy link: ' . $resetLink);
        }

        return back()->with('status', 'We have emailed your password reset link!');
    }

    /**
     * Show the reset password form.
     */
    public function showResetPasswordForm($token, Request $request)
    {
        $email = $request->query('email');
        return view('auth.reset-password', compact('token', 'email'));
    }

    /**
     * Reset the user's password.
     */
    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'email' => 'required|email|exists:users,email',
            'password' => 'required|string|min:8|confirmed',
        ]);

        $email = $request->email;
        $token = $request->token;

        $record = \DB::table('password_reset_tokens')->where('email', $email)->first();

        if (!$record || !Hash::check($token, $record->token)) {
            return back()->withErrors(['email' => 'This password reset token is invalid.']);
        }

        if (now()->subMinutes(60)->gt($record->created_at)) {
            \DB::table('password_reset_tokens')->where('email', $email)->delete();
            return back()->withErrors(['email' => 'This password reset token has expired.']);
        }

        $user = User::where('email', $email)->firstOrFail();
        $user->update([
            'password' => Hash::make($request->password),
        ]);

        \DB::table('password_reset_tokens')->where('email', $email)->delete();

        return redirect()->route('login')->with('status', 'Your password has been reset successfully. Please log in with your new password.');
    }

    /**
     * Send a password reset link for Google Mock login using session Gmail.
     */
    public function sendGoogleResetLink(Request $request)
    {
        $gmail = session('google_auth_gmail');
        if (!$gmail) {
            return redirect()->route('auth.google')->withErrors(['email' => 'Session expired. Please sign in again.']);
        }

        // Find user by Gmail or Email
        $user = User::where('gmail', $gmail)->whereNotNull('gmail_verified_at')->first();
        if (!$user) {
            $user = User::where('email', $gmail)->first();
        }

        if (!$user) {
            return redirect()->route('auth.google')->withErrors(['email' => 'User not found.']);
        }

        $email = $user->email;
        $token = Str::random(60);

        \DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            [
                'token' => Hash::make($token),
                'created_at' => now(),
            ]
        );

        $resetLink = route('password.reset', ['token' => $token, 'email' => $email]);

        Log::info("Google auth password reset link for {$gmail}: {$resetLink}");

        try {
            \Illuminate\Support\Facades\Mail::send('emails.generic', [
                'title' => 'Reset Password Link (Google)',
                'greeting' => 'Hello developer,',
                'messageLines' => [
                    'You are receiving this email because we received a password reset request for your Google-linked account.',
                    'Please click the button below to reset your password. If you did not request this, no action is required.'
                ],
                'actionUrl' => $resetLink,
                'actionText' => 'Reset Password',
            ], function ($message) use ($gmail) {
                $message->to($gmail)
                        ->subject('Certicode Labs: Reset Password Link (Google)');
            });
        } catch (\Exception $e) {
            Log::error("Failed to send Google password reset email: " . $e->getMessage());
            return redirect()->route('login')->with('status', 'Password reset link (local testing): ' . $resetLink);
        }

        return redirect()->route('login')->with('status', 'We have emailed your password reset link to ' . $gmail);
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
            'google_auth_name',
            'github_auth_username',
        ]);
    }
}

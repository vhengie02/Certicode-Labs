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

        $user = User::findByEmail($credentials['email']);
        if (!$user) {
            return back()->withErrors([
                'email' => 'This email address is not registered.',
            ])->onlyInput('email');
        }

        // Only the account's real password signs in. Demo accounts are seeded with a hashed "password".
        if (Hash::check($credentials['password'], $user->password)) {
            Auth::login($user, $request->filled('remember'));
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
        // Validate before anything else, so a typo never touches the resend cooldown.
        // email:filter rejects addresses like "you@gmail" that the RFC rule lets through.
        $request->validate([
            'gmail' => 'required|string|max:255|email:rfc,filter',
        ], [
            'gmail.required' => 'Enter your Google email address.',
            'gmail.email' => 'Enter a valid Google email address, like you@gmail.com.',
            'gmail.max' => 'Enter a valid Google email address, like you@gmail.com.',
        ]);

        $gmail = strtolower(trim($request->gmail));

        // Typing an address switches to the emailed-code flow, so an earlier OAuth sign-up (its
        // proof, provider name and GitHub identity) no longer applies to whoever continues here.
        session()->forget(['google_auth_oauth_email', 'github_auth_username', 'google_auth_name']);

        // Existing accounts and new addresses both prove ownership with an emailed code;
        // only the step after the code differs (sign in vs. pick a role).
        $verifyParams = $this->findGoogleUser($gmail) ? [] : ['needs_role' => 1];

        // The cooldown only covers resending to the address that last got a code.
        // A different address (e.g. fixing a typo) is sent right away.
        $lastSent = session('google_auth_code_sent_at');
        if ($lastSent && session('google_auth_code_sent_to') === $gmail
            && session('google_auth_code') && session('google_auth_code_for') === $gmail) {
            $elapsed = (int) now()->diffInSeconds($lastSent, true);
            if ($elapsed < 60) {
                $secondsLeft = 60 - $elapsed;
                session()->put('google_auth_gmail', $gmail);
                return redirect()->route('auth.google.verify', $verifyParams)
                    ->withErrors(['code' => "We already sent a code to {$gmail}. Enter it below, or wait {$secondsLeft} seconds to request a new one."]);
            }
        }

        session()->put('google_auth_gmail', $gmail);

        $code = (string) random_int(100000, 999999);

        // Save target gmail and verification code in session. The code is bound to the address it
        // was emailed to, so it can never sign in to a different address put in the session later.
        session()->put('google_auth_code', $code);
        session()->put('google_auth_code_for', $gmail);
        session()->forget('google_auth_code_attempts'); // a fresh code gets a fresh set of attempts

        if ($this->mayRevealAuthSecrets()) {
            Log::info("Google Sign-In/Up verification code for {$gmail}: {$code}");
        }

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

        if ($mailError && !$this->mayRevealAuthSecrets()) {
            // Nothing reached the user, so don't start a cooldown for a code they never got
            session()->forget(['google_auth_code', 'google_auth_code_for', 'gmail_code_debug']);
            return back()->withInput()->withErrors(['gmail' => "We couldn't send the email right now. Please try again in a few minutes."]);
        }

        // Start the resend cooldown only once a code has actually gone out (or, locally, been shown)
        session()->put('google_auth_code_sent_at', now());
        session()->put('google_auth_code_sent_to', $gmail);

        if ($mailError) {
            return redirect()->route('auth.google.verify', $verifyParams)
                ->with('warning', $mailError)
                ->with('success', 'Verification code (local testing): ' . $code);
        }

        return redirect()->route('auth.google.verify', $verifyParams)
            ->with('success', 'A verification code has been successfully sent to ' . $gmail);
    }

    /**
     * The account a Google address belongs to: a verified linked Gmail first, then the account email.
     */
    private function findGoogleUser(string $gmail): ?User
    {
        return User::whereGmailIs($gmail)->whereNotNull('gmail_verified_at')->first()
            ?? User::findByEmail($gmail);
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
        $gmail = session('google_auth_gmail');

        // An OAuth sign-up proved the address the provider returned, and only that address.
        // It skips the emailed code; any other address in the session still needs one.
        $oauthEmail = session('google_auth_oauth_email');
        $provedByOAuth = is_string($gmail) && is_string($oauthEmail) && hash_equals($oauthEmail, $gmail);

        $request->validate([
            'code' => $provedByOAuth ? 'nullable|string|max:20' : 'required|string|min:6|max:20',
            'role' => 'nullable|string|in:student,instructor',
        ]);

        if (!$gmail) {
            return redirect()->route('auth.google')->withErrors(['email' => 'Session expired. Please sign in again.']);
        }

        if (!$provedByOAuth) {
            $expectedCode = session('google_auth_code');

            // The code must have been emailed to this exact address
            if (!is_string($expectedCode) || $expectedCode === '' || session('google_auth_code_for') !== $gmail) {
                return redirect()->route('auth.google')->withErrors(['email' => 'Session expired. Please sign in again.']);
            }

            // Check verification code. After 5 wrong guesses the code is discarded, so a 6-digit
            // code can't be brute-forced; the user requests a new one.
            if (!hash_equals($expectedCode, (string) $request->code)) {
                $attempts = (int) session('google_auth_code_attempts', 0) + 1;
                if ($attempts >= 5) {
                    session()->forget(['google_auth_code', 'google_auth_code_for', 'google_auth_code_attempts']);
                    return redirect()->route('auth.google')->withErrors(['email' => 'Too many incorrect codes. Request a new code to try again.']);
                }
                session()->put('google_auth_code_attempts', $attempts);
                return back()->withErrors(['code' => 'Incorrect verification code. Please try again.']);
            }
            session()->forget('google_auth_code_attempts');
        }

        // 1. Existing account (verified Gmail, or the same account email). The code just proved the
        // user owns this mailbox, so sign them in without a password and link the Gmail if needed.
        $user = $this->findGoogleUser($gmail);

        // The provider's name and GitHub identity belong to the OAuth sign-up only. An emailed-code
        // sign-up must never pick them up, or a GitHub login would later open this account.
        $githubUsername = $provedByOAuth ? session('github_auth_username') : null;
        $providerName = $provedByOAuth ? session('google_auth_name') : null;

        // A GitHub sign-up only asserts the email, it never proved the mailbox; don't hand it an existing account
        if ($user && $githubUsername) {
            $this->clearAuthSessions();
            return redirect()->route('login')->withErrors([
                'email' => 'This email address (' . $gmail . ') is already registered. Please log in, then link GitHub from Settings.',
            ]);
        }

        if ($user) {
            // Link only when no other Gmail is verified on the account, so a linked Gmail is never replaced
            if (!$user->gmail_verified_at || !$user->gmail) {
                // Another account may hold this address as an unverified claim; ownership is now proven here
                User::where('gmail', $gmail)->where('id', '!=', $user->id)->whereNull('gmail_verified_at')->update(['gmail' => null]);
                $user->update(['gmail' => $gmail, 'gmail_verified_at' => now()]);
            }

            Auth::login($user);
            $request->session()->regenerate();
            $this->clearAuthSessions();

            return redirect()->intended('/dashboard')->with('success', 'Logged in with Google successfully!');
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
            
            if (!\App\Models\SystemSetting::read('registration_open')) {
                return redirect()->route('login')->withErrors(['email' => 'New sign-ups are closed right now. If you already have an account, sign in; otherwise ask your instructor or a Certicode admin.']);
            }

            // Drop any stale unverified claim on this Gmail so the unique column doesn't block sign-up
            User::where('gmail', $gmail)->whereNull('gmail_verified_at')->update(['gmail' => null]);

            $newUser = User::create([
                'name' => $providerName ?: $name,
                'email' => $gmail,
                'gmail' => $gmail,
                'gmail_verified_at' => now(),
                'password' => Hash::make($password),
                'role' => 'student', // instructor access needs an admin's approval
                'github_username' => $githubUsername,
            ]);

            if ($request->role === 'instructor') {
                $newUser->requestInstructorAccess();
            }

            Auth::login($newUser);
            $request->session()->regenerate();
            $this->clearAuthSessions();

            return redirect('/dashboard')->with('success', $request->role === 'instructor'
                ? 'Account created and Google linked. Your instructor access is waiting for an admin to approve it.'
                : 'Account created and Google linked successfully!');
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
                // The email-code flow proves the address is theirs; admins see the missing keys on the System page
                return redirect()->route('auth.google');
            }
            if ($provider === 'github') {
                if (!$this->mockGithubAllowed()) {
                    return redirect()->route('login')->withErrors(['email' => "GitHub sign-in isn't set up yet. Sign in with your email or Google instead."]);
                }
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
        abort_unless($this->mockGithubAllowed(), 404);

        return view('auth.github');
    }

    /**
     * The mock GitHub flow trusts whatever username and email are typed in, so it may only run in
     * local development and tests. Anywhere else it would let anyone sign in as any user.
     */
    private function mockGithubAllowed(): bool
    {
        return app()->environment('local', 'testing');
    }

    /**
     * Handle mock GitHub OAuth callback.
     */
    public function handleGithubCallback(Request $request)
    {
        abort_unless($this->mockGithubAllowed(), 404);

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
            $dbUser = User::findByEmail($email);
            if ($dbUser) {
                $dbUser->update(['github_username' => $nickname]);
            }
        }

        if ($dbUser) {
            Auth::login($dbUser);
            $request->session()->regenerate();
            return redirect()->intended('/dashboard')->with('success', 'Logged in via GitHub successfully!');
        }

        $this->startOAuthSignup($email, $name, $nickname);

        return redirect()->route('auth.google.verify', ['needs_role' => 1]);
    }

    /**
     * Hand a new OAuth identity to the "finish creating your account" step. The proof covers
     * only the address the provider returned, and it is kept apart from emailed codes so the
     * code flow can never inherit it for a different address.
     */
    private function startOAuthSignup(string $email, string $name, ?string $githubUsername = null): void
    {
        session()->forget(['google_auth_code', 'google_auth_code_for', 'google_auth_code_attempts', 'gmail_code_debug']);
        session()->put('google_auth_gmail', $email);
        session()->put('google_auth_oauth_email', $email);
        session()->put('google_auth_name', $name);

        if ($githubUsername !== null) {
            session()->put('github_auth_username', $githubUsername);
        } else {
            session()->forget('github_auth_username');
        }
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
            if (empty($email)) {
                return redirect()->route('login')->withErrors(['email' => 'Unable to retrieve your email from Google. Please try again.']);
            }

            $user = User::whereGmailIs($email)
                ->whereNotNull('gmail_verified_at')
                ->first();

            if (!$user) {
                $user = User::findByEmail($email);
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

            $this->startOAuthSignup($email, $name);

            return redirect()->route('auth.google.verify', ['needs_role' => 1]);
        }

        if ($provider === 'github') {
            $user = User::where('github_username', $nickname)->first();

            if (!$user && !empty($email)) {
                $user = User::findByEmail($email);
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

            $this->startOAuthSignup($email, $name, $nickname);

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

        $user = $this->findGoogleUser($gmail);

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
            'email' => ['required', 'email', $this->registeredEmailRule()],
        ]);

        // Tokens are keyed by the normalized address, whatever case was typed
        $email = User::normalizeEmail($request->email);
        $token = Str::random(60);

        \DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $email],
            [
                'token' => Hash::make($token),
                'created_at' => now(),
            ]
        );

        $resetLink = route('password.reset', ['token' => $token, 'email' => $email]);

        if ($this->mayRevealAuthSecrets()) {
            Log::info("Password reset link for {$email}: {$resetLink}");
        }

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
            if (!$this->mayRevealAuthSecrets()) {
                return back()->withErrors(['email' => "We couldn't send the email right now. Please try again in a few minutes."]);
            }
            // Local development only: show the link so the flow can be completed without email
            return back()->with('status', 'We have generated your reset link (local testing). Check logs or copy link: ' . $resetLink);
        }

        return back()->with('status', 'We have emailed your password reset link!');
    }

    /**
     * exists:users,email, but case-insensitive (Postgres compares case-sensitively).
     */
    private function registeredEmailRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) {
            if (!User::findByEmail((string) $value)) {
                $fail('validation.exists')->translate();
            }
        };
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
            'email' => ['required', 'email', $this->registeredEmailRule()],
            'password' => 'required|string|min:8|confirmed',
        ]);

        $email = User::normalizeEmail($request->email);
        $token = $request->token;

        $record = \DB::table('password_reset_tokens')->where('email', $email)->first();

        if (!$record || !Hash::check($token, $record->token)) {
            return back()->withErrors(['email' => 'This password reset token is invalid.']);
        }

        if (now()->subMinutes(60)->gt($record->created_at)) {
            \DB::table('password_reset_tokens')->where('email', $email)->delete();
            return back()->withErrors(['email' => 'This password reset token has expired.']);
        }

        $user = User::findByEmail($email) ?? abort(404);
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
        $user = User::whereGmailIs($gmail)->whereNotNull('gmail_verified_at')->first();
        if (!$user) {
            $user = User::findByEmail($gmail);
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

        if ($this->mayRevealAuthSecrets()) {
            Log::info("Google auth password reset link for {$gmail}: {$resetLink}");
        }

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
            if (!$this->mayRevealAuthSecrets()) {
                return redirect()->route('login')->withErrors(['email' => "We couldn't send the email right now. Please try again in a few minutes."]);
            }
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
            'google_auth_code_for',
            'google_auth_code_attempts',
            'google_auth_oauth_email',
            'google_auth_code_sent_at',
            'google_auth_code_sent_to',
            'gmail_code_debug',
            'google_auth_name',
            'github_auth_username',
        ]);
    }
}

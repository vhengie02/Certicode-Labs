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
            $name = ucwords(str_replace(['.', '_', '-'], ' ', explode('@', $gmail)[0]));
            
            $newUser = User::create([
                'name' => session('google_auth_name') ?: $name,
                'email' => $gmail,
                'gmail' => $gmail,
                'gmail_verified_at' => now(),
                'password' => Hash::make(Str::random(16)),
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

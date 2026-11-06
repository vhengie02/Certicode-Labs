<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Rules\UniqueEmail;
use App\Support\AccountDeletion;
use App\Support\AccountExport;
use App\Support\DeviceLabel;
use App\Support\DisplayTime;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class SettingsController extends Controller
{
    /** How long an emailed Gmail code stays valid, and how many tries it gets. */
    private const CODE_TTL_MINUTES = 10;

    private const CODE_MAX_ATTEMPTS = 5;

    /**
     * Show settings dashboard.
     */
    public function show(Request $request, AccountDeletion $deletion)
    {
        $user = auth()->user();

        return view('settings.index', [
            'user' => $user,
            'toggles' => $this->notificationToggles($user),
            'activeSessions' => $this->activeSessions($request),
            'deletionBlocker' => $deletion->blocker($user),
            'deletionKeepsRecord' => $deletion->keepsRecord($user),
        ]);
    }

    /**
     * Email toggles for this user's role, as field => [label, hint]. Saving only touches these,
     * so a toggle a role never sees keeps its value.
     *
     * @return array<string, array{string, string}>
     */
    private function notificationToggles(User $user): array
    {
        $toggles = [
            'notify_class' => ['Class invitations', 'When an instructor invites you to a class, or a student joins yours.'],
            'notify_module' => ['New lessons', 'When a module is added to one of your classes.'],
            'notify_lab' => ['New labs', 'When a lab is added to one of your classes.'],
        ];

        if ($user->role === 'student') {
            $toggles['notify_grade'] = ['Grades', 'When your work is graded, or an instructor reviews your grade.'];
        }

        $toggles['notify_certificate'] = ['Certificates', 'When you reach a class’s pass mark and your certificate is issued.'];

        if ($user->role !== 'student') {
            $toggles['notify_lab'] = ['Labs', 'When a lab is added to one of your classes, or students submit one of your labs. At most one email per quiet spell for submissions.'];
            $toggles['notify_integrity'] = ['Camera flags', 'When the camera flags a student in one of your labs. At most one email per quiet spell.'];
        }

        return $toggles;
    }

    /**
     * This user's signed-in browsers, newest first. Null when sessions aren't kept in the database,
     * because there is then nothing to list.
     */
    private function activeSessions(Request $request): ?Collection
    {
        if (config('session.driver') !== 'database') {
            return null;
        }

        return DB::table(config('session.table'))
            ->where('user_id', $request->user()->id)
            ->where('last_activity', '>=', now()->subMinutes((int) config('session.lifetime'))->timestamp)
            ->orderByDesc('last_activity')
            ->get()
            ->map(fn ($session) => [
                'device' => DeviceLabel::from($session->user_agent),
                'ip_address' => $session->ip_address,
                'last_active' => Carbon::createFromTimestamp($session->last_activity),
                'is_current' => $session->id === $request->session()->getId(),
            ]);
    }

    /**
     * Update notification settings.
     */
    public function updateNotifications(Request $request)
    {
        $user = auth()->user();
        $fields = array_keys($this->notificationToggles($user));

        $request->validate(
            collect($fields)->push('notify_email_channel')->mapWithKeys(fn ($field) => [$field => 'boolean'])->all()
        );

        // Default toggles to false if not present in request (since checkboxes are omitted when unchecked)
        $user->update(
            collect($fields)->push('notify_email_channel')->mapWithKeys(fn ($field) => [$field => $request->has($field)])->all()
        );

        return redirect()->route('settings.show')->with('success', 'Notification preferences updated successfully!');
    }

    /**
     * Update the time zone and date style.
     */
    public function updateDateTime(Request $request)
    {
        $validated = $request->validate([
            'timezone' => ['nullable', 'timezone'],
            'date_style' => ['nullable', Rule::in(array_keys(DisplayTime::DATE_STYLES))],
        ]);

        $request->user()->update($validated);

        return redirect()->route('settings.show')->with('success', 'Date and time settings updated.');
    }

    /**
     * Sign this account out of every other browser. The remember token changes too, or a
     * "Keep me signed in" cookie would sign those browsers straight back in.
     */
    public function signOutOtherSessions(Request $request)
    {
        $user = $request->user();

        DB::table(config('session.table'))
            ->where('user_id', $user->id)
            ->where('id', '!=', $request->session()->getId())
            ->delete();

        $keepMeSignedIn = $request->hasCookie(Auth::guard()->getRecallerName());
        $user->setRememberToken(Str::random(60));
        $user->save();

        // This browser's own cookie just went stale with the old token
        if ($keepMeSignedIn) {
            Auth::login($user, true);
        }

        return redirect()->route('settings.show')->with('success', 'You were signed out everywhere else.');
    }

    /**
     * Download everything we hold about this account.
     */
    public function exportData(Request $request)
    {
        $export = new AccountExport($request->user());

        return response()->streamDownload(
            fn () => $export->stream(),
            'certicode-data-'.now()->format('Y-m-d').'.json',
            ['Content-Type' => 'application/json']
        );
    }

    /**
     * Delete this account. Typing the email confirms it: Google and GitHub accounts have no
     * password they know.
     */
    public function destroyAccount(Request $request, AccountDeletion $deletion)
    {
        $user = $request->user();

        $request->validate(['confirm_email' => 'required|string']);

        if (strcasecmp(trim($request->confirm_email), $user->email) !== 0) {
            return back()->withErrors(['confirm_email' => "That doesn't match your account email."]);
        }

        if ($blocker = $deletion->blocker($user)) {
            return back()->withErrors(['confirm_email' => $blocker]);
        }

        // Logging out saves a fresh remember token on the user, which would re-insert a deleted row
        Auth::logout();
        $deletion->delete($user);

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Your account was deleted.');
    }

    /**
     * Generate and mock send a Gmail verification code.
     */
    public function sendGmailCode(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'gmail' => ['required', 'email', new UniqueEmail('gmail', $user->id)],
        ]);

        if (! app()->runningUnitTests()) {
            $lastSent = session('gmail_code_sent_at');
            $elapsed = $lastSent ? (int) now()->diffInSeconds($lastSent, true) : null;
            if ($lastSent && $elapsed < 60) {
                $secondsLeft = 60 - $elapsed;

                return back()->withErrors(['code' => "Please wait {$secondsLeft} seconds before requesting a new code."]);
            }
        }

        // Generate 6-digit code
        $code = (string) random_int(100000, 999999);

        // Store the unverified Gmail and a hash of the code, its expiry and a fresh attempt count
        $user->update([
            'gmail' => $request->gmail,
            'gmail_verification_code' => $this->encodePendingCode(
                now()->addMinutes(self::CODE_TTL_MINUTES)->timestamp,
                0,
                $this->codeHash($code)
            ),
            'gmail_verified_at' => null,
        ]);

        session()->put('gmail_code_sent_at', now());

        // Local development only: log the code so the flow works without email
        if ($this->mayRevealAuthSecrets()) {
            Log::info("Gmail connection code for User ID {$user->id} ({$request->gmail}): {$code}");
        }

        $mailError = null;
        try {
            Mail::send('emails.generic', [
                'title' => 'Gmail Connection Code',
                'greeting' => 'Hello '.($user->name ?? 'developer').',',
                'messageLines' => [
                    'You requested to connect your Gmail account ('.$request->gmail.') to your Certicode Labs profile.',
                    'Please use the verification code below to verify your email ownership. It expires in '.self::CODE_TTL_MINUTES.' minutes.',
                ],
                'code' => $code,
            ], function ($message) use ($request) {
                $message->to($request->gmail)
                    ->subject('Certicode Labs: Gmail Connection Code');
            });
        } catch (\Exception $e) {
            Log::error('Failed to send verification email: '.$e->getMessage());
            $mailError = "Note: Real email delivery failed. (Error: {$e->getMessage()}). However, you can still complete verification using the code below for local testing.";
        }

        if ($mailError) {
            if (! $this->mayRevealAuthSecrets()) {
                return redirect()->route('settings.show')->withErrors(['gmail' => "We couldn't send the email right now. Please try again in a few minutes."]);
            }

            return redirect()->route('settings.show')
                ->with('warning', $mailError)
                ->with('success', 'Verification code (local testing): '.$code);
        }

        return redirect()->route('settings.show')->with('success', 'A verification code has been successfully sent to '.$request->gmail);
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

        $stored = $user->refresh()->gmail_verification_code;
        $pending = $this->decodePendingCode($stored);

        if (! $pending || $pending['expires_at'] < now()->timestamp || $pending['attempts'] >= self::CODE_MAX_ATTEMPTS) {
            if ($stored !== null) {
                $user->update(['gmail_verification_code' => null]);
            }

            return redirect()->route('settings.show')->withErrors(['code' => 'This code has expired. Request a new one.']);
        }

        // Count the attempt before checking it. The write only lands if nobody else changed the
        // code meanwhile, so parallel guesses can't share one attempt.
        $attempts = $pending['attempts'] + 1;
        $claimed = User::whereKey($user->id)
            ->where('gmail_verification_code', $stored)
            ->update(['gmail_verification_code' => $this->encodePendingCode($pending['expires_at'], $attempts, $pending['hash'])]);

        if ($claimed === 0) {
            return redirect()->route('settings.show')->withErrors(['code' => 'Incorrect verification code. Please try again.']);
        }

        if (hash_equals($pending['hash'], $this->codeHash($request->code))) {
            $user->update([
                'gmail_verified_at' => now(),
                'gmail_verification_code' => null,
            ]);

            session()->forget('gmail_code_debug');

            return redirect()->route('settings.show')->with('success', 'Gmail account connected successfully!');
        }

        if ($attempts >= self::CODE_MAX_ATTEMPTS) {
            $user->update(['gmail_verification_code' => null]);

            return redirect()->route('settings.show')->withErrors(['code' => 'Too many incorrect codes. Request a new code to try again.']);
        }

        return redirect()->route('settings.show')->withErrors(['code' => 'Incorrect verification code. Please try again.']);
    }

    /**
     * A pending Gmail code is kept in gmail_verification_code as "expires|attempts|hash",
     * so the plain code is never stored and expiry and attempts need no extra columns.
     */
    private function encodePendingCode(int $expiresAt, int $attempts, string $hash): string
    {
        return "{$expiresAt}|{$attempts}|{$hash}";
    }

    /**
     * @return array{expires_at: int, attempts: int, hash: string}|null
     */
    private function decodePendingCode(?string $value): ?array
    {
        $parts = explode('|', (string) $value);
        if (count($parts) !== 3 || ! ctype_digit($parts[0]) || ! ctype_digit($parts[1]) || $parts[2] === '') {
            return null;
        }

        return ['expires_at' => (int) $parts[0], 'attempts' => (int) $parts[1], 'hash' => $parts[2]];
    }

    private function codeHash(string $code): string
    {
        return hash_hmac('sha256', $code, (string) config('app.key'));
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
            'username' => 'required|string|max:255|unique:users,username,'.$user->id,
            'gender' => 'nullable|string|in:male,female,other',
            'email' => ['sometimes', 'required', 'string', 'email', 'max:255', new UniqueEmail(ignoreUserId: $user->id)],
        ]);

        $validated['name'] = $validated['first_name'].' '.$validated['last_name'];
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
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user->update([
            'password' => Hash::make($request->password),
        ]);

        return redirect()->route('settings.show')->with('success', 'Password updated successfully!');
    }
}

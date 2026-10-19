<?php

namespace App\Providers;

use App\Support\PostgresConnection;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Connection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        Connection::resolverFor('pgsql', fn ($pdo, $database, $prefix, $config) => new PostgresConnection($pdo, $database, $prefix, $config));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureRateLimiting();
    }

    /**
     * Throttles for endpoints that can be guessed against, send mail, or spend Claude and
     * Judge0 quota. The counts live in the limiter store (cache.limiter, the database), so
     * they hold across serverless instances. Per-address limits are generous because a
     * whole class often signs in from one school network.
     */
    private function configureRateLimiting(): void
    {
        // Password guesses, per account from one address and per address overall
        RateLimiter::for('login', function (Request $request) {
            $account = $this->throttleIdentity($request, 'email');

            return [
                Limit::perMinute(5)->by('login:'.$account.'|'.$request->ip()),
                Limit::perMinute(100)->by('login-ip:'.$request->ip()),
            ];
        });

        // Anything that emails a code or a link: per recipient, and per address
        RateLimiter::for('auth-mail', function (Request $request) {
            $recipient = $this->throttleIdentity($request, 'gmail', 'email');

            return [
                Limit::perMinutes(15, 5)->by('auth-mail-to:'.$recipient),
                Limit::perHour(100)->by('auth-mail-ip:'.$request->ip()),
            ];
        });

        // Emailed-code and reset-token checks, per account (or sign-in session) and per address
        RateLimiter::for('code-verify', function (Request $request) {
            $who = $request->user()?->id ?? ($request->hasSession() ? $request->session()->getId() : $request->ip());

            return [
                Limit::perMinute(10)->by('code-verify:'.$who),
                Limit::perMinute(100)->by('code-verify-ip:'.$request->ip()),
            ];
        });

        RateLimiter::for('register', function (Request $request) {
            return Limit::perHour(60)->by('register:'.$request->ip());
        });

        // Claude grading (check-progress, submit) and Judge0 runs, per lab session
        RateLimiter::for('lab-grade', function (Request $request) {
            return Limit::perMinute(6)->by('lab-grade:'.$request->route('sessionId'));
        });

        RateLimiter::for('lab-execute', function (Request $request) {
            return Limit::perMinute(20)->by('lab-execute:'.$request->route('sessionId'));
        });
    }

    /**
     * The address a request is about: the first filled input, else the Google address the
     * sign-in flow keeps in the session.
     */
    private function throttleIdentity(Request $request, string ...$inputs): string
    {
        foreach ($inputs as $input) {
            $value = $request->input($input);
            if (is_string($value) && trim($value) !== '') {
                return Str::lower(trim($value));
            }
        }

        return $request->hasSession() ? Str::lower((string) $request->session()->get('google_auth_gmail', '')) : '';
    }
}

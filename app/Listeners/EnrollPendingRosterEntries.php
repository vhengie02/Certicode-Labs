<?php

namespace App\Listeners;

use App\Models\User;
use App\Services\ClassRoster;
use Illuminate\Auth\Events\Login;

/**
 * A student whose email an instructor put on a roster before they had an account joins
 * those classes on their first sign-in, whether by password, Google or a new sign-up.
 */
class EnrollPendingRosterEntries
{
    public function __construct(private ClassRoster $roster) {}

    public function handle(Login $event): void
    {
        if (! $event->user instanceof User) {
            return;
        }

        // A roster problem must never stop someone signing in
        try {
            $this->roster->enrollPending($event->user);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}

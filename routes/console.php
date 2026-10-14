<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\Laboratory;
use App\Models\SchoolClass;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('certicode:make-admin {email}', function (string $email) {
    $user = \App\Models\User::where('email', $email)->first();
    if (!$user) {
        $this->error("No user with email {$email}. Create the account first, then run this again.");
        return 1;
    }

    $user->forceFill(['role' => 'admin', 'instructor_requested_at' => null])->save();
    $this->info("{$user->name} ({$email}) is now an admin and can review instructor requests at /admin/instructor-requests.");
    return 0;
})->purpose('Make an existing user an admin');

Artisan::command('certicode:close-expired-live-labs', function () {
    $expiredCount = 0;
    $activeLiveLabs = Laboratory::where('availability_mode', 'live')
        ->where('live_status', 'active')
        ->get();

    foreach ($activeLiveLabs as $lab) {
        if ($lab->getRemainingLiveSeconds() <= 0) {
            $lab->closeLive();
            $expiredCount++;
        }
    }

    $this->info("Checked live labs: {$expiredCount} expired live lab(s) closed.");
})->purpose('Check and auto-close expired Live Labs whose countdown reached zero');

Artisan::command('certicode:auto-conclude-expired-classes', function () {
    $expiredClasses = SchoolClass::where('status', '!=', 'completed')
        ->whereNotNull('scheduled_end_date')
        ->where('scheduled_end_date', '<=', now())
        ->with(['students', 'modules.laboratories'])
        ->get();

    $closedCount = 0;
    foreach ($expiredClasses as $class) {
        $class->concludeAndCertify();
        $closedCount++;
    }

    $this->info("Checked scheduled classes: {$closedCount} expired class(es) auto-concluded.");
})->purpose('Auto-conclude classes whose scheduled end date has arrived and issue e-certificates');

Schedule::command('certicode:close-expired-live-labs')->everyMinute();
Schedule::command('certicode:auto-conclude-expired-classes')->everyMinute();

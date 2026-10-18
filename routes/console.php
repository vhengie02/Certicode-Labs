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
        ->get();

    $closedCount = 0;
    foreach ($expiredClasses as $class) {
        $class->concludeAndCertify();
        $closedCount++;
    }

    $this->info("Checked scheduled classes: {$closedCount} expired class(es) auto-concluded.");
})->purpose('Auto-conclude classes whose scheduled end date has arrived and issue e-certificates');

Artisan::command('certicode:refresh-competencies {--dry-run : Report what would change without saving}', function () {
    $dryRun = (bool) $this->option('dry-run');
    $totals = ['created' => 0, 'changed' => 0, 'removed' => 0, 'unchanged' => 0];

    // Every (student, competency) pair that has completed tagged work or a stored score, once
    // each: the first set finds missed credits, the second finds scores left stale by work
    // that was later reopened
    $withWork = \Illuminate\Support\Facades\DB::table('lab_sessions')
        ->join('laboratory_competency', 'laboratory_competency.laboratory_id', '=', 'lab_sessions.lab_id')
        ->where('lab_sessions.status', 'completed')
        ->whereNotNull('lab_sessions.user_id')
        ->select('lab_sessions.user_id', 'laboratory_competency.competency_id');
    $pairs = \Illuminate\Support\Facades\DB::table('student_competencies')
        ->select('user_id', 'competency_id')
        ->union($withWork)
        ->get();

    $run = function () use ($pairs, &$totals) {
        foreach ($pairs as $pair) {
            $totals[\App\Models\StudentCompetency::refreshCompetency((int) $pair->user_id, (int) $pair->competency_id)]++;
        }
    };

    if ($dryRun) {
        \Illuminate\Support\Facades\DB::beginTransaction();
        try {
            $run();
        } finally {
            \Illuminate\Support\Facades\DB::rollBack();
        }
    } else {
        $run();
    }

    $this->info(($dryRun ? '[dry run, nothing saved] ' : '')
        . 'Checked ' . $pairs->count() . " student/competency pair(s): {$totals['created']} created, {$totals['changed']} changed, "
        . "{$totals['removed']} removed, {$totals['unchanged']} unchanged.");
})->purpose('Recalculate every competency score from completed lab sessions (run once after deploying the competency fix)');

Artisan::command('certicode:expire-old-snapshots', function () {
    $count = \App\Models\Anomaly::expireOldSnapshots();
    $this->info("Removed the webcam snapshot from {$count} flag(s) older than " . config('app.snapshot_retention_days') . ' days.');
})->purpose('Remove webcam snapshots from old integrity flags, keeping the flags');

Schedule::command('certicode:close-expired-live-labs')->everyMinute();
Schedule::command('certicode:auto-conclude-expired-classes')->everyMinute();
Schedule::command('model:prune', ['--model' => [\App\Models\Anomaly::class, \App\Models\TelemetryLog::class]])->daily();
Schedule::command('certicode:expire-old-snapshots')->daily();

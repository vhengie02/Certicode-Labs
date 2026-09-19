<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use App\Models\Laboratory;
use App\Models\SchoolClass;
use Illuminate\Support\Str;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

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
        $class->update(['status' => 'completed']);

        $threshold = $class->passing_threshold ?? 75;
        foreach ($class->students as $student) {
            $progress = $class->getStudentProgress($student);
            if ($progress['percent'] >= $threshold && $progress['total'] > 0) {
                $existing = \App\Models\Certificate::where('user_id', $student->id)
                    ->where('class_id', $class->id)
                    ->first();

                if (!$existing) {
                    $code = 'CERT-' . strtoupper(Str::random(12));
                    while (\App\Models\Certificate::where('verification_code', $code)->exists()) {
                        $code = 'CERT-' . strtoupper(Str::random(12));
                    }

                    $cert = \App\Models\Certificate::create([
                        'user_id' => $student->id,
                        'class_id' => $class->id,
                        'verification_code' => $code,
                        'qr_code_path' => 'certificates/qr-' . $code . '.svg',
                        'issued_at' => now(),
                    ]);

                    try {
                        $student->notify(new \App\Notifications\ClassActivityNotification(
                            "Course Completed: {$class->name}",
                            "Congratulations! You completed '{$class->name}' with {$progress['percent']}% (threshold: {$threshold}%) and earned your official certificate.",
                            route('certificates.show', $cert->id),
                            'certificate'
                        ));
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::warning("Certificate notification failed: " . $e->getMessage());
                    }
                }
            }
        }
        $closedCount++;
    }

    $this->info("Checked scheduled classes: {$closedCount} expired class(es) auto-concluded.");
})->purpose('Auto-conclude classes whose scheduled end date has arrived and issue e-certificates');

Schedule::command('certicode:close-expired-live-labs')->everyMinute();
Schedule::command('certicode:auto-conclude-expired-classes')->everyMinute();

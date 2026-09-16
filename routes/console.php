<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;
use App\Models\Laboratory;

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

Schedule::command('certicode:close-expired-live-labs')->everyMinute();

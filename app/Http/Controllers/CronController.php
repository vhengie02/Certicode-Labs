<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;

/**
 * Scheduled tick (Vercel cron). Registered as /api/cron/tick and /cron/tick: on Vercel the
 * function lives at api/index.php and the "/api" prefix is stripped before Laravel routes it.
 */
class CronController extends Controller
{
    public function __invoke(Request $request)
    {
        if (env('CRON_SECRET') && $request->header('Authorization') !== 'Bearer ' . env('CRON_SECRET')) {
            return response()->json(['error' => 'unauthorized'], 401);
        }

        Artisan::call('certicode:close-expired-live-labs');
        $liveLabsOutput = trim(Artisan::output());

        Artisan::call('certicode:auto-conclude-expired-classes');
        $classesOutput = trim(Artisan::output());

        // The Laravel scheduler doesn't run on Vercel, so the daily prune from routes/console.php runs here too.
        Artisan::call('model:prune', ['--model' => [\App\Models\Anomaly::class]]);
        $pruneOutput = trim(Artisan::output());

        return response()->json([
            'status' => 'success',
            'timestamp' => now()->toIso8601String(),
            'results' => [
                'live_labs' => $liveLabsOutput,
                'classes' => $classesOutput,
                'anomaly_prune' => $pruneOutput,
            ],
        ]);
    }
}

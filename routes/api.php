<?php

use App\Http\Controllers\Api\LabSessionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider or bootstrap configuration.
|
*/

// Protected routes (Sanctum)
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::post('/labs/{labId}/start', [LabSessionController::class, 'startSession']);
    Route::get('/sessions/{sessionId}', [LabSessionController::class, 'getSession']);
    Route::post('/sessions/{sessionId}/telemetry', [LabSessionController::class, 'submitTelemetry']);
    Route::post('/sessions/{sessionId}/ping', [LabSessionController::class, 'pingSession']);
    Route::post('/sessions/{sessionId}/execute', [LabSessionController::class, 'executeCode']);
    Route::post('/sessions/{sessionId}/check-progress', [LabSessionController::class, 'checkProgress']);
    Route::post('/sessions/{sessionId}/submit', [LabSessionController::class, 'submitSession']);
    Route::post('/sessions/{sessionId}/github-contributions', [LabSessionController::class, 'syncGithubContributions']);
    Route::get('/sessions/{sessionId}/diff', [LabSessionController::class, 'recordDiff']);
    Route::get('/sessions/{sessionId}/chat', [LabSessionController::class, 'getChats']);
    Route::post('/sessions/{sessionId}/chat', [LabSessionController::class, 'sendChat']);
    Route::get('/sessions/{sessionId}/leaderboard', [LabSessionController::class, 'getLeaderboard']);
    Route::post('/sessions/{sessionId}/end', [LabSessionController::class, 'endSession']);
    Route::post('/sessions/{sessionId}/reopen', [LabSessionController::class, 'reopenSession']);
    Route::post('/labs/{labId}/verify-camera', [LabSessionController::class, 'verifyCameraLab']);
    Route::post('/sessions/{sessionId}/verify-camera', [LabSessionController::class, 'verifyCamera']);
    Route::post('/labs/{labId}/open-live', [LabSessionController::class, 'openLive']);
    Route::post('/labs/{labId}/end-live', [LabSessionController::class, 'endLive']);
    Route::post('/labs/{labId}/reopen-live', [LabSessionController::class, 'reopenLive']);
});

// Public Prototyping routes (v1 prefix) for early-stage frontend testing without tokens
Route::prefix('v1')->group(function () {
    Route::post('/labs/{labId}/start', [LabSessionController::class, 'startSession']);
    Route::get('/sessions/{sessionId}', [LabSessionController::class, 'getSession']);
    Route::post('/sessions/{sessionId}/telemetry', [LabSessionController::class, 'submitTelemetry']);
    Route::post('/sessions/{sessionId}/ping', [LabSessionController::class, 'pingSession']);
    Route::post('/sessions/{sessionId}/verify-camera', [LabSessionController::class, 'verifyCamera']);
    Route::post('/labs/{labId}/verify-camera', [LabSessionController::class, 'verifyCameraLab']);
    Route::post('/sessions/{sessionId}/execute', [LabSessionController::class, 'executeCode']);
    Route::post('/sessions/{sessionId}/check-progress', [LabSessionController::class, 'checkProgress']);
    Route::post('/sessions/{sessionId}/submit', [LabSessionController::class, 'submitSession']);
    Route::post('/sessions/{sessionId}/github-contributions', [LabSessionController::class, 'syncGithubContributions']);
    Route::post('/sessions/{sessionId}/diff', [LabSessionController::class, 'recordDiff']);
    Route::get('/sessions/{sessionId}/chat', [LabSessionController::class, 'getChats']);
    Route::post('/sessions/{sessionId}/chat', [LabSessionController::class, 'sendChat']);
    Route::get('/sessions/{sessionId}/leaderboard', [LabSessionController::class, 'getLeaderboard']);
    Route::post('/sessions/{sessionId}/end', [LabSessionController::class, 'endSession']);
    Route::post('/sessions/{sessionId}/reopen', [LabSessionController::class, 'reopenSession']);
    Route::post('/labs/{labId}/open-live', [LabSessionController::class, 'openLive']);
    Route::post('/labs/{labId}/end-live', [LabSessionController::class, 'endLive']);
    Route::post('/labs/{labId}/reopen-live', [LabSessionController::class, 'reopenLive']);
});

// Vercel / Cloud Scheduled Cron Trigger Endpoint
Route::get('/cron/tick', function (Request $request) {
    if (env('CRON_SECRET') && $request->header('Authorization') !== 'Bearer ' . env('CRON_SECRET')) {
        return response()->json(['error' => 'unauthorized'], 401);
    }

    \Illuminate\Support\Facades\Artisan::call('certicode:close-expired-live-labs');
    $liveLabsOutput = trim(\Illuminate\Support\Facades\Artisan::output());

    \Illuminate\Support\Facades\Artisan::call('certicode:auto-conclude-expired-classes');
    $classesOutput = trim(\Illuminate\Support\Facades\Artisan::output());

    return response()->json([
        'status' => 'success',
        'timestamp' => now()->toIso8601String(),
        'results' => [
            'live_labs' => $liveLabsOutput,
            'classes' => $classesOutput,
        ],
    ]);
});

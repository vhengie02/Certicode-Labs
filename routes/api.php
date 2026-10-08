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

// VS Code extension routes (v1). Session routes require the session's extension token
// (X-Session-Token, issued in the vscode:// deep link) or an authorized signed-in user.
Route::prefix('v1')->group(function () {
    Route::middleware('auth')->group(function () {
        Route::post('/labs/{labId}/start', [LabSessionController::class, 'startSession']);
        Route::post('/labs/{labId}/verify-camera', [LabSessionController::class, 'verifyCameraLab']);
        Route::post('/labs/{labId}/open-live', [LabSessionController::class, 'openLive']);
        Route::post('/labs/{labId}/end-live', [LabSessionController::class, 'endLive']);
        Route::post('/labs/{labId}/reopen-live', [LabSessionController::class, 'reopenLive']);
    });

    Route::middleware('lab.session')->group(function () {
        Route::get('/sessions/{sessionId}', [LabSessionController::class, 'getSession']);
        Route::post('/sessions/{sessionId}/telemetry', [LabSessionController::class, 'submitTelemetry']);
        Route::post('/sessions/{sessionId}/ping', [LabSessionController::class, 'pingSession']);
        Route::post('/sessions/{sessionId}/verify-camera', [LabSessionController::class, 'verifyCamera']);
        Route::post('/sessions/{sessionId}/execute', [LabSessionController::class, 'executeCode']);
        Route::post('/sessions/{sessionId}/check-progress', [LabSessionController::class, 'checkProgress']);
        Route::post('/sessions/{sessionId}/submit', [LabSessionController::class, 'submitSession']);
        Route::post('/sessions/{sessionId}/github-contributions', [LabSessionController::class, 'syncGithubContributions']);
        Route::post('/sessions/{sessionId}/diff', [LabSessionController::class, 'recordDiff']);
        Route::get('/sessions/{sessionId}/chat', [LabSessionController::class, 'getChats']);
        Route::post('/sessions/{sessionId}/chat', [LabSessionController::class, 'sendChat']);
        Route::get('/sessions/{sessionId}/leaderboard', [LabSessionController::class, 'getLeaderboard']);
        Route::post('/sessions/{sessionId}/broadcasting/auth', [LabSessionController::class, 'authorizeBroadcast']);
        Route::post('/sessions/{sessionId}/end', [LabSessionController::class, 'endSession']);
        Route::post('/sessions/{sessionId}/reopen', [LabSessionController::class, 'reopenSession']);
    });
});

// Vercel / Cloud Scheduled Cron Trigger Endpoint (also registered as /cron/tick in web.php)
Route::get('/cron/tick', \App\Http\Controllers\CronController::class);

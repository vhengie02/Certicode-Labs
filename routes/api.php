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
    Route::post('/sessions/{sessionId}/telemetry', [LabSessionController::class, 'submitTelemetry']);
    Route::post('/sessions/{sessionId}/execute', [LabSessionController::class, 'executeCode']);
    Route::post('/sessions/{sessionId}/github-contributions', [LabSessionController::class, 'syncGithubContributions']);
});

// Public Prototyping routes (v1 prefix) for early-stage frontend testing without tokens
Route::prefix('v1')->group(function () {
    Route::post('/labs/{labId}/start', [LabSessionController::class, 'startSession']);
    Route::post('/sessions/{sessionId}/telemetry', [LabSessionController::class, 'submitTelemetry']);
    Route::post('/sessions/{sessionId}/execute', [LabSessionController::class, 'executeCode']);
    Route::post('/sessions/{sessionId}/github-contributions', [LabSessionController::class, 'syncGithubContributions']);
});

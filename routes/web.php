<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\ClassController;
use App\Http\Controllers\LaboratoryController;
use App\Http\Controllers\StudentProfileController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\InstructorMonitoringController;

// Diagnostic Health Check for Serverless & Monitoring (bypasses session/cookie middleware)
Route::get('/health-check', function () {
    $dbOk = false;
    $dbError = null;
    $sampleTables = [];
    try {
        \Illuminate\Support\Facades\DB::connection()->getPdo();
        $dbOk = true;
        if (config('database.default') === 'pgsql') {
            $tables = \Illuminate\Support\Facades\DB::select("SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' LIMIT 5");
            $sampleTables = array_column($tables, 'table_name');
        }
    } catch (\Throwable $e) {
        $dbError = $e->getMessage();
    }

    return response()->json([
        'status' => $dbOk ? 'healthy' : 'degraded',
        'php_version' => PHP_VERSION,
        'app_key_configured' => !empty(config('app.key')),
        'db_connection' => config('database.default'),
        'db_connected' => $dbOk,
        'db_error' => $dbError,
        'sample_tables' => $sampleTables,
        'storage_writable' => is_writable(storage_path()),
        'storage_path' => storage_path(),
        'session_config' => [
            'driver' => config('session.driver'),
            'lifetime' => config('session.lifetime'),
            'expire_on_close' => config('session.expire_on_close'),
            'cookie' => config('session.cookie'),
            'domain' => config('session.domain'),
            'secure' => config('session.secure'),
            'http_only' => config('session.http_only'),
            'same_site' => config('session.same_site'),
        ],
        'env_session_lifetime' => env('SESSION_LIFETIME'),
        'env_session_expire_on_close' => env('SESSION_EXPIRE_ON_CLOSE'),
    ]);
})->withoutMiddleware([
    \Illuminate\Cookie\Middleware\EncryptCookies::class,
    \Illuminate\Session\Middleware\StartSession::class,
    \Illuminate\View\Middleware\ShareErrorsFromSession::class,
    \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
]);

Route::get('/', function () {
    return view('welcome');
});

// Public Certificate Verification
Route::get('/verify-certificate/{code}', [CertificateController::class, 'verify'])->name('certificates.verify');

// Public Google Authentication Mock
Route::get('/auth/google', [LoginController::class, 'redirectToGoogle'])->name('auth.google');
Route::post('/auth/google/email', [LoginController::class, 'submitGoogleEmail'])->name('auth.google.email');
Route::get('/auth/google/verify', [LoginController::class, 'showGoogleVerify'])->name('auth.google.verify');
Route::post('/auth/google/callback', [LoginController::class, 'handleGoogleCallback'])->name('auth.google.callback');
Route::get('/auth/google/password', [LoginController::class, 'showGooglePassword'])->name('auth.google.password');
Route::post('/auth/google/password', [LoginController::class, 'handleGooglePassword'])->name('auth.google.password.submit');
Route::post('/auth/google/forgot-password', [LoginController::class, 'sendGoogleResetLink'])->name('auth.google.forgot');

// Password Reset Routes
Route::get('/forgot-password', [LoginController::class, 'showForgotPasswordForm'])->name('password.request');
Route::post('/forgot-password', [LoginController::class, 'sendResetLinkEmail'])->name('password.email');
Route::get('/reset-password/{token}', [LoginController::class, 'showResetPasswordForm'])->name('password.reset');
Route::post('/reset-password', [LoginController::class, 'resetPassword'])->name('password.update');

// Public GitHub Authentication Mock (Local Fallback)
Route::get('/auth/github', [LoginController::class, 'redirectToGithub'])->name('auth.github');
Route::post('/auth/github/callback', [LoginController::class, 'handleGithubCallback'])->name('auth.github.callback');

// Production OAuth Routes (Laravel Socialite)
Route::get('/auth/{provider}/redirect', [LoginController::class, 'redirectToProvider'])->name('auth.provider.redirect');
Route::get('/auth/{provider}/callback', [LoginController::class, 'handleProviderCallback'])->name('auth.provider.callback');

// Authentication Routes
Route::get('/register', [RegisterController::class, 'show'])->name('register.show');
Route::post('/register', [RegisterController::class, 'store'])->name('register.store');

Route::get('/login', [LoginController::class, 'show'])->name('login');
Route::post('/login', [LoginController::class, 'store'])->name('login.store');

Route::post('/logout', LogoutController::class)->name('logout')->middleware('auth');

// Protected Routes
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    // Settings & Account preferences
    Route::get('/settings', [SettingsController::class, 'show'])->name('settings.show');
    Route::put('/settings/profile', [SettingsController::class, 'updateProfile'])->name('settings.profile.update');
    Route::put('/settings/password', [SettingsController::class, 'updatePassword'])->name('settings.password.update');
    Route::post('/settings/notifications', [SettingsController::class, 'updateNotifications'])->name('settings.notifications.update');
    Route::post('/settings/gmail/connect', [SettingsController::class, 'sendGmailCode'])->name('settings.gmail.connect');
    Route::post('/settings/gmail/verify', [SettingsController::class, 'verifyGmailCode'])->name('settings.gmail.verify');
    Route::post('/settings/gmail/disconnect', [SettingsController::class, 'disconnectGmail'])->name('settings.gmail.disconnect');
    Route::post('/settings/github/disconnect', [SettingsController::class, 'disconnectGithub'])->name('settings.github.disconnect');

    // Certificates
    Route::post('/classes/{class_id}/claim-certificate', [CertificateController::class, 'claim'])->name('classes.claim-certificate');
    Route::get('/certificates/{id}', [CertificateController::class, 'show'])->name('certificates.show');

    // Classes & Module management (NetAcad inspired)
    Route::resource('classes', ClassController::class);
    Route::get('/classes/{class_id}/telemetry', [ClassController::class, 'telemetry'])->name('classes.telemetry');
    Route::get('/sessions/{id}/telemetry-timeline', [ClassController::class, 'telemetryTimeline'])->name('sessions.telemetry-timeline');
    Route::post('/anomalies/{id}/resolve', [ClassController::class, 'resolveAnomaly'])->name('anomalies.resolve');
    Route::post('/classes/join', [ClassController::class, 'joinByCode'])->name('classes.join');
    Route::post('/classes/{id}/invite', [ClassController::class, 'inviteStudent'])->name('classes.invite');
    Route::post('/classes/{class_id}/invite-accept', [ClassController::class, 'acceptInvite'])->name('classes.invite-accept');
    
    // Modules routing
    Route::get('/classes/{class_id}/modules/create', [ClassController::class, 'createModule'])->name('modules.create');
    Route::post('/classes/{class_id}/modules', [ClassController::class, 'storeModule'])->name('modules.store');
    Route::get('/classes/{class_id}/modules/{module_id}', [ClassController::class, 'showModule'])->name('modules.show');
    Route::get('/classes/{class_id}/modules/{module_id}/edit', [ClassController::class, 'editModule'])->name('modules.edit');
    Route::put('/classes/{class_id}/modules/{module_id}', [ClassController::class, 'updateModule'])->name('modules.update');
    Route::delete('/classes/{class_id}/modules/{module_id}', [ClassController::class, 'destroyModule'])->name('modules.destroy');
    Route::get('/attachments/{id}/download', [ClassController::class, 'downloadAttachment'])->name('attachments.download');

    // Laboratory (Coding Challenge) CRUD & Workspace sessions
    Route::resource('laboratories', LaboratoryController::class)->except(['index', 'create']);
    Route::get('/classes/{class_id}/laboratories/create', [LaboratoryController::class, 'create'])->name('laboratories.create');
    Route::get('/laboratories/{id}/starter-files/download', [LaboratoryController::class, 'downloadStarterFiles'])->name('laboratories.starter-files.download');
    Route::post('/laboratories/{id}/start', [LaboratoryController::class, 'startSession'])->name('laboratories.start');
    Route::post('/sessions/{id}/complete', [LaboratoryController::class, 'completeSession'])->name('sessions.complete');

    // Live Lab Lifecycle Controls (Feature 9)
    Route::post('/laboratories/{id}/open-live', [LaboratoryController::class, 'openLive'])->name('laboratories.open-live');
    Route::post('/laboratories/{id}/end-live', [LaboratoryController::class, 'endLive'])->name('laboratories.end-live');
    Route::post('/laboratories/{id}/reopen-live', [LaboratoryController::class, 'reopenLive'])->name('laboratories.reopen-live');

    // Instructor Live Monitoring & Lifecycle Controls (Features 6 & 7)
    Route::post('/classes/{id}/end', [ClassController::class, 'endClass'])->name('classes.end');
    Route::post('/instructor/sessions/{id}/end', [ClassController::class, 'endSession'])->name('instructor.sessions.end');
    Route::post('/instructor/sessions/{id}/reopen', [ClassController::class, 'reopenSession'])->name('instructor.sessions.reopen');
    Route::get('/laboratories/{id}/monitoring', [InstructorMonitoringController::class, 'show'])->name('instructor.monitoring.show');
    Route::get('/laboratories/{id}/monitoring/data', [InstructorMonitoringController::class, 'streamData'])->name('instructor.monitoring.data');

    // Student Profiles & Directory CRUD
    Route::get('/students', [StudentProfileController::class, 'index'])->name('students.index');
    Route::get('/profiles/{id}/edit', [StudentProfileController::class, 'edit'])->name('profiles.edit');
    Route::put('/profiles/{id}', [StudentProfileController::class, 'update'])->name('profiles.update');
    Route::delete('/profiles/{id}', [StudentProfileController::class, 'destroy'])->name('profiles.destroy');

    // Global Search
    Route::get('/search', [SearchController::class, 'search'])->name('search');

    // Notifications
    Route::get('/notifications/fetch', function () {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();
        if (!$user) {
            return response()->json(['unreadCount' => 0, 'notifications' => []]);
        }

        $cacheKey = "user_notifs_{$user->id}";
        $data = \Illuminate\Support\Facades\Cache::remember($cacheKey, 15, function () use ($user) {
            $unreadCount = $user->unreadNotifications()->count();
            $notifications = $user->notifications()->take(5)->get()->map(function ($notif) {
                return [
                    'id' => $notif->id,
                    'unread' => $notif->unread(),
                    'url' => $notif->data['url'] ?? '#',
                    'title' => $notif->data['title'] ?? 'Notification',
                    'message' => $notif->data['message'] ?? '',
                    'type' => $notif->data['type'] ?? 'info',
                    'time' => $notif->created_at->diffForHumans(),
                ];
            });

            return [
                'unreadCount' => $unreadCount,
                'notifications' => $notifications,
            ];
        });

        return response()->json($data)
            ->header('Cache-Control', 'private, max-age=15');
    })->name('notifications.fetch');

    Route::post('/notifications/mark-as-read', function () {
        /** @var \App\Models\User|null $user */
        $user = auth()->user();
        if ($user) {
            $user->unreadNotifications->markAsRead();
            \Illuminate\Support\Facades\Cache::forget("user_notifs_{$user->id}");
        }
        return response()->json(['status' => 'success']);
    })->name('notifications.mark-as-read');
});

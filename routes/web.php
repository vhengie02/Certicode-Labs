<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\ClassController;
use App\Http\Controllers\LaboratoryController;
use App\Http\Controllers\StudentProfileController;

Route::get('/', function () {
    return view('welcome');
});

// Public Certificate Verification
Route::get('/verify-certificate/{code}', [\App\Http\Controllers\CertificateController::class, 'verify'])->name('certificates.verify');

// Public Google Authentication Mock
Route::get('/auth/google', [\App\Http\Controllers\Auth\LoginController::class, 'redirectToGoogle'])->name('auth.google');
Route::post('/auth/google/email', [\App\Http\Controllers\Auth\LoginController::class, 'submitGoogleEmail'])->name('auth.google.email');
Route::get('/auth/google/verify', [\App\Http\Controllers\Auth\LoginController::class, 'showGoogleVerify'])->name('auth.google.verify');
Route::post('/auth/google/callback', [\App\Http\Controllers\Auth\LoginController::class, 'handleGoogleCallback'])->name('auth.google.callback');

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
    Route::get('/settings', [\App\Http\Controllers\SettingsController::class, 'show'])->name('settings.show');
    Route::post('/settings/notifications', [\App\Http\Controllers\SettingsController::class, 'updateNotifications'])->name('settings.notifications.update');
    Route::post('/settings/gmail/connect', [\App\Http\Controllers\SettingsController::class, 'sendGmailCode'])->name('settings.gmail.connect');
    Route::post('/settings/gmail/verify', [\App\Http\Controllers\SettingsController::class, 'verifyGmailCode'])->name('settings.gmail.verify');
    Route::post('/settings/gmail/disconnect', [\App\Http\Controllers\SettingsController::class, 'disconnectGmail'])->name('settings.gmail.disconnect');

    // Certificates
    Route::post('/classes/{class_id}/claim-certificate', [\App\Http\Controllers\CertificateController::class, 'claim'])->name('classes.claim-certificate');
    Route::get('/certificates/{id}', [\App\Http\Controllers\CertificateController::class, 'show'])->name('certificates.show');

    // Classes & Module management (NetAcad inspired)
    Route::resource('classes', ClassController::class);
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
    Route::resource('laboratories', LaboratoryController::class)->except(['index']);
    Route::get('/classes/{class_id}/laboratories/create', [LaboratoryController::class, 'create'])->name('laboratories.create');
    Route::post('/laboratories/{id}/start', [LaboratoryController::class, 'startSession'])->name('laboratories.start');
    Route::get('/sessions/{id}', [LaboratoryController::class, 'showWorkspace'])->name('sessions.show');
    Route::post('/sessions/{id}/complete', [LaboratoryController::class, 'completeSession'])->name('sessions.complete');

    // Student Profiles & Directory CRUD
    Route::get('/students', [StudentProfileController::class, 'index'])->name('students.index');
    Route::get('/profiles/{id}/edit', [StudentProfileController::class, 'edit'])->name('profiles.edit');
    Route::put('/profiles/{id}', [StudentProfileController::class, 'update'])->name('profiles.update');
    Route::delete('/profiles/{id}', [StudentProfileController::class, 'destroy'])->name('profiles.destroy');

    // Notifications
    Route::post('/notifications/mark-as-read', function () {
        auth()->user()->unreadNotifications->markAsRead();
        return response()->json(['status' => 'success']);
    })->name('notifications.mark-as-read');
});

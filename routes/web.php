<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;

Route::get('/', function () {
    return view('welcome');
});

// Authentication Routes
Route::get('/register', [RegisterController::class, 'show'])->name('register.show');
Route::post('/register', [RegisterController::class, 'store'])->name('register.store');

Route::get('/login', [LoginController::class, 'show'])->name('login.show');
Route::post('/login', [LoginController::class, 'store'])->name('login.store');

Route::post('/logout', LogoutController::class)->name('logout')->middleware('auth');

// Dashboard (protected route)
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    // Laboratory CRUD resource
    Route::resource('laboratories', App\Http\Controllers\LaboratoryController::class);
    Route::post('/laboratories/{id}/start', [App\Http\Controllers\LaboratoryController::class, 'startSession'])->name('laboratories.start');
    Route::get('/sessions/{id}', [App\Http\Controllers\LaboratoryController::class, 'showWorkspace'])->name('sessions.show');

    // Student Profiles & Directory CRUD
    Route::get('/students', [App\Http\Controllers\StudentProfileController::class, 'index'])->name('students.index');
    Route::get('/profiles/{id}/edit', [App\Http\Controllers\StudentProfileController::class, 'edit'])->name('profiles.edit');
    Route::put('/profiles/{id}', [App\Http\Controllers\StudentProfileController::class, 'update'])->name('profiles.update');
    Route::delete('/profiles/{id}', [App\Http\Controllers\StudentProfileController::class, 'destroy'])->name('profiles.destroy');
});

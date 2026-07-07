<?php

use Illuminate\Support\Facades\Route;

use App\Http\Controllers\LoginController;


// Auth routes
Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('login', [LoginController::class, 'login']);
});

// Logout route
Route::post('logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');


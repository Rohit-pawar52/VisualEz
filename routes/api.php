<?php

use App\Http\Controllers\ProductSyncController;
use Illuminate\Support\Facades\Route;

Route::post('login', [ProductSyncController::class, 'login']);
Route::post('register', [ProductSyncController::class, 'register']);

Route::middleware(['auth:sanctum', 'user'])->group(function () {
    Route::post('sync-products', [ProductSyncController::class, 'syncProducts']);
    Route::get('products', [ProductSyncController::class, 'search']);
    Route::get('sync-dashboard', [ProductSyncController::class, 'dashboard']);
});

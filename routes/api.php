<?php

use App\Http\Controllers\ProductSyncController;
use Illuminate\Support\Facades\Route;

Route::middleware('api')->group(function () {
    Route::post('sync-products', [ProductSyncController::class, 'syncProducts']);
    Route::get('products', [ProductSyncController::class, 'search']);
    Route::get('sync-dashboard', [ProductSyncController::class, 'dashboard']);
});

<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PaymentManagement\OrderController;
use App\Http\Controllers\PaymentManagement\PayOSController;

Route::middleware('auth:sanctum')->group(function () {
    // Orders
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);

    // PayOS management
    Route::post('/payos/webhook/confirm', [PayOSController::class, 'confirmWebhook']);
});

// ─── PayOS callbacks (no auth – verified by SDK/webhook signature)
Route::get('/payos/return', [PayOSController::class, 'payosReturn']);
Route::post('/payos/webhook', [PayOSController::class, 'payosWebhook']);

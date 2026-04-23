<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AIManagement\CareerAdvisorController;
use App\Http\Controllers\AIManagement\AIChatController;
use App\Http\Controllers\AIManagement\AiManagementController;

Route::middleware('auth:sanctum')->group(function () {
    // ─── Career Advisor ───
    Route::get('/career/advisor', [CareerAdvisorController::class, 'index']);
    Route::post('/career/upload-cv', [CareerAdvisorController::class, 'uploadCV']);
    Route::post('/career/recommend', [CareerAdvisorController::class, 'recommend']);

    // ─── AI Chat ───
    Route::post('/ai/chat', [AIChatController::class, 'chat']);

    // ─── AI Management (Admin) ───
    Route::prefix('admin/ai')->group(function () {
        Route::get('/dashboard', [AiManagementController::class, 'dashboard']);
        Route::put('/settings', [AiManagementController::class, 'updateSettings']);
        Route::post('/reset-quota', [AiManagementController::class, 'resetQuota']);
        Route::get('/providers', [AiManagementController::class, 'providers']);
    });
});

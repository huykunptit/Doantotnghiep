<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AIManagement\CareerAdvisorController;
use App\Http\Controllers\AIManagement\AIChatController;
use App\Http\Controllers\AIManagement\AiManagementController;

// Chat công khai cho khách chưa đăng nhập (giới hạn tần suất).
Route::post('/ai/chat/guest', [AIChatController::class, 'guestChat'])
    ->middleware('throttle:20,1');
Route::post('/ai/chat/guest/stream', [AIChatController::class, 'guestChatStream'])
    ->middleware('throttle:20,1');

Route::middleware('auth:sanctum')->group(function () {
    // ─── Career Advisor ───
    Route::get('/career/advisor', [CareerAdvisorController::class, 'index']);
    Route::post('/career/upload-cv', [CareerAdvisorController::class, 'uploadCV']);
    Route::delete('/career/cv', [CareerAdvisorController::class, 'deleteCV']);
    Route::post('/career/cv-form', [CareerAdvisorController::class, 'saveForm']);
    Route::post('/career/evaluate', [CareerAdvisorController::class, 'evaluate']);
    Route::post('/career/recommend', [CareerAdvisorController::class, 'recommend']);

    // ─── AI Chat + tutoring ───
    Route::post('/ai/chat', [AIChatController::class, 'chat']);
    Route::post('/ai/chat/stream', [AIChatController::class, 'chatStream']);
    Route::post('/ai/tutoring', [AIChatController::class, 'tutoring']);
    Route::post('/ai/study-advisor', [AIChatController::class, 'studyAdvisorAdvice']);

    // ─── AI Management (Admin) ───
    Route::prefix('admin/ai')->middleware('role:admin')->group(function () {
        Route::get('/dashboard', [AiManagementController::class, 'dashboard']);
        Route::put('/settings', [AiManagementController::class, 'updateSettings']);
        Route::post('/reset-quota', [AiManagementController::class, 'resetQuota']);
        Route::get('/providers', [AiManagementController::class, 'providers']);
    });
});

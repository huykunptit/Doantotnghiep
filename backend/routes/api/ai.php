<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AIManagement\CareerAdvisorController;
use App\Http\Controllers\AIManagement\AIChatController;
use App\Http\Controllers\AIManagement\AiManagementController;
use App\Http\Controllers\AIManagement\RagController;
use App\Http\Controllers\AIManagement\LearningAdvisorController;

Route::middleware('auth:sanctum')->group(function () {
    // ─── Career Advisor ───
    Route::get('/career/advisor', [CareerAdvisorController::class, 'index']);
    Route::post('/career/upload-cv', [CareerAdvisorController::class, 'uploadCV']);
    Route::post('/career/recommend', [CareerAdvisorController::class, 'recommend']);

    // ─── AI Chat ───
    Route::post('/ai/chat', [AIChatController::class, 'chat']);

    // ─── RAG Query ───
    Route::post('/ai/rag/query', [RagController::class, 'query']);

    // ─── AI Learning Advisor ───
    Route::get('/ai/learning/analysis', [LearningAdvisorController::class, 'getAnalysis']);
    Route::post('/ai/learning/advise', [LearningAdvisorController::class, 'getAdvice']);

    // ─── AI Management (Admin) ───
    Route::prefix('admin/ai')->group(function () {
        Route::get('/dashboard', [AiManagementController::class, 'dashboard']);
        Route::put('/settings', [AiManagementController::class, 'updateSettings']);
        Route::post('/reset-quota', [AiManagementController::class, 'resetQuota']);
        Route::get('/providers', [AiManagementController::class, 'providers']);

        // RAG Management
        Route::prefix('rag')->group(function () {
            Route::post('/ingest/url', [RagController::class, 'ingestFromUrl']);
            Route::post('/ingest/upload', [RagController::class, 'ingestUpload']);
            Route::get('/collections', [RagController::class, 'collections']);
            Route::delete('/collections/{name}', [RagController::class, 'deleteCollection']);
        });
    });
});

<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\UserManagement\AuthController;
use App\Http\Controllers\UserManagement\AdminController;
use App\Http\Controllers\UserManagement\InstructorController;
use App\Http\Controllers\UserManagement\LessonProgressController;
use App\Http\Controllers\NotificationController;

// ─── Auth ───
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    Route::get('/google/url', [AuthController::class, 'googleLoginUrl']);
    Route::get('/google/redirect', [AuthController::class, 'redirectToGoogle']);
    Route::get('/google/callback', [AuthController::class, 'handleGoogleCallback']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
        Route::put('/change-password', [AuthController::class, 'changePassword']);
    });
});

// Public site settings (no auth)
Route::get('/site-settings', [AdminController::class, 'publicSiteSettings']);

// Backward-compatible aliases
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::get('/me', [AuthController::class, 'me'])->middleware('auth:sanctum');
Route::get('/user', fn (Request $request) => $request->user())->middleware('auth:sanctum');

Route::middleware('auth:sanctum')->group(function () {
    // ─── Lesson Progress & Enrollments ───
    Route::post('/courses/{course}/lessons/{lesson}/progress', [LessonProgressController::class, 'updateProgress']);
    Route::get('/courses/{course}/progress', [LessonProgressController::class, 'courseProgress']);
    Route::get('/enrollments', [LessonProgressController::class, 'enrollments']);

    // ─── Notifications ───
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::put('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
    Route::put('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);

    // ─── Instructor ───
    Route::get('/instructor/stats', [InstructorController::class, 'stats']);
    Route::get('/instructor/courses/{course}/students', [InstructorController::class, 'students']);
    Route::get('/instructor/courses/{course}/revenue', [InstructorController::class, 'revenue']);

    // ─── Admin ───
    Route::prefix('admin')->group(function () {
        Route::get('/stats', [AdminController::class, 'stats']);
        Route::post('/upload', [AdminController::class, 'uploadAsset']);

        // Users
        Route::get('/users', [AdminController::class, 'users']);
        Route::post('/users', [AdminController::class, 'storeUser']);
        Route::put('/users/{user}', [AdminController::class, 'updateUser']);
        Route::put('/users/{user}/role', [AdminController::class, 'updateUserRole']);
        Route::delete('/users/{user}', [AdminController::class, 'destroyUser']);

        // Courses
        Route::get('/courses', [AdminController::class, 'courses']);
        Route::get('/courses/{course}', [AdminController::class, 'showCourse']);
        Route::put('/courses/{course}/approve', [AdminController::class, 'approveCourse']);
        Route::put('/courses/{course}/reject', [AdminController::class, 'rejectCourse']);

        // Categories
        Route::get('/categories', [AdminController::class, 'categories']);
        Route::post('/categories', [AdminController::class, 'storeCategory']);
        Route::put('/categories/{category}', [AdminController::class, 'updateCategory']);
        Route::delete('/categories/{category}', [AdminController::class, 'destroyCategory']);

        // Orders
        Route::get('/orders', [AdminController::class, 'orders']);
        Route::get('/orders/{order}', [AdminController::class, 'showOrder']);

        // Reviews
        Route::get('/reviews', [AdminController::class, 'reviews']);
        Route::delete('/reviews/{review}', [AdminController::class, 'destroyReview']);

        // Site Settings
        Route::get('/settings', [AdminController::class, 'siteSettings']);
        Route::put('/settings', [AdminController::class, 'updateSiteSettings']);
    });
});

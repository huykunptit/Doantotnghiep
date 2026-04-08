<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\InstructorController;
use App\Http\Controllers\LessonController;
use App\Http\Controllers\LessonProgressController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SectionController;

// ─── Auth ────────────────────────────────────────────────────────────────────
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    Route::get('/google/redirect', [AuthController::class, 'redirectToGoogle']);
    Route::get('/google/callback', [AuthController::class, 'handleGoogleCallback']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
        Route::put('/change-password', [AuthController::class, 'changePassword']);
    });
});

// Backward-compatible aliases
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth:sanctum');
Route::get('/me', [AuthController::class, 'me'])->middleware('auth:sanctum');
Route::get('/user', fn (Request $request) => $request->user())->middleware('auth:sanctum');

// ─── Categories (public) ─────────────────────────────────────────────────────
Route::get('/categories', [CategoryController::class, 'index']);

// ─── Courses (public) ─────────────────────────────────────────────────────────
Route::get('/courses/categories', [CourseController::class, 'categories']);
Route::get('/courses', [CourseController::class, 'index']);
Route::get('/courses/{course}', [CourseController::class, 'show']);
Route::get('/courses/{course}/lessons', [LessonController::class, 'index']);
Route::get('/courses/{course}/reviews', [ReviewController::class, 'index']);

// ─── Protected routes ─────────────────────────────────────────────────────────
Route::middleware('auth:sanctum')->group(function () {

    // Courses (instructor/admin)
    Route::post('/courses', [CourseController::class, 'store']);
    Route::put('/courses/{course}', [CourseController::class, 'update']);
    Route::delete('/courses/{course}', [CourseController::class, 'destroy']);
    Route::post('/courses/{course}/publish', [CourseController::class, 'publish']);
    Route::get('/my-courses', [CourseController::class, 'myCoures']);

    // Sections (instructor/admin)
    Route::get('/courses/{course}/sections', [SectionController::class, 'index']);
    Route::post('/courses/{course}/sections', [SectionController::class, 'store']);
    Route::get('/sections/{section}', [SectionController::class, 'show']);
    Route::put('/sections/{section}', [SectionController::class, 'update']);
    Route::delete('/sections/{section}', [SectionController::class, 'destroy']);
    Route::post('/courses/{course}/sections/reorder', [SectionController::class, 'reorder']);

    // Lessons (instructor/admin)
    Route::post('/courses/{course}/lessons', [LessonController::class, 'store']);
    Route::get('/courses/{course}/lessons/{lesson}', [LessonController::class, 'show']);
    Route::put('/courses/{course}/lessons/{lesson}', [LessonController::class, 'update']);
    Route::delete('/courses/{course}/lessons/{lesson}', [LessonController::class, 'destroy']);
    Route::post('/courses/{course}/lessons/{lesson}/upload-video', [LessonController::class, 'uploadVideo']);
    Route::get('/courses/{course}/lessons/{lesson}/video-url', [LessonController::class, 'videoUrl']);
    Route::post('/courses/{course}/lessons/reorder', [LessonController::class, 'reorder']);

    // Progress
    Route::post('/courses/{course}/lessons/{lesson}/progress', [LessonProgressController::class, 'updateProgress']);
    Route::get('/courses/{course}/progress', [LessonProgressController::class, 'courseProgress']);
    Route::get('/enrollments', [LessonProgressController::class, 'enrollments']);

    // Reviews (authenticated)
    Route::post('/courses/{course}/reviews', [ReviewController::class, 'store']);
    Route::put('/courses/{course}/reviews/{review}', [ReviewController::class, 'update']);
    Route::delete('/courses/{course}/reviews/{review}', [ReviewController::class, 'destroy']);

    // Orders
    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);

    // ─── Instructor ──────────────────────────────────────────────────────────
    Route::get('/instructor/stats', [InstructorController::class, 'stats']);
    Route::get('/instructor/courses/{course}/students', [InstructorController::class, 'students']);
    Route::get('/instructor/courses/{course}/revenue', [InstructorController::class, 'revenue']);

    // ─── Admin ───────────────────────────────────────────────────────────────
    Route::prefix('admin')->group(function () {
        Route::get('/stats', [AdminController::class, 'stats']);

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
    });
});

// ─── VNPay callbacks (no auth – signed by VNPay) ─────────────────────────────
Route::get('/vnpay/return', [OrderController::class, 'vnpayReturn']);
Route::post('/vnpay/ipn', [OrderController::class, 'vnpayIpn']);

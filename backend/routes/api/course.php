<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CourseManagement\CategoryController;
use App\Http\Controllers\CourseManagement\CourseController;
use App\Http\Controllers\CourseManagement\SectionController;
use App\Http\Controllers\CourseManagement\LessonController;
use App\Http\Controllers\CourseManagement\LessonProgressController;
use App\Http\Controllers\CourseManagement\LessonNoteController;
use App\Http\Controllers\CourseManagement\LessonAttachmentController;
use App\Http\Controllers\CourseManagement\ReviewController;
use App\Http\Controllers\CourseManagement\VirtualClassController;
use App\Http\Controllers\CourseManagement\LessonOfflineSessionController;
use App\Http\Controllers\CourseManagement\AssignmentController;
use App\Http\Controllers\CourseManagement\ScormController;
use App\Http\Controllers\CourseManagement\ScormPackageController;
use App\Http\Controllers\CourseManagement\CourseQaController;

// ─── Categories (public)
Route::get('/categories', [CategoryController::class, 'index']);
Route::get('/categories/{category}', [CategoryController::class, 'show']);

// ─── Courses (public)
Route::get('/courses/categories', [CourseController::class, 'categories']);
Route::get('/courses', [CourseController::class, 'index']);
Route::get('/courses/{course}', [CourseController::class, 'show']);
Route::get('/courses/{course}/lessons', [LessonController::class, 'index']);
Route::get('/courses/{course}/lessons/{lesson}', [LessonController::class, 'show']);
Route::get('/courses/{course}/lessons/{lesson}/video-url', [LessonController::class, 'videoUrl']);
Route::get('/courses/{course}/reviews', [ReviewController::class, 'index']);

// ─── Protected Course Routes
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/my-courses', [CourseController::class, 'myCoures']);

    // Course write — manage_courses
    Route::middleware('permission:manage_courses')->group(function () {
        Route::post('/courses', [CourseController::class, 'store']);
        Route::put('/courses/{course}', [CourseController::class, 'update']);
        Route::delete('/courses/{course}', [CourseController::class, 'destroy']);
        Route::post('/courses/{course}/publish', [CourseController::class, 'publish']);

        Route::get('/courses/{course}/sections', [SectionController::class, 'index']);
        Route::post('/courses/{course}/sections', [SectionController::class, 'store']);
        Route::get('/sections/{section}', [SectionController::class, 'show']);
        Route::put('/sections/{section}', [SectionController::class, 'update']);
        Route::delete('/sections/{section}', [SectionController::class, 'destroy']);
        Route::post('/courses/{course}/sections/reorder', [SectionController::class, 'reorder']);
    });

    // Lesson write — manage_lessons|manage_courses
    Route::middleware('permission:manage_lessons|manage_courses')->group(function () {
        Route::post('/courses/{course}/lessons', [LessonController::class, 'store']);
        Route::put('/courses/{course}/lessons/{lesson}', [LessonController::class, 'update']);
        Route::delete('/courses/{course}/lessons/{lesson}', [LessonController::class, 'destroy']);
        Route::post('/courses/{course}/lessons/{lesson}/upload-video', [LessonController::class, 'uploadVideo']);
        Route::post('/courses/{course}/lessons/reorder', [LessonController::class, 'reorder']);

        Route::post('/courses/{course}/lessons/{lesson}/attachments', [LessonAttachmentController::class, 'store']);
        Route::delete('/courses/{course}/lessons/{lesson}/attachments/{attachment}', [LessonAttachmentController::class, 'destroy']);

        Route::post('/courses/{course}/lessons/{lesson}/virtual-class', [VirtualClassController::class, 'store']);
        Route::delete('/courses/{course}/lessons/{lesson}/virtual-class', [VirtualClassController::class, 'destroy']);

        Route::post('/courses/{course}/lessons/{lesson}/offline-session', [LessonOfflineSessionController::class, 'store']);
        Route::post('/courses/{course}/lessons/{lesson}/offline-session/qr', [LessonOfflineSessionController::class, 'generateQr']);
        Route::delete('/courses/{course}/lessons/{lesson}/offline-session', [LessonOfflineSessionController::class, 'destroy']);

        Route::post('/courses/{course}/lessons/{lesson}/scorm-package', [ScormPackageController::class, 'store']);
        Route::delete('/courses/{course}/lessons/{lesson}/scorm-package', [ScormPackageController::class, 'destroy']);

        Route::post('/courses/{course}/lessons/{lesson}/assignment', [AssignmentController::class, 'store']);
        Route::get('/courses/{course}/lessons/{lesson}/assignment/submissions', [AssignmentController::class, 'indexSubmissions']);
    });

    // Learning Experience (enrolled student / owner)
    Route::get('/courses/{course}/lessons/{lesson}/progress', [LessonProgressController::class, 'show']);
    Route::put('/courses/{course}/lessons/{lesson}/progress', [LessonProgressController::class, 'upsert']);
    Route::get('/courses/{course}/lessons/{lesson}/notes', [LessonNoteController::class, 'index']);
    Route::post('/courses/{course}/lessons/{lesson}/notes', [LessonNoteController::class, 'store']);
    Route::put('/courses/{course}/lessons/{lesson}/notes/{note}', [LessonNoteController::class, 'update']);
    Route::delete('/courses/{course}/lessons/{lesson}/notes/{note}', [LessonNoteController::class, 'destroy']);

    Route::get('/courses/{course}/lessons/{lesson}/attachments', [LessonAttachmentController::class, 'index']);
    Route::get('/courses/{course}/lessons/{lesson}/attachments/{attachment}/download', [LessonAttachmentController::class, 'download']);

    Route::get('/courses/{course}/lessons/{lesson}/virtual-class', [VirtualClassController::class, 'show']);
    Route::get('/courses/{course}/lessons/{lesson}/offline-session', [LessonOfflineSessionController::class, 'show']);
    Route::get('/courses/{course}/lessons/{lesson}/scorm-package', [ScormPackageController::class, 'show']);
    Route::get('/courses/{course}/lessons/{lesson}/assignment', [AssignmentController::class, 'show']);
    Route::post('/courses/{course}/lessons/{lesson}/assignment/submit', [AssignmentController::class, 'submit']);
    Route::post('/courses/{course}/lessons/{lesson}/scorm/track', [ScormController::class, 'track']);

    // Reviews
    Route::middleware('permission:submit_reviews')->group(function () {
        Route::post('/courses/{course}/reviews', [ReviewController::class, 'store']);
        Route::put('/courses/{course}/reviews/{review}', [ReviewController::class, 'update']);
        Route::delete('/courses/{course}/reviews/{review}', [ReviewController::class, 'destroy']);
    });

    // Q&A
    Route::get('/courses/{course}/qas', [CourseQaController::class, 'index']);
    Route::post('/courses/{course}/qas', [CourseQaController::class, 'store']);
    Route::get('/courses/{course}/qas/{qa}', [CourseQaController::class, 'show']);
    Route::post('/courses/{course}/qas/{qa}/replies', [CourseQaController::class, 'storeReply']);
    Route::post('/courses/{course}/qa-reactions', [CourseQaController::class, 'react']);
});

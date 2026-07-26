<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CourseManagement\CareerPathController;

// Public catalog
Route::get('/career-paths', [CareerPathController::class, 'publicIndex']);
Route::get('/career-paths/{slug}', [CareerPathController::class, 'publicShow']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/career-paths/{careerPath}/follow', [CareerPathController::class, 'follow']);
    Route::get('/me/career-paths', [CareerPathController::class, 'myPaths']);

    Route::prefix('admin')->middleware('role:admin')->group(function () {
        Route::get('/career-paths', [CareerPathController::class, 'adminIndex']);
        Route::post('/career-paths', [CareerPathController::class, 'adminStore']);
        Route::get('/career-paths/{careerPath}', [CareerPathController::class, 'adminShow']);
        Route::put('/career-paths/{careerPath}', [CareerPathController::class, 'adminUpdate']);
        Route::delete('/career-paths/{careerPath}', [CareerPathController::class, 'adminDestroy']);
        Route::put('/career-paths/{careerPath}/courses', [CareerPathController::class, 'syncCourses']);
        Route::delete('/career-paths/{careerPath}/courses/{pathCourse}', [CareerPathController::class, 'destroyCourse']);
    });
});

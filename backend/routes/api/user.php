<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Http\Request;
use App\Http\Controllers\UserManagement\AcademicManagementController;
use App\Http\Controllers\UserManagement\AdminClassSectionController;
use App\Http\Controllers\UserManagement\AdvisorController;
use App\Http\Controllers\UserManagement\AuthController;
use App\Http\Controllers\UserManagement\AdminController;
use App\Http\Controllers\UserManagement\CurriculumBuilderController;
use App\Http\Controllers\UserManagement\EnrollmentManagementController;
use App\Http\Controllers\UserManagement\GradebookController;
use App\Http\Controllers\UserManagement\InstructorController;
use App\Http\Controllers\UserManagement\OfflineSessionController;
use App\Http\Controllers\UserManagement\InstructorDashboardController;
use App\Http\Controllers\UserManagement\LessonProgressController;
use App\Http\Controllers\UserManagement\StudentDashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\CertificateController;
use App\Http\Controllers\PointsController;

// ─── Auth ───
Route::prefix('auth')->group(function () {
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('/reset-password', [AuthController::class, 'resetPassword']);
    Route::post('/resend-verification-email', [AuthController::class, 'resendVerificationEmail']);
    Route::get('/verify-email/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->middleware('signed')
        ->name('verification.verify');
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

// Public certificate verification
Route::get('/certificates/verify/{credentialId}', [CertificateController::class, 'showByCredential']);

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

    // ─── Certificates ───
    Route::get('/my-certificates', [CertificateController::class, 'myCertificates']);

    // ─── Notifications ───
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::get('/notifications/unread-count', [NotificationController::class, 'unreadCount']);
    Route::put('/notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
    Route::put('/notifications/read-all', [NotificationController::class, 'markAllAsRead']);

    // ─── Instructor (legacy + dashboard + gradebook) ───
    Route::get('/instructor/stats', [InstructorController::class, 'stats']);
    Route::get('/instructor/courses/{course}/students', [InstructorController::class, 'students']);
    Route::get('/instructor/courses/{course}/revenue', [InstructorController::class, 'revenue']);

    Route::get('/instructor/dashboard', [InstructorDashboardController::class, 'dashboard']);
    Route::get('/instructor/sections/{classSection}/grades', [GradebookController::class, 'show']);
    Route::put('/instructor/sections/{classSection}/grades', [GradebookController::class, 'update']);
    Route::get('/instructor/sections/{classSection}/grade-report', [GradebookController::class, 'sectionGpaReport']);
    Route::get('/instructor/courses/{course}/grade-components', [GradebookController::class, 'listComponents']);
    Route::put('/instructor/courses/{course}/grade-components', [GradebookController::class, 'upsertComponents']);
    Route::post('/instructor/courses/{course}/grade-components/preset', [GradebookController::class, 'presetComponents']);

    // ─── Offline Sessions ───
    Route::get('/instructor/sections/{classSection}/sessions', [OfflineSessionController::class, 'index']);
    Route::post('/instructor/sections/{classSection}/sessions', [OfflineSessionController::class, 'store']);
    Route::put('/instructor/sessions/{session}', [OfflineSessionController::class, 'update']);
    Route::delete('/instructor/sessions/{session}', [OfflineSessionController::class, 'destroy']);
    Route::post('/instructor/sessions/{session}/qr', [OfflineSessionController::class, 'generateQr']);
    Route::get('/instructor/sessions/{session}/attendance', [OfflineSessionController::class, 'attendanceReport']);
    Route::get('/instructor/sections/{classSection}/attendance-stats', [OfflineSessionController::class, 'sectionStats']);

    // ─── Advisor ───
    Route::get('/advisor/advisees', [AdvisorController::class, 'advisees']);
    Route::get('/advisor/at-risk', [AdvisorController::class, 'atRisk']);

    // ─── Student-facing /me ───
    Route::get('/me/dashboard', [StudentDashboardController::class, 'dashboard']);
    Route::get('/me/learning-path', [StudentDashboardController::class, 'learningPath']);
    Route::get('/me/transcript', [StudentDashboardController::class, 'transcript']);
    Route::get('/me/exams', [StudentDashboardController::class, 'exams']);
    Route::get('/me/tasks', [StudentDashboardController::class, 'tasks']);
    Route::get('/me/recommendations/extensions', [StudentDashboardController::class, 'recommendations']);
    Route::get('/me/attendance', [StudentDashboardController::class, 'attendanceHistory']);
    Route::post('/me/attendance/check-in', [StudentDashboardController::class, 'checkIn']);

    // ─── Admin ───
    Route::prefix('admin')->group(function () {
        Route::get('/stats', [AdminController::class, 'stats']);
        Route::get('/dashboard-extra', [AdminController::class, 'dashboardExtra']);
        Route::post('/upload', [AdminController::class, 'uploadAsset']);

        // Roles & Permissions
        Route::get('/roles', [AdminController::class, 'roles']);
        Route::put('/roles/{role}/permissions', [AdminController::class, 'updateRolePermissions']);

        // Users
        Route::get('/users', [AdminController::class, 'users']);
        Route::get('/users/export', [AdminController::class, 'exportUsers']);
        Route::get('/users/import-template', [AdminController::class, 'importUsersTemplate']);
        Route::post('/users/import-preview', [AdminController::class, 'importUsersPreview']);
        Route::post('/users/import-execute', [AdminController::class, 'importUsersExecute']);
        Route::post('/users/bulk-delete', [AdminController::class, 'bulkDestroyUsers']);
        Route::get('/students', [AdminController::class, 'listStudents']);
        Route::get('/instructors', [AdminController::class, 'listInstructors']);
        Route::post('/users', [AdminController::class, 'storeUser']);
        Route::get('/users/{user}/academic-summary', [AdminController::class, 'userAcademicSummary']);
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

        // Academic Enrollments & Class Sections
        Route::get('/academic/enrollments', [EnrollmentManagementController::class, 'index']);
        Route::get('/academic/class-sections', [EnrollmentManagementController::class, 'classSections']);
        Route::post('/academic/class-sections', [EnrollmentManagementController::class, 'storeClassSection']);
        Route::put('/academic/class-sections/{classSection}', [EnrollmentManagementController::class, 'updateClassSection']);
        Route::delete('/academic/class-sections/{classSection}', [EnrollmentManagementController::class, 'destroyClassSection']);
        Route::get('/academic/cohorts/{cohort}/students', [EnrollmentManagementController::class, 'students']);
        Route::get('/academic/lnd/reports/class-progress', [EnrollmentManagementController::class, 'classProgressReport']);
        Route::get('/academic/lnd/analytics/overview', [EnrollmentManagementController::class, 'analyticsOverview']);
        Route::get('/academic/lnd/analytics/enrollment-by-cohort', [EnrollmentManagementController::class, 'analyticsEnrollmentByCohort']);
        Route::get('/academic/lnd/analytics/at-risk', [EnrollmentManagementController::class, 'analyticsAtRisk']);
        Route::get('/academic/lnd/analytics/completion-rate', [EnrollmentManagementController::class, 'analyticsCompletionRate']);
        Route::post('/academic/cohorts/{cohort}/enroll-core', [EnrollmentManagementController::class, 'bulkEnrollCore']);
        Route::post('/academic/enrollments/manual', [EnrollmentManagementController::class, 'enrollManual']);
        Route::post('/academic/enrollments/import-preview', [EnrollmentManagementController::class, 'importPreview']);
        Route::post('/academic/enrollments/import-execute', [EnrollmentManagementController::class, 'importExecute']);
        Route::post('/academic/enrollments/delete', [EnrollmentManagementController::class, 'destroyEnrollment']);
        Route::post('/academic/enrollments/delete-import-preview', [EnrollmentManagementController::class, 'importDeletePreview']);
        Route::post('/academic/enrollments/delete-import-execute', [EnrollmentManagementController::class, 'importDeleteExecute']);
        Route::post('/academic/administrative-classes/enroll-students', [EnrollmentManagementController::class, 'enrollStudentsToAdminClass']);
        Route::post('/academic/administrative-classes/import-students-preview', [EnrollmentManagementController::class, 'importStudentsToAdminClassPreview']);
        Route::post('/academic/administrative-classes/import-students-execute', [EnrollmentManagementController::class, 'importStudentsToAdminClassExecute']);

        Route::get('/academic/admin-classes/{adminClass}/sections', [AdminClassSectionController::class, 'index']);
        Route::post('/academic/admin-classes/{adminClass}/sections', [AdminClassSectionController::class, 'attach']);
        Route::delete('/academic/admin-classes/{adminClass}/sections/{section}', [AdminClassSectionController::class, 'detach']);

        Route::get('/academic/curricula/{curriculum}/courses', [CurriculumBuilderController::class, 'index']);
        Route::post('/academic/curricula/{curriculum}/courses', [CurriculumBuilderController::class, 'bulkUpsert']);
        Route::delete('/academic/curricula/{curriculum}/courses/{curriculumCourse}', [CurriculumBuilderController::class, 'destroy']);

        // Academic Organization & Structure (generic CRUD per resource)
        Route::get('/academic/{resource}', [AcademicManagementController::class, 'index']);
        Route::post('/academic/{resource}', [AcademicManagementController::class, 'store']);
        Route::put('/academic/{resource}/{id}', [AcademicManagementController::class, 'update']);
        Route::delete('/academic/{resource}/{id}', [AcademicManagementController::class, 'destroy']);

        // Certificates
        Route::get('/certificates', [CertificateController::class, 'index']);
        Route::post('/certificates', [CertificateController::class, 'store']);
        Route::put('/certificates/{template}', [CertificateController::class, 'update']);
        Route::put('/certificates/{template}/fields', [CertificateController::class, 'updateFields']);
        Route::delete('/certificates/{template}', [CertificateController::class, 'destroy']);
    });

    // ── Points & Vouchers ──────────────────────────────────────────────────────
    Route::get('/points/summary', [PointsController::class, 'summary']);
    Route::get('/points/transactions', [PointsController::class, 'transactions']);
    Route::post('/points/daily-login', [PointsController::class, 'dailyLogin']);
    Route::get('/points/quests', [PointsController::class, 'quests']);
    Route::get('/points/leaderboard', [PointsController::class, 'leaderboard']);
    Route::get('/vouchers', [PointsController::class, 'shopIndex']);
    Route::post('/vouchers/{voucher}/redeem', [PointsController::class, 'redeem']);
    Route::get('/me/vouchers', [PointsController::class, 'myVouchers']);

    // ── Admin / Instructor: Voucher management + Points stats ──────────────
    Route::get('/admin/vouchers', [PointsController::class, 'adminVoucherIndex']);
    Route::post('/admin/vouchers', [PointsController::class, 'adminVoucherStore']);
    Route::put('/admin/vouchers/{voucher}', [PointsController::class, 'adminVoucherUpdate']);
    Route::delete('/admin/vouchers/{voucher}', [PointsController::class, 'adminVoucherDestroy']);
    Route::get('/admin/points/stats', [PointsController::class, 'adminStats']);
});

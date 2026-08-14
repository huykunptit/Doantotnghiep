<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\QuizManagement\ExamController;
use App\Http\Controllers\QuizManagement\ExamProctorController;
use App\Http\Controllers\QuizManagement\QuizController;
use App\Http\Controllers\QuizManagement\QuestionBankController;

Route::middleware('auth:sanctum')->group(function () {

    // Lesson quiz — read for learners; write needs manage_exams
    Route::get('/courses/{course}/lessons/{lesson}/quiz', [QuizController::class, 'show']);
    Route::post('/courses/{course}/lessons/{lesson}/quiz/{quiz}/submit', [QuizController::class, 'submit']);

    Route::middleware('permission:manage_exams')->group(function () {
        Route::post('/courses/{course}/lessons/{lesson}/quiz', [QuizController::class, 'storeOrUpdate']);

        Route::get('/admin/question-banks', [QuestionBankController::class, 'allBanks']);

        Route::get('/courses/{course}/question-banks', [QuestionBankController::class, 'index']);
        Route::post('/courses/{course}/question-banks', [QuestionBankController::class, 'store']);
        Route::get('/courses/{course}/question-banks/{bank}', [QuestionBankController::class, 'show']);
        Route::put('/courses/{course}/question-banks/{bank}', [QuestionBankController::class, 'update']);
        Route::delete('/courses/{course}/question-banks/{bank}', [QuestionBankController::class, 'destroy']);
        Route::post('/courses/{course}/question-groups', [QuestionBankController::class, 'storeGroup']);
        Route::put('/courses/{course}/question-groups/{group}', [QuestionBankController::class, 'updateGroup']);
        Route::delete('/courses/{course}/question-groups/{group}', [QuestionBankController::class, 'destroyGroup']);
        Route::post('/courses/{course}/question-banks/{bank}/questions', [QuestionBankController::class, 'storeQuestion']);
        Route::put('/courses/{course}/question-banks/{bank}/questions/{question}', [QuestionBankController::class, 'updateQuestion']);
        Route::delete('/courses/{course}/question-banks/{bank}/questions/{question}', [QuestionBankController::class, 'destroyQuestion']);
        Route::post('/courses/{course}/question-banks/{bank}/questions/{question}/attachments', [QuestionBankController::class, 'uploadAttachment']);
        Route::delete('/courses/{course}/question-banks/{bank}/questions/{question}/attachments/{attachment}', [QuestionBankController::class, 'destroyAttachment']);

        Route::get('/courses/{course}/exams', [ExamController::class, 'index']);
        Route::post('/courses/{course}/exams', [ExamController::class, 'store']);
        Route::get('/courses/{course}/exams/{exam}', [ExamController::class, 'show']);
        Route::put('/courses/{course}/exams/{exam}', [ExamController::class, 'update']);
        Route::delete('/courses/{course}/exams/{exam}', [ExamController::class, 'destroy']);
        Route::get('/courses/{course}/exams/{exam}/quiz', [QuizController::class, 'showExamQuiz']);
        Route::post('/courses/{course}/exams/{exam}/quiz', [QuizController::class, 'storeOrUpdateExamQuiz']);

        Route::get('/admin/exams', [ExamController::class, 'adminIndex']);
        Route::get('/exams/standalone', [ExamController::class, 'standaloneIndex']);
        Route::post('/exams/standalone', [ExamController::class, 'storeStandalone']);
        Route::get('/exams/{exam}/detail', [ExamController::class, 'showStandalone']);
        Route::put('/exams/{exam}', [ExamController::class, 'updateStandalone']);
        Route::delete('/exams/{exam}', [ExamController::class, 'destroyStandalone']);
        Route::get('/exams/{exam}/quiz', [QuizController::class, 'showStandaloneExamQuiz']);
        Route::post('/exams/{exam}/quiz', [QuizController::class, 'storeOrUpdateStandaloneExamQuiz']);

        Route::post('/exams/{exam}/enroll', [ExamController::class, 'enrollUsers']);
        Route::post('/exams/{exam}/enroll-class', [ExamController::class, 'enrollByAdminClass']);
        Route::delete('/exams/{exam}/unenroll/{targetUser}', [ExamController::class, 'unenrollUser']);
        Route::get('/exams/{exam}/enrolled-users', [ExamController::class, 'enrolledUsers']);
        Route::get('/exams/{exam}/results', [ExamController::class, 'results']);

        Route::post('/attempts/{attempt}/pause', [ExamProctorController::class, 'pauseAttempt']);
        Route::post('/attempts/{attempt}/resume', [ExamProctorController::class, 'resumeAttempt']);
        Route::post('/attempts/{attempt}/force-stop', [ExamProctorController::class, 'forceStopAttempt']);
        Route::post('/attempts/{attempt}/extend-time', [ExamProctorController::class, 'extendTime']);
        Route::get('/exams/{exam}/live-monitor', [ExamProctorController::class, 'liveMonitor']);
        Route::get('/exams/{exam}/print', [ExamController::class, 'printExam']);
    });

    // Student exam-taking
    Route::middleware('permission:take_exams')->group(function () {
        Route::get('/exams/{exam}/pre-check', [QuizController::class, 'examPreCheck']);
        Route::post('/exams/{exam}/verify-face', [ExamProctorController::class, 'verifyFace']);
        Route::get('/exams/{exam}/start', [QuizController::class, 'startExamQuiz']);
        Route::post('/exams/{exam}/submit', [QuizController::class, 'submitExam']);
        Route::get('/exams/{exam}/results/{attempt}', [QuizController::class, 'examResults']);
        Route::post('/attempts/{attempt}/auto-save', [ExamProctorController::class, 'autoSave']);
        Route::get('/attempts/{attempt}/status', [ExamProctorController::class, 'checkStatus']);
        Route::post('/attempts/{attempt}/violations', [ExamProctorController::class, 'logViolation'])->middleware('throttle:30,1');
    });
});

<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\QuizManagement\ExamController;
use App\Http\Controllers\QuizManagement\QuizController;
use App\Http\Controllers\QuizManagement\QuestionBankController;

Route::middleware('auth:sanctum')->group(function () {
    // Quizzes inside a lesson
    Route::get('/courses/{course}/lessons/{lesson}/quiz', [QuizController::class, 'show']);
    Route::post('/courses/{course}/lessons/{lesson}/quiz', [QuizController::class, 'storeOrUpdate']);
    Route::post('/courses/{course}/lessons/{lesson}/quiz/{quiz}/submit', [QuizController::class, 'submit']);

    // Question Bank (Instructor/Admin)
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

    // Independent exams inside a course
    Route::get('/courses/{course}/exams', [ExamController::class, 'index']);
    Route::post('/courses/{course}/exams', [ExamController::class, 'store']);
    Route::get('/courses/{course}/exams/{exam}', [ExamController::class, 'show']);
    Route::put('/courses/{course}/exams/{exam}', [ExamController::class, 'update']);
    Route::delete('/courses/{course}/exams/{exam}', [ExamController::class, 'destroy']);
    Route::get('/courses/{course}/exams/{exam}/quiz', [QuizController::class, 'showExamQuiz']);
    Route::post('/courses/{course}/exams/{exam}/quiz', [QuizController::class, 'storeOrUpdateExamQuiz']);
});

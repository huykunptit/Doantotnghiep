<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| The monolithic routes have been broken down into specific modular domains.
| Each module defines its own subset of endpoints.
|
*/

// Load Modular Routes
require __DIR__ . '/api/user.php';      // Auth, Admin, Instructor, Progress
require __DIR__ . '/api/course.php';    // Courses, Sections, Lessons, Interactions
require __DIR__ . '/api/quiz.php';      // Quizzes, Banks, Submissions
require __DIR__ . '/api/payment.php';   // Orders, VNPay Callbacks
require __DIR__ . '/api/ai.php';        // AI Chat, Career Advisor

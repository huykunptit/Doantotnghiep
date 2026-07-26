<?php

namespace App\Http\Controllers\UserManagement;

use App\Helpers\GpaCalculator;
use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Term;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Student-facing endpoints:
 *  GET /me/transcript                       — full grade history grouped by term
 *  GET /me/dashboard                        — current-term snapshot
 *  GET /me/recommendations/extensions       — extension marketplace suggestions
 */
class StudentDashboardController extends Controller
{
    /**
     * BẢNG ĐIỂM — chỉ tổng hợp KẾT QUẢ THI trên LMS (không dùng gradebook trọng số).
     * Mỗi dòng = 1 kỳ thi (Exam) mà SV được ghi danh + điểm bài làm tốt nhất.
     */
    public function transcript(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthorized'], 401);

        $examEnrollments = \App\Models\ExamEnrollment::query()
            ->where('user_id', $user->id)
            ->with([
                'exam:id,title,type,pass_score,starts_at,ends_at,course_id',
                'exam.course:id,title,credit_value',
                'exam.quiz:id,exam_id',
            ])
            ->get();

        $results = [];
        $scores = [];
        $passedCount = 0;

        foreach ($examEnrollments as $enrollment) {
            $exam = $enrollment->exam;
            if (!$exam) continue;

            $quiz = $exam->quiz;
            $bestAttempt = null;
            if ($quiz) {
                $bestAttempt = \App\Models\QuizAttempt::query()
                    ->where('user_id', $user->id)
                    ->where('quiz_id', $quiz->id)
                    ->whereIn('status', ['submitted', 'force_stopped'])
                    ->orderByDesc('score')
                    ->first();
            }

            $score = $bestAttempt?->score !== null ? (float) $bestAttempt->score : null;
            $passed = $bestAttempt ? (bool) $bestAttempt->passed : null;

            if ($score !== null) {
                $scores[] = $score;
                if ($passed) $passedCount++;
            }

            $results[] = [
                'exam_id'      => $exam->id,
                'exam_title'   => $exam->title,
                'exam_type'    => $exam->type,
                'course'       => $exam->course,
                'credit_value' => (int) ($exam->course->credit_value ?? 0),
                'pass_score'   => $exam->pass_score,
                'score'        => $score,
                'passed'       => $passed,
                'taken_at'     => $bestAttempt?->completed_at?->toIso8601String(),
                'exam_date'    => $exam->starts_at?->toIso8601String(),
            ];
        }

        // Sắp xếp theo ngày thi
        usort($results, fn ($a, $b) => strcmp((string) $a['exam_date'], (string) $b['exam_date']));

        $average = count($scores) > 0 ? round(array_sum($scores) / count($scores), 2) : null;

        // Bảng điểm ở trên chỉ tổng hợp kết quả thi (average_score), chưa phải GPA thật sự.
        // Bổ sung GPA tích lũy theo tín chỉ, chỉ tính trên các ghi danh học vụ (CTĐT) —
        // loại bỏ enrollment_source = 'marketplace' và các khóa không tính tín chỉ.
        $academicGpaCourses = Enrollment::where('user_id', $user->id)
            ->where('enrollment_source', '!=', 'marketplace')
            ->with('course:id,credit_value,is_credit_bearing')
            ->get()
            ->filter(fn ($e) => (bool) $e->course?->is_credit_bearing)
            ->map(fn ($e) => [
                'final_score'  => $e->final_score,
                'credit_value' => (int) ($e->course->credit_value ?? 0),
            ])
            ->all();

        $creditWeightedGpa = GpaCalculator::cumulativeGpa($academicGpaCourses);

        return response()->json([
            'student'     => $user->only(['id', 'name', 'email', 'student_code', 'cohort_id', 'major_id', 'program_id']),
            'results'     => $results,
            'summary'     => [
                'total_exams'        => count($results),
                'taken'              => count($scores),
                'passed'             => $passedCount,
                'average_score'      => $average,
                'credit_weighted_gpa' => $creditWeightedGpa,
            ],
        ]);
    }

    /**
     * THỜI KHÓA BIỂU — theo lớp hành chính của SV (cả lớp cùng lịch) + lịch thi sắp tới.
     */
    public function timetable(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthorized'], 401);

        $currentTerm = Term::where('is_current', true)->latest('id')->first()
            ?? Term::latest('id')->first();

        $schedules = collect();
        if ($user->administrative_class_id) {
            $query = \App\Models\ClassSchedule::query()
                ->where('administrative_class_id', $user->administrative_class_id)
                ->with(['course:id,title', 'lecturer:id,name']);

            if ($currentTerm) {
                $query->where(function ($w) use ($currentTerm) {
                    $w->where('term_id', $currentTerm->id)->orWhereNull('term_id');
                });
            }

            $schedules = $query->orderBy('weekday')->orderBy('start_time')->get()->map(fn ($s) => [
                'id'         => $s->id,
                'weekday'    => $s->weekday,
                'start_time' => substr((string) $s->start_time, 0, 5),
                'end_time'   => substr((string) $s->end_time, 0, 5),
                'room'       => $s->room,
                'course'     => $s->course,
                'lecturer'   => $s->lecturer,
            ])->values();
        }

        $exams = \App\Models\ExamEnrollment::query()
            ->where('user_id', $user->id)
            ->with('exam:id,title,starts_at,ends_at,duration,room')
            ->get()
            ->map(fn ($e) => $e->exam)
            ->filter()
            ->map(fn ($ex) => [
                'id'        => $ex->id,
                'title'     => $ex->title,
                'starts_at' => $ex->starts_at?->toIso8601String(),
                'ends_at'   => $ex->ends_at?->toIso8601String(),
                'duration'  => $ex->duration,
                'room'      => $ex->room,
            ])
            ->values();

        return response()->json([
            'current_term' => $currentTerm,
            'schedules'    => $schedules,
            'exams'        => $exams,
        ]);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthorized'], 401);

        $currentTerm = Term::where('is_current', true)->latest('id')->first()
            ?? Term::latest('id')->first();

        $currentEnrollments = collect();
        if ($currentTerm) {
            $currentEnrollments = Enrollment::where('user_id', $user->id)
                ->where('term_id', $currentTerm->id)
                ->with(['course:id,title,course_mode,credit_value', 'classSection:id,code,lecturer_id', 'classSection.lecturer:id,name'])
                ->get();
        }

        $totalEnrollments = Enrollment::where('user_id', $user->id)->count();
        $completedCourses = Enrollment::where('user_id', $user->id)
            ->where('enrollment_source', 'academic')
            ->whereHas('gradeEntries')
            ->count();

        $user->load([
            'administrativeClass.advisor:id,name,email,avatar',
            'administrativeClass.students:id,name,email,avatar,student_code',
            'cohort:id,name,code',
            'major:id,name,code',
            'program:id,name,code',
        ]);

        return response()->json([
            'student' => $user,
            'current_term' => $currentTerm,
            'current_enrollments' => $currentEnrollments,
            'totals' => [
                'enrollments' => $totalEnrollments,
                'in_progress' => $currentEnrollments->count(),
                'completed' => $completedCourses,
            ],
        ]);
    }

    public function tasks(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthorized'], 401);

        $enrollments = $user->enrollments()
            ->with([
                'course:id,title,thumbnail',
                'course.sections.lessons:id,title,section_id,lesson_type',
                'course.sections.lessons.assignment:id,lesson_id,title,due_date',
            ])
            ->get();

        $result = $enrollments->map(function ($enrollment) use ($user) {
            $tasks = collect();

            foreach ($enrollment->course->sections ?? [] as $section) {
                foreach ($section->lessons ?? [] as $lesson) {
                    if ($lesson->assignment) {
                        $progress = $lesson->progress()
                            ->where('user_id', $user->id)
                            ->first();

                        $tasks->push([
                            'id'           => $lesson->assignment->id,
                            'title'        => $lesson->assignment->title ?? $lesson->title,
                            'type'         => $lesson->lesson_type ?? 'assignment',
                            'deadline'     => $lesson->assignment->due_date,
                            'completed_at' => $progress?->completed_at,
                            'is_completed' => (bool) ($progress?->completed_at),
                        ]);
                    }
                }
            }

            return [
                'course_id'    => $enrollment->course->id,
                'course_title' => $enrollment->course->title,
                'thumbnail'    => $enrollment->course->thumbnail,
                'tasks'        => $tasks->values(),
            ];
        })->filter(fn($g) => $g['tasks']->isNotEmpty())->values();

        return response()->json($result);
    }

    public function recommendations(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthorized'], 401);

        $result = app(\App\Services\RecommendationService::class)->recommend($user);

        // Backward-compatible shape for existing CourseRecommendations.vue
        return response()->json([
            'recommendations' => $result['courses'],
            'paths' => $result['paths'],
            'context' => $result['context'],
        ]);
    }

    public function learnerProfile(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthorized'], 401);

        return response()->json(
            app(\App\Services\LearnerProfileService::class)->build($user, useCache: false)
        );
    }

    public function curriculumEvaluation(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthorized'], 401);

        return response()->json(
            app(\App\Services\CurriculumEvaluationService::class)->evaluate($user)
        );
    }

    public function learningPath(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthorized'], 401);

        $user->load('administrativeClass');
        $adminClass = $user->administrativeClass;

        if (!$adminClass || !$adminClass->curriculum_id) {
            return response()->json([
                'has_curriculum' => false,
                'message' => 'Lớp hành chính của bạn chưa được gán chương trình đào tạo.',
                'terms' => []
            ]);
        }

        $curriculum = \App\Models\Curriculum::with([
            'curriculumCourses.course:id,title,credit_value,is_credit_bearing,course_mode,slug,thumbnail'
        ])->find($adminClass->curriculum_id);

        if (!$curriculum) {
            return response()->json([
                'has_curriculum' => false,
                'message' => 'Không tìm thấy chương trình đào tạo tương ứng.',
                'terms' => []
            ]);
        }

        // Get student enrollments for these courses
        $enrollments = Enrollment::where('user_id', $user->id)
            ->get()
            ->keyBy('course_id');

        $curriculumCourses = $curriculum->curriculumCourses;
        $totalCreditsRequired = 0;
        $totalCreditsEarned = 0;

        $termsData = [];
        for ($i = 1; $i <= 8; $i++) {
            $termsData[$i] = [
                'term_number' => $i,
                'courses' => [],
                'credits' => 0
            ];
        }

        foreach ($curriculumCourses as $cc) {
            $course = $cc->course;
            if (!$course) continue;

            $enrollment = $enrollments->get($course->id);
            $status = 'not_started';
            $progress = 0;
            $finalScore = null;

            if ($enrollment) {
                $progress = $enrollment->progress ?? 0;
                $finalScore = $enrollment->final_score;
                if ($progress >= 100) {
                    $status = 'completed';
                    if ($course->is_credit_bearing) {
                        $totalCreditsEarned += ($course->credit_value ?? $cc->credits ?? 0);
                    }
                } else {
                    $status = 'learning';
                }
            }

            $creditsVal = ($course->credit_value ?? $cc->credits ?? 0);
            if ($course->is_credit_bearing) {
                $totalCreditsRequired += $creditsVal;
            }

            $termNum = $cc->term_number;
            if ($termNum < 1 || $termNum > 8) {
                continue;
            }

            $termsData[$termNum]['courses'][] = [
                'id' => $course->id,
                'title' => $course->title,
                'slug' => $course->slug,
                'thumbnail' => $course->thumbnail,
                'credits' => $creditsVal,
                'is_required' => $cc->is_required,
                'course_mode' => $course->course_mode,
                'status' => $status,
                'progress' => $progress,
                'final_score' => $finalScore,
            ];
            $termsData[$termNum]['credits'] += $creditsVal;
        }

        return response()->json([
            'has_curriculum' => true,
            'curriculum_name' => $curriculum->name,
            'curriculum_code' => $curriculum->code,
            'total_credits_required' => $totalCreditsRequired,
            'total_credits_earned' => $totalCreditsEarned,
            'terms' => array_values($termsData)
        ]);
    }

    public function exams(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthorized'], 401);

        // Fetch ExamEnrollment for this student
        $enrollments = \App\Models\ExamEnrollment::where('user_id', $user->id)
            ->with([
                'exam:id,title,description,type,status,duration,pass_score,starts_at,ends_at,room,proctoring_enabled'
            ])
            ->get();

        $examsData = [];
        foreach ($enrollments as $enrollment) {
            $exam = $enrollment->exam;
            if (!$exam) continue;

            // Fetch the quiz associated with this exam
            $quiz = \App\Models\Quiz::where('exam_id', $exam->id)->first();
            
            $attemptsCount = 0;
            $bestScore = null;
            $attemptId = null;
            $status = 'scheduled'; // scheduled, active, closed, completed

            if ($quiz) {
                // Fetch attempts by user on this quiz
                $attempts = \App\Models\QuizAttempt::where('user_id', $user->id)
                    ->where('quiz_id', $quiz->id)
                    ->get();
                
                $attemptsCount = $attempts->count();
                if ($attemptsCount > 0) {
                    $bestScore = $attempts->max('score');
                    $latestAttempt = $attempts->sortByDesc('created_at')->first();
                    $attemptId = $latestAttempt ? $latestAttempt->id : null;
                    $status = 'completed';
                }
            }

            $now = now();
            if ($status !== 'completed') {
                if ($exam->starts_at && $now->lt($exam->starts_at)) {
                    $status = 'scheduled';
                } elseif ($exam->ends_at && $now->gt($exam->ends_at)) {
                    $status = 'closed';
                } else {
                    $status = 'active';
                }
            }

            $isOpen = ($status === 'active' && $exam->status !== 'closed' && $exam->status !== 'draft');

            $examsData[] = [
                'id' => $exam->id,
                'title' => $exam->title,
                'description' => $exam->description,
                'type' => $exam->type,
                'status' => $status,
                'is_open' => $isOpen,
                'duration' => $exam->duration,
                'pass_score' => $exam->pass_score,
                'starts_at' => $exam->starts_at ? $exam->starts_at->toIso8601String() : null,
                'ends_at' => $exam->ends_at ? $exam->ends_at->toIso8601String() : null,
                'room' => $exam->room,
                'proctoring_enabled' => $exam->proctoring_enabled,
                'attempts_count' => $attemptsCount,
                'best_score' => $bestScore,
                'attempt_id' => $attemptId,
                'quiz_id' => $quiz ? $quiz->id : null,
            ];
        }

        return response()->json([
            'exams' => $examsData
        ]);
    }

    public function checkIn(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthorized'], 401);

        $request->validate([
            'qr_token'   => 'required|string',
            'latitude'   => 'required|numeric|between:-90,90',
            'longitude'  => 'required|numeric|between:-180,180',
            'device_info' => 'nullable|string',
        ]);

        $rawToken = trim((string) $request->qr_token);
        // Accept either raw token or QR JSON payload {"type":"sylva_attendance","token":"..."}
        if (str_starts_with($rawToken, '{')) {
            $decoded = json_decode($rawToken, true);
            if (is_array($decoded) && !empty($decoded['token'])) {
                $rawToken = (string) $decoded['token'];
            }
        }

        $session = \App\Models\OfflineSession::where('qr_token', $rawToken)
            ->with(['lesson.section.course', 'classSection.course'])
            ->first();

        if (!$session) {
            return response()->json(['message' => 'Mã QR không hợp lệ.'], 404);
        }

        if (!$session->isQrValid()) {
            return response()->json(['message' => 'Mã QR đã hết hạn. Vui lòng yêu cầu giảng viên làm mới.'], 422);
        }

        if (!$session->is_active) {
            return response()->json(['message' => 'Phiên điểm danh chưa được mở.'], 422);
        }

        // Validate distance within configured geofence (default 15m)
        $radius = $session->checkInRadiusMeters();
        if ($session->latitude !== null && $session->longitude !== null) {
            $distance = \App\Helpers\GpaCalculator::distanceMeters(
                (float) $request->latitude,
                (float) $request->longitude,
                (float) $session->latitude,
                (float) $session->longitude
            );

            if ($distance > $radius) {
                return response()->json([
                    'message'          => "Bạn đang cách vị trí lớp học ".round($distance, 1)."m. Cần ở trong phạm vi {$radius}m.",
                    'distance_meters'  => round($distance, 1),
                    'allowed_radius_meters' => $radius,
                ], 422);
            }
        } else {
            $distance = null;
        }

        // Verify enrollment via class section
        $enrolled = false;
        if ($session->classSection) {
            $enrolled = \App\Models\Enrollment::where('user_id', $user->id)
                ->where('class_section_id', $session->class_section_id)
                ->exists();
        }
        if (!$enrolled && $session->lesson) {
            $course = $session->lesson->section?->course;
            if ($course) {
                $enrolled = \App\Models\Enrollment::where('user_id', $user->id)
                    ->where('course_id', $course->id)
                    ->exists();
            }
        }
        if (!$enrolled) {
            return response()->json(['message' => 'Bạn không thuộc lớp học này.'], 403);
        }

        $now     = now();
        $status  = $now->gt((clone $session->start_at)->addMinutes(15)) ? 'late' : 'present';

        $attendance = \App\Models\OfflineSessionAttendance::updateOrCreate(
            ['user_id' => $user->id, 'offline_session_id' => $session->id],
            [
                'status'          => $status,
                'checked_in_at'   => $now,
                'device_info'     => $request->input('device_info'),
                'latitude'        => $request->latitude,
                'longitude'       => $request->longitude,
                'distance_meters' => $distance !== null ? round($distance, 2) : null,
            ]
        );

        $courseTitle = $session->classSection?->course?->title
            ?? $session->lesson?->section?->course?->title
            ?? null;

        return response()->json([
            'message'    => 'Điểm danh thành công!',
            'attendance' => [
                'id'             => $attendance->id,
                'status'         => $attendance->status,
                'checked_in_at'  => $attendance->checked_in_at->toIso8601String(),
                'distance_meters' => $attendance->distance_meters,
                'offline_session' => [
                    'id'       => $session->id,
                    'title'    => $session->title,
                    'location' => $session->location,
                    'start_at' => $session->start_at->toIso8601String(),
                    'lesson_title' => $session->title,
                    'course_title' => $courseTitle,
                ],
            ],
        ]);
    }

    public function attendanceHistory(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthorized'], 401);

        $attendances = \App\Models\OfflineSessionAttendance::where('user_id', $user->id)
            ->with(['offlineSession.lesson.section.course'])
            ->orderBy('checked_in_at', 'desc')
            ->get();

        $history = $attendances->map(function ($att) {
            $session = $att->offlineSession;
            $lesson = $session?->lesson;
            $course = $lesson?->section?->course;

            return [
                'id' => $att->id,
                'status' => $att->status,
                'checked_in_at' => $att->checked_in_at ? $att->checked_in_at->toIso8601String() : null,
                'device_info' => $att->device_info,
                'offline_session' => $session ? [
                    'id' => $session->id,
                    'title' => $session->title,
                    'location' => $session->location,
                    'start_at' => $session->start_at ? $session->start_at->toIso8601String() : null,
                    'duration' => $session->duration,
                    'lesson_title' => $lesson?->title ?? $session->title,
                    'course_title' => $course?->title,
                ] : null
            ];
        });

        return response()->json([
            'history' => $history
        ]);
    }
}

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
    public function transcript(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthorized'], 401);

        $enrollments = Enrollment::query()
            ->where('user_id', $user->id)
            ->with([
                'course:id,title,course_mode,credit_value,is_credit_bearing',
                'term:id,name,code,start_date,end_date,academic_year_id',
                'classSection:id,code,lecturer_id',
                'classSection.lecturer:id,name',
                'gradeEntries.component:id,name,weight,max_score',
            ])
            ->orderBy('term_id')
            ->orderBy('id')
            ->get();

        $byTerm = $enrollments->groupBy(fn ($e) => $e->term_id ?? 0);

        $allCoursesForGpa = [];

        $terms = $byTerm->map(function (Collection $items, $termId) use (&$allCoursesForGpa) {
            $term = $items->first()->term;
            $courses = $items->map(function (Enrollment $enrollment) {
                $finalScore = $enrollment->final_score;
                $gradeInfo  = $finalScore !== null
                    ? GpaCalculator::gradeInfo((float) $finalScore)
                    : ['letter' => null, 'gpa4' => null];

                return [
                    'enrollment_id'    => $enrollment->id,
                    'course'           => $enrollment->course,
                    'class_section'    => $enrollment->classSection,
                    'enrollment_source' => $enrollment->enrollment_source,
                    'final_score'      => $finalScore,
                    'letter_grade'     => $gradeInfo['letter'],
                    'gpa4'             => $gradeInfo['gpa4'],
                    'credit_value'     => (int) ($enrollment->course->credit_value ?? 0),
                    'entries'          => $enrollment->gradeEntries->map(fn ($entry) => [
                        'component' => $entry->component?->name,
                        'weight'    => $entry->component?->weight,
                        'max_score' => $entry->component?->max_score,
                        'score'     => $entry->score,
                    ])->values(),
                ];
            })->values();

            foreach ($courses as $c) {
                $allCoursesForGpa[] = [
                    'final_score'  => $c['final_score'],
                    'credit_value' => $c['credit_value'],
                ];
            }

            $termGpa = GpaCalculator::cumulativeGpa($courses->map(fn ($c) => [
                'final_score'  => $c['final_score'],
                'credit_value' => $c['credit_value'],
            ])->all());

            return [
                'term'    => $term,
                'courses' => $courses,
                'term_gpa' => $termGpa,
                'credits' => $items->sum(fn ($e) => (int) ($e->course->credit_value ?? 0)),
            ];
        })->values();

        $overallGpa = GpaCalculator::cumulativeGpa($allCoursesForGpa);

        return response()->json([
            'student'     => $user->only(['id', 'name', 'email', 'student_code', 'cohort_id', 'major_id', 'program_id']),
            'terms'       => $terms,
            'overall_gpa' => $overallGpa,
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

        $enrolledIds = Enrollment::where('user_id', $user->id)->pluck('course_id')->all();

        // Pull extension courses, score by program/major match + skill overlap.
        $skillIdsForUser = collect();
        if ($user->major_id) {
            // Use core courses of user's program/major as proxy for "what they've studied".
            $coreCourses = Course::query()
                ->where('course_mode', 'core')
                ->where(function ($q) use ($user) {
                    $q->where('program_id', $user->program_id)
                      ->orWhereNull('program_id');
                })
                ->with('skills:id')
                ->get();
            $skillIdsForUser = $coreCourses->flatMap->skills->pluck('id')->unique();
        }

        $candidates = Course::query()
            ->where('course_mode', 'extension')
            ->where('status', 'published')
            ->whereNotIn('id', $enrolledIds)
            ->with(['skills:id,name', 'category:id,name,slug', 'instructor:id,name,avatar'])
            ->limit(50)
            ->get();

        $scored = $candidates->map(function (Course $course) use ($skillIdsForUser, $user) {
            $courseSkillIds = $course->skills->pluck('id');
            $overlap = $skillIdsForUser->intersect($courseSkillIds)->count();
            $score = $overlap * 30;

            // Tiny bonus if instructor is in same unit as student's major.
            if ($user->major_id && $course->major_id && $course->major_id === $user->major_id) {
                $score += 25;
            } elseif ($user->program_id && $course->program_id === $user->program_id) {
                $score += 15;
            }

            // Cheap recency boost.
            if ($course->published_at) {
                $daysOld = max(1, now()->diffInDays($course->published_at));
                $score += max(0, 30 - min(30, $daysOld));
            }

            return [
                'course' => $course,
                'score' => $score,
                'matched_skills' => $course->skills->whereIn('id', $skillIdsForUser->all())->pluck('name')->values(),
            ];
        })
        ->sortByDesc('score')
        ->take(8)
        ->values();

        return response()->json([
            'recommendations' => $scored,
            'context' => [
                'program_id' => $user->program_id,
                'major_id' => $user->major_id,
                'skill_pool_size' => $skillIdsForUser->count(),
            ],
        ]);
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
                'exam:id,title,description,type,status,duration,pass_score,starts_at,ends_at,proctoring_enabled'
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

        $session = \App\Models\OfflineSession::where('qr_token', $request->qr_token)
            ->with('lesson.section.course', 'classSection')
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

        // Validate distance ≤ 10m
        if ($session->latitude && $session->longitude) {
            $distance = \App\Helpers\GpaCalculator::distanceMeters(
                (float) $request->latitude,
                (float) $request->longitude,
                $session->latitude,
                $session->longitude
            );

            if ($distance > 10) {
                return response()->json([
                    'message'          => "Bạn đang cách vị trí lớp học {$distance}m. Cần ở trong phạm vi 10m.",
                    'distance_meters'  => round($distance, 1),
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
                    'location' => $session->location,
                    'start_at' => $session->start_at ? $session->start_at->toIso8601String() : null,
                    'duration' => $session->duration,
                    'lesson_title' => $lesson?->title,
                    'course_title' => $course?->title,
                ] : null
            ];
        });

        return response()->json([
            'history' => $history
        ]);
    }
}

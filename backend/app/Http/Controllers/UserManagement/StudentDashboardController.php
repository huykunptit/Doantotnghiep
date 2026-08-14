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
     * BẢNG ĐIỂM:
     *  - results: điểm ngoài CTĐT (kỳ thi / khóa LMS)
     *  - curriculum: điểm trong CTĐT theo học kỳ (GPA / CPA / tín chỉ)
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

        usort($results, fn ($a, $b) => strcmp((string) $a['exam_date'], (string) $b['exam_date']));

        $average = count($scores) > 0 ? round(array_sum($scores) / count($scores), 2) : null;

        $curriculum = $this->buildCurriculumTranscript($user);
        $external = $this->buildExternalTranscript($user);

        return response()->json([
            'student'     => $user->only(['id', 'name', 'email', 'student_code', 'cohort_id', 'major_id', 'program_id']),
            'results'     => $results,
            'summary'     => [
                'total_exams'         => count($results),
                'taken'               => count($scores),
                'passed'              => $passedCount,
                'average_score'       => $average,
                'credit_weighted_gpa' => $curriculum['overall']['gpa4'] ?? null,
            ],
            'curriculum'  => $curriculum,
            'external'    => $external,
        ]);
    }

    /**
     * Điểm trong CTĐT: nhóm theo học kỳ khung chương trình, kèm GPA/CPA/tín chỉ.
     */
    private function buildCurriculumTranscript($user): array
    {
        $user->loadMissing(['administrativeClass', 'cohort:id,start_year,end_year,name']);
        $curriculumId = $user->administrativeClass?->curriculum_id;

        $empty = [
            'has_curriculum' => false,
            'semesters' => [],
            'overall' => [
                'gpa4' => null,
                'gpa10' => null,
                'credits_earned' => 0,
                'credits_attempted' => 0,
                'courses_graded' => 0,
            ],
        ];

        if (!$curriculumId) {
            return $empty;
        }

        $curriculumCourses = \App\Models\CurriculumCourse::query()
            ->where('curriculum_id', $curriculumId)
            ->with(['course:id,title,slug,credit_value,is_credit_bearing,status'])
            ->orderBy('term_number')
            ->orderBy('position')
            ->get();

        if ($curriculumCourses->isEmpty()) {
            return $empty;
        }

        $enrollments = Enrollment::query()
            ->where('user_id', $user->id)
            ->where('enrollment_source', '!=', 'marketplace')
            ->with([
                'gradeEntries.component',
                'classSection:id,code,name',
            ])
            ->get()
            ->keyBy('course_id');

        $startYear = (int) ($user->cohort?->start_year ?? now()->year);
        $byTerm = $curriculumCourses->groupBy(fn ($cc) => (int) $cc->term_number);

        // Học kỳ đang học = kỳ có ghi danh cao nhất (cuối kỳ mới có điểm → để trống).
        $currentTermNumber = $curriculumCourses
            ->filter(fn ($cc) => $cc->course_id && $enrollments->has($cc->course_id))
            ->max(fn ($cc) => (int) $cc->term_number);

        $semesters = [];
        $cumulativeRows = [];

        foreach ($byTerm->sortKeys() as $termNumber => $rows) {
            $coursesOut = [];
            $semesterRows = [];
            $stt = 0;
            $isCurrent = $currentTermNumber !== null && (int) $termNumber === (int) $currentTermNumber;

            foreach ($rows as $cc) {
                $course = $cc->course;
                if (!$course) continue;

                $enrollment = $enrollments->get($course->id);

                // Chỉ hiện học kỳ đã có ghi danh (đã/đang học trong CTĐT)
                if (!$enrollment) {
                    continue;
                }

                $stt++;
                $credits = (int) ($course->credit_value ?? $cc->credits ?? 0);

                // Kỳ đang học: liệt kê môn + tín chỉ, điểm để trống (thi cuối kỳ mới có).
                if ($isCurrent) {
                    $coursesOut[] = [
                        'stt' => $stt,
                        'course_id' => $course->id,
                        'code' => $course->slug ? strtoupper(str_replace('-', '', substr($course->slug, 0, 12))) : null,
                        'title' => $course->title,
                        'credits' => $credits,
                        'attendance_score' => null,
                        'midterm_score' => null,
                        'exam_score' => null,
                        'score10' => null,
                        'score4' => null,
                        'letter' => null,
                        'passed' => null,
                        'components' => [],
                    ];
                    continue;
                }

                $score10 = $enrollment->final_score;
                $componentScores = $this->extractComponentScores($enrollment);
                $grade = $score10 !== null ? GpaCalculator::gradeInfo((float) $score10) : null;
                $passed = GpaCalculator::isPassed($score10);

                $coursesOut[] = [
                    'stt' => $stt,
                    'course_id' => $course->id,
                    'code' => $course->slug ? strtoupper(str_replace('-', '', substr($course->slug, 0, 12))) : null,
                    'title' => $course->title,
                    'credits' => $credits,
                    'attendance_score' => $componentScores['attendance'],
                    'midterm_score' => $componentScores['midterm'],
                    'exam_score' => $componentScores['final'],
                    'score10' => $score10,
                    'score4' => $grade['gpa4'] ?? null,
                    'letter' => $grade['letter'] ?? null,
                    'passed' => $score10 !== null ? $passed : null,
                    'components' => $this->mapGradeComponents($enrollment),
                ];

                if ($score10 !== null && $credits > 0) {
                    $semesterRows[] = ['final_score' => $score10, 'credit_value' => $credits];
                    $cumulativeRows[] = ['final_score' => $score10, 'credit_value' => $credits];
                }
            }

            if (!count($coursesOut)) {
                continue;
            }

            $yearOffset = (int) ceil($termNumber / 2) - 1;
            $ayStart = $startYear + $yearOffset;
            // Trong năm học chỉ có HK 1 / HK 2 (kỳ lẻ = 1, kỳ chẵn = 2 theo khung CTĐT).
            $semesterInYear = ((int) $termNumber % 2 === 1) ? 1 : 2;

            $semesters[] = [
                'term_number' => (int) $termNumber,
                'label' => sprintf(
                    'Học kỳ %d - Năm học %d - %d',
                    $semesterInYear,
                    $ayStart,
                    $ayStart + 1
                ),
                'is_current' => $isCurrent,
                'courses' => $coursesOut,
                // Kỳ đang học: GPA / tín chỉ kỳ để trống; CPA vẫn giữ đến kỳ trước.
                'semester_gpa4' => $isCurrent ? null : GpaCalculator::cumulativeGpa($semesterRows),
                'semester_gpa10' => $isCurrent ? null : GpaCalculator::cumulativeScore10($semesterRows),
                'semester_credits_earned' => $isCurrent ? null : GpaCalculator::earnedCredits($semesterRows),
                'cumulative_gpa4' => $isCurrent ? null : GpaCalculator::cumulativeGpa($cumulativeRows),
                'cumulative_gpa10' => $isCurrent ? null : GpaCalculator::cumulativeScore10($cumulativeRows),
                'cumulative_credits' => $isCurrent ? null : GpaCalculator::earnedCredits($cumulativeRows),
            ];
        }

        // Newest semester first (như mẫu PTIT)
        $semesters = array_reverse($semesters);

        return [
            'has_curriculum' => true,
            'semesters' => $semesters,
            'overall' => [
                'gpa4' => GpaCalculator::cumulativeGpa($cumulativeRows),
                'gpa10' => GpaCalculator::cumulativeScore10($cumulativeRows),
                'credits_earned' => GpaCalculator::earnedCredits($cumulativeRows),
                'credits_attempted' => array_sum(array_map(fn ($r) => (int) $r['credit_value'], $cumulativeRows)),
                'courses_graded' => count($cumulativeRows),
            ],
        ];
    }

    /**
     * Điểm ngoài CTĐT: khóa marketplace / extension — điểm tổng kết + đầu điểm chi tiết.
     */
    private function buildExternalTranscript($user): array
    {
        $enrollments = Enrollment::query()
            ->where('user_id', $user->id)
            ->where(function ($q) {
                $q->where('enrollment_source', 'marketplace')
                    ->orWhereHas('course', fn ($c) => $c->where('course_mode', 'extension'));
            })
            ->with([
                'course:id,title,credit_value,course_mode,status',
                'gradeEntries.component',
            ])
            ->orderByDesc('enrolled_at')
            ->get()
            ->unique('course_id')
            ->values();

        $courses = [];
        $scores = [];
        $passedCount = 0;

        foreach ($enrollments as $enrollment) {
            $course = $enrollment->course;
            if (!$course) continue;

            $score10 = $enrollment->final_score;
            $passed = $score10 !== null ? GpaCalculator::isPassed($score10) : null;
            if ($score10 !== null) {
                $scores[] = $score10;
                if ($passed) $passedCount++;
            }

            $courses[] = [
                'enrollment_id' => $enrollment->id,
                'course_id' => $course->id,
                'title' => $course->title,
                'credits' => (int) ($course->credit_value ?? 0),
                'score10' => $score10,
                'passed' => $passed,
                'components' => $this->mapGradeComponents($enrollment),
            ];
        }

        return [
            'courses' => $courses,
            'summary' => [
                'total_courses' => count($courses),
                'graded' => count($scores),
                'passed' => $passedCount,
                'average_score' => count($scores) ? round(array_sum($scores) / count($scores), 2) : null,
            ],
        ];
    }

    /** Danh sách đầu điểm (thành phần) của một enrollment. */
    private function mapGradeComponents(?Enrollment $enrollment): array
    {
        if (!$enrollment) {
            return [];
        }

        return $enrollment->gradeEntries
            ->filter(fn ($e) => $e->component)
            ->sortBy(fn ($e) => (int) ($e->component->position ?? 0))
            ->values()
            ->map(fn ($e, $i) => [
                'stt' => $i + 1,
                'name' => $e->component->name,
                'weight' => (float) $e->component->weight,
                'score' => $e->score !== null ? (float) $e->score : null,
                'max_score' => (float) ($e->component->max_score ?: 10),
            ])
            ->all();
    }

    private function extractComponentScores(?Enrollment $enrollment): array
    {
        $out = ['attendance' => null, 'midterm' => null, 'final' => null];
        if (!$enrollment) return $out;

        foreach ($enrollment->gradeEntries as $entry) {
            $name = mb_strtolower((string) ($entry->component?->name ?? ''));
            $score = $entry->score !== null ? (float) $entry->score : null;
            if ($score === null) continue;

            if (str_contains($name, 'chuyên cần') || str_contains($name, 'attendance')) {
                $out['attendance'] = $score;
            } elseif (str_contains($name, 'giữa') || str_contains($name, 'mid')) {
                $out['midterm'] = $score;
            } elseif (str_contains($name, 'cuối') || str_contains($name, 'final') || str_contains($name, 'thi')) {
                $out['final'] = $score;
            }
        }

        return $out;
    }

    /**
     * THỜI KHÓA BIỂU — theo lớp hành chính của SV (cả lớp cùng lịch) + lịch thi sắp tới.
     */
    public function timetable(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthorized'], 401);

        $currentTerm = Term::operational();

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

        $currentTerm = Term::operational();

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
            'curriculumCourses' => fn ($q) => $q->orderBy('term_number')->orderBy('position'),
            'curriculumCourses.course:id,title,credit_value,is_credit_bearing,course_mode,slug,thumbnail,major_id',
            'curriculumCourses.course.major:id,name,code',
        ])->find($adminClass->curriculum_id);

        if (!$curriculum) {
            return response()->json([
                'has_curriculum' => false,
                'message' => 'Không tìm thấy chương trình đào tạo tương ứng.',
                'terms' => []
            ]);
        }

        // Chỉ ghi danh CTĐT (không tính khóa marketplace bổ trợ trên web).
        $enrollments = Enrollment::where('user_id', $user->id)
            ->where('enrollment_source', '!=', 'marketplace')
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

            $termNum = (int) $cc->term_number;
            if ($termNum < 1 || $termNum > 8) {
                continue;
            }

            $enrollment = $enrollments->get($course->id);
            // Đã học = đã/đang học môn trong CTĐT (có ghi danh học vụ), không phải tiến độ khóa web.
            $studied = $enrollment !== null;
            $finalScore = $enrollment?->final_score;

            $creditsVal = ($course->credit_value ?? $cc->credits ?? 0);
            if ($course->is_credit_bearing) {
                $totalCreditsRequired += $creditsVal;
                if ($studied && $finalScore !== null && \App\Helpers\GpaCalculator::isPassed($finalScore)) {
                    $totalCreditsEarned += $creditsVal;
                }
            }

            $termsData[$termNum]['courses'][] = [
                'id' => $course->id,
                'title' => $course->title,
                'code' => $course->slug
                    ? strtoupper(str_replace('-', '', substr($course->slug, 0, 12)))
                    : null,
                'slug' => $course->slug,
                'thumbnail' => $course->thumbnail,
                'major' => $course->major?->name,
                'credits' => $creditsVal,
                'is_required' => (bool) $cc->is_required,
                'course_mode' => $course->course_mode,
                'studied' => $studied,
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
            try {
                $exam = $enrollment->exam;
                if (!$exam) continue;

                $quiz = \App\Models\Quiz::where('exam_id', $exam->id)->first();

                $attemptsCount = 0;
                $bestScore = null;
                $attemptId = null;
                $status = 'scheduled';

                if ($quiz) {
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
                    'proctoring_enabled' => (bool) $exam->proctoring_enabled,
                    'attempts_count' => $attemptsCount,
                    'best_score' => $bestScore,
                    'attempt_id' => $attemptId,
                    'quiz_id' => $quiz ? $quiz->id : null,
                ];
            } catch (\Throwable) {
                continue;
            }
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
            ->with(['lesson.section.course', 'classSection.course', 'course', 'administrativeClass'])
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

        // Verify enrollment: lớp HC / lớp tín chỉ / ghi danh khoá
        $enrolled = false;
        if ($session->administrative_class_id) {
            $enrolled = (int) $user->administrative_class_id === (int) $session->administrative_class_id;
        }
        if (!$enrolled && $session->classSection) {
            $enrolled = Enrollment::where('user_id', $user->id)
                ->where('class_section_id', $session->class_section_id)
                ->exists();
        }
        if (!$enrolled && $session->course_id) {
            $enrolled = Enrollment::where('user_id', $user->id)
                ->where('course_id', $session->course_id)
                ->exists();
        }
        if (!$enrolled && $session->lesson) {
            $course = $session->lesson->section?->course;
            if ($course) {
                $enrolled = Enrollment::where('user_id', $user->id)
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

        $courseTitle = $session->course?->title
            ?? $session->classSection?->course?->title
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

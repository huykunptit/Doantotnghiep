<?php

namespace Database\Seeders;

use App\Models\AdministrativeClass;
use App\Models\AcademicYear;
use App\Models\ClassSchedule;
use App\Models\CurriculumCourse;
use App\Models\Exam;
use App\Models\ExamEnrollment;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Models\Term;
use App\Models\Tuition;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * BA 2026 — dữ liệu demo cho cổng sinh viên mới:
 *  - Học phí cố định theo kỳ (tuitions)
 *  - Thời khóa biểu theo lớp hành chính (class_schedules)
 *  - Lịch thi cuối kỳ (Exam standalone) + ghi danh + điểm thi (bảng điểm)
 */
class AcademicExtrasSeeder extends Seeder
{
    /** Học phí cố định theo chương trình (VND/kỳ) */
    private const TUITION_BY_PROGRAM = [
        'CNTT' => 9_500_000,
        'DTVT' => 9_000_000,
        'QTKD' => 8_500_000,
    ];

    private const TIME_SLOTS = [
        ['07:00', '09:30'],
        ['09:45', '12:15'],
        ['13:00', '15:30'],
        ['15:45', '18:15'],
    ];

    public function run(): void
    {
        $currentTerm = Term::query()->where('is_current', true)->latest('id')->first()
            ?? Term::query()->latest('id')->first();

        if (!$currentTerm) {
            $this->command?->warn('AcademicExtrasSeeder: chưa có Term nào, bỏ qua.');
            return;
        }

        $previousYear = AcademicYear::query()->updateOrCreate(
            [
                'institution_id' => $currentTerm->academicYear?->institution_id,
                'name' => '2024-2025',
            ],
            [
                'start_date' => '2024-08-01',
                'end_date' => '2025-07-31',
                'is_current' => false,
                'status' => 'completed',
            ]
        );

        $previousTerms = collect([
            [
                'code' => 'HK1-2024',
                'name' => 'Học kỳ 1 (2024-2025)',
                'start_date' => '2024-08-01',
                'end_date' => '2025-01-10',
            ],
            [
                'code' => 'HK2-2024',
                'name' => 'Học kỳ 2 (2024-2025)',
                'start_date' => '2025-02-01',
                'end_date' => '2025-06-15',
            ],
        ])->map(fn (array $term) => Term::query()->updateOrCreate(
            ['academic_year_id' => $previousYear->id, 'code' => $term['code']],
            [...$term, 'is_current' => false, 'status' => 'completed']
        ));

        $adminClasses = AdministrativeClass::query()
            ->with(['program:id,code', 'cohort:id,start_year', 'advisor:id,name'])
            ->whereNotNull('curriculum_id')
            ->get();

        $tuitionCount = 0;
        $scheduleCount = 0;
        $examCount = 0;
        $attemptCount = 0;

        foreach ($adminClasses as $class) {
            $students = User::query()
                ->where('administrative_class_id', $class->id)
                ->where('user_type', 'student')
                ->get();

            if ($students->isEmpty()) {
                continue;
            }

            $programCode = $class->program?->code ?? 'CNTT';
            $termAmount = self::TUITION_BY_PROGRAM[$programCode] ?? 8_500_000;

            // ── Học phí ──
            foreach ($students as $student) {
                $tuition = Tuition::query()->updateOrCreate(
                    ['user_id' => $student->id, 'term_id' => $currentTerm->id],
                    [
                        'amount' => $termAmount,
                        'status' => ($student->id % 2 === 0) ? 'paid' : 'unpaid',
                        'paid_at' => ($student->id % 2 === 0) ? now()->subDays(20) : null,
                        'note' => 'Học phí ' . $currentTerm->name,
                    ]
                );
                $tuitionCount++;

                foreach ($previousTerms as $termIndex => $previousTerm) {
                    Tuition::query()->updateOrCreate(
                        ['user_id' => $student->id, 'term_id' => $previousTerm->id],
                        [
                            'amount' => max(0, $termAmount - (($termIndex + 1) * 500_000)),
                            'status' => 'paid',
                            'paid_at' => $previousTerm->end_date?->copy()->subDays(20),
                            'note' => 'Học phí ' . $previousTerm->name,
                        ]
                    );
                    $tuitionCount++;
                }
            }

            // ── Môn CTĐT của kỳ hiện tại ──
            $startYear = (int) ($class->cohort?->start_year ?? 2024);
            $currentTermNumber = $startYear <= 2023 ? 5 : 3;

            $courses = CurriculumCourse::query()
                ->where('curriculum_id', $class->curriculum_id)
                ->where('term_number', $currentTermNumber)
                ->with('course:id,title,status')
                ->orderBy('position')
                ->get()
                ->map(fn ($cc) => $cc->course)
                ->filter(fn ($c) => $c && $c->status === 'published')
                ->values();

            if ($courses->isEmpty()) {
                continue;
            }

            // ── Thời khóa biểu ──
            foreach ($courses as $idx => $course) {
                $slot = self::TIME_SLOTS[$idx % count(self::TIME_SLOTS)];
                ClassSchedule::query()->updateOrCreate(
                    [
                        'administrative_class_id' => $class->id,
                        'course_id' => $course->id,
                        'term_id' => $currentTerm->id,
                    ],
                    [
                        'lecturer_id' => $class->advisor?->id,
                        'weekday' => ($idx % 6) + 1,
                        'start_time' => $slot[0],
                        'end_time' => $slot[1],
                        'room' => sprintf('%s-%03d', $programCode, 100 + $idx),
                    ]
                );
                $scheduleCount++;
            }

            // ── Lịch thi cuối kỳ + điểm thi (tối đa 3 môn đầu) ──
            $examRooms = ['502 - A3', '105 - A2', '803 - NT', '301 - A1', '204 - B2'];
            foreach ($courses->take(3) as $idx => $course) {
                // Kỳ thi đã diễn ra (đóng) → có điểm cho bảng điểm
                $isPast = $idx < 2;
                $starts = $isPast
                    ? now()->subDays(30 - $idx * 3)->setTime(8, 0)
                    : now()->addDays(7 + $idx)->setTime(8, 0);

                $exam = Exam::query()->updateOrCreate(
                    [
                        'course_id' => $course->id,
                        'title' => 'Thi cuối kỳ - ' . $course->title . ' (' . $class->code . ')',
                    ],
                    [
                        'description' => 'Kỳ thi cuối kỳ môn ' . $course->title . ' cho lớp ' . $class->code . '.',
                        'type' => 'standalone',
                        'status' => $isPast ? 'closed' : 'scheduled',
                        'duration' => 90,
                        'pass_score' => 50,
                        'max_attempts' => 1,
                        'starts_at' => $starts,
                        'ends_at' => (clone $starts)->addMinutes(90),
                        'room' => $examRooms[$idx % count($examRooms)],
                        'proctoring_enabled' => true,
                        'review_options' => Exam::defaultReviewOptions(),
                        'created_by' => $class->advisor?->id,
                    ]
                );
                $examCount++;

                $quiz = Quiz::query()->updateOrCreate(
                    ['exam_id' => $exam->id],
                    [
                        'scope' => 'exam',
                        'title' => $exam->title,
                        'time_limit' => 90,
                        'pass_score' => 50,
                    ]
                );

                // Gắn câu hỏi demo từ ngân hàng (để SV vào thi được)
                $questionIds = Question::query()
                    ->inRandomOrder()
                    ->limit(10)
                    ->pluck('id');
                if ($questionIds->isNotEmpty()) {
                    $sync = [];
                    foreach ($questionIds->values() as $pos => $qid) {
                        $sync[$qid] = ['order' => $pos + 1, 'points' => 1];
                    }
                    $quiz->questions()->sync($sync);
                }

                foreach ($students as $student) {
                    ExamEnrollment::query()->updateOrCreate(
                        ['exam_id' => $exam->id, 'user_id' => $student->id],
                        ['enrolled_by' => $class->advisor?->id, 'enrolled_at' => now()->subDays(40)]
                    );

                    // Điểm thi cho kỳ đã đóng (~80% SV có bài làm)
                    if ($isPast && ($student->id % 5) !== 0) {
                        $score = 45 + (($student->id + $course->id) % 50); // 45..94
                        QuizAttempt::query()->updateOrCreate(
                            ['user_id' => $student->id, 'quiz_id' => $quiz->id],
                            [
                                'status' => 'submitted',
                                'score' => $score,
                                'passed' => $score >= 50,
                                'started_at' => (clone $starts),
                                'completed_at' => (clone $starts)->addMinutes(70),
                            ]
                        );
                        $attemptCount++;
                    }
                }
            }
        }

        $this->command?->info(sprintf(
            'AcademicExtrasSeeder: %d học phí, %d buổi TKB, %d kỳ thi, %d bài thi có điểm.',
            $tuitionCount,
            $scheduleCount,
            $examCount,
            $attemptCount
        ));
    }
}

<?php

namespace Database\Seeders;

use App\Models\AcademicYear;
use App\Models\AdministrativeClass;
use App\Models\AdministrativeClassTerm;
use App\Models\AssignmentSubmission;
use App\Models\ClassSchedule;
use App\Models\ClassSection;
use App\Models\CurriculumCourse;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamEnrollment;
use App\Models\ExamViolation;
use App\Models\GradeComponent;
use App\Models\GradeEntry;
use App\Models\Lesson;
use App\Models\LessonAssignment;
use App\Models\LessonProgress;
use App\Models\OfflineSession;
use App\Models\OfflineSessionAttendance;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\Term;
use App\Models\User;
use App\Models\UserCertificate;
use App\Models\VirtualClass;
use App\Services\CareerPathFulfillmentService;
use Carbon\CarbonImmutable;
use Database\Seeders\Support\SeededQuizAttempt;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Complete, repeatable academic demo dataset.
 *
 * This seeder only updates student profiles and academic relations. It never
 * deletes users, so locally-created non-demo accounts remain intact.
 */
class AcademicDemoDataSeeder extends Seeder
{
    private const AVATAR_COUNT = 16;

    /** @var array<string, int> */
    private const CERT_COURSE_COUNTS = [
        'student1@lms.com' => 2,
        'student2@lms.com' => 1,
        'student3@lms.com' => 2,
        'student5@lms.com' => 2,
        'student8@lms.com' => 1,
        'student11@lms.com' => 1,
        'student13@lms.com' => 1,
        'student16@lms.com' => 1,
    ];

    private const SLOTS = [
        [1, '07:00', '09:30'],
        [1, '09:45', '12:15'],
        [1, '13:00', '15:30'],
        [1, '15:45', '18:15'],
        [2, '07:00', '09:30'],
        [2, '09:45', '12:15'],
        [2, '13:00', '15:30'],
        [2, '15:45', '18:15'],
        [3, '07:00', '09:30'],
        [3, '09:45', '12:15'],
        [3, '13:00', '15:30'],
        [3, '15:45', '18:15'],
        [4, '07:00', '09:30'],
        [4, '09:45', '12:15'],
        [4, '13:00', '15:30'],
        [4, '15:45', '18:15'],
        [5, '07:00', '09:30'],
        [5, '09:45', '12:15'],
        [5, '13:00', '15:30'],
        [5, '15:45', '18:15'],
        [6, '07:00', '09:30'],
        [6, '09:45', '12:15'],
        [6, '13:00', '15:30'],
        [6, '15:45', '18:15'],
    ];

    private array $stats = [
        'profiles' => 0,
        'term_maps' => 0,
        'sections' => 0,
        'schedules' => 0,
        'enrollments' => 0,
        'grade_entries' => 0,
        'exams' => 0,
        'exam_enrollments' => 0,
        'attempts' => 0,
        'violations' => 0,
        'completed_courses' => 0,
        'certificates' => 0,
        'qr_sessions' => 0,
        'qr_checkins' => 0,
    ];

    public function run(): void
    {
        $this->publishAvatars();
        $this->shiftHk1StartsToAugust();

        $currentTerm = $this->resolveOperationalTerm();
        if (! $currentTerm) {
            throw new RuntimeException('AcademicDemoDataSeeder: no dated Term exists.');
        }
        $this->synchronizeCurrentFlags($currentTerm);

        DB::transaction(function () use ($currentTerm): void {
            $this->completeStudentProfiles();
            $this->upsertTermMaps();
            $this->upsertSectionsSchedulesAndEnrollments($currentTerm);
            $this->seedHistoricalGrades($currentTerm);
        });

        // Reuse the existing activity/persona generator only after all current
        // academic enrollments have correct term/section/cohort references.
        $previousCurrentOnly = config('demo.student_learning_current_only');
        config(['demo.student_learning_current_only' => true]);
        try {
            $this->call(StudentLearningSeeder::class);
        } finally {
            config(['demo.student_learning_current_only' => $previousCurrentOnly]);
        }

        DB::transaction(function () use ($currentTerm): void {
            $this->seedExams($currentTerm);
            $this->seedCompletedCoursesAndCertificates();
            $this->seedQrAttendanceSessions($currentTerm);
        });

        $this->synchronizeCurrentFlags($currentTerm);

        $audit = $this->audit($currentTerm);
        $this->printSummary($currentTerm, $audit);

        $fatal = array_filter($audit, fn (int $count): bool => $count > 0);
        if ($fatal !== []) {
            throw new RuntimeException(
                'Academic demo audit failed: '.collect($fatal)
                    ->map(fn (int $count, string $name): string => "{$name}={$count}")
                    ->implode(', ')
            );
        }
    }

    private function publishAvatars(): void
    {
        $source = database_path('seeders/assets/avatars');
        $target = storage_path('app/public/seed/avatars');

        File::ensureDirectoryExists($target);

        foreach (['male', 'female'] as $gender) {
            for ($number = 1; $number <= self::AVATAR_COUNT; $number++) {
                $file = sprintf('%s-%02d.jpg', $gender, $number);
                $sourceFile = $source.DIRECTORY_SEPARATOR.$file;
                if (! File::isFile($sourceFile)) {
                    throw new RuntimeException("Missing seeded avatar asset: {$sourceFile}");
                }

                File::copy($sourceFile, $target.DIRECTORY_SEPARATOR.$file);
            }
        }
    }

    private function resolveOperationalTerm(): ?Term
    {
        $today = CarbonImmutable::today();
        $terms = Term::query()->with('academicYear')->get();

        return $terms
            ->filter(fn (Term $term): bool => $term->start_date && $term->end_date
                && $today->betweenIncluded($term->start_date, $term->end_date))
            ->sortByDesc(fn (Term $term): array => [(int) $term->is_current, $term->start_date?->timestamp])
            ->first()
            ?? $terms
                ->filter(fn (Term $term): bool => $term->enrollment_start_at && $term->start_date
                    && $today->betweenIncluded($term->enrollment_start_at, $term->start_date))
                ->sortBy('start_date')
                ->first()
            ?? $terms
                ->filter(fn (Term $term): bool => $term->start_date && $term->start_date->gte($today))
                ->sortBy('start_date')
                ->first()
            ?? $terms
                ->where('is_current', true)
                ->sortByDesc('start_date')
                ->first()
            ?? $terms->sortByDesc('end_date')->first();
    }

    private function synchronizeCurrentFlags(Term $currentTerm): void
    {
        Term::query()->whereKeyNot($currentTerm->id)->update(['is_current' => false]);
        $currentTerm->forceFill(['is_current' => true])->save();

        if ($currentTerm->academic_year_id) {
            DB::table('academic_years')->where('id', '!=', $currentTerm->academic_year_id)->update(['is_current' => false]);
            DB::table('academic_years')->where('id', $currentTerm->academic_year_id)->update(['is_current' => true]);
        }
    }

    private function completeStudentProfiles(): void
    {
        $classes = AdministrativeClass::query()
            ->with(['cohort', 'curriculum.major'])
            ->whereNotNull('cohort_id')
            ->whereNotNull('curriculum_id')
            ->orderBy('id')
            ->get();

        if ($classes->isEmpty()) {
            throw new RuntimeException('AcademicDemoDataSeeder: no usable administrative classes.');
        }

        $hometowns = ['Hà Nội', 'Hải Phòng', 'Đà Nẵng', 'Nghệ An', 'Thanh Hóa', 'Nam Định', 'Bắc Ninh', 'Phú Thọ'];

        User::query()
            ->where('user_type', 'student')
            ->orderBy('id')
            ->get()
            ->each(function (User $student, int $index) use ($classes, $hometowns): void {
                $class = $classes->firstWhere('id', $student->administrative_class_id)
                    ?? $classes[$index % $classes->count()];
                $gender = in_array($student->gender, ['male', 'female'], true)
                    ? $student->gender
                    : ($student->id % 2 === 0 ? 'female' : 'male');
                $avatarNumber = (($student->id - 1) % self::AVATAR_COUNT) + 1;
                $birthYear = max(1980, (int) $class->cohort->start_year - 18);

                $values = [
                    'institution_id' => $student->institution_id ?: $class->institution_id,
                    'unit_id' => $student->unit_id ?: $class->unit_id,
                    'program_id' => $student->program_id ?: $class->program_id,
                    'major_id' => $student->major_id ?: ($class->major_id ?: $class->curriculum?->major_id),
                    'cohort_id' => $student->cohort_id ?: $class->cohort_id,
                    'administrative_class_id' => $student->administrative_class_id ?: $class->id,
                    'advisor_id' => $student->advisor_id ?: $class->advisor_id,
                    'student_code' => $student->student_code ?: 'SV'.str_pad((string) $student->id, 10, '0', STR_PAD_LEFT),
                    'phone' => $student->phone ?: '09'.str_pad((string) ($student->id % 100000000), 8, '0', STR_PAD_LEFT),
                    'gender' => $gender,
                    'date_of_birth' => $student->date_of_birth
                        ?: sprintf('%04d-%02d-%02d', $birthYear, ($student->id % 12) + 1, ($student->id % 28) + 1),
                    'nationality' => $student->nationality ?: 'Việt Nam',
                    'hometown' => $student->hometown ?: $hometowns[$index % count($hometowns)],
                    'permanent_address' => $student->permanent_address ?: 'Số '.($index + 10).' đường Nguyễn Trãi, Hà Nội',
                    'id_card_number' => $student->id_card_number
                        ?: '079'.str_pad((string) ($student->id % 1000000000), 9, '0', STR_PAD_LEFT),
                    'study_status' => $student->study_status ?: 'dang_hoc',
                    'avatar' => sprintf('/storage/seed/avatars/%s-%02d.jpg', $gender, $avatarNumber),
                ];

                $student->forceFill($values)->save();
                $this->stats['profiles']++;
            });
    }

    private function upsertTermMaps(): void
    {
        AdministrativeClass::query()
            ->with(['cohort:id,start_year', 'curriculum:id'])
            ->whereNotNull('cohort_id')
            ->whereNotNull('curriculum_id')
            ->get()
            ->each(function (AdministrativeClass $class): void {
                $termNumbers = CurriculumCourse::query()
                    ->where('curriculum_id', $class->curriculum_id)
                    ->distinct()
                    ->orderBy('term_number')
                    ->pluck('term_number');

                foreach ($termNumbers as $termNumber) {
                    $academicYearStart = (int) $class->cohort->start_year + intdiv((int) $termNumber - 1, 2);
                    $code = (int) $termNumber % 2 === 1 ? 'HK1' : 'HK2';
                    $term = $this->ensureCalendarTerm(
                        (int) $class->institution_id,
                        $academicYearStart,
                        $code,
                    );

                    AdministrativeClassTerm::query()->updateOrCreate(
                        [
                            'administrative_class_id' => $class->id,
                            'term_number' => $termNumber,
                        ],
                        ['term_id' => $term->id],
                    );
                    $this->stats['term_maps']++;
                }
            });
    }

    private function ensureCalendarTerm(int $institutionId, int $academicYearStart, string $code): Term
    {
        $academicYearEnd = $academicYearStart + 1;
        $yearStart = CarbonImmutable::create($academicYearStart, 8, 1);
        $yearEnd = CarbonImmutable::create($academicYearEnd, 7, 31);
        $year = AcademicYear::query()->firstOrCreate(
            [
                'institution_id' => $institutionId,
                'name' => "{$academicYearStart}-{$academicYearEnd}",
            ],
            [
                'start_date' => $yearStart,
                'end_date' => $yearEnd,
                'is_current' => false,
                'status' => $yearEnd->isPast() ? 'completed' : ($yearStart->isFuture() ? 'planned' : 'active'),
            ],
        );

        $isFirst = $code === 'HK1';
        $termStart = $isFirst
            ? CarbonImmutable::create($academicYearStart, 8, 1)
            : CarbonImmutable::create($academicYearEnd, 2, 1);
        $termEnd = $isFirst
            ? CarbonImmutable::create($academicYearEnd, 1, 15)
            : CarbonImmutable::create($academicYearEnd, 6, 30);

        return Term::query()->updateOrCreate(
            ['academic_year_id' => $year->id, 'code' => $code],
            [
                'name' => $isFirst ? 'Học kỳ 1' : 'Học kỳ 2',
                'start_date' => $termStart,
                'end_date' => $termEnd,
                'enrollment_start_at' => $termStart->subDays(17),
                'enrollment_end_at' => $termStart->addDays(5)->endOfDay(),
                'exam_start_at' => $termEnd->subMonth(),
                'exam_end_at' => $termEnd->endOfDay(),
                'is_current' => false,
                'status' => $termEnd->isPast() ? 'completed' : ($termStart->isFuture() ? 'planned' : 'active'),
            ],
        );
    }

    private function upsertSectionsSchedulesAndEnrollments(Term $currentTerm): void
    {
        $lecturersByUnit = User::query()
            ->where('user_type', 'instructor')
            ->orderBy('id')
            ->get()
            ->groupBy('unit_id');
        $allLecturers = $lecturersByUnit->flatten()->values();
        $occupied = [];

        $classes = AdministrativeClass::query()
            ->with(['cohort', 'termMap.term'])
            ->whereNotNull('curriculum_id')
            ->orderBy('id')
            ->get();

        foreach ($classes as $class) {
            $students = User::query()
                ->where('user_type', 'student')
                ->where('administrative_class_id', $class->id)
                ->orderBy('id')
                ->get();
            $termMap = $class->termMap->keyBy('term_number');

            $curriculumGroups = CurriculumCourse::query()
                ->where('curriculum_id', $class->curriculum_id)
                ->with('course')
                ->orderBy('term_number')
                ->orderBy('position')
                ->get()
                ->filter(fn (CurriculumCourse $row): bool => $row->course !== null)
                ->groupBy('term_number');

            foreach ($curriculumGroups as $termNumber => $rows) {
                $mapped = $termMap->get($termNumber);
                if (! $mapped?->term) {
                    continue;
                }

                $courses = $rows->take(5)->values();
                foreach ($courses as $courseIndex => $curriculumCourse) {
                    $course = $curriculumCourse->course;
                    $lecturer = $this->lecturerFor(
                        $class,
                        $courseIndex,
                        $lecturersByUnit,
                        $allLecturers,
                    );
                    $section = ClassSection::query()->updateOrCreate(
                        [
                            'course_id' => $course->id,
                            'term_id' => $mapped->term_id,
                            'code' => sprintf('DEMO-%s-%s-%s', $class->code, $mapped->term->code, $course->id),
                        ],
                        [
                            'cohort_id' => $class->cohort_id,
                            'administrative_class_id' => $class->id,
                            'lecturer_id' => $lecturer?->id,
                            'name' => "{$course->title} - {$class->code}",
                            'capacity' => max(40, $students->count()),
                            'enrolled_count' => $students->count(),
                            'status' => $mapped->term->end_date?->lt(today()) ? 'closed' : 'open',
                            'description' => "Lớp học phần demo kỳ {$termNumber} cho {$class->code}.",
                        ],
                    );
                    $this->stats['sections']++;

                    DB::table('administrative_class_class_section')->updateOrInsert(
                        [
                            'administrative_class_id' => $class->id,
                            'class_section_id' => $section->id,
                        ],
                        [
                            'term_number' => $termNumber,
                            'assigned_by' => $lecturer?->id,
                            'assigned_at' => now(),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ],
                    );

                    $slot = $this->reserveSlot(
                        (int) $mapped->term_id,
                        (int) $class->id,
                        $lecturer?->id,
                        (int) $course->id,
                        $occupied,
                    );
                    ClassSchedule::query()->updateOrCreate(
                        [
                            'administrative_class_id' => $class->id,
                            'course_id' => $course->id,
                            'term_id' => $mapped->term_id,
                        ],
                        [
                            'lecturer_id' => $lecturer?->id,
                            'weekday' => $slot[0],
                            'start_time' => $slot[1],
                            'end_time' => $slot[2],
                            'room' => sprintf('%s-%03d', $class->code, 101 + $courseIndex),
                        ],
                    );
                    $this->stats['schedules']++;

                    $isFutureTerm = $mapped->term->start_date?->gt($currentTerm->start_date);

                    foreach ($students as $student) {
                        $attributes = [
                            'term_id' => $mapped->term_id,
                            'cohort_id' => $class->cohort_id,
                            'class_section_id' => $section->id,
                            'order_id' => null,
                            'enrollment_source' => 'academic',
                            'enrolled_at' => $mapped->term->start_date,
                        ];

                        if ($isFutureTerm) {
                            Enrollment::query()->firstOrCreate(
                                ['user_id' => $student->id, 'course_id' => $course->id],
                                $attributes,
                            );
                        } else {
                            Enrollment::query()->updateOrCreate(
                                ['user_id' => $student->id, 'course_id' => $course->id],
                                $attributes,
                            );
                        }
                        $this->stats['enrollments']++;
                    }
                }
            }
        }

        $this->repairLegacyAcademicEnrollments($lecturersByUnit, $allLecturers, $occupied);
    }

    private function repairLegacyAcademicEnrollments(
        Collection $lecturersByUnit,
        Collection $allLecturers,
        array &$occupied,
    ): void {
        $enrollments = Enrollment::query()
            ->where('enrollment_source', 'academic')
            ->with(['user.administrativeClass.termMap.term', 'course', 'classSection'])
            ->get()
            ->filter(fn (Enrollment $enrollment): bool => ! $enrollment->classSection
                || (int) $enrollment->classSection->course_id !== (int) $enrollment->course_id
                || (int) $enrollment->classSection->term_id !== (int) $enrollment->term_id
                || (int) $enrollment->classSection->cohort_id !== (int) $enrollment->cohort_id);

        foreach ($enrollments as $enrollment) {
            $class = $enrollment->user?->administrativeClass;
            if (! $class || ! $enrollment->course) {
                continue;
            }

            $curriculumCourse = CurriculumCourse::query()
                ->where('curriculum_id', $class->curriculum_id)
                ->where('course_id', $enrollment->course_id)
                ->first();
            $mapped = $curriculumCourse
                ? $class->termMap->firstWhere('term_number', $curriculumCourse->term_number)
                : $class->termMap->firstWhere('term_id', $enrollment->term_id);
            if (! $mapped?->term) {
                continue;
            }

            $lecturer = $this->lecturerFor($class, 0, $lecturersByUnit, $allLecturers);
            $section = ClassSection::query()->updateOrCreate(
                [
                    'course_id' => $enrollment->course_id,
                    'term_id' => $mapped->term_id,
                    'code' => sprintf('DEMO-%s-%s-%s', $class->code, $mapped->term->code, $enrollment->course_id),
                ],
                [
                    'cohort_id' => $class->cohort_id,
                    'administrative_class_id' => $class->id,
                    'lecturer_id' => $lecturer?->id,
                    'name' => "{$enrollment->course->title} - {$class->code}",
                    'capacity' => max(40, $class->students()->count()),
                    'enrolled_count' => $class->students()->count(),
                    'status' => $mapped->term->end_date?->lt(today()) ? 'closed' : 'open',
                    'description' => 'Legacy academic enrollment aligned by AcademicDemoDataSeeder.',
                ],
            );

            DB::table('administrative_class_class_section')->updateOrInsert(
                [
                    'administrative_class_id' => $class->id,
                    'class_section_id' => $section->id,
                ],
                [
                    'term_number' => $curriculumCourse?->term_number,
                    'assigned_by' => $lecturer?->id,
                    'assigned_at' => now(),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            );

            $schedule = ClassSchedule::query()
                ->where('administrative_class_id', $class->id)
                ->where('course_id', $enrollment->course_id)
                ->where('term_id', $mapped->term_id)
                ->first();
            if (! $schedule) {
                $slot = $this->reserveSlot(
                    (int) $mapped->term_id,
                    (int) $class->id,
                    $lecturer?->id,
                    (int) $enrollment->course_id,
                    $occupied,
                );
                ClassSchedule::query()->create([
                    'administrative_class_id' => $class->id,
                    'course_id' => $enrollment->course_id,
                    'term_id' => $mapped->term_id,
                    'lecturer_id' => $lecturer?->id,
                    'weekday' => $slot[0],
                    'start_time' => $slot[1],
                    'end_time' => $slot[2],
                    'room' => sprintf('%s-%03d', $class->code, 150 + ($enrollment->course_id % 40)),
                ]);
            }

            $enrollment->update([
                'term_id' => $mapped->term_id,
                'cohort_id' => $class->cohort_id,
                'class_section_id' => $section->id,
            ]);
        }
    }

    private function lecturerFor(
        AdministrativeClass $class,
        int $courseIndex,
        Collection $lecturersByUnit,
        Collection $allLecturers,
    ): ?User {
        $pool = $lecturersByUnit->get($class->unit_id, collect());
        if ($pool->isEmpty()) {
            $pool = $allLecturers;
        }
        if ($pool->isEmpty()) {
            return $class->advisor;
        }

        return $pool[($class->id + $courseIndex) % $pool->count()];
    }

    private function reserveSlot(
        int $termId,
        int $classId,
        ?int $lecturerId,
        int $courseId,
        array &$occupied,
    ): array {
        $start = abs(crc32("{$termId}|{$classId}|{$courseId}")) % count(self::SLOTS);

        foreach (range(0, count(self::SLOTS) - 1) as $offset) {
            $slot = self::SLOTS[($start + $offset) % count(self::SLOTS)];
            $classKey = "{$termId}|class|{$classId}|{$slot[0]}|{$slot[1]}";
            $lecturerKey = "{$termId}|lecturer|{$lecturerId}|{$slot[0]}|{$slot[1]}";
            if (isset($occupied[$classKey]) || ($lecturerId && isset($occupied[$lecturerKey]))) {
                continue;
            }

            $occupied[$classKey] = true;
            if ($lecturerId) {
                $occupied[$lecturerKey] = true;
            }

            return $slot;
        }

        throw new RuntimeException("No conflict-free schedule slot for class {$classId}, term {$termId}.");
    }

    private function seedHistoricalGrades(Term $currentTerm): void
    {
        $graderId = User::query()->where('user_type', 'instructor')->orderBy('id')->value('id');
        $historical = Enrollment::query()
            ->where('enrollment_source', 'academic')
            ->whereHas('term', fn ($query) => $query->where('end_date', '<', $currentTerm->start_date))
            ->get();

        foreach ($historical as $enrollment) {
            $components = $this->gradeComponents((int) $enrollment->course_id);
            $final = $this->stableScore((int) $enrollment->user_id, (int) $enrollment->course_id);

            foreach ($components as $component) {
                $score = match ($component->name) {
                    'Chuyên cần' => min(10, $final + 0.6),
                    'Giữa kỳ' => max(4, $final - 0.3),
                    default => $final,
                };
                GradeEntry::query()->updateOrCreate(
                    [
                        'enrollment_id' => $enrollment->id,
                        'grade_component_id' => $component->id,
                    ],
                    [
                        'score' => round($score, 1),
                        'graded_by' => $graderId,
                        'graded_at' => $enrollment->term?->end_date,
                        'note' => 'AcademicDemoDataSeeder',
                    ],
                );
                $this->stats['grade_entries']++;
            }
        }
    }

    private function gradeComponents(int $courseId): Collection
    {
        $template = [
            ['name' => 'Chuyên cần', 'weight' => 10, 'position' => 1],
            ['name' => 'Giữa kỳ', 'weight' => 30, 'position' => 2],
            ['name' => 'Cuối kỳ', 'weight' => 60, 'position' => 3],
        ];

        return collect($template)->map(fn (array $row): GradeComponent => GradeComponent::query()->updateOrCreate(
            ['course_id' => $courseId, 'name' => $row['name']],
            [...$row, 'max_score' => 10, 'is_required' => true, 'note' => 'AcademicDemoDataSeeder'],
        ));
    }

    private function stableScore(int $studentId, int $courseId): float
    {
        return round(5.2 + (($studentId * 17 + $courseId * 31) % 44) / 10, 1);
    }

    private function seedExams(Term $currentTerm): void
    {
        $recentCutoff = $currentTerm->start_date?->copy()->subMonths(18) ?? now()->subYear();
        $lastCompletedId = Term::query()
            ->where('end_date', '<', $currentTerm->start_date)
            ->orderByDesc('end_date')
            ->value('id');

        $examSections = ClassSection::query()
            ->with(['course', 'term', 'enrollments'])
            ->whereHas('term', fn ($query) => $query
                ->where('end_date', '>=', $recentCutoff)
                ->where(function ($inner) use ($currentTerm): void {
                    $inner->where('end_date', '<', $currentTerm->start_date)
                        ->orWhere('id', $currentTerm->id);
                }))
            ->whereNotNull('administrative_class_id')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (ClassSection $section): string => $section->administrative_class_id.'|'.$section->term_id)
            ->flatMap(function (Collection $sections) use ($currentTerm): Collection {
                $isCurrent = (int) $sections->first()?->term_id === (int) $currentTerm->id;

                return $sections->take($isCurrent ? 2 : 3);
            });

        foreach ($examSections as $section) {
            $isHistorical = $section->term->end_date?->lt($currentTerm->start_date) ?? false;
            $includeMidterm = $isHistorical && (int) $section->term_id === (int) $lastCompletedId;
            $kinds = $includeMidterm
                ? [
                    ['label' => 'Thi giữa kỳ', 'minutes' => 45, 'offset_days' => 55, 'type' => 'standalone'],
                    ['label' => 'Thi cuối kỳ', 'minutes' => 90, 'offset_days' => 10, 'type' => 'course_final'],
                ]
                : [
                    ['label' => 'Thi cuối kỳ', 'minutes' => 90, 'offset_days' => 10, 'type' => 'course_final'],
                ];

            foreach ($kinds as $kind) {
                $this->seedSectionExam($section, $currentTerm, $isHistorical, $kind);
            }
        }
    }

    /**
     * @param  array{label: string, minutes: int, offset_days: int, type: string}  $kind
     */
    private function seedSectionExam(ClassSection $section, Term $currentTerm, bool $isHistorical, array $kind): void
    {
        $termEnd = $section->term->end_date?->copy() ?? $currentTerm->end_date?->copy();
        $startsAt = $kind['type'] === 'course_final' && $section->term->exam_start_at
            ? $section->term->exam_start_at->copy()->setTime(8, 0)
            : $termEnd?->copy()->subDays($kind['offset_days'])->setTime(8, 0);

        $title = "{$kind['label']} - {$section->course->title} ({$section->code})";
        $status = $isHistorical
            ? 'closed'
            : ($startsAt?->isPast() ? 'active' : 'scheduled');

        $exam = Exam::query()->updateOrCreate(
            ['course_id' => $section->course_id, 'title' => $title],
            [
                'description' => "{$kind['label']} học phần {$section->course->title} — lớp {$section->code}.",
                'type' => $kind['type'],
                'status' => $status,
                'duration' => $kind['minutes'],
                'pass_score' => 50,
                'max_attempts' => 1,
                'shuffle_questions' => false,
                'shuffle_answers' => false,
                'review_options' => Exam::defaultReviewOptions(),
                'starts_at' => $startsAt,
                'ends_at' => $startsAt?->copy()->addMinutes($kind['minutes']),
                'room' => 'P'.(200 + ($section->id % 20)),
                'proctoring_enabled' => true,
                'created_by' => $section->lecturer_id,
            ],
        );
        $this->stats['exams']++;

        $quiz = Quiz::query()->updateOrCreate(
            ['exam_id' => $exam->id],
            [
                'lesson_id' => null,
                'course_id' => $section->course_id,
                'scope' => 'exam',
                'title' => $title,
                'time_limit' => $kind['minutes'],
                'pass_score' => 50,
                'settings' => ['seeded_by' => 'AcademicDemoDataSeeder'],
            ],
        );

        $questionIds = $this->questionIdsForCourse((int) $section->course_id, $kind['type'] === 'course_final' ? 16 : 10);
        if ($questionIds->isNotEmpty()) {
            $quiz->questions()->sync(
                $questionIds->mapWithKeys(fn (int $id, int $index): array => [
                    $id => ['order' => $index + 1, 'points' => 1],
                ])->all()
            );
        }
        $quiz->unsetRelation('questions');

        $finalComponent = GradeComponent::query()
            ->where('course_id', $section->course_id)
            ->where('name', 'Cuối kỳ')
            ->first();
        $midComponent = GradeComponent::query()
            ->where('course_id', $section->course_id)
            ->where('name', 'Giữa kỳ')
            ->first();

        foreach ($section->enrollments as $enrollment) {
            ExamEnrollment::query()->updateOrCreate(
                ['exam_id' => $exam->id, 'user_id' => $enrollment->user_id],
                [
                    'enrolled_by' => $section->lecturer_id,
                    'enrolled_at' => $section->term->start_date,
                ],
            );
            $this->stats['exam_enrollments']++;

            $shouldAttempt = $isHistorical || ($status === 'active' && ($enrollment->user_id % 5) !== 0);
            if (! $shouldAttempt) {
                continue;
            }

            $component = $kind['type'] === 'course_final' ? $finalComponent : $midComponent;
            $score10 = $component
                ? GradeEntry::query()
                    ->where('enrollment_id', $enrollment->id)
                    ->where('grade_component_id', $component->id)
                    ->value('score')
                : null;
            $score10 = $score10 !== null
                ? (float) $score10
                : $this->stableScore((int) $enrollment->user_id, (int) $section->course_id);
            if ($kind['type'] !== 'course_final') {
                $score10 = min(10, max(4, $score10 + ((($enrollment->user_id + $exam->id) % 5) - 2) / 10));
            }

            $attempt = SeededQuizAttempt::upsert(
                (int) $enrollment->user_id,
                $quiz,
                round($score10 * 10, 2),
                $startsAt,
                $startsAt?->copy()->addMinutes(max(20, $kind['minutes'] - 15)),
            );
            $this->stats['attempts']++;

            if (($attempt->id + $enrollment->user_id) % 11 === 0) {
                ExamViolation::query()->updateOrCreate(
                    [
                        'attempt_id' => $attempt->id,
                        'user_id' => $enrollment->user_id,
                        'type' => 'focus_lost',
                    ],
                    [
                        'severity' => 'warning',
                        'metadata' => ['seeded_by' => 'AcademicDemoDataSeeder'],
                    ],
                );
                $this->stats['violations']++;
            }
        }
    }

    private function questionIdsForCourse(int $courseId, int $limit): Collection
    {
        $ids = Question::query()
            ->where('course_id', $courseId)
            ->whereIn('type', ['single_choice', 'true_false', 'multiple_choice'])
            ->orderBy('difficulty')
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        if ($ids->count() >= min(8, $limit)) {
            return $ids;
        }

        return Question::query()
            ->whereIn('type', ['single_choice', 'true_false', 'multiple_choice'])
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');
    }

    private function seedCompletedCoursesAndCertificates(): void
    {
        $fulfillment = app(CareerPathFulfillmentService::class);

        foreach (self::CERT_COURSE_COUNTS as $email => $count) {
            $student = User::query()->where('email', $email)->where('user_type', 'student')->first();
            if (! $student) {
                continue;
            }

            $enrollments = Enrollment::query()
                ->where('user_id', $student->id)
                ->with(['course.lessons', 'course.certificateTemplate'])
                ->get()
                ->filter(fn (Enrollment $enrollment): bool => (bool) $enrollment->course)
                ->sortBy(function (Enrollment $enrollment): array {
                    $course = $enrollment->course;
                    $marketplace = $enrollment->enrollment_source === 'marketplace' ? 0 : 1;
                    $extension = ($course->course_mode ?? '') === 'extension' ? 0 : 1;
                    $lessons = $course->lessons->count();

                    return [$marketplace, $extension, $lessons, $course->id];
                })
                ->values()
                ->take($count);

            foreach ($enrollments as $enrollment) {
                $this->completeEnrollmentForCertificate($student, $enrollment);
                $this->stats['completed_courses']++;

                $course = $enrollment->course;
                $templateId = $course->certificate_template_id;
                if ($templateId) {
                    $cert = UserCertificate::query()->firstOrCreate(
                        [
                            'user_id' => $student->id,
                            'course_id' => $course->id,
                        ],
                        [
                            'certificate_template_id' => $templateId,
                            'credential_id' => 'CERT-'.strtoupper(Str::random(10)),
                            'issued_at' => now()->subDays(3 + ($student->id % 12)),
                        ],
                    );
                    if ($cert->wasRecentlyCreated) {
                        $this->stats['certificates']++;
                    }
                }

                $fulfillment->refreshProgressForUserCourse($student->id, $course->id);
            }
        }
    }

    private function completeEnrollmentForCertificate(User $student, Enrollment $enrollment): void
    {
        $lessons = $enrollment->course?->lessons ?? collect();
        foreach ($lessons as $index => $lesson) {
            LessonProgress::query()->updateOrCreate(
                [
                    'user_id' => $student->id,
                    'lesson_id' => $lesson->id,
                ],
                [
                    'progress_percent' => 100,
                    'watched_seconds' => max(60, (int) ($lesson->duration ?? 600)),
                    'completed' => true,
                    'completed_at' => now()->subDays(4 + ($index % 6)),
                    'last_watched_at' => now()->subDays(1 + ($index % 3)),
                    'metadata' => [
                        'seeded_by' => 'AcademicDemoDataSeeder',
                        'completed_for_certificate' => true,
                    ],
                ],
            );

            if ($lesson->type === 'quiz') {
                $quiz = Quiz::query()->where('lesson_id', $lesson->id)->first();
                if ($quiz) {
                    $score = 82 + (($student->id + $quiz->id) % 16);
                    SeededQuizAttempt::upsert(
                        $student->id,
                        $quiz,
                        (float) $score,
                        now()->subDays(5)->subMinutes(40),
                        now()->subDays(5),
                    );
                    $this->stats['attempts']++;
                }
            }

            if ($lesson->type === 'assignment') {
                $assignment = LessonAssignment::query()->where('lesson_id', $lesson->id)->first();
                if ($assignment) {
                    AssignmentSubmission::query()->updateOrCreate(
                        [
                            'user_id' => $student->id,
                            'lesson_assignment_id' => $assignment->id,
                        ],
                        [
                            'file_url' => 'https://example.com/submissions/'.$student->id.'-'.$assignment->id.'.pdf',
                            'student_note' => 'Bài nộp hoàn thành khóa — seed chứng chỉ.',
                            'grade' => round(8.0 + (($student->id + $assignment->id) % 15) / 10, 1),
                            'feedback' => 'Đạt yêu cầu, hoàn thành khóa học.',
                            'submitted_at' => now()->subDays(6),
                        ],
                    );
                }
            }
        }
    }

    /**
     * @return array<string, int>
     */
    private function audit(Term $currentTerm): array
    {
        $studentProfileQuery = User::query()->where('user_type', 'student');
        $profileColumns = [
            'institution_id',
            'unit_id',
            'program_id',
            'major_id',
            'cohort_id',
            'administrative_class_id',
            'advisor_id',
            'student_code',
            'phone',
            'gender',
            'date_of_birth',
            'nationality',
            'hometown',
            'permanent_address',
            'id_card_number',
            'study_status',
            'avatar',
        ];
        $missingProfiles = (clone $studentProfileQuery)
            ->where(function ($query) use ($profileColumns): void {
                foreach ($profileColumns as $column) {
                    $query->orWhereNull($column)->orWhere($column, '');
                }
            })
            ->count();

        $missingAvatarFiles = (clone $studentProfileQuery)
            ->pluck('avatar')
            ->filter(fn (?string $avatar): bool => ! $avatar
                || ! File::isFile(storage_path('app/public/'.ltrim(str_replace('/storage/', '', $avatar), '/'))))
            ->count();

        $missingTermMaps = DB::table('administrative_classes as ac')
            ->join('curriculum_courses as cc', 'cc.curriculum_id', '=', 'ac.curriculum_id')
            ->leftJoin('administrative_class_terms as act', function ($join): void {
                $join->on('act.administrative_class_id', '=', 'ac.id')
                    ->on('act.term_number', '=', 'cc.term_number');
            })
            ->whereNull('act.id')
            ->distinct()
            ->count(DB::raw("CONCAT(ac.id, '-', cc.term_number)"));

        $missingSchedules = DB::table('administrative_class_class_section as pivot')
            ->join('class_sections as cs', 'cs.id', '=', 'pivot.class_section_id')
            ->leftJoin('class_schedules as schedules', function ($join): void {
                $join->on('schedules.administrative_class_id', '=', 'pivot.administrative_class_id')
                    ->on('schedules.course_id', '=', 'cs.course_id')
                    ->on('schedules.term_id', '=', 'cs.term_id');
            })
            ->whereNull('schedules.id')
            ->count();

        $badEnrollmentRelations = Enrollment::query()
            ->where('enrollment_source', 'academic')
            ->where(function ($query): void {
                $query->whereNull('term_id')
                    ->orWhereNull('cohort_id')
                    ->orWhereNull('class_section_id')
                    ->orWhereHas('classSection', function ($section): void {
                        $section->whereColumn('class_sections.course_id', '!=', 'enrollments.course_id')
                            ->orWhereColumn('class_sections.term_id', '!=', 'enrollments.term_id')
                            ->orWhereColumn('class_sections.cohort_id', '!=', 'enrollments.cohort_id');
                    });
            })
            ->count();

        $missingHistoricalComponents = Enrollment::query()
            ->where('enrollment_source', 'academic')
            ->whereHas('term', fn ($query) => $query->where('end_date', '<', $currentTerm->start_date))
            ->where(function ($query): void {
                $query->whereDoesntHave('gradeEntries')
                    ->orWhereHas('gradeEntries', function ($entries): void {
                        $entries->whereNull('score');
                    });
            })
            ->count();

        $examMissingEnrollment = DB::table('quiz_attempts as qa')
            ->join('quizzes as q', 'q.id', '=', 'qa.quiz_id')
            ->join('exams as e', 'e.id', '=', 'q.exam_id')
            ->leftJoin('exam_enrollments as ee', function ($join): void {
                $join->on('ee.exam_id', '=', 'e.id')->on('ee.user_id', '=', 'qa.user_id');
            })
            ->whereNull('ee.id')
            ->count();

        $examWithoutEnrollments = Exam::query()
            ->where('title', 'like', 'Thi cuối kỳ - %')
            ->whereDoesntHave('examEnrollments')
            ->count();

        $orphanForeignKeys = $this->orphanCount('enrollments', 'user_id', 'users')
            + $this->orphanCount('enrollments', 'course_id', 'courses')
            + $this->orphanCount('enrollments', 'term_id', 'terms')
            + $this->orphanCount('enrollments', 'class_section_id', 'class_sections')
            + $this->orphanCount('grade_entries', 'enrollment_id', 'enrollments')
            + $this->orphanCount('grade_entries', 'grade_component_id', 'grade_components')
            + $this->orphanCount('exam_enrollments', 'exam_id', 'exams')
            + $this->orphanCount('quiz_attempts', 'quiz_id', 'quizzes')
            + $this->orphanCount('exam_violations', 'attempt_id', 'quiz_attempts');

        return [
            'missing_profiles' => $missingProfiles,
            'missing_avatar_files' => $missingAvatarFiles,
            'missing_term_maps' => $missingTermMaps,
            'missing_schedules' => $missingSchedules,
            'bad_enrollment_relations' => $badEnrollmentRelations,
            'missing_historical_grades' => $missingHistoricalComponents,
            'exam_without_enrollments' => $examWithoutEnrollments,
            'exam_attempt_without_enrollment' => $examMissingEnrollment,
            'orphan_foreign_keys' => $orphanForeignKeys,
        ];
    }

    private function orphanCount(string $childTable, string $foreignKey, string $parentTable): int
    {
        return DB::table("{$childTable} as child")
            ->leftJoin("{$parentTable} as parent", 'parent.id', '=', "child.{$foreignKey}")
            ->whereNotNull("child.{$foreignKey}")
            ->whereNull('parent.id')
            ->count();
    }

    /**
     * HK1 demo: bắt đầu 01/08 thay vì 01/09 để kỳ hiện tại có khóa đang học.
     */
    private function shiftHk1StartsToAugust(): void
    {
        Term::query()
            ->where('code', 'HK1')
            ->whereMonth('start_date', 9)
            ->whereDay('start_date', 1)
            ->get()
            ->each(function (Term $term): void {
                $start = $term->start_date?->copy()->month(8)->day(1);
                if (! $start) {
                    return;
                }
                $term->forceFill([
                    'start_date' => $start,
                    'enrollment_start_at' => $start->copy()->subDays(17)->startOfDay(),
                    'enrollment_end_at' => $start->copy()->addDays(5)->endOfDay(),
                ])->save();
            });
    }

    /**
     * QR điểm danh theo từng lớp tín chỉ kỳ hiện tại: live workshop + buổi offline.
     */
    private function seedQrAttendanceSessions(Term $currentTerm): void
    {
        $sections = ClassSection::query()
            ->where('term_id', $currentTerm->id)
            ->with([
                'course.lessons' => fn ($query) => $query->whereIn('type', ['offline', 'live'])->orderBy('order'),
                'enrollments.user:id,email,user_type',
            ])
            ->get();

        $workshopStart = now()->setTime(19, 30);
        $labStart = now()->subMinutes(20)->seconds(0);

        foreach ($sections as $section) {
            $lessons = $section->course?->lessons ?? collect();
            foreach ($lessons as $index => $lesson) {
                $isLive = $lesson->type === 'live';
                $session = OfflineSession::query()->updateOrCreate(
                    [
                        'class_section_id' => $section->id,
                        'lesson_id' => $lesson->id,
                    ],
                    [
                        'course_id' => $section->course_id,
                        'administrative_class_id' => $section->administrative_class_id,
                        'title' => $isLive
                            ? 'Live workshop — '.$section->code
                            : 'Buổi offline — '.$section->code,
                        'location' => $isLive ? 'Google Meet / Workshop trực tuyến' : 'Phòng Lab PTIT - Tầng 5',
                        'room' => $isLive ? 'ONLINE' : sprintf('LAB-%s', str_pad((string) (101 + ($section->id % 40)), 3, '0', STR_PAD_LEFT)),
                        'start_at' => $isLive ? $workshopStart : $labStart,
                        'duration' => $isLive ? 90 : 120,
                        'max_participants' => max(40, (int) $section->capacity),
                        'latitude' => null,
                        'longitude' => null,
                        'check_in_radius_meters' => OfflineSession::DEFAULT_CHECK_IN_RADIUS_METERS,
                        'is_active' => true,
                        'qr_enabled' => true,
                        'qr_mode' => OfflineSession::QR_MODE_MANUAL,
                        'qr_rotate_seconds' => 60,
                    ],
                );
                $session->generateQrToken(14 * 24 * 60);
                $this->stats['qr_sessions']++;

                if ($isLive) {
                    VirtualClass::query()->where('lesson_id', $lesson->id)->update([
                        'start_at' => $workshopStart,
                        'duration' => 90,
                    ]);
                }

                $this->seedQrCheckins($session, $section);
            }
        }
    }

    private function seedQrCheckins(OfflineSession $session, ClassSection $section): void
    {
        $students = $section->enrollments
            ->pluck('user')
            ->filter()
            ->unique('id')
            ->values();

        foreach ($students as $offset => $student) {
            if ($student->user_type !== 'student') {
                continue;
            }
            $email = strtolower((string) $student->email);
            // Tài khoản demo gốc còn trống để quét QR live.
            if (preg_match('/^student(?:[1-9]|1[0-6])@lms\.com$/', $email)) {
                OfflineSessionAttendance::query()
                    ->where('user_id', $student->id)
                    ->where('offline_session_id', $session->id)
                    ->delete();
                continue;
            }

            $late = ($student->id + $session->id) % 7 === 0;
            OfflineSessionAttendance::query()->updateOrCreate(
                [
                    'user_id' => $student->id,
                    'offline_session_id' => $session->id,
                ],
                [
                    'status' => $late ? 'late' : 'present',
                    'checked_in_at' => $session->start_at?->copy()->addMinutes($late ? 15 : 4 + ($offset % 10)),
                    'device_info' => 'seed-qr-demo',
                    'latitude' => null,
                    'longitude' => null,
                    'distance_meters' => null,
                ],
            );
            $this->stats['qr_checkins']++;
        }
    }

    private function printSummary(Term $currentTerm, array $audit): void
    {
        $this->command?->info(
            "AcademicDemoDataSeeder operational term: {$currentTerm->displayName()} [{$currentTerm->start_date?->toDateString()}..{$currentTerm->end_date?->toDateString()}]"
        );
        $this->command?->table(
            ['Dataset', 'Upserted'],
            collect($this->stats)->map(fn (int $count, string $name): array => [$name, $count])->values()->all(),
        );
        $this->command?->table(
            ['Audit', 'Errors'],
            collect($audit)->map(fn (int $count, string $name): array => [$name, $count])->values()->all(),
        );
    }
}

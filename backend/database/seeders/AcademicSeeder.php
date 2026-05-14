<?php

namespace Database\Seeders;

use App\Models\ClassSection;
use App\Models\Cohort;
use App\Models\Course;
use App\Models\CourseLearningOutcome;
use App\Models\Enrollment;
use App\Models\GradeComponent;
use App\Models\GradeEntry;
use App\Models\Program;
use App\Models\ProgramLearningOutcome;
use App\Models\Skill;
use App\Models\Term;
use App\Models\User;
use Illuminate\Database\Seeder;


/**
 * Seeds academic-side enrollment data:
 *  - ClassSection (lớp học phần) per (core course × term × cohort) with a lecturer
 *  - Enrollment with enrollment_source='academic' for every student in the cohort
 *
 * Runs after CourseSeeder (in DatabaseSeeder) and OrgAcademicSeeder so all
 * dependencies (cohorts, terms, core courses, lecturers) exist.
 */
class AcademicSeeder extends Seeder
{
    public function run(): void
    {
        $currentTerm = Term::query()
            ->where('is_current', true)
            ->orderByDesc('id')
            ->first()
            ?? Term::query()->orderByDesc('id')->first();

        if (!$currentTerm) {
            $this->command?->warn('Bỏ qua AcademicSeeder: chưa có Term nào.');
            return;
        }

        $cohorts = Cohort::query()->get();
        if ($cohorts->isEmpty()) {
            $this->command?->warn('Bỏ qua AcademicSeeder: chưa có Cohort nào.');
            return;
        }

        // Chỉ tạo class_section mẫu cho core courses có category_id (4 courses gốc trong DatabaseSeeder.seedCourses).
        // Courses từ TrainingProgramSeeder (CTĐT) không có category_id và sẽ được tạo class_section
        // thông qua UI/quy trình mở lớp tín chỉ theo kỳ thực tế.
        $coreCourses = Course::query()
            ->where('course_mode', 'core')
            ->where('status', 'published')
            ->whereNotNull('category_id')
            ->get();

        if ($coreCourses->isEmpty()) {
            $this->command?->warn('Bỏ qua AcademicSeeder: chưa có core course nào.');
            return;
        }

        $lecturerPool = User::query()
            ->whereNotNull('staff_code')
            ->where('user_type', 'instructor')
            ->orderBy('id')
            ->get();

        $totalSections = 0;
        $totalEnrollments = 0;
        $skippedEnrollments = 0;

        foreach ($cohorts as $cohort) {
            $cohortCourses = $coreCourses->filter(function (Course $course) use ($cohort) {
                if ($course->program_id && $course->program_id !== $cohort->program_id) {
                    return false;
                }
                if ($course->major_id && $cohort->major_id && $course->major_id !== $cohort->major_id) {
                    return false;
                }
                return true;
            });

            if ($cohortCourses->isEmpty()) {
                continue;
            }

            $studentIds = User::query()
                ->where('cohort_id', $cohort->id)
                ->pluck('id');

            foreach ($cohortCourses->values() as $idx => $course) {
                $lecturer = $lecturerPool->isEmpty()
                    ? null
                    : $lecturerPool[($cohort->id + $idx) % $lecturerPool->count()];

                $sectionCode = sprintf('%s-%s-%s', $course->id, $currentTerm->code, $cohort->code);

                $section = ClassSection::query()->updateOrCreate(
                    [
                        'course_id' => $course->id,
                        'term_id' => $currentTerm->id,
                        'code' => $sectionCode,
                    ],
                    [
                        'cohort_id' => $cohort->id,
                        'lecturer_id' => $lecturer?->id,
                        'name' => "{$course->title} - {$cohort->code}",
                        'capacity' => max(40, $studentIds->count()),
                        'enrolled_count' => $studentIds->count(),
                        'status' => 'open',
                        'description' => "Lớp học phần demo cho khóa {$cohort->code}, kỳ {$currentTerm->code}.",
                    ]
                );
                $totalSections++;

                foreach ($studentIds as $studentId) {
                    $existing = Enrollment::query()
                        ->where('user_id', $studentId)
                        ->where('course_id', $course->id)
                        ->first();

                    if ($existing) {
                        $existing->update([
                            'term_id' => $currentTerm->id,
                            'cohort_id' => $cohort->id,
                            'class_section_id' => $section->id,
                            'enrollment_source' => 'academic',
                        ]);
                        $skippedEnrollments++;
                        continue;
                    }

                    Enrollment::query()->create([
                        'user_id' => $studentId,
                        'course_id' => $course->id,
                        'term_id' => $currentTerm->id,
                        'cohort_id' => $cohort->id,
                        'class_section_id' => $section->id,
                        'enrollment_source' => 'academic',
                        'enrolled_at' => now()->subDays(20),
                    ]);
                    $totalEnrollments++;
                }
            }
        }

        $this->command?->info(sprintf(
            'AcademicSeeder: %d class sections, %d new academic enrollments (%d existing aligned).',
            $totalSections,
            $totalEnrollments,
            $skippedEnrollments,
        ));

        $this->seedGradeComponents($coreCourses);
        $this->seedSampleGrades();
        $this->seedLearningOutcomes($coreCourses);
        $this->seedSkills();
    }

    /**
     * Standard 3-component template for every core course:
     *  Chuyên cần 10% — max 10
     *  Giữa kỳ 30%    — max 10
     *  Cuối kỳ 60%    — max 10
     */
    private function seedGradeComponents($coreCourses): void
    {
        $template = [
            ['name' => 'Chuyên cần', 'weight' => 10, 'max_score' => 10, 'position' => 1],
            ['name' => 'Giữa kỳ',    'weight' => 30, 'max_score' => 10, 'position' => 2],
            ['name' => 'Cuối kỳ',    'weight' => 60, 'max_score' => 10, 'position' => 3],
        ];

        foreach ($coreCourses as $course) {
            foreach ($template as $row) {
                GradeComponent::query()->updateOrCreate(
                    ['course_id' => $course->id, 'name' => $row['name']],
                    array_merge($row, ['course_id' => $course->id, 'is_required' => true]),
                );
            }
        }
    }

    /**
     * Fills in sample grades for ~70% of academic enrollments so the gradebook
     * and transcript views have something to show out of the box.
     */
    private function seedSampleGrades(): void
    {
        $components = GradeComponent::query()->get()->groupBy('course_id');
        $instructor = User::query()
            ->where('user_type', 'instructor')
            ->orderBy('id')
            ->first();

        $enrollments = Enrollment::query()
            ->where('enrollment_source', 'academic')
            ->get();

        $created = 0;
        foreach ($enrollments as $enrollment) {
            $courseComponents = $components->get($enrollment->course_id);
            if (!$courseComponents || $courseComponents->isEmpty()) continue;

            // 70% of enrollments get full grade entries; rest stay ungraded.
            if (($enrollment->id % 10) >= 7) continue;

            foreach ($courseComponents as $component) {
                // Vary scores a bit but keep them passing.
                $base = match ($component->name) {
                    'Chuyên cần' => mt_rand(8, 10),
                    'Giữa kỳ'    => mt_rand(60, 90) / 10,
                    'Cuối kỳ'    => mt_rand(50, 92) / 10,
                    default      => 7.5,
                };

                GradeEntry::query()->updateOrCreate(
                    [
                        'enrollment_id' => $enrollment->id,
                        'grade_component_id' => $component->id,
                    ],
                    [
                        'score' => $base,
                        'graded_by' => $instructor?->id,
                        'graded_at' => now()->subDays(mt_rand(1, 14)),
                    ],
                );
                $created++;
            }
        }

        $this->command?->info("AcademicSeeder: seeded {$created} grade entries.");
    }

    private function seedLearningOutcomes($coreCourses): void
    {
        $itProgram = Program::query()->where('code', 'CNTT')->first();
        if ($itProgram) {
            $plos = [
                ['code' => 'PLO1', 'description' => 'Hiểu nguyên lý cơ bản của ngành CNTT.', 'level' => 'knowledge'],
                ['code' => 'PLO2', 'description' => 'Phân tích, thiết kế và triển khai phần mềm.', 'level' => 'skill'],
                ['code' => 'PLO3', 'description' => 'Làm việc nhóm, giao tiếp kỹ thuật bằng tiếng Anh.', 'level' => 'skill'],
                ['code' => 'PLO4', 'description' => 'Đạo đức nghề nghiệp và học tập suốt đời.', 'level' => 'attitude'],
            ];
            foreach ($plos as $idx => $row) {
                ProgramLearningOutcome::query()->updateOrCreate(
                    ['program_id' => $itProgram->id, 'code' => $row['code']],
                    array_merge($row, ['program_id' => $itProgram->id, 'position' => $idx + 1]),
                );
            }
        }

        foreach ($coreCourses as $course) {
            $clos = [
                ['code' => 'CLO1', 'description' => "Hiểu nội dung cốt lõi của {$course->title}."],
                ['code' => 'CLO2', 'description' => "Áp dụng kiến thức vào tình huống thực tế."],
                ['code' => 'CLO3', 'description' => "Phối hợp nhóm hoàn thành dự án nhỏ."],
            ];
            foreach ($clos as $idx => $row) {
                CourseLearningOutcome::query()->updateOrCreate(
                    ['course_id' => $course->id, 'code' => $row['code']],
                    array_merge($row, ['course_id' => $course->id, 'position' => $idx + 1]),
                );
            }
        }
    }

    private function seedSkills(): void
    {
        $skillBlueprints = [
            ['code' => 'PHP', 'name' => 'PHP', 'category' => 'backend'],
            ['code' => 'LARAVEL', 'name' => 'Laravel', 'category' => 'backend'],
            ['code' => 'PYTHON', 'name' => 'Python', 'category' => 'backend'],
            ['code' => 'JS', 'name' => 'JavaScript', 'category' => 'frontend'],
            ['code' => 'VUE', 'name' => 'Vue.js', 'category' => 'frontend'],
            ['code' => 'NUXT', 'name' => 'Nuxt', 'category' => 'frontend'],
            ['code' => 'SQL', 'name' => 'SQL', 'category' => 'database'],
            ['code' => 'DOCKER', 'name' => 'Docker', 'category' => 'devops'],
            ['code' => 'COMM', 'name' => 'Giao tiếp kỹ thuật', 'category' => 'soft'],
            ['code' => 'TEAMWORK', 'name' => 'Làm việc nhóm', 'category' => 'soft'],
        ];

        $skills = collect($skillBlueprints)->map(fn ($row) => Skill::query()->updateOrCreate(
            ['code' => $row['code']],
            $row,
        ));

        // Tag every published course with 2-4 random skills so the recommender has data.
        $allCourses = Course::query()->where('status', 'published')->get();
        foreach ($allCourses as $course) {
            $picked = $skills->random(min(3, $skills->count()));
            $sync = [];
            foreach ($picked as $skill) {
                $sync[$skill->id] = ['weight' => 1.0];
            }
            $course->skills()->syncWithoutDetaching($sync);
        }
    }
}

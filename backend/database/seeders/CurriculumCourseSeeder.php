<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Curriculum;
use App\Models\CurriculumCourse;
use Illuminate\Database\Seeder;

/**
 * Skeleton CTĐT cố định: gán mọi core course CNTT vào curriculum 'CTDT-CNTT'
 * theo round-robin 8 kỳ. Sẽ replace bằng dữ liệu thật khi user gửi sheet.
 *
 * Khi sheet đến: thay run() bằng parse CSV + upsert theo (curriculum_id, course_id).
 */
class CurriculumCourseSeeder extends Seeder
{
    public function run(): void
    {
        $curricula = Curriculum::query()->get()->keyBy('code');
        if ($curricula->isEmpty()) {
            $this->command?->warn('CurriculumCourseSeeder: chưa có curriculum nào.');
            return;
        }

        // Tạm dùng core courses (course_mode='core') để fill skeleton.
        // Sau khi có sheet, courses sẽ được tạo theo đúng môn của CTĐT.
        $coreCourses = Course::query()
            ->where('course_mode', 'core')
            ->where('status', 'published')
            ->orderBy('id')
            ->get();

        if ($coreCourses->isEmpty()) {
            $this->command?->warn('CurriculumCourseSeeder: chưa có core course nào.');
            return;
        }

        $count = 0;
        foreach ($curricula as $code => $curriculum) {
            // Mỗi curriculum nhận tất cả core course với term_number xoay vòng 1..8
            foreach ($coreCourses as $idx => $course) {
                $termNumber = ($idx % 8) + 1;
                CurriculumCourse::query()->updateOrCreate(
                    ['curriculum_id' => $curriculum->id, 'course_id' => $course->id],
                    [
                        'term_number' => $termNumber,
                        'is_required' => true,
                        'credits' => $course->credit_value ?: 3,
                        'position' => $idx,
                        'notes' => 'Seed skeleton — sẽ replace bằng dữ liệu sheet thật.',
                    ]
                );
                $count++;
            }
        }

        $this->command?->info("CurriculumCourseSeeder: seeded {$count} curriculum_course rows.");
    }
}

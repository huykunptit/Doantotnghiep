<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Curriculum;
use App\Models\CurriculumCourse;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Parse chuong_trinh_hoc.json (sao chép tại database/seeders/data/training_programs.json)
 * thành courses + curriculum_courses cho 9 chuyên ngành.
 *
 *  - Môn cố định: dedup theo title (1 row courses cho tất cả CTĐT dùng môn đó)
 *  - Môn tự chọn (có `tu_chon`): tạo placeholder course riêng cho từng (curriculum × kỳ × slot),
 *    list option lưu vào curriculum_courses.notes (JSON).
 */
class TrainingProgramSeeder extends Seeder
{
    /** Map program id trong JSON → program code trong DB */
    private const PROGRAM_MAP = [
        'CNTT' => 'CNTT',
        'QTKD' => 'QTKD',
        'KTDTVT' => 'DTVT',
    ];

    /** Map (chuẩn hóa code) chuyên ngành để remove diacritic */
    private const MAJOR_CODE_FIX = [
        'TTVTDĐ' => 'TTVTDD',
    ];

    public function run(): void
    {
        $jsonPath = database_path('seeders/data/training_programs.json');
        if (!is_file($jsonPath)) {
            $this->command?->error("TrainingProgramSeeder: không tìm thấy file {$jsonPath}");
            return;
        }

        $data = json_decode(file_get_contents($jsonPath), true);
        if (!isset($data['nganh']) || !is_array($data['nganh'])) {
            $this->command?->error('TrainingProgramSeeder: JSON sai cấu trúc, thiếu "nganh".');
            return;
        }

        $defaultInstructor = User::query()
            ->where('user_type', 'instructor')
            ->orderBy('id')
            ->first()
            ?? User::query()->where('user_type', 'admin')->first();

        if (!$defaultInstructor) {
            $this->command?->error('TrainingProgramSeeder: chưa có user nào làm owner cho courses.');
            return;
        }

        $loaiLabels = $data['mo_ta_loai'] ?? [];

        $courseCache = []; // [normalized title] => Course
        $stats = ['courses_created' => 0, 'courses_reused' => 0, 'curriculum_courses' => 0, 'electives' => 0, 'curricula_missing' => 0];

        foreach ($data['nganh'] as $progEntry) {
            $progJsonId = $progEntry['id'] ?? null;
            $progCode = self::PROGRAM_MAP[$progJsonId] ?? null;
            if (!$progCode) {
                $this->command?->warn("TrainingProgramSeeder: bỏ qua program JSON id '{$progJsonId}' (chưa map).");
                continue;
            }

            // BA 2026: mỗi chương trình chỉ còn 1 chuyên ngành = 1 CTĐT.
            $program = \App\Models\Program::query()->where('code', $progCode)->first();
            $curriculum = $program
                ? Curriculum::query()->where('program_id', $program->id)->orderBy('id')->first()
                : Curriculum::query()->where('code', 'CTDT-' . $progCode)->first();
            if (!$curriculum) {
                $this->command?->warn("TrainingProgramSeeder: không tìm thấy curriculum cho chương trình {$progCode}, bỏ qua.");
                $stats['curricula_missing']++;
                continue;
            }

            // Lấy chuyên ngành đầu tiên trong JSON làm CTĐT đại diện cho cả chương trình
            $chuyenNganh = ($progEntry['chuyen_nganh'] ?? [])[0] ?? null;
            if ($chuyenNganh) {
                $majorCode = $progCode;

                foreach (($chuyenNganh['hoc_ky'] ?? []) as $termEntry) {
                    $termNumber = (int) ($termEntry['hoc_ky'] ?? 0);
                    if ($termNumber < 1) continue;

                    $position = 0;
                    foreach (($termEntry['mon_hoc'] ?? []) as $mon) {
                        $tenMon = trim($mon['ten'] ?? '');
                        if ($tenMon === '') continue;

                        $tinChi = (int) ($mon['tin_chi'] ?? 0);
                        $loai = $mon['loai'] ?? null;
                        $tuChon = $mon['tu_chon'] ?? null;
                        $isElective = is_array($tuChon) && count($tuChon) > 0;

                        if ($isElective) {
                            // Placeholder course riêng cho từng slot trong từng curriculum
                            $placeholderTitle = sprintf('%s — %s (kỳ %d)', $tenMon, $majorCode, $termNumber);
                            $course = $this->upsertCourse(
                                $placeholderTitle,
                                $tinChi,
                                $defaultInstructor->id,
                                $loaiLabels[$loai] ?? $loai,
                                $courseCache,
                                $stats,
                            );

                            $notes = json_encode([
                                'type' => 'elective_slot',
                                'placeholder' => $tenMon,
                                'loai' => $loai,
                                'loai_label' => $loaiLabels[$loai] ?? null,
                                'options' => $tuChon,
                            ], JSON_UNESCAPED_UNICODE);
                            $stats['electives']++;
                        } else {
                            $course = $this->upsertCourse(
                                $tenMon,
                                $tinChi,
                                $defaultInstructor->id,
                                $loaiLabels[$loai] ?? $loai,
                                $courseCache,
                                $stats,
                            );

                            $notes = json_encode([
                                'loai' => $loai,
                                'loai_label' => $loaiLabels[$loai] ?? null,
                            ], JSON_UNESCAPED_UNICODE);
                        }

                        CurriculumCourse::query()->updateOrCreate(
                            ['curriculum_id' => $curriculum->id, 'course_id' => $course->id],
                            [
                                'term_number' => $termNumber,
                                'is_required' => !$isElective,
                                'credits' => $tinChi,
                                'position' => $position++,
                                'notes' => $notes,
                            ]
                        );
                        $stats['curriculum_courses']++;
                    }
                }
            }
        }

        $this->command?->info(sprintf(
            'TrainingProgramSeeder: %d courses (mới: %d, dùng lại: %d), %d curriculum_courses (%d slot tự chọn).',
            $stats['courses_created'] + $stats['courses_reused'],
            $stats['courses_created'],
            $stats['courses_reused'],
            $stats['curriculum_courses'],
            $stats['electives']
        ));
        if ($stats['curricula_missing'] > 0) {
            $this->command?->warn("Có {$stats['curricula_missing']} chuyên ngành không tìm được curriculum tương ứng.");
        }
    }

    /**
     * Tạo hoặc lấy course theo title (dedup không phân biệt hoa thường/khoảng trắng).
     */
    private function upsertCourse(
        string $title,
        int $credits,
        int $ownerId,
        ?string $loaiLabel,
        array &$cache,
        array &$stats
    ): Course {
        $key = $this->normalizeTitle($title);
        if (isset($cache[$key])) {
            $stats['courses_reused']++;
            return $cache[$key];
        }

        $existing = Course::query()->where('title', $title)->first();
        if ($existing) {
            $cache[$key] = $existing;
            $stats['courses_reused']++;
            return $existing;
        }

        $baseSlug = Str::slug($title);
        $slug = $baseSlug;
        $suffix = 1;
        while (Course::query()->where('slug', $slug)->exists()) {
            $slug = $baseSlug . '-' . (++$suffix);
        }

        $course = Course::query()->create([
            'user_id' => $ownerId,
            'category_id' => null,
            'title' => $title,
            'slug' => $slug,
            'description' => $loaiLabel ? "Học phần thuộc nhóm: {$loaiLabel}" : null,
            'price' => 0,
            'status' => 'published',
            'course_mode' => 'core',
            'is_credit_bearing' => true,
            'credit_value' => $credits > 0 ? $credits : null,
            'published_at' => now(),
        ]);

        $cache[$key] = $course;
        $stats['courses_created']++;
        return $course;
    }

    private function normalizeTitle(string $title): string
    {
        return preg_replace('/\s+/', ' ', mb_strtolower(trim($title)));
    }
}

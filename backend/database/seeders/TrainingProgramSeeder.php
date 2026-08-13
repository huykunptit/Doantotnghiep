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

        $instructors = User::query()
            ->where('email', 'like', 'instructor%@lms.com')
            ->orderBy('id')
            ->get();

        if ($instructors->isEmpty()) {
            $fallback = User::query()->where('email', 'admin@lms.com')->first()
                ?? User::query()->where('user_type', 'admin')->first();
            if (!$fallback) {
                $this->command?->error('TrainingProgramSeeder: chưa có user nào làm owner cho courses.');
                return;
            }
            $instructors = collect([$fallback]);
        }

        $loaiLabels = $data['mo_ta_loai'] ?? [];

        $categoryByProgram = [
            'CNTT' => \App\Models\Category::query()->where('slug', 'like', 'cong-nghe-thong-tin%')->value('id'),
            'QTKD' => \App\Models\Category::query()->where('slug', 'like', 'quan-tri-kinh-doanh%')->value('id'),
            'DTVT' => \App\Models\Category::query()->where('slug', 'like', 'dien-tu-vien-thong%')->value('id'),
        ];

        $courseCache = []; // [normalized title] => Course
        $stats = ['courses_created' => 0, 'courses_reused' => 0, 'curriculum_courses' => 0, 'electives' => 0, 'curricula_missing' => 0];
        $ownerCursor = 0;

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

            $categoryId = $categoryByProgram[$progCode] ?? null;

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
                            $owner = $instructors[$ownerCursor % $instructors->count()];
                            $ownerCursor++;
                            $course = $this->upsertCourse(
                                $placeholderTitle,
                                $tinChi,
                                (int) $owner->id,
                                $loaiLabels[$loai] ?? $loai,
                                $courseCache,
                                $stats,
                                $program?->id,
                                $categoryId,
                                $curriculum->id,
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
                            $owner = $instructors[$ownerCursor % $instructors->count()];
                            $ownerCursor++;
                            $course = $this->upsertCourse(
                                $tenMon,
                                $tinChi,
                                (int) $owner->id,
                                $loaiLabels[$loai] ?? $loai,
                                $courseCache,
                                $stats,
                                $program?->id,
                                $categoryId,
                                $curriculum->id,
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
        array &$stats,
        ?int $programId = null,
        ?int $categoryId = null,
        ?int $curriculumId = null,
    ): Course {
        $key = $this->normalizeTitle($title);
        if (isset($cache[$key])) {
            $course = $cache[$key];
            $patch = [];
            if (empty($course->thumbnail)) {
                $patch['thumbnail'] = $this->fakeThumbnail($title, (int) $course->id);
            }
            if ($programId && !$course->program_id) {
                $patch['program_id'] = $programId;
            }
            if ($categoryId && !$course->category_id) {
                $patch['category_id'] = $categoryId;
            }
            if ($curriculumId && !$course->curriculum_id) {
                $patch['curriculum_id'] = $curriculumId;
            }
            if ($patch !== []) {
                $course->update($patch);
                $course->refresh();
            }
            $stats['courses_reused']++;
            return $course;
        }

        $existing = Course::query()->where('title', $title)->first();
        if ($existing) {
            $patch = ['user_id' => $ownerId];
            if (empty($existing->thumbnail)) {
                $patch['thumbnail'] = $this->fakeThumbnail($title, (int) $existing->id);
            }
            if ($programId && !$existing->program_id) {
                $patch['program_id'] = $programId;
            }
            if ($categoryId && !$existing->category_id) {
                $patch['category_id'] = $categoryId;
            }
            if ($curriculumId && !$existing->curriculum_id) {
                $patch['curriculum_id'] = $curriculumId;
            }
            $existing->update($patch);
            $existing->refresh();
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
            'category_id' => $categoryId,
            'program_id' => $programId,
            'curriculum_id' => $curriculumId,
            'title' => $title,
            'slug' => $slug,
            'description' => $loaiLabel ? "Học phần thuộc nhóm: {$loaiLabel}" : null,
            'price' => 0,
            'status' => 'published',
            'course_mode' => 'core',
            'is_credit_bearing' => true,
            'credit_value' => $credits > 0 ? $credits : null,
            'thumbnail' => $this->fakeThumbnail($title, 0),
            'published_at' => now(),
        ]);

        // Re-pick with stable id so siblings look different
        $course->update(['thumbnail' => $this->fakeThumbnail($title, (int) $course->id)]);

        $cache[$key] = $course->fresh();
        $stats['courses_created']++;
        return $cache[$key];
    }

    private function fakeThumbnail(string $title, int $seed): string
    {
        $t = mb_strtolower($title);
        $pools = [
            'ai' => [
                'https://images.unsplash.com/photo-1677442136019-21780ecad995?w=1200&q=80',
                'https://images.unsplash.com/photo-1620712943543-bcc4688e7485?w=1200&q=80',
            ],
            'network' => [
                'https://images.unsplash.com/photo-1558494949-ef010cbdcc31?w=1200&q=80',
                'https://images.unsplash.com/photo-1544197150-b99a580bb7a8?w=1200&q=80',
            ],
            'software' => [
                'https://images.unsplash.com/photo-1555066931-4365d14bab8c?w=1200&q=80',
                'https://images.unsplash.com/photo-1461749280684-dccba630e2f6?w=1200&q=80',
            ],
            'db' => [
                'https://images.unsplash.com/photo-1544197150-b99a580bb7a8?w=1200&q=80',
                'https://images.unsplash.com/photo-1451187580459-43490279c0fa?w=1200&q=80',
            ],
            'web' => [
                'https://images.unsplash.com/photo-1547658719-da2b51169166?w=1200&q=80',
                'https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=1200&q=80',
            ],
            'english' => [
                'https://images.unsplash.com/photo-1543109740-4bdb38fda756?w=1200&q=80',
                'https://images.unsplash.com/photo-1456513080510-7bf3a84b82f8?w=1200&q=80',
            ],
            'math' => [
                'https://images.unsplash.com/photo-1635070041078-e363dbe005cb?w=1200&q=80',
                'https://images.unsplash.com/photo-1509228468518-180dd4864904?w=1200&q=80',
            ],
            'default' => [
                'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=1200&q=80',
                'https://images.unsplash.com/photo-1522542550221-31fd19575a2d?w=1200&q=80',
                'https://images.unsplash.com/photo-1504639725590-34d0984388bd?w=1200&q=80',
                'https://images.unsplash.com/photo-1536148935331-408321065b18?w=1200&q=80',
                'https://images.unsplash.com/photo-1515879218367-8466d910aaa4?w=1200&q=80',
                'https://images.unsplash.com/photo-1581091226825-a6a2a5aee158?w=1200&q=80',
            ],
        ];

        $pool = match (true) {
            str_contains($t, 'trí tuệ') || str_contains($t, ' ai') || str_contains($t, 'học máy') => $pools['ai'],
            str_contains($t, 'mạng') || str_contains($t, 'iot') || str_contains($t, 'an ninh') => $pools['network'],
            str_contains($t, 'cơ sở dữ liệu') || str_contains($t, 'dữ liệu') || str_contains($t, 'database') => $pools['db'],
            str_contains($t, 'web') || str_contains($t, 'nuxt') || str_contains($t, 'frontend') => $pools['web'],
            str_contains($t, 'phần mềm') || str_contains($t, 'lập trình') || str_contains($t, 'python') || str_contains($t, 'java') => $pools['software'],
            str_contains($t, 'tiếng anh') || str_contains($t, 'english') => $pools['english'],
            str_contains($t, 'toán') || str_contains($t, 'giải tích') || str_contains($t, 'đại số') || str_contains($t, 'xác suất') => $pools['math'],
            default => $pools['default'],
        };

        return $pool[abs($seed) % count($pool)];
    }

    private function normalizeTitle(string $title): string
    {
        return preg_replace('/\s+/', ' ', mb_strtolower(trim($title)));
    }
}

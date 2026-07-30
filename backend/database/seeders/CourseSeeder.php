<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Course;
use App\Models\Curriculum;
use App\Models\Lesson;
use App\Models\Program;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Tạo courses (core + extension) và cập nhật thumbnail / video_url.
 * Gộp logic từ: DatabaseSeeder::seedCourses(), CourseThumbnailSeeder, MediaResourceSeeder.
 *
 * Fix bug: curriculum lookup dùng program_id thay vì hard-code code CTDT-CNTT.
 */
class CourseSeeder extends Seeder
{
    // ── Thumbnail map: category slug → [core, thực chiến, chuyên sâu, dự án, …] ──
    private array $thumbMap = [
        'cong-nghe-thong-tin' => [
            'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=1200&q=80',
            'https://images.unsplash.com/photo-1461749280684-dccba630e2f6?w=1200&q=80',
            'https://images.unsplash.com/photo-1504639725590-34d0984388bd?w=1200&q=80',
            'https://images.unsplash.com/photo-1555066931-4365d14bab8c?w=1200&q=80',
        ],
        'lap-trinh-phan-mem' => [
            'https://images.unsplash.com/photo-1547658719-da2b51169166?w=1200&q=80',
            'https://images.unsplash.com/photo-1512941937669-90a1b58e7e9c?w=1200&q=80',
            'https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=1200&q=80',
        ],
        'csdl-he-thong' => [
            'https://images.unsplash.com/photo-1544197150-b99a580bb7a8?w=1200&q=80',
            'https://images.unsplash.com/photo-1667372393119-3d4c48d07fc9?w=1200&q=80',
        ],
        'tri-tue-nhan-tao' => [
            'https://images.unsplash.com/photo-1677442136019-21780ecad995?w=1200&q=80',
            'https://images.unsplash.com/photo-1620712943543-bcc4688e7485?w=1200&q=80',
        ],
        'quan-tri-kinh-doanh' => [
            'https://images.unsplash.com/photo-1460925895917-afdab827c52f?w=1200&q=80',
            'https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?w=1200&q=80',
            'https://images.unsplash.com/photo-1552664730-d307ca884978?w=1200&q=80',
        ],
        'marketing-thuong-mai' => [
            'https://images.unsplash.com/photo-1533750349088-cd871a92f312?w=1200&q=80',
            'https://images.unsplash.com/photo-1611926653458-09294b3142bf?w=1200&q=80',
        ],
        'quan-ly-du-an'  => [
            'https://images.unsplash.com/photo-1507925921958-8a62f3d1a50d?w=1200&q=80',
            'https://images.unsplash.com/photo-1542626991-cbc4e32524cc?w=1200&q=80',
        ],
        'dien-tu-vien-thong' => [
            'https://images.unsplash.com/photo-1518770660439-4636190af475?w=1200&q=80',
            'https://images.unsplash.com/photo-1581091226825-a6a2a5aee158?w=1200&q=80',
            'https://images.unsplash.com/photo-1558494949-ef010cbdcc31?w=1200&q=80',
        ],
        'mang-vien-thong' => [
            'https://images.unsplash.com/photo-1558494949-ef010cbdcc31?w=1200&q=80',
            'https://images.unsplash.com/photo-1544197150-b99a580bb7a8?w=1200&q=80',
        ],
        'dien-tu-iot' => [
            'https://images.unsplash.com/photo-1518770660439-4636190af475?w=1200&q=80',
            'https://images.unsplash.com/photo-1581091226825-a6a2a5aee158?w=1200&q=80',
        ],
        // Legacy aliases (nếu còn khóa cũ)
        'lap-trinh-cntt' => [
            'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=1200&q=80',
            'https://images.unsplash.com/photo-1461749280684-dccba630e2f6?w=1200&q=80',
        ],
        'kinh-doanh'     => ['https://images.unsplash.com/photo-1460925895917-afdab827c52f?w=1200&q=80','https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?w=1200&q=80'],
        'thiet-ke'       => ['https://images.unsplash.com/photo-1518770660439-4636190af475?w=1200&q=80','https://images.unsplash.com/photo-1581091226825-a6a2a5aee158?w=1200&q=80'],
        'ngoai-ngu'      => ['https://images.unsplash.com/photo-1518770660439-4636190af475?w=1200&q=80'],
    ];

    private array $fallbackThumbs = [
        'https://images.unsplash.com/photo-1522542550221-31fd19575a2d?w=1200&q=80',
        'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=1200&q=80',
        'https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=1200&q=80',
        'https://images.unsplash.com/photo-1460925895917-afdab827c52f?w=1200&q=80',
    ];

    // ── Video map: asset slug → category slug → YouTube URL ──────────────────
    private array $videoMap = [
        'intro-overview'       => ['lap-trinh-cntt'=>'https://www.youtube.com/watch?v=zOjov-2OZ0E','web-dev'=>'https://www.youtube.com/watch?v=zOjov-2OZ0E','database'=>'https://www.youtube.com/watch?v=HXV3zeQKqGY','devops'=>'https://www.youtube.com/watch?v=a-8MPGzrChQ','mobile-dev'=>'https://www.youtube.com/watch?v=0-S5a0eXPoc','thiet-ke'=>'https://www.youtube.com/watch?v=c9Wg6Cb_YlU','ui-ux'=>'https://www.youtube.com/watch?v=c9Wg6Cb_YlU','do-hoa'=>'https://www.youtube.com/watch?v=3_PnuDQ4pNg','kinh-doanh'=>'https://www.youtube.com/watch?v=Vbn3fNPP0Hk','marketing'=>'https://www.youtube.com/watch?v=bixR-KIJKYM','quan-ly-du-an'=>'https://www.youtube.com/watch?v=gT5BBwi8aTk','ngoai-ngu'=>'https://www.youtube.com/watch?v=1MVKjd_FVG4','tieng-anh'=>'https://www.youtube.com/watch?v=1MVKjd_FVG4','tieng-nhat'=>'https://www.youtube.com/watch?v=rGrBHiuPlT0','_default'=>'https://www.youtube.com/watch?v=zOjov-2OZ0E'],
        'setup-environment'    => ['lap-trinh-cntt'=>'https://www.youtube.com/watch?v=bMknfKXIFA8','web-dev'=>'https://www.youtube.com/watch?v=bMknfKXIFA8','database'=>'https://www.youtube.com/watch?v=uj4OYk5nKCg','devops'=>'https://www.youtube.com/watch?v=pg19Z8LL06w','mobile-dev'=>'https://www.youtube.com/watch?v=1ukSR1GRtMU','thiet-ke'=>'https://www.youtube.com/watch?v=Cx2dkpBxst8','ui-ux'=>'https://www.youtube.com/watch?v=Cx2dkpBxst8','do-hoa'=>'https://www.youtube.com/watch?v=ysz5S6PUM-U','kinh-doanh'=>'https://www.youtube.com/watch?v=qz0aGYrrlhU','ngoai-ngu'=>'https://www.youtube.com/watch?v=WNSRFTbAAPk','_default'=>'https://www.youtube.com/watch?v=bMknfKXIFA8'],
        'core-concepts'        => ['lap-trinh-cntt'=>'https://www.youtube.com/watch?v=SqcY0GlETPk','web-dev'=>'https://www.youtube.com/watch?v=SqcY0GlETPk','database'=>'https://www.youtube.com/watch?v=p3qvj9hO_Bo','devops'=>'https://www.youtube.com/watch?v=kTp5xUtcalw','mobile-dev'=>'https://www.youtube.com/watch?v=x0uinJvhNxI','thiet-ke'=>'https://www.youtube.com/watch?v=II-6dDzc-80','ui-ux'=>'https://www.youtube.com/watch?v=II-6dDzc-80','do-hoa'=>'https://www.youtube.com/watch?v=x3zJ0DqKJtQ','kinh-doanh'=>'https://www.youtube.com/watch?v=mEqu4S5FP_Y','marketing'=>'https://www.youtube.com/watch?v=bixR-KIJKYM','ngoai-ngu'=>'https://www.youtube.com/watch?v=V_TnqZoHkFM','tieng-anh'=>'https://www.youtube.com/watch?v=V_TnqZoHkFM','tieng-nhat'=>'https://www.youtube.com/watch?v=6p9Il_j0zjc','_default'=>'https://www.youtube.com/watch?v=SqcY0GlETPk'],
        'practice-walkthrough' => ['lap-trinh-cntt'=>'https://www.youtube.com/watch?v=G3e-cpL7ofc','web-dev'=>'https://www.youtube.com/watch?v=G3e-cpL7ofc','database'=>'https://www.youtube.com/watch?v=7S_tz1z_5bA','devops'=>'https://www.youtube.com/watch?v=fqMOX6JJhGo','mobile-dev'=>'https://www.youtube.com/watch?v=tye0zhHMl5A','thiet-ke'=>'https://www.youtube.com/watch?v=4W4LvJnNegA','ui-ux'=>'https://www.youtube.com/watch?v=4W4LvJnNegA','do-hoa'=>'https://www.youtube.com/watch?v=r9sVwRVCDC8','kinh-doanh'=>'https://www.youtube.com/watch?v=XB5OUQO6O_k','ngoai-ngu'=>'https://www.youtube.com/watch?v=HMq2BvAoFns','tieng-nhat'=>'https://www.youtube.com/watch?v=6p9Il_j0zjc','_default'=>'https://www.youtube.com/watch?v=G3e-cpL7ofc'],
        'live-workshop'        => ['lap-trinh-cntt'=>'https://www.youtube.com/watch?v=PlxWf493en4','web-dev'=>'https://www.youtube.com/watch?v=PlxWf493en4','database'=>'https://www.youtube.com/watch?v=9GIbChqqMT0','devops'=>'https://www.youtube.com/watch?v=LFJfC73g2RE','mobile-dev'=>'https://www.youtube.com/watch?v=CDhaS04_kkk','thiet-ke'=>'https://www.youtube.com/watch?v=Oi9ciYM3Rk8','kinh-doanh'=>'https://www.youtube.com/watch?v=v80TV8XBOBE','ngoai-ngu'=>'https://www.youtube.com/watch?v=bCBd_M4aIfA','_default'=>'https://www.youtube.com/watch?v=PlxWf493en4'],
    ];

    public function run(): void
    {
        $this->createCourses();
        $this->refreshThumbnails();
        $this->refreshVideoUrls();
    }

    // ── 1. Tạo core + extension courses ──────────────────────────────────────

    private function createCourses(): void
    {
        $rootCategories = Category::query()
            ->whereNull('parent_id')
            ->with(['children' => fn($q) => $q->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();

        if ($rootCategories->isEmpty()) {
            $this->command?->warn('CourseSeeder: không có category nào.');
            return;
        }

        $instructors = UserSeeder::getInstructors();

        // Fix bug cũ: lấy curriculum đầu tiên của program CNTT, không hard-code code
        $itProgram = Program::query()->where('code', 'CNTT')->first();
        $itCurriculum = $itProgram
            ? Curriculum::query()->where('program_id', $itProgram->id)->first()
            : null;

        $extensionTemplates = [
            ['title' => 'Thực chiến', 'price' => 399000],
            ['title' => 'Chuyên sâu',  'price' => 549000],
            ['title' => 'Dự án cuối khóa', 'price' => 699000],
        ];

        $cursor = 0;

        foreach ($rootCategories as $rootCategory) {
            $leafCategories = $rootCategory->children->isNotEmpty()
                ? $rootCategory->children->values()
                : collect([$rootCategory]);

            // Core course (1 per root category)
            $coreTitle    = "Nhập môn {$rootCategory->name}";
            $coreCategory = $leafCategories->first();
            $coreInstructor = $instructors[$cursor % $instructors->count()];
            $cursor++;

            Course::query()->updateOrCreate(
                ['slug' => Str::slug($coreTitle)],
                [
                    'user_id'          => $coreInstructor->id,
                    'category_id'      => $coreCategory->id,
                    'program_id'       => $itProgram?->id,
                    'program_type_id'  => $itProgram?->program_type_id,
                    'major_id'         => null,
                    'curriculum_id'    => $itCurriculum?->id,
                    'title'            => $coreTitle,
                    'description'      => "Học phần chính quy: {$coreTitle}. Tự động ghi danh cho sinh viên khóa thuộc CTĐT.",
                    'price'            => 0,
                    'course_mode'      => 'core',
                    'is_credit_bearing'=> true,
                    'credit_value'     => 3,
                    'status'           => 'published',
                    'thumbnail'        => $this->resolveThumbnail($rootCategory->slug, 'core', 0),
                    'published_at'     => now()->subDays(30),
                ]
            );

            // Extension courses (3 per root category)
            foreach ($extensionTemplates as $tmplIndex => $template) {
                $title      = "{$template['title']} {$rootCategory->name}";
                $category   = $leafCategories[$tmplIndex % $leafCategories->count()];
                $instructor = $instructors[$cursor % $instructors->count()];
                $cursor++;

                Course::query()->updateOrCreate(
                    ['slug' => Str::slug($title)],
                    [
                        'user_id'          => $instructor->id,
                        'category_id'      => $category->id,
                        'program_id'       => null,
                        'program_type_id'  => null,
                        'major_id'         => null,
                        'curriculum_id'    => null,
                        'title'            => $title,
                        'description'      => "Khóa mở rộng: {$title}. Bán trên marketplace, có thể thanh toán qua PayOS.",
                        'price'            => $template['price'],
                        'course_mode'      => 'extension',
                        'is_credit_bearing'=> false,
                        'credit_value'     => null,
                        'status'           => 'published',
                        'thumbnail'        => $this->resolveThumbnail($rootCategory->slug, $template['title'], $tmplIndex + 1),
                        'published_at'     => now()->subDays(random_int(5, 25)),
                    ]
                );
            }
        }

        $this->command?->info('CourseSeeder: core + extension courses đã được tạo/cập nhật.');
    }

    // ── 2. Refresh thumbnails cho toàn bộ courses ────────────────────────────

    private function refreshThumbnails(): void
    {
        $updated = 0;
        Course::with('category.parent')->get()->each(function (Course $course) use (&$updated) {
            $cat      = $course->category;
            $catSlug  = $cat?->slug ?? '';
            $rootSlug = $cat?->parent?->slug ?? $catSlug;

            $pool = $this->thumbMap[$catSlug]
                ?? $this->thumbMap[$rootSlug]
                ?? $this->fallbackThumbs;

            $title = Str::lower($course->title);
            $thumb = match (true) {
                Str::contains($title, ['trí tuệ', 'học máy', ' ai', 'ai ']) => 'https://images.unsplash.com/photo-1677442136019-21780ecad995?w=1200&q=80',
                Str::contains($title, ['mạng máy tính', 'mạng', 'an ninh mạng', 'iot']) => 'https://images.unsplash.com/photo-1558494949-ef010cbdcc31?w=1200&q=80',
                Str::contains($title, ['cơ sở dữ liệu', 'dữ liệu', 'database']) => 'https://images.unsplash.com/photo-1544197150-b99a580bb7a8?w=1200&q=80',
                Str::contains($title, ['công nghệ phần mềm', 'phần mềm', 'devops']) => 'https://images.unsplash.com/photo-1555066931-4365d14bab8c?w=1200&q=80',
                Str::contains($title, ['web', 'frontend', 'html', 'css']) => 'https://images.unsplash.com/photo-1547658719-da2b51169166?w=1200&q=80',
                Str::contains($title, ['mobile', 'android', 'ios', 'flutter']) => 'https://images.unsplash.com/photo-1512941937669-90a1b58e7e9c?w=1200&q=80',
                Str::contains($title, ['tiếng anh', 'english']) => 'https://images.unsplash.com/photo-1543109740-4bdb38fda756?w=1200&q=80',
                Str::contains($title, ['toán', 'giải tích', 'đại số', 'xác suất']) => 'https://images.unsplash.com/photo-1635070041078-e363dbe005cb?w=1200&q=80',
                Str::contains($title, ['nhập môn', 'cơ bản', 'beginner']) => $pool[0],
                Str::contains($title, ['thực chiến', 'thực hành', 'thực tập']) => $pool[1 % count($pool)],
                Str::contains($title, ['chuyên sâu', 'pro', 'expert']) => $pool[2 % count($pool)],
                Str::contains($title, ['dự án', 'project', 'đồ án']) => $pool[3 % count($pool)],
                default => $pool[$course->id % count($pool)],
            };

            if (empty($course->thumbnail) || $course->thumbnail !== $thumb) {
                $course->update(['thumbnail' => $thumb]);
                $updated++;
            }
        });

        $this->command?->info("CourseSeeder: {$updated} thumbnail(s) đã được refresh.");
    }

    // ── 3. Refresh video_url cho lessons ─────────────────────────────────────

    private function refreshVideoUrls(): void
    {
        $updated = 0;
        Lesson::with('course.category.parent')
            ->whereIn('type', ['video', 'live'])
            ->get()
            ->each(function (Lesson $lesson) use (&$updated) {
                $catSlug  = $lesson->course?->category?->slug ?? '';
                $rootSlug = $lesson->course?->category?->parent?->slug ?? $catSlug;

                // Xác định asset từ title
                $title = Str::lower($lesson->title);
                $asset = match (true) {
                    Str::contains($title, ['tổng quan', 'giới thiệu', 'intro']) => 'intro-overview',
                    Str::contains($title, ['thiết lập', 'cài đặt', 'setup', 'môi trường']) => 'setup-environment',
                    Str::contains($title, ['kiến thức', 'nền tảng', 'core', 'concepts']) => 'core-concepts',
                    Str::contains($title, ['thực hành', 'walkthrough', 'ví dụ mẫu']) => 'practice-walkthrough',
                    Str::contains($title, ['live', 'workshop', 'chia sẻ']) => 'live-workshop',
                    default => null,
                };

                if ($asset && isset($this->videoMap[$asset])) {
                    $assetMap = $this->videoMap[$asset];
                    $url = $assetMap[$catSlug]
                        ?? $assetMap[$rootSlug]
                        ?? $assetMap['_default']
                        ?? 'https://www.youtube.com/watch?v=zOjov-2OZ0E';
                } else {
                    $url = 'https://www.youtube.com/watch?v=zOjov-2OZ0E';
                }

                $lesson->update(['video_url' => $url, 'video_status' => 'ready']);
                $updated++;
            });

        $this->command?->info("CourseSeeder: {$updated} lesson video_url(s) đã được refresh.");
    }

    // ── Helper ───────────────────────────────────────────────────────────────

    private function resolveThumbnail(string $rootSlug, string $titleHint, int $fallbackIndex): string
    {
        $pool = $this->thumbMap[$rootSlug] ?? $this->fallbackThumbs;
        return $pool[$fallbackIndex % count($pool)];
    }
}


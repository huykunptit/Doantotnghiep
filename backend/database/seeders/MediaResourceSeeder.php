<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Lesson;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Cập nhật video_url của lesson bằng YouTube links thực tế và thumbnail khóa học
 * với ảnh chất lượng cao từ Unsplash/Pexels.
 *
 * Chạy: php artisan db:seed --class=MediaResourceSeeder
 */
class MediaResourceSeeder extends Seeder
{
    // -----------------------------------------------------------------------
    // YouTube videos map: lesson asset slug → YouTube embed URL
    // Mỗi "asset" tương ứng với lesson blueprint trong CourseContentSeeder.
    // -----------------------------------------------------------------------
    private array $videoMap = [
        // --- Lập trình & CNTT (web-dev, database, devops, mobile-dev) ---
        'intro-overview' => [
            'lap-trinh-cntt' => 'https://www.youtube.com/watch?v=zOjov-2OZ0E', // Roadmap Web Dev
            'web-dev'        => 'https://www.youtube.com/watch?v=zOjov-2OZ0E',
            'database'       => 'https://www.youtube.com/watch?v=HXV3zeQKqGY', // SQL Tutorial
            'devops'         => 'https://www.youtube.com/watch?v=a-8MPGzrChQ', // DevOps Overview
            'mobile-dev'     => 'https://www.youtube.com/watch?v=0-S5a0eXPoc', // Flutter Intro
            'thiet-ke'       => 'https://www.youtube.com/watch?v=c9Wg6Cb_YlU', // UI/UX Intro
            'ui-ux'          => 'https://www.youtube.com/watch?v=c9Wg6Cb_YlU',
            'do-hoa'         => 'https://www.youtube.com/watch?v=3_PnuDQ4pNg', // Graphic Design
            'kinh-doanh'     => 'https://www.youtube.com/watch?v=Vbn3fNPP0Hk', // Business Intro
            'marketing'      => 'https://www.youtube.com/watch?v=bixR-KIJKYM', // Digital Marketing
            'quan-ly-du-an'  => 'https://www.youtube.com/watch?v=gT5BBwi8aTk', // Project Mgmt
            'ngoai-ngu'      => 'https://www.youtube.com/watch?v=1MVKjd_FVG4', // English Learning
            'tieng-anh'      => 'https://www.youtube.com/watch?v=1MVKjd_FVG4',
            'tieng-nhat'     => 'https://www.youtube.com/watch?v=rGrBHiuPlT0', // Japanese Intro
            '_default'       => 'https://www.youtube.com/watch?v=zOjov-2OZ0E',
        ],
        'setup-environment' => [
            'lap-trinh-cntt' => 'https://www.youtube.com/watch?v=bMknfKXIFA8', // VS Code Setup
            'web-dev'        => 'https://www.youtube.com/watch?v=bMknfKXIFA8',
            'database'       => 'https://www.youtube.com/watch?v=uj4OYk5nKCg', // MySQL Setup
            'devops'         => 'https://www.youtube.com/watch?v=pg19Z8LL06w', // Docker Setup
            'mobile-dev'     => 'https://www.youtube.com/watch?v=1ukSR1GRtMU', // Flutter Setup
            'thiet-ke'       => 'https://www.youtube.com/watch?v=Cx2dkpBxst8', // Figma Setup
            'ui-ux'          => 'https://www.youtube.com/watch?v=Cx2dkpBxst8',
            'do-hoa'         => 'https://www.youtube.com/watch?v=ysz5S6PUM-U', // Photoshop Setup
            'kinh-doanh'     => 'https://www.youtube.com/watch?v=qz0aGYrrlhU', // Excel/Tools Setup
            'ngoai-ngu'      => 'https://www.youtube.com/watch?v=WNSRFTbAAPk', // Anki Setup
            '_default'       => 'https://www.youtube.com/watch?v=bMknfKXIFA8',
        ],
        'core-concepts' => [
            'lap-trinh-cntt' => 'https://www.youtube.com/watch?v=SqcY0GlETPk', // HTML/CSS Basics
            'web-dev'        => 'https://www.youtube.com/watch?v=SqcY0GlETPk',
            'database'       => 'https://www.youtube.com/watch?v=p3qvj9hO_Bo', // SQL Core Concepts
            'devops'         => 'https://www.youtube.com/watch?v=kTp5xUtcalw', // CI/CD Concepts
            'mobile-dev'     => 'https://www.youtube.com/watch?v=x0uinJvhNxI', // Flutter Widgets
            'thiet-ke'       => 'https://www.youtube.com/watch?v=II-6dDzc-80', // Design Principles
            'ui-ux'          => 'https://www.youtube.com/watch?v=II-6dDzc-80',
            'do-hoa'         => 'https://www.youtube.com/watch?v=x3zJ0DqKJtQ', // Color Theory
            'kinh-doanh'     => 'https://www.youtube.com/watch?v=mEqu4S5FP_Y', // Business Fundamentals
            'marketing'      => 'https://www.youtube.com/watch?v=bixR-KIJKYM',
            'ngoai-ngu'      => 'https://www.youtube.com/watch?v=V_TnqZoHkFM', // English Grammar
            'tieng-anh'      => 'https://www.youtube.com/watch?v=V_TnqZoHkFM',
            'tieng-nhat'     => 'https://www.youtube.com/watch?v=6p9Il_j0zjc', // Hiragana
            '_default'       => 'https://www.youtube.com/watch?v=SqcY0GlETPk',
        ],
        'practice-walkthrough' => [
            'lap-trinh-cntt' => 'https://www.youtube.com/watch?v=G3e-cpL7ofc', // Build a website
            'web-dev'        => 'https://www.youtube.com/watch?v=G3e-cpL7ofc',
            'database'       => 'https://www.youtube.com/watch?v=7S_tz1z_5bA', // SQL Practice
            'devops'         => 'https://www.youtube.com/watch?v=fqMOX6JJhGo', // Docker Walkthrough
            'mobile-dev'     => 'https://www.youtube.com/watch?v=tye0zhHMl5A', // Flutter App Build
            'thiet-ke'       => 'https://www.youtube.com/watch?v=4W4LvJnNegA', // Figma Prototype
            'ui-ux'          => 'https://www.youtube.com/watch?v=4W4LvJnNegA',
            'do-hoa'         => 'https://www.youtube.com/watch?v=r9sVwRVCDC8', // Design Practice
            'kinh-doanh'     => 'https://www.youtube.com/watch?v=XB5OUQO6O_k', // Business Plan Practice
            'ngoai-ngu'      => 'https://www.youtube.com/watch?v=HMq2BvAoFns', // English Practice
            'tieng-nhat'     => 'https://www.youtube.com/watch?v=6p9Il_j0zjc',
            '_default'       => 'https://www.youtube.com/watch?v=G3e-cpL7ofc',
        ],
        'live-workshop' => [
            'lap-trinh-cntt' => 'https://www.youtube.com/watch?v=PlxWf493en4', // Live coding
            'web-dev'        => 'https://www.youtube.com/watch?v=PlxWf493en4',
            'database'       => 'https://www.youtube.com/watch?v=9GIbChqqMT0', // SQL Live
            'devops'         => 'https://www.youtube.com/watch?v=LFJfC73g2RE', // K8s Live
            'mobile-dev'     => 'https://www.youtube.com/watch?v=CDhaS04_kkk', // Flutter Live
            'thiet-ke'       => 'https://www.youtube.com/watch?v=Oi9ciYM3Rk8', // Design Workshop
            'kinh-doanh'     => 'https://www.youtube.com/watch?v=v80TV8XBOBE', // Business Workshop
            'ngoai-ngu'      => 'https://www.youtube.com/watch?v=bCBd_M4aIfA', // Speaking Practice
            '_default'       => 'https://www.youtube.com/watch?v=PlxWf493en4',
        ],
    ];

    // -----------------------------------------------------------------------
    // Thumbnail map: category root slug → array of Unsplash/Pexels image URLs
    // Nhiều ảnh hơn để đa dạng hóa theo từng khóa học.
    // -----------------------------------------------------------------------
    private array $thumbMap = [
        'lap-trinh-cntt' => [
            // Code editor, dev setup, programming
            'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=1200&q=80',
            'https://images.unsplash.com/photo-1461749280684-dccba630e2f6?w=1200&q=80',
            'https://images.unsplash.com/photo-1504639725590-34d0984388bd?w=1200&q=80',
            'https://images.unsplash.com/photo-1555066931-4365d14bab8c?w=1200&q=80',
            'https://images.unsplash.com/photo-1542831371-29b0f74f9713?w=1200&q=80',
            'https://images.unsplash.com/photo-1536148935331-408321065b18?w=1200&q=80',
            'https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=1200&q=80',
            'https://images.unsplash.com/photo-1595675024853-0f3ec9098ac7?w=1200&q=80',
        ],
        'web-dev' => [
            'https://images.unsplash.com/photo-1547658719-da2b51169166?w=1200&q=80',
            'https://images.unsplash.com/photo-1627398242454-45a1465c2479?w=1200&q=80',
            'https://images.unsplash.com/photo-1593720219276-0b1eacd0aef4?w=1200&q=80',
            'https://images.unsplash.com/photo-1583121274602-3e2820c69888?w=1200&q=80',
        ],
        'database' => [
            'https://images.unsplash.com/photo-1544197150-b99a580bb7a8?w=1200&q=80',
            'https://images.unsplash.com/photo-1558494949-ef010cbdcc31?w=1200&q=80',
            'https://images.unsplash.com/photo-1599507593499-a3f7d7d97667?w=1200&q=80',
            'https://images.unsplash.com/photo-1451187580459-43490279c0fa?w=1200&q=80',
        ],
        'devops' => [
            'https://images.unsplash.com/photo-1667372393119-3d4c48d07fc9?w=1200&q=80',
            'https://images.unsplash.com/photo-1605745341112-85968b19335b?w=1200&q=80',
            'https://images.unsplash.com/photo-1600267204091-5c1ab8b10c02?w=1200&q=80',
            'https://images.unsplash.com/photo-1518770660439-4636190af475?w=1200&q=80',
        ],
        'mobile-dev' => [
            'https://images.unsplash.com/photo-1512941937669-90a1b58e7e9c?w=1200&q=80',
            'https://images.unsplash.com/photo-1526374965328-7f61d4dc18c5?w=1200&q=80',
            'https://images.unsplash.com/photo-1555774698-0b77e0d5fac6?w=1200&q=80',
            'https://images.unsplash.com/photo-1601972599720-36938d4ecd31?w=1200&q=80',
        ],
        'thiet-ke' => [
            'https://images.unsplash.com/photo-1561070791-2526d30994b5?w=1200&q=80',
            'https://images.unsplash.com/photo-1558655146-364adaf1fcc9?w=1200&q=80',
            'https://images.unsplash.com/photo-1541462608143-67571c6738dd?w=1200&q=80',
            'https://images.unsplash.com/photo-1586717791821-3f44a563fa4c?w=1200&q=80',
        ],
        'ui-ux' => [
            'https://images.unsplash.com/photo-1581291518633-83b4ebd1d83e?w=1200&q=80',
            'https://images.unsplash.com/photo-1559028012-481c04fa702d?w=1200&q=80',
            'https://images.unsplash.com/photo-1621839673705-6617adf9e890?w=1200&q=80',
            'https://images.unsplash.com/photo-1523726491678-bf852e717f6a?w=1200&q=80',
        ],
        'do-hoa' => [
            'https://images.unsplash.com/photo-1572044162444-ad60f128bdea?w=1200&q=80',
            'https://images.unsplash.com/photo-1626785774625-ddcddc3445e9?w=1200&q=80',
            'https://images.unsplash.com/photo-1561070791-2526d30994b5?w=1200&q=80',
            'https://images.unsplash.com/photo-1615209853186-e4bd66602508?w=1200&q=80',
        ],
        'kinh-doanh' => [
            'https://images.unsplash.com/photo-1460925895917-afdab827c52f?w=1200&q=80',
            'https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?w=1200&q=80',
            'https://images.unsplash.com/photo-1552664730-d307ca884978?w=1200&q=80',
            'https://images.unsplash.com/photo-1551288049-bebda4e38f71?w=1200&q=80',
        ],
        'marketing' => [
            'https://images.unsplash.com/photo-1533750349088-cd871a92f312?w=1200&q=80',
            'https://images.unsplash.com/photo-1611926653458-09294b3142bf?w=1200&q=80',
            'https://images.unsplash.com/photo-1504711434969-e33886168f5c?w=1200&q=80',
            'https://images.unsplash.com/photo-1432888498266-38ffec3eaf0a?w=1200&q=80',
        ],
        'quan-ly-du-an' => [
            'https://images.unsplash.com/photo-1507925921958-8a62f3d1a50d?w=1200&q=80',
            'https://images.unsplash.com/photo-1542626991-cbc4e32524cc?w=1200&q=80',
            'https://images.unsplash.com/photo-1611532736597-de2d4265fba3?w=1200&q=80',
            'https://images.unsplash.com/photo-1531538606174-0f90ff5dce83?w=1200&q=80',
        ],
        'ngoai-ngu' => [
            'https://images.unsplash.com/photo-1546410531-bb4caa6b424d?w=1200&q=80',
            'https://images.unsplash.com/photo-1434030216411-0b793f4b6b23?w=1200&q=80',
            'https://images.unsplash.com/photo-1456513080510-7bf3a84b82f8?w=1200&q=80',
            'https://images.unsplash.com/photo-1528360983277-13d401cdc186?w=1200&q=80',
        ],
        'tieng-anh' => [
            'https://images.unsplash.com/photo-1543109740-4bdb38fda756?w=1200&q=80',
            'https://images.unsplash.com/photo-1505664063603-28e48ca204eb?w=1200&q=80',
            'https://images.unsplash.com/photo-1457369804613-52c61a468e7d?w=1200&q=80',
            'https://images.unsplash.com/photo-1488190211105-8b0e65b80b4e?w=1200&q=80',
        ],
        'tieng-nhat' => [
            'https://images.unsplash.com/photo-1528360983277-13d401cdc186?w=1200&q=80',
            'https://images.unsplash.com/photo-1490761668535-35497054e360?w=1200&q=80',
            'https://images.unsplash.com/photo-1570521462033-3015e76e7432?w=1200&q=80',
            'https://images.unsplash.com/photo-1536098561742-ca998e48cbcc?w=1200&q=80',
        ],
    ];

    private array $fallbackThumbs = [
        'https://images.unsplash.com/photo-1522542550221-31fd19575a2d?w=1200&q=80',
        'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=1200&q=80',
        'https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=1200&q=80',
        'https://images.unsplash.com/photo-1460925895917-afdab827c52f?w=1200&q=80',
    ];

    public function run(): void
    {
        $this->updateThumbnails();
        $this->updateVideoUrls();
    }

    // -----------------------------------------------------------------------
    // 1. Cập nhật thumbnail khóa học
    // -----------------------------------------------------------------------
    private function updateThumbnails(): void
    {
        $this->command?->info('=== Cập nhật thumbnail khóa học ===');
        $courses = Course::with('category.parent')->get();
        $updated = 0;

        foreach ($courses as $course) {
            $thumb = $this->resolveThumbnail($course);
            $course->update(['thumbnail' => $thumb]);
            $updated++;
            $this->command?->line("  ✓ [{$course->id}] {$course->title}");
        }

        $this->command?->info("Thumbnail: {$updated} khóa học đã cập nhật.");
    }

    private function resolveThumbnail(Course $course): string
    {
        $cat     = $course->category;
        $catSlug = $cat?->slug ?? '';
        $rootSlug = $cat?->parent?->slug ?? $catSlug;

        // Ưu tiên tìm theo sub-category slug trước, rồi root slug
        $pool = $this->thumbMap[$catSlug]
            ?? $this->thumbMap[$rootSlug]
            ?? $this->fallbackThumbs;

        // Phân biệt theo từ khóa trong title
        $title = Str::lower($course->title);
        if (Str::contains($title, ['nhập môn', 'cơ bản', 'beginner'])) return $pool[0];
        if (Str::contains($title, ['thực chiến', 'thực hành', 'advanced'])) return $pool[1 % count($pool)];
        if (Str::contains($title, ['chuyên sâu', 'pro', 'expert']))        return $pool[2 % count($pool)];
        if (Str::contains($title, ['dự án', 'project']))                   return $pool[3 % count($pool)];

        return $pool[$course->id % count($pool)];
    }

    // -----------------------------------------------------------------------
    // 2. Cập nhật video_url của lesson bằng YouTube links
    // -----------------------------------------------------------------------
    private function updateVideoUrls(): void
    {
        $this->command?->info('=== Cập nhật video_url lesson (YouTube) ===');
        $lessons = Lesson::with('course.category.parent')
            ->whereIn('type', ['video', 'live'])
            ->get();

        $updated = 0;
        $skipped = 0;

        foreach ($lessons as $lesson) {
            $youtubeUrl = $this->resolveVideoUrl($lesson);

            if (! $youtubeUrl) {
                $skipped++;
                continue;
            }

            $lesson->update([
                'video_url'    => $youtubeUrl,
                'video_status' => 'ready',
            ]);

            $updated++;
            $this->command?->line("  ✓ Lesson [{$lesson->id}] {$lesson->title}");
        }

        $this->command?->info("Video: {$updated} lesson đã cập nhật, {$skipped} bỏ qua.");
    }

    private function resolveVideoUrl(Lesson $lesson): ?string
    {
        // Xác định asset slug từ video_url cũ (dạng "demo/{course-slug}/{asset}.mp4")
        $asset = $this->extractAsset($lesson->video_url ?? '');

        if (! $asset || ! isset($this->videoMap[$asset])) {
            return $this->fallbackVideoByTitle($lesson);
        }

        $catSlug  = $lesson->course?->category?->slug ?? '';
        $rootSlug = $lesson->course?->category?->parent?->slug ?? $catSlug;

        $assetMap = $this->videoMap[$asset];

        return $assetMap[$catSlug]
            ?? $assetMap[$rootSlug]
            ?? $assetMap['_default']
            ?? null;
    }

    private function extractAsset(string $videoUrl): string
    {
        // "demo/my-course/intro-overview.mp4" → "intro-overview"
        if (preg_match('#/([^/]+)\.mp4$#', $videoUrl, $m)) {
            return $m[1];
        }
        return '';
    }

    private function fallbackVideoByTitle(Lesson $lesson): ?string
    {
        $title = Str::lower($lesson->title);

        // Ánh xạ theo từ khóa trong tiêu đề
        $keywordMap = [
            ['keys' => ['tổng quan', 'giới thiệu', 'intro', 'overview'],  'url' => 'https://www.youtube.com/watch?v=zOjov-2OZ0E'],
            ['keys' => ['thiết lập', 'cài đặt', 'setup', 'môi trường'],   'url' => 'https://www.youtube.com/watch?v=bMknfKXIFA8'],
            ['keys' => ['kiến thức', 'nền tảng', 'core', 'concepts'],     'url' => 'https://www.youtube.com/watch?v=SqcY0GlETPk'],
            ['keys' => ['thực hành', 'walkthrough', 'ví dụ mẫu'],          'url' => 'https://www.youtube.com/watch?v=G3e-cpL7ofc'],
            ['keys' => ['live', 'workshop', 'chia sẻ'],                    'url' => 'https://www.youtube.com/watch?v=PlxWf493en4'],
        ];

        foreach ($keywordMap as $entry) {
            foreach ($entry['keys'] as $key) {
                if (Str::contains($title, $key)) {
                    return $entry['url'];
                }
            }
        }

        return 'https://www.youtube.com/watch?v=zOjov-2OZ0E';
    }
}

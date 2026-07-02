<?php

namespace App\Console\Commands;

use App\Models\Course;
use App\Models\Lesson;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class SeedMediaResources extends Command
{
    protected $signature = 'media:seed
                            {--dry-run : Chỉ hiển thị thay đổi, không lưu vào DB}
                            {--thumbnails : Chỉ cập nhật thumbnail khóa học}
                            {--videos : Chỉ cập nhật video_url lesson}';

    protected $description = 'Cập nhật thumbnail khóa học (Unsplash) và video_url lesson (YouTube) bằng dữ liệu thực';

    // -----------------------------------------------------------------------
    // YouTube map: asset slug → {cat-slug → youtube-watch-url}
    // -----------------------------------------------------------------------
    private array $videoMap = [
        'intro-overview' => [
            'lap-trinh-cntt' => 'https://www.youtube.com/watch?v=zOjov-2OZ0E',
            'web-dev'        => 'https://www.youtube.com/watch?v=zOjov-2OZ0E',
            'database'       => 'https://www.youtube.com/watch?v=HXV3zeQKqGY',
            'devops'         => 'https://www.youtube.com/watch?v=a-8MPGzrChQ',
            'mobile-dev'     => 'https://www.youtube.com/watch?v=0-S5a0eXPoc',
            'thiet-ke'       => 'https://www.youtube.com/watch?v=c9Wg6Cb_YlU',
            'ui-ux'          => 'https://www.youtube.com/watch?v=c9Wg6Cb_YlU',
            'do-hoa'         => 'https://www.youtube.com/watch?v=3_PnuDQ4pNg',
            'kinh-doanh'     => 'https://www.youtube.com/watch?v=Vbn3fNPP0Hk',
            'marketing'      => 'https://www.youtube.com/watch?v=bixR-KIJKYM',
            'quan-ly-du-an'  => 'https://www.youtube.com/watch?v=gT5BBwi8aTk',
            'ngoai-ngu'      => 'https://www.youtube.com/watch?v=1MVKjd_FVG4',
            'tieng-anh'      => 'https://www.youtube.com/watch?v=1MVKjd_FVG4',
            'tieng-nhat'     => 'https://www.youtube.com/watch?v=rGrBHiuPlT0',
            '_default'       => 'https://www.youtube.com/watch?v=zOjov-2OZ0E',
        ],
        'setup-environment' => [
            'lap-trinh-cntt' => 'https://www.youtube.com/watch?v=bMknfKXIFA8',
            'web-dev'        => 'https://www.youtube.com/watch?v=bMknfKXIFA8',
            'database'       => 'https://www.youtube.com/watch?v=uj4OYk5nKCg',
            'devops'         => 'https://www.youtube.com/watch?v=pg19Z8LL06w',
            'mobile-dev'     => 'https://www.youtube.com/watch?v=1ukSR1GRtMU',
            'thiet-ke'       => 'https://www.youtube.com/watch?v=Cx2dkpBxst8',
            'ui-ux'          => 'https://www.youtube.com/watch?v=Cx2dkpBxst8',
            'do-hoa'         => 'https://www.youtube.com/watch?v=ysz5S6PUM-U',
            'kinh-doanh'     => 'https://www.youtube.com/watch?v=qz0aGYrrlhU',
            'ngoai-ngu'      => 'https://www.youtube.com/watch?v=WNSRFTbAAPk',
            '_default'       => 'https://www.youtube.com/watch?v=bMknfKXIFA8',
        ],
        'core-concepts' => [
            'lap-trinh-cntt' => 'https://www.youtube.com/watch?v=SqcY0GlETPk',
            'web-dev'        => 'https://www.youtube.com/watch?v=SqcY0GlETPk',
            'database'       => 'https://www.youtube.com/watch?v=p3qvj9hO_Bo',
            'devops'         => 'https://www.youtube.com/watch?v=kTp5xUtcalw',
            'mobile-dev'     => 'https://www.youtube.com/watch?v=x0uinJvhNxI',
            'thiet-ke'       => 'https://www.youtube.com/watch?v=II-6dDzc-80',
            'ui-ux'          => 'https://www.youtube.com/watch?v=II-6dDzc-80',
            'do-hoa'         => 'https://www.youtube.com/watch?v=x3zJ0DqKJtQ',
            'kinh-doanh'     => 'https://www.youtube.com/watch?v=mEqu4S5FP_Y',
            'marketing'      => 'https://www.youtube.com/watch?v=bixR-KIJKYM',
            'ngoai-ngu'      => 'https://www.youtube.com/watch?v=V_TnqZoHkFM',
            'tieng-anh'      => 'https://www.youtube.com/watch?v=V_TnqZoHkFM',
            'tieng-nhat'     => 'https://www.youtube.com/watch?v=6p9Il_j0zjc',
            '_default'       => 'https://www.youtube.com/watch?v=SqcY0GlETPk',
        ],
        'practice-walkthrough' => [
            'lap-trinh-cntt' => 'https://www.youtube.com/watch?v=G3e-cpL7ofc',
            'web-dev'        => 'https://www.youtube.com/watch?v=G3e-cpL7ofc',
            'database'       => 'https://www.youtube.com/watch?v=7S_tz1z_5bA',
            'devops'         => 'https://www.youtube.com/watch?v=fqMOX6JJhGo',
            'mobile-dev'     => 'https://www.youtube.com/watch?v=tye0zhHMl5A',
            'thiet-ke'       => 'https://www.youtube.com/watch?v=4W4LvJnNegA',
            'ui-ux'          => 'https://www.youtube.com/watch?v=4W4LvJnNegA',
            'do-hoa'         => 'https://www.youtube.com/watch?v=r9sVwRVCDC8',
            'kinh-doanh'     => 'https://www.youtube.com/watch?v=XB5OUQO6O_k',
            'ngoai-ngu'      => 'https://www.youtube.com/watch?v=HMq2BvAoFns',
            'tieng-nhat'     => 'https://www.youtube.com/watch?v=6p9Il_j0zjc',
            '_default'       => 'https://www.youtube.com/watch?v=G3e-cpL7ofc',
        ],
        'live-workshop' => [
            'lap-trinh-cntt' => 'https://www.youtube.com/watch?v=PlxWf493en4',
            'web-dev'        => 'https://www.youtube.com/watch?v=PlxWf493en4',
            'database'       => 'https://www.youtube.com/watch?v=9GIbChqqMT0',
            'devops'         => 'https://www.youtube.com/watch?v=LFJfC73g2RE',
            'mobile-dev'     => 'https://www.youtube.com/watch?v=CDhaS04_kkk',
            'thiet-ke'       => 'https://www.youtube.com/watch?v=Oi9ciYM3Rk8',
            'kinh-doanh'     => 'https://www.youtube.com/watch?v=v80TV8XBOBE',
            'ngoai-ngu'      => 'https://www.youtube.com/watch?v=bCBd_M4aIfA',
            '_default'       => 'https://www.youtube.com/watch?v=PlxWf493en4',
        ],
    ];

    // -----------------------------------------------------------------------
    // Thumbnail map: slug → image URLs
    // -----------------------------------------------------------------------
    private array $thumbMap = [
        'lap-trinh-cntt' => [
            'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=1200&q=80',
            'https://images.unsplash.com/photo-1461749280684-dccba630e2f6?w=1200&q=80',
            'https://images.unsplash.com/photo-1504639725590-34d0984388bd?w=1200&q=80',
            'https://images.unsplash.com/photo-1555066931-4365d14bab8c?w=1200&q=80',
            'https://images.unsplash.com/photo-1542831371-29b0f74f9713?w=1200&q=80',
            'https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=1200&q=80',
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
            'https://images.unsplash.com/photo-1518770660439-4636190af475?w=1200&q=80',
        ],
        'mobile-dev' => [
            'https://images.unsplash.com/photo-1512941937669-90a1b58e7e9c?w=1200&q=80',
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
        ],
        'do-hoa' => [
            'https://images.unsplash.com/photo-1572044162444-ad60f128bdea?w=1200&q=80',
            'https://images.unsplash.com/photo-1626785774625-ddcddc3445e9?w=1200&q=80',
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
            'https://images.unsplash.com/photo-1432888498266-38ffec3eaf0a?w=1200&q=80',
        ],
        'quan-ly-du-an' => [
            'https://images.unsplash.com/photo-1507925921958-8a62f3d1a50d?w=1200&q=80',
            'https://images.unsplash.com/photo-1542626991-cbc4e32524cc?w=1200&q=80',
            'https://images.unsplash.com/photo-1531538606174-0f90ff5dce83?w=1200&q=80',
        ],
        'ngoai-ngu' => [
            'https://images.unsplash.com/photo-1546410531-bb4caa6b424d?w=1200&q=80',
            'https://images.unsplash.com/photo-1434030216411-0b793f4b6b23?w=1200&q=80',
            'https://images.unsplash.com/photo-1456513080510-7bf3a84b82f8?w=1200&q=80',
        ],
        'tieng-anh' => [
            'https://images.unsplash.com/photo-1543109740-4bdb38fda756?w=1200&q=80',
            'https://images.unsplash.com/photo-1488190211105-8b0e65b80b4e?w=1200&q=80',
        ],
        'tieng-nhat' => [
            'https://images.unsplash.com/photo-1528360983277-13d401cdc186?w=1200&q=80',
            'https://images.unsplash.com/photo-1490761668535-35497054e360?w=1200&q=80',
            'https://images.unsplash.com/photo-1536098561742-ca998e48cbcc?w=1200&q=80',
        ],
    ];

    private array $fallbackThumbs = [
        'https://images.unsplash.com/photo-1522542550221-31fd19575a2d?w=1200&q=80',
        'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=1200&q=80',
        'https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=1200&q=80',
        'https://images.unsplash.com/photo-1460925895917-afdab827c52f?w=1200&q=80',
    ];

    public function handle(): int
    {
        $isDry       = $this->option('dry-run');
        $onlyThumbs  = $this->option('thumbnails');
        $onlyVideos  = $this->option('videos');
        $doAll       = ! $onlyThumbs && ! $onlyVideos;

        if ($isDry) {
            $this->warn('[DRY RUN] Không có thay đổi nào được lưu.');
        }

        if ($doAll || $onlyThumbs) {
            $this->processThumbnails($isDry);
        }

        if ($doAll || $onlyVideos) {
            $this->processVideos($isDry);
        }

        $this->info('Hoàn tất.');
        return self::SUCCESS;
    }

    // -----------------------------------------------------------------------
    private function processThumbnails(bool $dry): void
    {
        $this->info('');
        $this->info('=== Thumbnail khóa học ===');

        $rows = [['ID', 'Tiêu đề', 'Thumbnail mới']];

        $courses = Course::with('category.parent')->get();
        foreach ($courses as $course) {
            $thumb = $this->resolveThumbnail($course);
            $rows[] = [$course->id, Str::limit($course->title, 35), Str::limit($thumb, 60)];

            if (! $dry) {
                $course->update(['thumbnail' => $thumb]);
            }
        }

        $this->table($rows[0], array_slice($rows, 1));
        $this->info(count($rows) - 1 . ' khóa học ' . ($dry ? 'sẽ được' : 'đã được') . ' cập nhật.');
    }

    private function resolveThumbnail(Course $course): string
    {
        $cat      = $course->category;
        $catSlug  = $cat?->slug ?? '';
        $rootSlug = $cat?->parent?->slug ?? $catSlug;

        $pool = $this->thumbMap[$catSlug]
            ?? $this->thumbMap[$rootSlug]
            ?? $this->fallbackThumbs;

        $title = Str::lower($course->title);
        if (Str::contains($title, ['nhập môn', 'cơ bản', 'beginner'])) return $pool[0];
        if (Str::contains($title, ['thực chiến', 'thực hành']))         return $pool[1 % count($pool)];
        if (Str::contains($title, ['chuyên sâu', 'pro', 'expert']))     return $pool[2 % count($pool)];
        if (Str::contains($title, ['dự án', 'project']))                return $pool[3 % count($pool)];

        return $pool[$course->id % count($pool)];
    }

    // -----------------------------------------------------------------------
    private function processVideos(bool $dry): void
    {
        $this->info('');
        $this->info('=== Video URL lesson (YouTube) ===');

        $lessons = Lesson::with('course.category.parent')
            ->whereIn('type', ['video', 'live'])
            ->get();

        $rows    = [['ID', 'Lesson', 'YouTube URL']];
        $updated = 0;

        foreach ($lessons as $lesson) {
            $url = $this->resolveVideoUrl($lesson);
            if (! $url) continue;

            $rows[] = [$lesson->id, Str::limit($lesson->title, 35), $url];

            if (! $dry) {
                $lesson->update(['video_url' => $url, 'video_status' => 'ready']);
            }
            $updated++;
        }

        $this->table($rows[0], array_slice($rows, 1));
        $this->info($updated . ' lesson ' . ($dry ? 'sẽ được' : 'đã được') . ' cập nhật.');
    }

    private function resolveVideoUrl(Lesson $lesson): ?string
    {
        $asset = $this->extractAsset($lesson->video_url ?? '');

        if ($asset && isset($this->videoMap[$asset])) {
            $catSlug  = $lesson->course?->category?->slug ?? '';
            $rootSlug = $lesson->course?->category?->parent?->slug ?? $catSlug;
            $map      = $this->videoMap[$asset];

            return $map[$catSlug] ?? $map[$rootSlug] ?? $map['_default'] ?? null;
        }

        return $this->fallbackVideoByTitle($lesson);
    }

    private function extractAsset(string $url): string
    {
        if (preg_match('#/([^/]+)\.mp4$#', $url, $m)) {
            return $m[1];
        }
        // Nếu đã là YouTube URL thì giữ nguyên (không extract)
        if (Str::contains($url, ['youtube.com', 'youtu.be'])) {
            return '';
        }
        return '';
    }

    private function fallbackVideoByTitle(Lesson $lesson): string
    {
        $title = Str::lower($lesson->title);

        $map = [
            ['keys' => ['tổng quan', 'giới thiệu', 'intro', 'overview'],  'url' => 'https://www.youtube.com/watch?v=zOjov-2OZ0E'],
            ['keys' => ['thiết lập', 'cài đặt', 'setup', 'môi trường'],   'url' => 'https://www.youtube.com/watch?v=bMknfKXIFA8'],
            ['keys' => ['kiến thức', 'nền tảng', 'core', 'concepts'],     'url' => 'https://www.youtube.com/watch?v=SqcY0GlETPk'],
            ['keys' => ['thực hành', 'walkthrough', 'ví dụ mẫu'],          'url' => 'https://www.youtube.com/watch?v=G3e-cpL7ofc'],
            ['keys' => ['live', 'workshop', 'chia sẻ'],                    'url' => 'https://www.youtube.com/watch?v=PlxWf493en4'],
        ];

        foreach ($map as $entry) {
            foreach ($entry['keys'] as $key) {
                if (Str::contains($title, $key)) {
                    return $entry['url'];
                }
            }
        }

        return 'https://www.youtube.com/watch?v=zOjov-2OZ0E';
    }
}

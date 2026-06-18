<?php

namespace Database\Seeders;

use App\Models\Course;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Cập nhật thumbnail cho tất cả courses hiện có dựa theo category slug.
 * Chạy độc lập: php artisan db:seed --class=CourseThumbnailSeeder
 */
class CourseThumbnailSeeder extends Seeder
{
    // Ảnh bìa phân theo category slug (root) → [core, thực chiến, chuyên sâu, dự án]
    private array $thumbMap = [
        'lap-trinh-cntt' => [
            'https://images.unsplash.com/photo-1555066931-4365d14bab8c?auto=format&fit=crop&w=1200&q=80',
            'https://images.unsplash.com/photo-1498050108023-c5249f4df085?auto=format&fit=crop&w=1200&q=80',
            'https://images.unsplash.com/photo-1547658719-da2b51169166?auto=format&fit=crop&w=1200&q=80',
            'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?auto=format&fit=crop&w=1200&q=80',
        ],
        'thiet-ke' => [
            'https://images.unsplash.com/photo-1561070791-2526d30994b5?auto=format&fit=crop&w=1200&q=80',
            'https://images.unsplash.com/photo-1541462608143-67571c6738dd?auto=format&fit=crop&w=1200&q=80',
            'https://images.unsplash.com/photo-1558655146-364adaf1fcc9?auto=format&fit=crop&w=1200&q=80',
            'https://images.unsplash.com/photo-1586717791821-3f44a563fa4c?auto=format&fit=crop&w=1200&q=80',
        ],
        'kinh-doanh' => [
            'https://images.unsplash.com/photo-1460925895917-afdab827c52f?auto=format&fit=crop&w=1200&q=80',
            'https://images.unsplash.com/photo-1552664730-d307ca884978?auto=format&fit=crop&w=1200&q=80',
            'https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?auto=format&fit=crop&w=1200&q=80',
            'https://images.unsplash.com/photo-1551288049-bebda4e38f71?auto=format&fit=crop&w=1200&q=80',
        ],
        'ngoai-ngu' => [
            'https://images.unsplash.com/photo-1546410531-bb4caa6b424d?auto=format&fit=crop&w=1200&q=80',
            'https://images.unsplash.com/photo-1434030216411-0b793f4b6b23?auto=format&fit=crop&w=1200&q=80',
            'https://images.unsplash.com/photo-1456513080510-7bf3a84b82f8?auto=format&fit=crop&w=1200&q=80',
            'https://images.unsplash.com/photo-1528360983277-13d401cdc186?auto=format&fit=crop&w=1200&q=80',
        ],
    ];

    // Fallback cho category chưa được map
    private array $fallback = [
        'https://images.unsplash.com/photo-1522542550221-31fd19575a2d?auto=format&fit=crop&w=1200&q=80',
        'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?auto=format&fit=crop&w=1200&q=80',
        'https://images.unsplash.com/photo-1498050108023-c5249f4df085?auto=format&fit=crop&w=1200&q=80',
        'https://images.unsplash.com/photo-1460925895917-afdab827c52f?auto=format&fit=crop&w=1200&q=80',
    ];

    public function run(): void
    {
        $courses = Course::with('category.parent')->get();
        $updated = 0;

        foreach ($courses as $course) {
            $thumb = $this->resolveThumbnail($course);

            if ($course->thumbnail !== $thumb) {
                $course->update(['thumbnail' => $thumb]);
                $updated++;
                $this->command?->line("  ✓ [{$course->id}] {$course->title}");
            }
        }

        $this->command?->info("Done — {$updated} thumbnail(s) updated.");
    }

    private function resolveThumbnail(Course $course): string
    {
        // Tìm root category slug
        $cat = $course->category;
        $rootSlug = $cat?->parent?->slug ?? $cat?->slug ?? '';

        $pool = $this->thumbMap[$rootSlug] ?? $this->fallback;

        // Phân biệt core (Nhập môn) vs extension theo vị trí title keyword
        $title = Str::lower($course->title);

        if (Str::startsWith($title, 'nhập môn') || $course->course_mode === 'core') {
            return $pool[0];
        }

        if (Str::contains($title, 'thực chiến')) return $pool[1];
        if (Str::contains($title, 'chuyên sâu')) return $pool[2];
        if (Str::contains($title, 'dự án'))      return $pool[3];

        // Fallback theo id để đa dạng
        return $pool[$course->id % count($pool)];
    }
}

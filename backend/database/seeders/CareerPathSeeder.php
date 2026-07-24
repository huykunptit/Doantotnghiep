<?php

namespace Database\Seeders;

use App\Models\CareerPath;
use App\Models\CareerPathCourse;
use App\Models\CertificateTemplate;
use App\Models\Course;
use App\Models\Skill;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seed 4 lộ trình nghề marketplace + gắn khóa extension.
 * Chạy sau CourseSeeder / CertificateTemplateSeeder.
 */
class CareerPathSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@lms.com')->first()
            ?? UserSeeder::getInstructors()->first();

        $pathCert = CertificateTemplate::query()
            ->where('name', 'like', '%Hoàn thành%')
            ->first()
            ?? CertificateTemplate::query()->first();

        $paths = [
            [
                'title' => 'Trở thành Fullstack Developer với Python',
                'slug' => 'fullstack-python-a-z',
                'target_role' => 'fullstack_python',
                'price' => 1499000,
                'cover_url' => 'https://images.unsplash.com/photo-1515879218367-8466d910aaa4?w=1400&q=80',
                'description' => 'Lộ trình A→Z từ Python nền tảng đến API, frontend và triển khai sản phẩm fullstack.',
                'skills' => ['Python', 'JavaScript', 'SQL', 'Docker'],
                'course_matchers' => ['python', 'web', 'database', 'devops', 'lap-trinh'],
            ],
            [
                'title' => 'Lộ trình Business Analyst từ A→Z',
                'slug' => 'business-analyst-a-z',
                'target_role' => 'business_analyst',
                'price' => 1299000,
                'cover_url' => 'https://images.unsplash.com/photo-1454165804606-c3d57bc86b40?w=1400&q=80',
                'description' => 'Phân tích nghiệp vụ, quản lý yêu cầu, storytelling dữ liệu và làm việc với stakeholder.',
                'skills' => ['Giao tiếp kỹ thuật', 'Làm việc nhóm', 'SQL'],
                'course_matchers' => ['kinh-doanh', 'quan-ly', 'marketing', 'database'],
            ],
            [
                'title' => 'Frontend Engineer với Vue & Nuxt',
                'slug' => 'frontend-vue-nuxt',
                'target_role' => 'frontend_vue',
                'price' => 1199000,
                'cover_url' => 'https://images.unsplash.com/photo-1547658719-da2b51169166?w=1400&q=80',
                'description' => 'Xây UI hiện đại với Vue/Nuxt, thiết kế tương tác và tối ưu trải nghiệm người học.',
                'skills' => ['Vue.js', 'Nuxt', 'JavaScript'],
                'course_matchers' => ['web-dev', 'ui-ux', 'thiet-ke', 'do-hoa'],
            ],
            [
                'title' => 'DevOps & Cloud cho kỹ sư phần mềm',
                'slug' => 'devops-cloud-path',
                'target_role' => 'devops',
                'price' => 1399000,
                'cover_url' => 'https://images.unsplash.com/photo-1667372393119-3d4c48d07fc9?w=1400&q=80',
                'description' => 'Container, CI/CD, giám sát và vận hành hệ thống LMS/cloud thực tế.',
                'skills' => ['Docker', 'Linux', 'CI/CD'],
                'course_matchers' => ['devops', 'database', 'lap-trinh'],
            ],
        ];

        $extensions = Course::query()
            ->where('course_mode', 'extension')
            ->where('status', 'published')
            ->with(['category.parent', 'skills'])
            ->orderBy('id')
            ->get();

        foreach ($paths as $def) {
            $path = CareerPath::query()->updateOrCreate(
                ['slug' => $def['slug']],
                [
                    'title' => $def['title'],
                    'description' => $def['description'],
                    'target_role' => $def['target_role'],
                    'price' => $def['price'],
                    'status' => 'published',
                    'cover_url' => $def['cover_url'],
                    'certificate_template_id' => $pathCert?->id,
                    'created_by' => $admin?->id,
                    'published_at' => now()->subDays(7),
                ]
            );

            $picked = $this->pickCourses($extensions, $def['course_matchers'], 4);
            if ($picked->isEmpty()) {
                $picked = $extensions->take(4);
            }

            CareerPathCourse::query()->where('career_path_id', $path->id)->delete();
            foreach ($picked->values() as $index => $course) {
                CareerPathCourse::query()->create([
                    'career_path_id' => $path->id,
                    'course_id' => $course->id,
                    'sort_order' => $index,
                    'is_required' => true,
                    'milestone_label' => match ($index) {
                        0 => 'Nền tảng',
                        1 => 'Thực chiến',
                        2 => 'Chuyên sâu',
                        default => 'Dự án / hoàn thiện',
                    },
                ]);

                // Tag skills onto course for recommender
                $skillIds = Skill::query()
                    ->whereIn('name', $def['skills'])
                    ->orWhereIn('code', collect($def['skills'])->map(fn ($s) => Str::upper(Str::slug($s, '_')))->all())
                    ->pluck('id');
                if ($skillIds->isNotEmpty()) {
                    $sync = [];
                    foreach ($skillIds as $sid) {
                        $sync[$sid] = ['weight' => 1.0];
                    }
                    $course->skills()->syncWithoutDetaching($sync);
                }
            }
        }

        $this->command?->info('CareerPathSeeder: ' . count($paths) . ' lộ trình nghề đã seed.');
    }

    private function pickCourses($extensions, array $matchers, int $limit)
    {
        $scored = $extensions->map(function (Course $course) use ($matchers) {
            $hay = mb_strtolower(
                ($course->title ?? '') . ' ' .
                ($course->slug ?? '') . ' ' .
                ($course->category?->slug ?? '') . ' ' .
                ($course->category?->parent?->slug ?? '')
            );
            $score = 0;
            foreach ($matchers as $m) {
                if (str_contains($hay, mb_strtolower($m))) {
                    $score += 10;
                }
            }
            return ['course' => $course, 'score' => $score];
        })
            ->filter(fn ($row) => $row['score'] > 0)
            ->sortByDesc('score')
            ->take($limit)
            ->pluck('course');

        return $scored->values();
    }
}

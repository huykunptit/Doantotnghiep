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
 * Seed lộ trình nghề marketplace theo ngành + vị trí phổ biến.
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
            // ── CNTT / Phần mềm ──────────────────────────────────────────────
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
                'title' => 'Backend Engineer với Laravel & PHP',
                'slug' => 'backend-laravel-php',
                'target_role' => 'backend_laravel',
                'price' => 1299000,
                'cover_url' => 'https://images.unsplash.com/photo-1555066931-4365d14bab8c?w=1400&q=80',
                'description' => 'Xây API, auth, ORM và kiến trúc backend production-ready với Laravel.',
                'skills' => ['PHP', 'Laravel', 'SQL'],
                'course_matchers' => ['web-dev', 'database', 'lap-trinh', 'php', 'laravel'],
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
                'title' => 'Mobile Developer với Flutter',
                'slug' => 'mobile-flutter-path',
                'target_role' => 'mobile_flutter',
                'price' => 1349000,
                'cover_url' => 'https://images.unsplash.com/photo-1512941937669-90a1b58e7e9c?w=1400&q=80',
                'description' => 'Phát triển app đa nền tảng, UI native-feel và tích hợp API backend.',
                'skills' => ['JavaScript', 'Giao tiếp kỹ thuật'],
                'course_matchers' => ['mobile-dev', 'web-dev', 'ui-ux', 'lap-trinh'],
            ],
            [
                'title' => 'DevOps & Cloud cho kỹ sư phần mềm',
                'slug' => 'devops-cloud-path',
                'target_role' => 'devops',
                'price' => 1399000,
                'cover_url' => 'https://images.unsplash.com/photo-1667372393119-3d4c48d07fc9?w=1400&q=80',
                'description' => 'Container, CI/CD, giám sát và vận hành hệ thống LMS/cloud thực tế.',
                'skills' => ['Docker', 'SQL'],
                'course_matchers' => ['devops', 'database', 'lap-trinh'],
            ],
            [
                'title' => 'Data Analyst từ SQL đến Dashboard',
                'slug' => 'data-analyst-path',
                'target_role' => 'data_analyst',
                'price' => 1249000,
                'cover_url' => 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?w=1400&q=80',
                'description' => 'Thu thập, làm sạch dữ liệu, SQL nâng cao và kể chuyện bằng biểu đồ.',
                'skills' => ['SQL', 'Python', 'Giao tiếp kỹ thuật'],
                'course_matchers' => ['database', 'python', 'kinh-doanh', 'marketing'],
            ],
            [
                'title' => 'QA / Automation Tester',
                'slug' => 'qa-automation-tester',
                'target_role' => 'qa_tester',
                'price' => 1099000,
                'cover_url' => 'https://images.unsplash.com/photo-1581091226825-a6a2a5aee158?w=1400&q=80',
                'description' => 'Kiểm thử thủ công, viết test case và làm quen automation cho web/API.',
                'skills' => ['Giao tiếp kỹ thuật', 'Làm việc nhóm', 'JavaScript'],
                'course_matchers' => ['web-dev', 'lap-trinh', 'database', 'devops'],
            ],
            [
                'title' => 'Cybersecurity Fundamentals',
                'slug' => 'cybersecurity-fundamentals',
                'target_role' => 'cybersecurity',
                'price' => 1449000,
                'cover_url' => 'https://images.unsplash.com/photo-1550751827-4bd374c3f58b?w=1400&q=80',
                'description' => 'Bảo mật ứng dụng web, mạng và vận hành an toàn hệ thống.',
                'skills' => ['Linux', 'Docker', 'SQL'],
                'course_matchers' => ['devops', 'database', 'lap-trinh', 'web-dev'],
            ],

            // ── Kinh doanh / Quản trị ───────────────────────────────────────
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
                'title' => 'Digital Marketing thực chiến',
                'slug' => 'digital-marketing-path',
                'target_role' => 'digital_marketing',
                'price' => 999000,
                'cover_url' => 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?w=1400&q=80',
                'description' => 'SEO, content, social ads và đo lường chuyển đổi cho sản phẩm số.',
                'skills' => ['Giao tiếp kỹ thuật', 'Làm việc nhóm'],
                'course_matchers' => ['marketing', 'kinh-doanh', 'do-hoa', 'ui-ux'],
            ],
            [
                'title' => 'Product Owner / Product Manager',
                'slug' => 'product-owner-path',
                'target_role' => 'product_owner',
                'price' => 1349000,
                'cover_url' => 'https://images.unsplash.com/photo-1552664730-d307ca884978?w=1400&q=80',
                'description' => 'Xây roadmap sản phẩm, ưu tiên backlog và phối hợp đội kỹ thuật.',
                'skills' => ['Giao tiếp kỹ thuật', 'Làm việc nhóm'],
                'course_matchers' => ['quan-ly-du-an', 'kinh-doanh', 'ui-ux', 'marketing'],
            ],
            [
                'title' => 'Project Manager Agile',
                'slug' => 'project-manager-agile',
                'target_role' => 'project_manager',
                'price' => 1199000,
                'cover_url' => 'https://images.unsplash.com/photo-1531403009284-440f080d1e12?w=1400&q=80',
                'description' => 'Quản lý dự án Scrum/Kanban, timeline, rủi ro và giao tiếp stakeholder.',
                'skills' => ['Làm việc nhóm', 'Giao tiếp kỹ thuật'],
                'course_matchers' => ['quan-ly-du-an', 'kinh-doanh', 'marketing'],
            ],

            // ── Thiết kế ────────────────────────────────────────────────────
            [
                'title' => 'UI/UX Designer cho sản phẩm số',
                'slug' => 'ui-ux-designer-path',
                'target_role' => 'ui_ux_designer',
                'price' => 1149000,
                'cover_url' => 'https://images.unsplash.com/photo-1561070791-2526d30994b5?w=1400&q=80',
                'description' => 'Research, wireframe, prototype và thiết kế giao diện lấy người dùng làm trung tâm.',
                'skills' => ['Giao tiếp kỹ thuật', 'JavaScript'],
                'course_matchers' => ['ui-ux', 'thiet-ke', 'do-hoa', 'web-dev'],
            ],
            [
                'title' => 'Graphic Designer sáng tạo',
                'slug' => 'graphic-designer-path',
                'target_role' => 'graphic_designer',
                'price' => 949000,
                'cover_url' => 'https://images.unsplash.com/photo-1626785774573-4b7993143493?w=1400&q=80',
                'description' => 'Thiết kế nhận diện, poster, social creative và nguyên tắc thị giác.',
                'skills' => ['Giao tiếp kỹ thuật'],
                'course_matchers' => ['do-hoa', 'thiet-ke', 'ui-ux', 'marketing'],
            ],

            // ── Điện tử / Viễn thông ────────────────────────────────────────
            [
                'title' => 'Network Engineer nền tảng',
                'slug' => 'network-engineer-path',
                'target_role' => 'network_engineer',
                'price' => 1299000,
                'cover_url' => 'https://images.unsplash.com/photo-1558494949-ef010cbdcc31?w=1400&q=80',
                'description' => 'Mạng máy tính, bảo mật perimeter và vận hành hạ tầng doanh nghiệp.',
                'skills' => ['Docker', 'SQL'],
                'course_matchers' => ['devops', 'database', 'lap-trinh'],
            ],
            [
                'title' => 'IoT & Embedded Systems',
                'slug' => 'iot-embedded-path',
                'target_role' => 'iot_embedded',
                'price' => 1399000,
                'cover_url' => 'https://images.unsplash.com/photo-1518770660439-4636190af475?w=1400&q=80',
                'description' => 'Cảm biến, kết nối thiết bị và pipeline dữ liệu IoT lên cloud.',
                'skills' => ['Python', 'Docker'],
                'course_matchers' => ['devops', 'python', 'mobile-dev', 'lap-trinh'],
            ],

            // ── Ngoại ngữ nghề nghiệp ───────────────────────────────────────
            [
                'title' => 'English for IT Professionals',
                'slug' => 'english-for-it',
                'target_role' => 'english_it',
                'price' => 799000,
                'cover_url' => 'https://images.unsplash.com/photo-1434030216411-0b793f4b4173?w=1400&q=80',
                'description' => 'Tiếng Anh kỹ thuật: đọc docs, viết email, phỏng vấn và thuyết trình dự án.',
                'skills' => ['Giao tiếp kỹ thuật'],
                'course_matchers' => ['tieng-anh', 'ngoai-ngu', 'lap-trinh'],
            ],
            [
                'title' => 'Tiếng Nhật cho kỹ sư phần mềm',
                'slug' => 'japanese-for-engineers',
                'target_role' => 'japanese_it',
                'price' => 899000,
                'cover_url' => 'https://images.unsplash.com/photo-1528164344705-47542687000d?w=1400&q=80',
                'description' => 'Giao tiếp công sở và thuật ngữ IT phục vụ môi trường làm việc Nhật Bản.',
                'skills' => ['Giao tiếp kỹ thuật', 'Làm việc nhóm'],
                'course_matchers' => ['tieng-nhat', 'ngoai-ngu', 'lap-trinh'],
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
        return $extensions->map(function (Course $course) use ($matchers) {
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
            ->pluck('course')
            ->values();
    }
}

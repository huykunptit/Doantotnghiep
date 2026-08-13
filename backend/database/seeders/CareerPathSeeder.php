<?php

namespace Database\Seeders;

use App\Models\CareerPath;
use App\Models\CareerPathCourse;
use App\Models\Category;
use App\Models\CertificateTemplate;
use App\Models\Course;
use App\Models\Program;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seed khoá bổ trợ (extension) + lộ trình nghề theo 3 ngành CNTT / QTKD / ĐTVT.
 * Đồng thời đảm bảo mọi khoá CTĐT (core / gắn curriculum) có price = 0.
 *
 * Chạy độc lập: php artisan db:seed --class=CareerPathSeeder
 */
class CareerPathSeeder extends Seeder
{
    public function run(): void
    {
        $this->ensureCurriculumCoursesAreFree();

        $instructors = User::query()
            ->where('email', 'like', 'instructor%@lms.com')
            ->orderBy('id')
            ->get();

        if ($instructors->isEmpty()) {
            $fallback = User::query()->where('email', 'admin@lms.com')->first()
                ?? User::query()->orderBy('id')->first();
            if (!$fallback) {
                $this->command?->error('CareerPathSeeder: chưa có user để làm owner khoá học.');
                return;
            }
            $instructors = collect([$fallback]);
        }

        $pathCert = CertificateTemplate::query()
            ->where('name', 'like', '%Hoàn thành%')
            ->orWhere('name', 'like', '%Nghề%')
            ->first()
            ?? CertificateTemplate::query()->first();

        $programs = Program::query()->whereIn('code', ['CNTT', 'QTKD', 'DTVT'])->get()->keyBy('code');
        $categories = $this->resolveCategories();

        $catalog = $this->catalog();
        $stats = ['courses' => 0, 'paths' => 0, 'links' => 0];
        $ownerCursor = 0;

        foreach ($catalog as $pathDef) {
            $program = $programs->get($pathDef['program_code']);
            $categoryId = $categories[$pathDef['category_key']] ?? $categories[$pathDef['program_code']] ?? null;

            $courseIds = [];
            $pathTotal = 0;
            foreach ($pathDef['courses'] as $index => $courseTitle) {
                $unitPrice = (int) ($pathDef['course_prices'][$index] ?? $pathDef['course_price'] ?? 300000);
                $owner = $instructors[$ownerCursor % $instructors->count()];
                $ownerCursor++;
                $course = $this->upsertExtensionCourse(
                    $courseTitle,
                    (int) $owner->id,
                    $categoryId,
                    $program?->id,
                    $unitPrice,
                    $pathDef['cover_url'],
                    $index,
                );
                $courseIds[] = $course->id;
                $pathTotal += (int) $course->price;
                $stats['courses']++;
            }

            // Giá lộ trình = tổng giá các khoá thành phần (cho phép mua lẻ từng khoá).
            $path = CareerPath::query()->updateOrCreate(
                ['slug' => $pathDef['slug']],
                [
                    'title' => $pathDef['title'],
                    'description' => $pathDef['description'],
                    'target_role' => $pathDef['target_role'],
                    'price' => $pathTotal,
                    'status' => 'published',
                    'cover_url' => $pathDef['cover_url'],
                    'certificate_template_id' => $pathCert?->id,
                    'created_by' => $owner->id,
                    'published_at' => now()->subDays(3),
                ]
            );
            $stats['paths']++;

            CareerPathCourse::query()->where('career_path_id', $path->id)->delete();
            foreach ($courseIds as $index => $courseId) {
                CareerPathCourse::query()->create([
                    'career_path_id' => $path->id,
                    'course_id' => $courseId,
                    'sort_order' => $index,
                    'is_required' => true,
                    'milestone_label' => $this->milestoneLabel($index, count($courseIds)),
                ]);
                $stats['links']++;
            }

            $path->recalculatePriceFromCourses();
        }

        $this->command?->info(sprintf(
            'CareerPathSeeder: %d lộ trình, %d khoá bổ trợ (upsert), %d liên kết path↔course. Core/CTĐT đã đặt price=0.',
            $stats['paths'],
            $stats['courses'],
            $stats['links'],
        ));
    }

    private function ensureCurriculumCoursesAreFree(): void
    {
        $updated = Course::query()
            ->where(function ($q) {
                $q->where('course_mode', 'core')
                    ->orWhereNotNull('curriculum_id')
                    ->orWhere('is_credit_bearing', true);
            })
            ->where('price', '>', 0)
            ->update(['price' => 0]);

        // Core luôn free dù chưa gắn curriculum
        Course::query()->where('course_mode', 'core')->where('price', '>', 0)->update(['price' => 0]);

        $this->command?->info("CareerPathSeeder: đã đặt free cho {$updated} khoá CTĐT/core (nếu có).");
    }

    private function upsertExtensionCourse(
        string $title,
        int $ownerId,
        ?int $categoryId,
        ?int $programId,
        int $price,
        string $fallbackCover,
        int $index,
    ): Course {
        $slugBase = Str::slug($title) ?: 'khoa-bo-tro';
        $existing = Course::query()->where('title', $title)->where('course_mode', 'extension')->first();

        if (!$existing) {
            $slug = $slugBase;
            $suffix = 1;
            while (Course::query()->where('slug', $slug)->exists()) {
                $slug = $slugBase . '-' . (++$suffix);
            }

            $existing = Course::query()->create([
                'user_id' => $ownerId,
                'category_id' => $categoryId,
                'program_id' => $programId,
                'title' => $title,
                'slug' => $slug,
                'description' => "Khoá bổ trợ marketplace: {$title}. Phục vụ lộ trình nghề nghiệp thực chiến.",
                'price' => $price,
                'status' => 'published',
                'course_mode' => 'extension',
                'is_credit_bearing' => false,
                'credit_value' => null,
                'thumbnail' => $this->courseThumbnail($title, $fallbackCover, $index),
                'published_at' => now()->subDays(2 + ($index % 10)),
            ]);
        } else {
            $existing->update([
                'user_id' => $ownerId,
                'category_id' => $categoryId ?: $existing->category_id,
                'program_id' => $programId ?: $existing->program_id,
                'price' => $price,
                'status' => 'published',
                'course_mode' => 'extension',
                'is_credit_bearing' => false,
                'thumbnail' => $existing->thumbnail ?: $this->courseThumbnail($title, $fallbackCover, $index),
                'published_at' => $existing->published_at ?: now()->subDays(2),
                'description' => $existing->description ?: "Khoá bổ trợ marketplace: {$title}. Phục vụ lộ trình nghề nghiệp thực chiến.",
            ]);
        }

        return $existing->fresh();
    }

    private function milestoneLabel(int $index, int $total): string
    {
        if ($total <= 1) {
            return 'Hoàn thiện';
        }
        if ($index === 0) {
            return 'Nền tảng';
        }
        if ($index === $total - 1) {
            return 'Hoàn thiện';
        }
        if ($index === 1) {
            return 'Thực chiến';
        }

        return 'Chuyên sâu';
    }

    private function resolveCategories(): array
    {
        $bySlug = Category::query()->get()->keyBy('slug');

        $find = function (array $slugs, ?string $nameContains = null) use ($bySlug) {
            foreach ($slugs as $slug) {
                if ($bySlug->has($slug)) {
                    return (int) $bySlug->get($slug)->id;
                }
                $fuzzy = $bySlug->first(fn ($c) => str_starts_with((string) $c->slug, $slug));
                if ($fuzzy) {
                    return (int) $fuzzy->id;
                }
            }
            if ($nameContains) {
                $hit = Category::query()->where('name', 'like', '%' . $nameContains . '%')->orderByRaw('parent_id is null')->first();
                if ($hit) {
                    return (int) $hit->id;
                }
            }

            return null;
        };

        return [
            'CNTT' => $find(['cong-nghe-thong-tin', 'lap-trinh-phan-mem'], 'Công nghệ thông tin'),
            'QTKD' => $find(['quan-tri-kinh-doanh', 'marketing-thuong-mai'], 'Quản trị kinh doanh'),
            'DTVT' => $find(['dien-tu-vien-thong', 'mang-vien-thong'], 'Điện tử'),
            'software' => $find(['lap-trinh-phan-mem'], 'Lập trình'),
            'data' => $find(['csdl-he-thong'], 'dữ liệu'),
            'ai' => $find(['tri-tue-nhan-tao'], 'Trí tuệ'),
            'marketing' => $find(['marketing-thuong-mai'], 'Marketing'),
            'project' => $find(['quan-ly-du-an'], 'dự án'),
            'network' => $find(['mang-vien-thong'], 'Mạng'),
            'iot' => $find(['dien-tu-iot'], 'IoT'),
        ];
    }

    private function courseThumbnail(string $title, string $fallback, int $seed): string
    {
        $t = mb_strtolower($title);
        $pool = match (true) {
            str_contains($t, 'ai') || str_contains($t, 'machine') || str_contains($t, 'deep learning') || str_contains($t, 'nlp') => [
                'https://images.unsplash.com/photo-1677442136019-21780ecad995?w=1200&q=80',
                'https://images.unsplash.com/photo-1620712943543-bcc4688e7485?w=1200&q=80',
            ],
            str_contains($t, 'docker') || str_contains($t, 'kubernetes') || str_contains($t, 'devops') || str_contains($t, 'ci/cd') || str_contains($t, 'linux') => [
                'https://images.unsplash.com/photo-1667372393119-3d4c48d07fc9?w=1200&q=80',
                'https://images.unsplash.com/photo-1558494949-ef010cbdcc31?w=1200&q=80',
            ],
            str_contains($t, 'sql') || str_contains($t, 'database') || str_contains($t, 'data') || str_contains($t, 'etl') || str_contains($t, 'bi') => [
                'https://images.unsplash.com/photo-1551288049-bebda4e38f71?w=1200&q=80',
                'https://images.unsplash.com/photo-1544197150-b99a580bb7a8?w=1200&q=80',
            ],
            str_contains($t, 'mobile') || str_contains($t, 'flutter') || str_contains($t, 'react native') || str_contains($t, 'kotlin') || str_contains($t, 'swift') => [
                'https://images.unsplash.com/photo-1512941937669-90a1b58e7e9c?w=1200&q=80',
            ],
            str_contains($t, 'security') || str_contains($t, 'penetration') || str_contains($t, 'an toàn') || str_contains($t, 'ceh') => [
                'https://images.unsplash.com/photo-1550751827-4bd374c3f58b?w=1200&q=80',
            ],
            str_contains($t, 'marketing') || str_contains($t, 'seo') || str_contains($t, 'bán hàng') || str_contains($t, 'crm') => [
                'https://images.unsplash.com/photo-1460925895917-afdab827c52f?w=1200&q=80',
                'https://images.unsplash.com/photo-1533750349088-cd871a92f312?w=1200&q=80',
            ],
            str_contains($t, 'iot') || str_contains($t, 'arduino') || str_contains($t, 'nhúng') || str_contains($t, 'fpga') || str_contains($t, 'rf') => [
                'https://images.unsplash.com/photo-1518770660439-4636190af475?w=1200&q=80',
                'https://images.unsplash.com/photo-1581091226825-a6a2a5aee158?w=1200&q=80',
            ],
            str_contains($t, 'mạng') || str_contains($t, 'ccna') || str_contains($t, 'network') || str_contains($t, '5g') => [
                'https://images.unsplash.com/photo-1558494949-ef010cbdcc31?w=1200&q=80',
            ],
            default => [$fallback, 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=1200&q=80'],
        };

        return $pool[abs($seed) % count($pool)];
    }

    /**
     * Catalog lộ trình theo ngành — mỗi path có danh sách khoá bổ trợ tương ứng.
     *
     * @return list<array{
     *   program_code: string,
     *   category_key: string,
     *   title: string,
     *   slug: string,
     *   target_role: string,
     *   description: string,
     *   cover_url: string,
     *   course_price: int,
     *   course_prices?: list<int>,
     *   courses: list<string>
     * }>
     */
    private function catalog(): array
    {
        return [
            // ── 1. CNTT ──────────────────────────────────────────────────────
            [
                'program_code' => 'CNTT',
                'category_key' => 'software',
                'title' => 'Lập trình viên Fullstack',
                'slug' => 'cntt-fullstack-developer',
                'target_role' => 'cntt_fullstack',
                'description' => 'Lộ trình CNTT: từ HTML/CSS/JS nâng cao đến frontend framework, backend, database và triển khai Docker/CI-CD.',
                'cover_url' => 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=1400&q=80',
                'course_price' => 300000,
                'courses' => [
                    'HTML/CSS/JavaScript nâng cao',
                    'Frontend Framework (React/Vue)',
                    'Backend (Node.js/PHP/Java Spring)',
                    'Database (MySQL/PostgreSQL/MongoDB)',
                    'Git & triển khai (Docker, CI/CD)',
                ],
            ],
            [
                'program_code' => 'CNTT',
                'category_key' => 'data',
                'title' => 'DevOps Engineer',
                'slug' => 'cntt-devops-engineer',
                'target_role' => 'cntt_devops',
                'description' => 'Lộ trình CNTT: Linux, Docker/Kubernetes, CI/CD, Cloud và Monitoring/Logging cho vận hành hệ thống.',
                'cover_url' => 'https://images.unsplash.com/photo-1667372393119-3d4c48d07fc9?w=1400&q=80',
                'course_price' => 300000,
                'courses' => [
                    'Linux nâng cao',
                    'Docker & Kubernetes',
                    'CI/CD (Jenkins/GitLab CI)',
                    'Cloud (AWS/Azure/GCP)',
                    'Monitoring & Logging (Prometheus, ELK)',
                ],
            ],
            [
                'program_code' => 'CNTT',
                'category_key' => 'data',
                'title' => 'Data Analyst / Data Engineer',
                'slug' => 'cntt-data-analyst-engineer',
                'target_role' => 'cntt_data',
                'description' => 'Lộ trình CNTT: SQL nâng cao, Python data stack, BI, ETL/Data Warehouse và thống kê ứng dụng.',
                'cover_url' => 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?w=1400&q=80',
                'course_price' => 300000,
                'courses' => [
                    'SQL nâng cao & tối ưu truy vấn',
                    'Python (Pandas, NumPy)',
                    'Công cụ BI (Power BI/Tableau)',
                    'ETL & Data Warehouse',
                    'Thống kê ứng dụng',
                ],
            ],
            [
                'program_code' => 'CNTT',
                'category_key' => 'software',
                'title' => 'Kỹ sư kiểm thử phần mềm (QA/Tester)',
                'slug' => 'cntt-qa-tester',
                'target_role' => 'cntt_qa',
                'description' => 'Lộ trình CNTT: Manual Testing, Automation (Selenium/Cypress), API testing và quy trình QA.',
                'cover_url' => 'https://images.unsplash.com/photo-1581091226825-a6a2a5aee158?w=1400&q=80',
                'course_price' => 300000,
                'courses' => [
                    'Kiến thức kiểm thử phần mềm (Manual Testing)',
                    'Automation Testing (Selenium/Cypress)',
                    'Kiểm thử API (Postman)',
                    'Thiết kế test case & quy trình QA',
                ],
            ],
            [
                'program_code' => 'CNTT',
                'category_key' => 'network',
                'title' => 'Chuyên viên An toàn thông tin',
                'slug' => 'cntt-cybersecurity',
                'target_role' => 'cntt_security',
                'description' => 'Lộ trình CNTT: mạng nâng cao, Penetration Testing, chứng chỉ CEH/Security+ và Linux hardening.',
                'cover_url' => 'https://images.unsplash.com/photo-1550751827-4bd374c3f58b?w=1400&q=80',
                'course_price' => 300000,
                'courses' => [
                    'Mạng máy tính nâng cao',
                    'Penetration Testing',
                    'Chứng chỉ CEH/CompTIA Security+',
                    'Linux hardening & Threat hunting',
                ],
            ],
            [
                'program_code' => 'CNTT',
                'category_key' => 'software',
                'title' => 'Lập trình viên Mobile',
                'slug' => 'cntt-mobile-developer',
                'target_role' => 'cntt_mobile',
                'description' => 'Lộ trình CNTT: Flutter/React Native (hoặc native), tích hợp API, UI/UX mobile và publish Store.',
                'cover_url' => 'https://images.unsplash.com/photo-1512941937669-90a1b58e7e9c?w=1400&q=80',
                'course_price' => 300000,
                'courses' => [
                    'Flutter/React Native (hoặc Kotlin/Swift native)',
                    'Tích hợp API/Backend cho mobile',
                    'UI/UX cho mobile',
                    'Publish app lên Store',
                ],
            ],
            [
                'program_code' => 'CNTT',
                'category_key' => 'ai',
                'title' => 'Kỹ sư AI/Machine Learning',
                'slug' => 'cntt-ai-ml-engineer',
                'target_role' => 'cntt_ai_ml',
                'description' => 'Lộ trình CNTT: Python chuyên sâu, toán xác suất & đại số tuyến tính, TensorFlow/PyTorch, Deep Learning & NLP/CV.',
                'cover_url' => 'https://images.unsplash.com/photo-1677442136019-21780ecad995?w=1400&q=80',
                'course_price' => 300000,
                'courses' => [
                    'Python chuyên sâu',
                    'Toán xác suất thống kê & Đại số tuyến tính',
                    'ML Framework (TensorFlow/PyTorch)',
                    'Deep Learning & NLP/Computer Vision',
                ],
            ],

            // ── 2. QTKD ──────────────────────────────────────────────────────
            [
                'program_code' => 'QTKD',
                'category_key' => 'marketing',
                'title' => 'Chuyên viên Digital Marketing',
                'slug' => 'qtkd-digital-marketing',
                'target_role' => 'qtkd_digital_marketing',
                'description' => 'Lộ trình QTKD: SEO/SEM, Social, Content, Analytics/Ads và Marketing Automation.',
                'cover_url' => 'https://images.unsplash.com/photo-1533750349088-cd871a92f312?w=1400&q=80',
                'course_price' => 300000,
                'courses' => [
                    'SEO/SEM',
                    'Social Media Marketing',
                    'Content Marketing',
                    'Công cụ phân tích (Google Analytics, Ads)',
                    'Marketing Automation',
                ],
            ],
            [
                'program_code' => 'QTKD',
                'category_key' => 'marketing',
                'title' => 'Chuyên viên/Quản lý Bán hàng',
                'slug' => 'qtkd-sales-management',
                'target_role' => 'qtkd_sales',
                'description' => 'Lộ trình QTKD: kỹ năng bán hàng, CRM, đàm phán & CSKH, quản trị kênh phân phối.',
                'cover_url' => 'https://images.unsplash.com/photo-1556745757-8d76bdb6984b?w=1400&q=80',
                'course_price' => 300000,
                'courses' => [
                    'Kỹ năng bán hàng chuyên nghiệp',
                    'Sử dụng CRM (Salesforce/HubSpot)',
                    'Đàm phán & chăm sóc khách hàng',
                    'Quản trị kênh phân phối',
                ],
            ],
            [
                'program_code' => 'QTKD',
                'category_key' => 'project',
                'title' => 'Chuyên viên Nhân sự (HR)',
                'slug' => 'qtkd-human-resources',
                'target_role' => 'qtkd_hr',
                'description' => 'Lộ trình QTKD: tuyển dụng, C&B, Luật lao động, đào tạo & phát triển nhân sự.',
                'cover_url' => 'https://images.unsplash.com/photo-1521737604893-d14cc237f11d?w=1400&q=80',
                'course_price' => 300000,
                'courses' => [
                    'Tuyển dụng & phỏng vấn',
                    'C&B (lương thưởng phúc lợi)',
                    'Luật lao động',
                    'Đào tạo & phát triển nhân sự',
                ],
            ],
            [
                'program_code' => 'QTKD',
                'category_key' => 'project',
                'title' => 'Chuyên viên Tài chính - Kế toán',
                'slug' => 'qtkd-finance-accounting',
                'target_role' => 'qtkd_finance',
                'description' => 'Lộ trình QTKD: kế toán nâng cao, phân tích BCTC, Excel/Power BI tài chính và ACCA/CFA cơ bản.',
                'cover_url' => 'https://images.unsplash.com/photo-1554224155-6726b3ff858f?w=1400&q=80',
                'course_price' => 300000,
                'courses' => [
                    'Nguyên lý kế toán nâng cao',
                    'Phân tích báo cáo tài chính',
                    'Excel/Power BI cho tài chính',
                    'Chứng chỉ ACCA/CFA cơ bản',
                ],
            ],
            [
                'program_code' => 'QTKD',
                'category_key' => 'project',
                'title' => 'Quản lý Dự án (Project Manager)',
                'slug' => 'qtkd-project-manager',
                'target_role' => 'qtkd_pm',
                'description' => 'Lộ trình QTKD: Agile/Scrum, PMP/PMI cơ bản, quản trị rủi ro và công cụ Jira/Trello/MS Project.',
                'cover_url' => 'https://images.unsplash.com/photo-1531403009284-440f080d1e12?w=1400&q=80',
                'course_price' => 300000,
                'courses' => [
                    'Phương pháp Agile/Scrum',
                    'Chứng chỉ PMP/PMI cơ bản',
                    'Quản trị rủi ro dự án',
                    'Công cụ quản lý (Jira/Trello/MS Project)',
                ],
            ],
            [
                'program_code' => 'QTKD',
                'category_key' => 'marketing',
                'title' => 'Khởi nghiệp / Quản trị doanh nghiệp',
                'slug' => 'qtkd-startup-management',
                'target_role' => 'qtkd_startup',
                'description' => 'Lộ trình QTKD: Business Plan, quản trị chiến lược, tài chính khởi nghiệp và gọi vốn.',
                'cover_url' => 'https://images.unsplash.com/photo-1552664730-d307ca884978?w=1400&q=80',
                'course_price' => 300000,
                'courses' => [
                    'Lập kế hoạch kinh doanh (Business Plan)',
                    'Quản trị chiến lược',
                    'Tài chính khởi nghiệp',
                    'Gọi vốn đầu tư',
                ],
            ],
            [
                'program_code' => 'QTKD',
                'category_key' => 'marketing',
                'title' => 'Chuyên viên Xuất nhập khẩu - Logistics',
                'slug' => 'qtkd-import-export-logistics',
                'target_role' => 'qtkd_logistics',
                'description' => 'Lộ trình QTKD: nghiệp vụ XNK, Incoterms, chuỗi cung ứng và hải quan điện tử.',
                'cover_url' => 'https://images.unsplash.com/photo-1586528116311-ad8dd3c8310d?w=1400&q=80',
                'course_price' => 300000,
                'courses' => [
                    'Nghiệp vụ xuất nhập khẩu',
                    'Incoterms & chứng từ thương mại',
                    'Quản trị chuỗi cung ứng',
                    'Khai báo hải quan điện tử',
                ],
            ],

            // ── 3. ĐTVT ──────────────────────────────────────────────────────
            [
                'program_code' => 'DTVT',
                'category_key' => 'network',
                'title' => 'Kỹ sư mạng viễn thông (Network Engineer)',
                'slug' => 'dtvt-network-engineer',
                'target_role' => 'dtvt_network',
                'description' => 'Lộ trình ĐTVT: CCNA/CCNP, mạng IP & định tuyến, 4G/5G, quản lý & vận hành hệ thống mạng.',
                'cover_url' => 'https://images.unsplash.com/photo-1558494949-ef010cbdcc31?w=1400&q=80',
                'course_price' => 300000,
                'courses' => [
                    'Chứng chỉ CCNA/CCNP',
                    'Mạng IP & định tuyến chuyển mạch',
                    'Công nghệ 4G/5G',
                    'Quản lý & vận hành hệ thống mạng',
                ],
            ],
            [
                'program_code' => 'DTVT',
                'category_key' => 'iot',
                'title' => 'Kỹ sư IoT',
                'slug' => 'dtvt-iot-engineer',
                'target_role' => 'dtvt_iot',
                'description' => 'Lộ trình ĐTVT: vi điều khiển, cảm biến & truyền thông không dây, MQTT/CoAP, Cloud IoT.',
                'cover_url' => 'https://images.unsplash.com/photo-1518770660439-4636190af475?w=1400&q=80',
                'course_price' => 300000,
                'courses' => [
                    'Lập trình vi điều khiển (Arduino/ESP32/STM32)',
                    'Cảm biến & truyền thông không dây',
                    'Giao thức IoT (MQTT/CoAP)',
                    'Nền tảng Cloud IoT (AWS IoT, Azure IoT)',
                ],
            ],
            [
                'program_code' => 'DTVT',
                'category_key' => 'iot',
                'title' => 'Kỹ sư thiết kế vi mạch (IC Design)',
                'slug' => 'dtvt-ic-design',
                'target_role' => 'dtvt_ic_design',
                'description' => 'Lộ trình ĐTVT: Verilog/VHDL, FPGA, quy trình VLSI và công cụ EDA Cadence/Synopsys.',
                'cover_url' => 'https://images.unsplash.com/photo-1518770660439-4636190af475?w=1400&q=80',
                'course_price' => 300000,
                'courses' => [
                    'Ngôn ngữ mô tả phần cứng (Verilog/VHDL)',
                    'Thiết kế trên FPGA',
                    'Quy trình thiết kế VLSI',
                    'Công cụ EDA (Cadence/Synopsys)',
                ],
            ],
            [
                'program_code' => 'DTVT',
                'category_key' => 'iot',
                'title' => 'Kỹ sư điện tử nhúng (Embedded Engineer)',
                'slug' => 'dtvt-embedded-engineer',
                'target_role' => 'dtvt_embedded',
                'description' => 'Lộ trình ĐTVT: C/C++ nhúng, kiến trúc ARM, RTOS và giao tiếp UART/SPI/I2C.',
                'cover_url' => 'https://images.unsplash.com/photo-1581091226825-a6a2a5aee158?w=1400&q=80',
                'course_price' => 300000,
                'courses' => [
                    'C/C++ nâng cao cho nhúng',
                    'Kiến trúc vi xử lý ARM',
                    'Hệ điều hành thời gian thực (RTOS)',
                    'Giao tiếp phần cứng (UART/SPI/I2C)',
                ],
            ],
            [
                'program_code' => 'DTVT',
                'category_key' => 'network',
                'title' => 'Kỹ sư RF/Anten',
                'slug' => 'dtvt-rf-antenna',
                'target_role' => 'dtvt_rf',
                'description' => 'Lộ trình ĐTVT: trường điện từ, mạch RF, đo kiểm & mô phỏng anten (CST/HFSS), thông tin vô tuyến.',
                'cover_url' => 'https://images.unsplash.com/photo-1451187580459-43490279c0fa?w=1400&q=80',
                'course_price' => 300000,
                'courses' => [
                    'Lý thuyết trường điện từ nâng cao',
                    'Thiết kế mạch RF',
                    'Đo kiểm & mô phỏng anten (CST/HFSS)',
                    'Hệ thống thông tin vô tuyến',
                ],
            ],
            [
                'program_code' => 'DTVT',
                'category_key' => 'network',
                'title' => 'Kỹ sư hạ tầng viễn thông',
                'slug' => 'dtvt-telecom-infrastructure',
                'target_role' => 'dtvt_infra',
                'description' => 'Lộ trình ĐTVT: truyền dẫn quang, tổng đài & báo hiệu, NMS, vận hành BTS/data center.',
                'cover_url' => 'https://images.unsplash.com/photo-1544197150-b99a580bb7a8?w=1400&q=80',
                'course_price' => 300000,
                'courses' => [
                    'Truyền dẫn quang',
                    'Hệ thống tổng đài & báo hiệu',
                    'Quản lý mạng viễn thông (NMS)',
                    'Vận hành trạm BTS/data center',
                ],
            ],
        ];
    }
}

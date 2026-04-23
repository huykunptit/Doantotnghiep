<?php

namespace Database\Seeders;

use App\Models\AssignmentSubmission;
use App\Models\CareerRecommendation;
use App\Models\Category;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\JobPosting;
use App\Models\Lesson;
use App\Models\LessonAssignment;
use App\Models\LessonAttachment;
use App\Models\LessonProgress;
use App\Models\OfflineSession;
use App\Models\Order;
use App\Models\Question;
use App\Models\QuestionBank;
use App\Models\Quiz;
use App\Models\Review;
use App\Models\ScormPackage;
use App\Models\Section;
use App\Models\User;
use App\Models\UserCV;
use App\Models\VirtualClass;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(RoleSeeder::class);
        $this->call(CategorySeeder::class);

        $flags = $this->schemaFlags();

        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@lms.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'avatar' => 'https://i.pravatar.cc/300?img=68',
            ]
        );
        $admin->syncRoles(['admin']);

        $instructors = collect(range(1, 8))->map(function (int $i) {
            $user = User::query()->updateOrCreate(
                ['email' => "instructor{$i}@lms.com"],
                [
                    'name' => "Instructor {$i}",
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'avatar' => "https://i.pravatar.cc/300?img=" . (10 + $i),
                    'bio' => "Giảng viên demo số {$i} phụ trách các khóa học mẫu cho hệ thống.",
                ]
            );
            $user->syncRoles(['instructor']);

            return $user;
        });

        $students = collect(range(1, 18))->map(function (int $i) {
            $user = User::query()->updateOrCreate(
                ['email' => "student{$i}@lms.com"],
                [
                    'name' => "Student {$i}",
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'avatar' => "https://i.pravatar.cc/300?img=" . (40 + $i),
                    'bio' => "Học viên demo số {$i} dùng để hiển thị dữ liệu mẫu.",
                ]
            );
            $user->syncRoles(['student']);

            return $user;
        });

        $categories = Category::query()
            ->whereNull('parent_id')
            ->with(['children' => fn ($query) => $query->orderBy('sort_order')->orderBy('name')])
            ->orderBy('sort_order')
            ->get();

        if ($categories->isEmpty()) {
            return;
        }

        $courseBlueprints = $this->courseBlueprints($categories);

        $publishedCourses = collect();

        $categoryCursor = [];

        foreach ($courseBlueprints as $index => $blueprint) {
            $rootCategory = $categories->firstWhere('id', $blueprint['root_category_id']) ?? $categories[$index % $categories->count()];
            $leafCategories = $rootCategory->children->isNotEmpty()
                ? $rootCategory->children->values()
                : collect([$rootCategory]);

            $cursor = $categoryCursor[$rootCategory->id] ?? 0;
            $category = $leafCategories[$cursor % $leafCategories->count()];
            $categoryCursor[$rootCategory->id] = $cursor + 1;

            $instructor = $instructors[$index % $instructors->count()];
            $course = $this->upsertCourse($blueprint, $instructor, $category);
            $this->seedCourseContent($course, $flags);

            if ($course->status === 'published') {
                $publishedCourses->push($course);
            }
        }

        $this->seedCommerceAndSocialData($publishedCourses, $students, $flags);
        $this->seedCareerDemoData($students, $publishedCourses, $flags);

        $this->call(CourseContentSeeder::class);
    }

    private function schemaFlags(): array
    {
        return [
            'sections' => Schema::hasTable('sections'),
            'lesson_attachments' => Schema::hasTable('lesson_attachments'),
            'quizzes' => Schema::hasTable('quizzes'),
            'quiz_question' => Schema::hasTable('quiz_question'),
            'questions' => Schema::hasTable('questions'),
            'answers' => Schema::hasTable('answers'),
            'question_banks' => Schema::hasTable('question_banks'),
            'quiz_attempts' => Schema::hasTable('quiz_attempts'),
            'orders' => Schema::hasTable('orders'),
            'enrollments' => Schema::hasTable('enrollments'),
            'reviews' => Schema::hasTable('reviews'),
            'lesson_progress' => Schema::hasTable('lesson_progress'),
            'virtual_classes' => Schema::hasTable('virtual_classes'),
            'scorm_packages' => Schema::hasTable('scorm_packages'),
            'offline_sessions' => Schema::hasTable('offline_sessions'),
            'lesson_assignments' => Schema::hasTable('lesson_assignments'),
            'assignment_submissions' => Schema::hasTable('assignment_submissions'),
            'user_cvs' => Schema::hasTable('user_cvs'),
            'job_postings' => Schema::hasTable('job_postings'),
            'career_recommendations' => Schema::hasTable('career_recommendations'),
            'lessons.section_id' => Schema::hasColumn('lessons', 'section_id'),
            'lessons.type' => Schema::hasColumn('lessons', 'type'),
            'lessons.video_size' => Schema::hasColumn('lessons', 'video_size'),
            'lessons.video_status' => Schema::hasColumn('lessons', 'video_status'),
            'quizzes.settings' => Schema::hasTable('quizzes') && Schema::hasColumn('quizzes', 'settings'),
            'questions.bank' => Schema::hasTable('questions') && Schema::hasColumn('questions', 'question_bank_id'),
            'questions.difficulty' => Schema::hasTable('questions') && Schema::hasColumn('questions', 'difficulty'),
            'questions.explanation' => Schema::hasTable('questions') && Schema::hasColumn('questions', 'explanation'),
            'answers.sub_content' => Schema::hasTable('answers') && Schema::hasColumn('answers', 'sub_content'),
            'answers.sort_order' => Schema::hasTable('answers') && Schema::hasColumn('answers', 'sort_order'),
            'answers.order' => Schema::hasTable('answers') && Schema::hasColumn('answers', 'order'),
            'quiz_attempts.answers_json' => Schema::hasTable('quiz_attempts') && Schema::hasColumn('quiz_attempts', 'answers_json'),
            'quiz_attempts.answers_data' => Schema::hasTable('quiz_attempts') && Schema::hasColumn('quiz_attempts', 'answers_data'),
            'quiz_attempts.started_at' => Schema::hasTable('quiz_attempts') && Schema::hasColumn('quiz_attempts', 'started_at'),
            'quiz_attempts.completed_at' => Schema::hasTable('quiz_attempts') && Schema::hasColumn('quiz_attempts', 'completed_at'),
        ];
    }

    private function courseBlueprints($categories): array
    {
        $templates = [
            ['title' => 'Nhập môn', 'price' => 0],
            ['title' => 'Thực chiến', 'price' => 399000],
            ['title' => 'Chuyên sâu', 'price' => 549000],
            ['title' => 'Dự án cuối khóa', 'price' => 699000],
        ];

        $thumbnails = [
            'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?auto=format&fit=crop&w=1200&q=80',
            'https://images.unsplash.com/photo-1498050108023-c5249f4df085?auto=format&fit=crop&w=1200&q=80',
            'https://images.unsplash.com/photo-1522542550221-31fd19575a2d?auto=format&fit=crop&w=1200&q=80',
            'https://images.unsplash.com/photo-1460925895917-afdab827c52f?auto=format&fit=crop&w=1200&q=80',
        ];

        $blueprints = [];

        foreach ($categories as $categoryIndex => $category) {
            $baseName = $category->name;

            foreach ($templates as $templateIndex => $template) {
                $title = sprintf('%s %s', $template['title'], $baseName);
                $blueprints[] = [
                    'title' => $title,
                    'slug' => Str::slug($title),
                    'description' => sprintf('Khóa học %s dành cho danh mục %s, có lộ trình rõ ràng, bài học thực tế và quiz kiểm tra ở cuối.', mb_strtolower($template['title']), $baseName),
                    'status' => 'published',
                    'price' => $template['price'],
                    'thumbnail' => $thumbnails[($categoryIndex + $templateIndex) % count($thumbnails)],
                    'root_category_id' => $category->id,
                ];
            }
        }

        return $blueprints;
    }

    private function upsertCourse(array $blueprint, User $instructor, Category $category): Course
    {
        $attributes = [
            'user_id' => $instructor->id,
            'category_id' => $category->id,
            'title' => $blueprint['title'],
            'slug' => $blueprint['slug'],
            'description' => $blueprint['description'],
            'price' => $blueprint['price'],
            'status' => $blueprint['status'],
            'thumbnail' => $blueprint['thumbnail'],
            'reject_reason' => $blueprint['status'] === 'rejected' ? ($blueprint['reject_reason'] ?? 'Cần cập nhật thêm nội dung trước khi xuất bản.') : null,
            'published_at' => $blueprint['status'] === 'published' ? now()->subDays(random_int(5, 45)) : null,
        ];

        return Course::query()->updateOrCreate(
            ['slug' => $blueprint['slug']],
            $attributes
        );
    }

    private function curriculumBlueprints(): array
    {
        return [
            'laravel-api-thuc-chien-cho-lms' => [
                'sections' => [
                    ['title' => 'Khởi động dự án', 'description' => 'Nhìn tổng thể kiến trúc LMS và cách tổ chức source backend.'],
                    ['title' => 'Xây dựng API cốt lõi', 'description' => 'Đi từ auth, phân quyền, course và lesson sang luồng nghiệp vụ chính.'],
                    ['title' => 'Vận hành production', 'description' => 'Kiểm thử, tối ưu và đưa ứng dụng lên môi trường thật.'],
                ],
                'lessons' => [
                    ['section' => 1, 'title' => 'Tổng quan kiến trúc Laravel cho LMS', 'description' => 'Phân tích domain model, route, service và cách tách module course, lesson, enrollment trong một LMS thực tế.', 'type' => 'video', 'duration' => 1260, 'is_preview' => true, 'asset' => 'laravel-architecture'],
                    ['section' => 1, 'title' => 'Thiết lập Sanctum, Socialite và workflow đăng nhập', 'description' => 'Khởi tạo auth API, luồng đăng nhập Google và cách bảo vệ route cho học viên, giảng viên, admin.', 'type' => 'video', 'duration' => 1380, 'asset' => 'laravel-auth', 'scorm' => true],
                    ['section' => 2, 'title' => 'Quiz kiểm tra thiết kế API course', 'description' => 'Kiểm tra nhanh khả năng chuẩn hóa endpoint, validation và status flow khi tạo khóa học.', 'type' => 'quiz', 'duration' => 1200],
                    ['section' => 2, 'title' => 'Assignment: tạo bộ endpoint cho enrollment và progress', 'description' => 'Hoàn thiện bộ endpoint cho ghi danh, cập nhật tiến độ học tập và response cho dashboard học viên.', 'type' => 'assignment', 'duration' => 1500, 'assignment_instructions' => 'Xây dựng endpoint enrollment, lesson progress và viết tài liệu API ngắn gọn kèm ví dụ request response.', 'asset' => 'laravel-enrollment'],
                    ['section' => 3, 'title' => 'Live workshop: queue, cache và upload video', 'description' => 'Buổi live tổng hợp cách xử lý upload media, queue job và tối ưu hiệu năng cho LMS.', 'type' => 'live', 'duration' => 5400, 'meeting_topic' => 'Queue và media pipeline cho Laravel LMS'],
                    ['section' => 3, 'title' => 'Offline lab: deploy backend lên VPS', 'description' => 'Thực hành deploy Laravel với worker, scheduler, queue và giám sát log trên production.', 'type' => 'offline', 'duration' => 7200, 'location' => 'Phòng Lab Backend - Tòa nhà A2'],
                ],
            ],
            'nuxt-frontend-cho-edtech' => [
                'sections' => [
                    ['title' => 'Nền tảng giao diện', 'description' => 'Đặt nền cho app Nuxt và hệ thống UI có thể tái sử dụng.'],
                    ['title' => 'Trải nghiệm học tập', 'description' => 'Tập trung vào course page, curriculum và dashboard của người học.'],
                    ['title' => 'Đồng bộ sản phẩm', 'description' => 'Hoàn thiện state, loading state và luồng release frontend.'],
                ],
                'lessons' => [
                    ['section' => 1, 'title' => 'Khởi tạo Nuxt workspace cho EdTech', 'description' => 'Cấu trúc app router, layout, composable và chiến lược tái sử dụng UI cho một nền tảng học tập.', 'type' => 'video', 'duration' => 1140, 'is_preview' => true, 'asset' => 'nuxt-workspace'],
                    ['section' => 1, 'title' => 'Dùng Pinia và schema UI để giữ giao diện đồng bộ', 'description' => 'Quản lý auth, course, lesson state và cách sử dụng token trong toàn bộ app Nuxt.', 'type' => 'video', 'duration' => 1320, 'asset' => 'nuxt-state', 'scorm' => true],
                    ['section' => 2, 'title' => 'Quiz: đánh giá hiệu quả trang chi tiết khóa học', 'description' => 'Kiểm tra tư duy bố cục, CTA và cách tổ chức section nội dung cho trang khóa học.', 'type' => 'quiz', 'duration' => 1080],
                    ['section' => 2, 'title' => 'Assignment: thiết kế lại dashboard giảng viên', 'description' => 'Làm một phiên bản dashboard để quản lý khóa học, lesson và doanh thu rõ ràng trên desktop và mobile.', 'type' => 'assignment', 'duration' => 1560, 'assignment_instructions' => 'Vẽ lại giao diện dashboard giảng viên với hệ thống button thống nhất, có state loading và empty state đầy đủ.', 'asset' => 'nuxt-dashboard'],
                    ['section' => 3, 'title' => 'Live review: tối ưu card course và bộ lọc tìm kiếm', 'description' => 'Review giao diện marketplace, card khóa học và bộ lọc để tăng khả năng scan trên màn hình lớn.', 'type' => 'live', 'duration' => 4800, 'meeting_topic' => 'Review giao diện marketplace cho Nuxt EdTech'],
                    ['section' => 3, 'title' => 'Workshop responsive cho khu học tập', 'description' => 'Thực hành tối ưu curriculum, sidebar và player để hoạt động mượt trên laptop và mobile.', 'type' => 'offline', 'duration' => 6600, 'location' => 'Phòng Lab Frontend - Tầng 5'],
                ],
            ],
            'ui-ux-cho-nen-tang-giao-duc-so' => [
                'sections' => [
                    ['title' => 'Nghiên cứu trải nghiệm', 'description' => 'Xác định pain point của học viên, giảng viên và admin trên nền tảng.'],
                    ['title' => 'Hệ thống giao diện', 'description' => 'Xây dựng flow, component và pattern để UI nhất quán hơn.'],
                    ['title' => 'Kiểm thử và tối ưu', 'description' => 'Đo lường và cải tiến trải nghiệm trước khi mở rộng sản phẩm.'],
                ],
                'lessons' => [
                    ['section' => 1, 'title' => 'Map hành trình học viên trên nền tảng số', 'description' => 'Phân tích từ landing page, thanh toán, học tập đến đánh giá khóa học để tìm điểm rời.', 'type' => 'video', 'duration' => 1180, 'is_preview' => true, 'asset' => 'ux-journey'],
                    ['section' => 1, 'title' => 'Tạo information architecture cho dashboard', 'description' => 'Sắp xếp menu, trang con và khu vực thao tác để giảng viên không bị quá tải thông tin.', 'type' => 'video', 'duration' => 1240, 'asset' => 'ux-ia', 'scorm' => true],
                    ['section' => 2, 'title' => 'Quiz: nguyên tắc affordance và hierarchy', 'description' => 'Ôn tập các nguyên tắc visual hierarchy, spacing và action hierarchy cho hệ thống button.', 'type' => 'quiz', 'duration' => 1020],
                    ['section' => 2, 'title' => 'Assignment: viết guideline cho button và form action', 'description' => 'Tạo bộ quy tắc cho màu sắc, hover, disabled state và vị trí nút trong toàn bộ hệ thống.', 'type' => 'assignment', 'duration' => 1500, 'assignment_instructions' => 'Soạn một guideline ngắn gọn cho button UI, form footer và quy tắc hierarchy giữa action chính và action phụ.', 'asset' => 'ux-buttons'],
                    ['section' => 3, 'title' => 'Live critique: review trang giảng viên và trang khóa học', 'description' => 'Critique bố cục trang quản lý khóa học, curriculum và course detail theo góc nhìn UX.', 'type' => 'live', 'duration' => 5100, 'meeting_topic' => 'UI critique cho hệ thống quản lý giảng viên'],
                    ['section' => 3, 'title' => 'Offline workshop về design review', 'description' => 'Nhóm học viên review case study và bài nộp theo checklist UX thực tế.', 'type' => 'offline', 'duration' => 6000, 'location' => 'Studio UX - Phòng B1'],
                ],
            ],
            'digital-marketing-cho-giao-duc' => [
                'sections' => [
                    ['title' => 'Nền tảng marketing', 'description' => 'Xác định audience, thông điệp và KPI cho sản phẩm giáo dục.'],
                    ['title' => 'Thiết kế funnel', 'description' => 'Tối ưu trang khóa học, form thanh toán và email nuôi dưỡng.'],
                    ['title' => 'Đo lường và scale', 'description' => 'Đọc dashboard, test thông điệp và mở rộng chiến dịch.'],
                ],
                'lessons' => [
                    ['section' => 1, 'title' => 'Chân dung học viên và thông điệp thu hút', 'description' => 'Xác định 3 nhóm khách hàng mục tiêu chính và thông điệp giá trị cho từng nhóm.', 'type' => 'video', 'duration' => 1110, 'is_preview' => true, 'asset' => 'marketing-persona'],
                    ['section' => 1, 'title' => 'Landing page và bộ CTA cho khóa học online', 'description' => 'Tổ chức heading, proof point, giá và CTA để tăng tỉ lệ click đăng ký.', 'type' => 'video', 'duration' => 1320, 'asset' => 'marketing-landing', 'scorm' => true],
                    ['section' => 2, 'title' => 'Quiz: xây funnel đăng ký hiệu quả', 'description' => 'Kiểm tra tư duy về lead magnet, email sequence và trang thanh toán cho khóa học.', 'type' => 'quiz', 'duration' => 960],
                    ['section' => 2, 'title' => 'Assignment: lập mini funnel cho khóa học mới', 'description' => 'Xây dựng thông điệp, CTA, email nhắc và nội dung campaign cho một khóa học cụ thể.', 'type' => 'assignment', 'duration' => 1560, 'assignment_instructions' => 'Chọn một khóa học và lập funnel ngắn gồm hero, lead magnet, email sequence 3 bước và CTA thanh toán.', 'asset' => 'marketing-funnel'],
                    ['section' => 3, 'title' => 'Live planning: dashboard KPI cho chiến dịch giáo dục', 'description' => 'Buổi live đọc dashboard đơn hàng, traffic và retention để tìm điểm cần cải thiện.', 'type' => 'live', 'duration' => 4500, 'meeting_topic' => 'Dashboard KPI cho marketing giáo dục'],
                    ['section' => 3, 'title' => 'Workshop nội dung quảng bá đa kênh', 'description' => 'Xây 1 tuần lịch nội dung cho Facebook, email và search ads xung quanh khóa học.', 'type' => 'offline', 'duration' => 6300, 'location' => 'Phòng Marketing Practice - Tầng 3'],
                ],
            ],
            'english-for-software-teams' => [
                'sections' => [
                    ['title' => 'English foundation', 'description' => 'Nâng cấp từ vựng và mẫu câu giao tiếp cho nhóm kỹ thuật.'],
                    ['title' => 'Working communication', 'description' => 'Thực hành meeting, email, report và trình bày giải pháp.'],
                    ['title' => 'Team simulation', 'description' => 'Áp dụng vào tình huống dự án software thật.'],
                ],
                'lessons' => [
                    ['section' => 1, 'title' => 'Vocabulary for product and engineering teams', 'description' => 'Tổng hợp từ vựng về sprint, bug, deployment, roadmap và giải thích bằng tình huống để nhớ lâu.', 'type' => 'video', 'duration' => 1050, 'is_preview' => true, 'asset' => 'english-vocabulary'],
                    ['section' => 1, 'title' => 'Writing clearer standup and update messages', 'description' => 'Cách viết update ngắn gọn, rõ ràng, đúng tone cho Slack, email và issue tracker.', 'type' => 'video', 'duration' => 1170, 'asset' => 'english-updates', 'scorm' => true],
                    ['section' => 2, 'title' => 'Quiz: giao tiếp với stakeholder quốc tế', 'description' => 'Kiểm tra cách chọn từ, đặt câu hỏi và phản hồi trong buổi họp đa chức năng.', 'type' => 'quiz', 'duration' => 900],
                    ['section' => 2, 'title' => 'Assignment: write a technical update and meeting recap', 'description' => 'Viết một bản update sprint và một email recap sau buổi meeting với đối tác nước ngoài.', 'type' => 'assignment', 'duration' => 1320, 'assignment_instructions' => 'Nộp 1 standup update và 1 email recap cho tình huống release sprint có blocker về API.', 'asset' => 'english-recap'],
                    ['section' => 3, 'title' => 'Live speaking clinic for software demos', 'description' => 'Luyện trình bày tính năng, trả lời câu hỏi và xử lý tình huống hỏi đáp bằng tiếng Anh.', 'type' => 'live', 'duration' => 4200, 'meeting_topic' => 'Speaking clinic for product demo'],
                    ['section' => 3, 'title' => 'Offline role-play with product and engineering peers', 'description' => 'Đóng vai product manager, QA, developer và stakeholder trong một buổi planning.', 'type' => 'offline', 'duration' => 5400, 'location' => 'Phòng Language Lab - Tầng 2'],
                ],
            ],
            'devops-foundation-cho-sinh-vien' => [
                'sections' => [
                    ['title' => 'Nền tảng DevOps', 'description' => 'Hiểu chuỗi delivery, automation và văn hóa làm việc liên tục.'],
                    ['title' => 'Công cụ cốt lõi', 'description' => 'Đi qua Docker, CI pipeline, logging và monitoring.'],
                    ['title' => 'Thực chiến vận hành', 'description' => 'Áp dụng pipeline lên ứng dụng thực tế.'],
                ],
                'lessons' => [
                    ['section' => 1, 'title' => 'DevOps là gì trong dự án phần mềm hiện đại', 'description' => 'Giải thích chuỗi từ commit, build, test, deploy đến monitoring bằng những case study gần gũi.', 'type' => 'video', 'duration' => 1200, 'is_preview' => true, 'asset' => 'devops-overview'],
                    ['section' => 1, 'title' => 'Làm quen Docker image, container và compose', 'description' => 'Thực hành đóng gói service backend, frontend và database thành một stack để chạy local.', 'type' => 'video', 'duration' => 1440, 'asset' => 'devops-docker', 'scorm' => true],
                    ['section' => 2, 'title' => 'Quiz: chọn pipeline CI/CD phù hợp', 'description' => 'Ôn tập build, test, artifact và deployment cho dự án web app.', 'type' => 'quiz', 'duration' => 1020],
                    ['section' => 2, 'title' => 'Assignment: tạo pipeline build-test-deploy cơ bản', 'description' => 'Thiết kế một pipeline để build frontend, chạy test backend và deploy lên môi trường staging.', 'type' => 'assignment', 'duration' => 1620, 'assignment_instructions' => 'Nộp file pipeline mô tả build frontend, test backend và deploy staging kèm giải thích trigger.', 'asset' => 'devops-pipeline'],
                    ['section' => 3, 'title' => 'Live demo: logging, metrics và cảnh báo', 'description' => 'Xem cách đọc log, đặt metrics và tạo cảnh báo cho stack học tập online.', 'type' => 'live', 'duration' => 4800, 'meeting_topic' => 'Logging và monitoring cho web app'],
                    ['section' => 3, 'title' => 'Offline lab: triage sự cố production', 'description' => 'Workshop xử lý sự cố giả lập với checklist rollback, cảnh báo và postmortem.', 'type' => 'offline', 'duration' => 6600, 'location' => 'Phòng SRE Practice - Tầng 4'],
                ],
            ],
            'thiet-ke-he-thong-quiz-hien-dai' => [
                'sections' => [
                    ['title' => 'Nền tảng hệ thống thi', 'description' => 'Xác định các thành phần cần có cho quiz, question bank và exam.'],
                    ['title' => 'Triển khai và bảo vệ', 'description' => 'Xây ngẫu nhiên đề, tính điểm và bảo vệ kết quả thi.'],
                    ['title' => 'Báo cáo và cải tiến', 'description' => 'Đọc kết quả, phân tích và tối ưu chất lượng bộ câu hỏi.'],
                ],
                'lessons' => [
                    ['section' => 1, 'title' => 'Question bank, quiz và exam khác nhau thế nào', 'description' => 'Phân biệt vai trò của ngân hàng câu hỏi, quiz nhỏ và kỳ thi có quy chế rõ ràng.', 'type' => 'video', 'duration' => 1190, 'is_preview' => true, 'asset' => 'quiz-foundation'],
                    ['section' => 1, 'title' => 'Cách tổ chức nhóm câu hỏi, độ khó và giải thích', 'description' => 'Tạo câu hỏi để sử dụng lại được, có metadata rõ ràng và để phân tích về sau.', 'type' => 'video', 'duration' => 1370, 'asset' => 'quiz-bank', 'scorm' => true],
                    ['section' => 2, 'title' => 'Quiz: random đề và lưu đáp án an toàn', 'description' => 'Kiểm tra logic random, tính điểm và lưu dữ liệu cho bộ máy quiz hiện đại.', 'type' => 'quiz', 'duration' => 1140],
                    ['section' => 2, 'title' => 'Assignment: thiết kế bộ đề cho môn học bất kỳ', 'description' => 'Tạo 1 ngân hàng mini và quy tắc random đề cho một chủ đề trong LMS.', 'type' => 'assignment', 'duration' => 1500, 'assignment_instructions' => 'Nộp 10 câu hỏi có độ khó, đáp án, giải thích và đề xuất cách random cho 1 bài quiz 15 phút.', 'asset' => 'quiz-assignment'],
                    ['section' => 3, 'title' => 'Live review: đọc báo cáo kết quả và chất lượng câu hỏi', 'description' => 'Đọc dữ liệu kết quả, nhận diện câu hỏi quá dễ, quá khó hoặc gây nhầm lẫn.', 'type' => 'live', 'duration' => 4500, 'meeting_topic' => 'Phân tích kết quả quiz và quality question bank'],
                    ['section' => 3, 'title' => 'Offline workshop: prototyping exam flow', 'description' => 'Workshop nhóm để phác thảo luồng thi, cảnh báo gian lận và màn hình kết quả.', 'type' => 'offline', 'duration' => 6300, 'location' => 'Phòng Assessment Lab - Tầng 6'],
                ],
            ],
            'quan-ly-du-an-edtech' => [
                'sections' => [
                    ['title' => 'Tầm nhìn và planning', 'description' => 'Đặt mục tiêu, scope và roadmap cho dự án giáo dục số.'],
                    ['title' => 'Vận hành đội ngũ', 'description' => 'Phối hợp product, content, engineering và instructor.'],
                    ['title' => 'Kiểm soát chất lượng', 'description' => 'Theo dõi risk, release và cải tiến liên tục.'],
                ],
                'lessons' => [
                    ['section' => 1, 'title' => 'Roadmap cho dự án EdTech từ MVP đến scale', 'description' => 'Chia giai đoạn xây dựng sản phẩm, từng mốc release và cách ưu tiên backlog hợp lý.', 'type' => 'video', 'duration' => 1230, 'is_preview' => true, 'asset' => 'pm-roadmap'],
                    ['section' => 1, 'title' => 'Quản lý stakeholder và đề cương nội dung khóa học', 'description' => 'Cân bằng kỳ vọng giữa business, instructor và team phát triển để tránh scope creep.', 'type' => 'video', 'duration' => 1410, 'asset' => 'pm-stakeholder', 'scorm' => true],
                    ['section' => 2, 'title' => 'Quiz: xử lý risk trong dự án giáo dục số', 'description' => 'Ôn tập communication plan, release plan và cách xử lý thay đổi nội dung vào phút cuối.', 'type' => 'quiz', 'duration' => 1020],
                    ['section' => 2, 'title' => 'Assignment: lập kế hoạch release cho khóa học mới', 'description' => 'Lập timeline, checklist deliverable và phân công trách nhiệm giữa các nhóm.', 'type' => 'assignment', 'duration' => 1620, 'assignment_instructions' => 'Tạo release plan cho một khóa học mới bao gồm content, QA, preview lesson, marketing và launch checklist.', 'asset' => 'pm-release-plan'],
                    ['section' => 3, 'title' => 'Live alignment: họp sprint review với đội nội dung', 'description' => 'Mô phỏng buổi review giữa PM, giảng viên và dev team để giải quyết bất cập.', 'type' => 'live', 'duration' => 4800, 'meeting_topic' => 'Sprint review cho dự án EdTech'],
                    ['section' => 3, 'title' => 'Offline workshop: retrospective và cải tiến quy trình', 'description' => 'Tổng hợp bài học, problem log và đề xuất hành động sau mỗi đợt release.', 'type' => 'offline', 'duration' => 6000, 'location' => 'Phòng Product Ops - Tầng 7'],
                ],
            ],
        ];
    }

    private function seedCourseContent(Course $course, array $flags): array
    {
        $blueprint = $this->curriculumBlueprints()[$course->slug] ?? null;
        $sectionSeeds = $blueprint['sections'] ?? [
            ['title' => 'Khởi động', 'description' => 'Giới thiệu mục tiêu, đầu ra và cách học hiệu quả cho toàn bộ khóa học.'],
            ['title' => 'Thực hành trọng tâm', 'description' => 'Đi qua các bài học chính và tình huống áp dụng thực tế.'],
            ['title' => 'Kiểm tra cuối chương', 'description' => 'Tổng kết kiến thức bằng bài kiểm tra ngắn ở cuối lộ trình.'],
        ];
        $lessonSeeds = $blueprint['lessons'] ?? [
            ['section' => 1, 'title' => 'Tổng quan khóa học', 'description' => "Nắm được lộ trình, mục tiêu và cách theo dõi tiến độ với khóa {$course->title}.", 'type' => 'video', 'duration' => 900, 'is_preview' => true, 'asset' => 'overview'],
            ['section' => 1, 'title' => 'Thiết lập công cụ học tập', 'description' => 'Chuẩn bị tài liệu, môi trường và các bước cần thiết trước khi bắt đầu học sâu.', 'type' => 'video', 'duration' => 1080, 'asset' => 'setup'],
            ['section' => 2, 'title' => 'Bài học thực hành 1', 'description' => 'Áp dụng kiến thức nền tảng vào ví dụ thực tế đầu tiên của khóa học.', 'type' => 'video', 'duration' => 1260, 'asset' => 'practice-1'],
            ['section' => 2, 'title' => 'Bài học thực hành 2', 'description' => 'Tiếp tục hoàn thiện kỹ năng qua tình huống mô phỏng và best practices.', 'type' => 'video', 'duration' => 1320, 'asset' => 'practice-2', 'scorm' => true],
            ['section' => 3, 'title' => 'Quiz cuối khóa', 'description' => 'Bài kiểm tra cuối để đánh giá mức độ nắm kiến thức sau khi hoàn thành 4 bài học.', 'type' => 'quiz', 'duration' => 1200],
        ];
        $sections = [];

        if ($flags['sections']) {
            foreach ($sectionSeeds as $index => $sectionSeed) {
                $sections[$index + 1] = Section::query()->updateOrCreate(
                    ['course_id' => $course->id, 'position' => $index + 1],
                    [
                        'title' => $sectionSeed['title'],
                        'description' => $sectionSeed['description'],
                        'position' => $index + 1,
                    ]
                );
            }
        }

        $lessons = [];

        foreach ($lessonSeeds as $index => $lessonSeed) {
            $lessonOrder = $index + 1;
            $sectionPosition = (int) ($lessonSeed['section'] ?? min(3, (int) floor($index / 2) + 1));
            $section = $sections[$sectionPosition] ?? null;
            $type = $lessonSeed['type'] ?? 'video';
            $asset = $lessonSeed['asset'] ?? Str::slug($lessonSeed['title'] ?? "lesson-{$lessonOrder}");

            $payload = [
                'title' => $lessonSeed['title'],
                'description' => $lessonSeed['description'],
                'duration' => $lessonSeed['duration'] ?? (900 + ($lessonOrder * 180)),
                'order' => $lessonOrder,
                'is_preview' => (bool) ($lessonSeed['is_preview'] ?? false),
                'video_url' => in_array($type, ['video', 'live'], true) ? "demo/{$course->slug}/{$asset}.mp4" : null,
            ];

            if ($flags['lessons.section_id']) {
                $payload['section_id'] = $section?->id;
            }
            if ($flags['lessons.type']) {
                $payload['type'] = $type;
            }
            if ($flags['lessons.video_size']) {
                $payload['video_size'] = in_array($type, ['video', 'live'], true) ? (string) (180 + ($lessonOrder * 14)) . ' MB' : null;
            }
            if ($flags['lessons.video_status']) {
                $payload['video_status'] = in_array($type, ['video', 'live'], true) ? 'ready' : 'pending';
            }

            $lesson = Lesson::query()->updateOrCreate(
                ['course_id' => $course->id, 'order' => $lessonOrder],
                $payload
            );

            $this->seedLessonSupportData($course, $lesson, $lessonSeed, $flags);
            $lessons[] = $lesson;
        }

        return $lessons;
    }

    private function seedLessonSupportData(Course $course, Lesson $lesson, array $lessonSeed, array $flags): void
    {
        $asset = $lessonSeed['asset'] ?? Str::slug($lesson->title);

        if ($flags['lesson_attachments']) {
            LessonAttachment::query()->updateOrCreate(
                ['lesson_id' => $lesson->id, 'original_name' => "{$asset}-slides.pdf"],
                [
                    'file_path' => "attachments/{$course->slug}/{$asset}-slides.pdf",
                    'file_size' => '1.8 MB',
                    'mime_type' => 'application/pdf',
                ]
            );

            LessonAttachment::query()->updateOrCreate(
                ['lesson_id' => $lesson->id, 'original_name' => "{$asset}-worksheet.docx"],
                [
                    'file_path' => "attachments/{$course->slug}/{$asset}-worksheet.docx",
                    'file_size' => '0.9 MB',
                    'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                ]
            );
        }

        if (($lessonSeed['type'] ?? null) === 'quiz' && $flags['quizzes']) {
            $quizPayload = [
                'lesson_id' => $lesson->id,
                'title' => "Quiz - {$lesson->title}",
                'description' => $lessonSeed['description'] ?? "Bài kiểm tra nhanh cho {$course->title}.",
                'time_limit' => 20,
                'pass_score' => 70,
            ];

            if ($flags['quizzes.settings']) {
                $quizPayload['settings'] = [
                    'randomize' => true,
                    'show_result' => true,
                ];
            }

            $quiz = Quiz::query()->updateOrCreate(
                ['lesson_id' => $lesson->id],
                $quizPayload
            );

            $this->seedQuizData($course, $quiz, $lessonSeed, $flags);
        }

        if (($lessonSeed['type'] ?? null) === 'assignment' && $flags['lesson_assignments']) {
            $assignment = LessonAssignment::query()->updateOrCreate(
                ['lesson_id' => $lesson->id],
                [
                    'instructions' => $lessonSeed['assignment_instructions'] ?? 'Hoàn thành báo cáo ngắn hoặc nộp tài liệu theo đề bài để được chấm điểm.',
                    'max_file_size' => 10240,
                    'allowed_extensions' => 'pdf,doc,docx,zip,pptx',
                    'due_at' => now()->addDays(10),
                ]
            );

            if ($flags['assignment_submissions']) {
                AssignmentSubmission::query()->updateOrCreate(
                    [
                        'lesson_assignment_id' => $assignment->id,
                        'user_id' => 3,
                    ],
                    [
                        'file_url' => "submissions/{$course->slug}/submission-demo.pdf",
                        'student_note' => 'Em đã nộp bài demo để kiểm tra luồng hiển thị bài tập.',
                        'grade' => 8.5,
                        'feedback' => 'Bố cục rõ ràng, cần bổ sung thêm phần phân tích và đề xuất.',
                        'submitted_at' => now()->subDays(2),
                    ]
                );
            }
        }

        if (($lessonSeed['type'] ?? null) === 'live' && $flags['virtual_classes']) {
            VirtualClass::query()->updateOrCreate(
                ['lesson_id' => $lesson->id],
                [
                    'provider' => 'google_meet',
                    'meeting_id' => strtoupper(Str::random(10)),
                    'meeting_password' => Str::upper(Str::random(6)),
                    'join_url' => "https://meet.google.com/" . Str::lower(Str::random(3)) . '-' . Str::lower(Str::random(4)) . '-' . Str::lower(Str::random(3)),
                    'start_url' => "https://meet.google.com/start/" . Str::lower(Str::random(12)),
                    'start_at' => now()->addDays(3)->setTime(19, 30),
                    'duration' => 90,
                ]
            );
        }

        if (($lessonSeed['type'] ?? null) === 'offline' && $flags['offline_sessions']) {
            OfflineSession::query()->updateOrCreate(
                ['lesson_id' => $lesson->id],
                [
                    'location' => $lessonSeed['location'] ?? 'Phòng Lab A3 - PTIT',
                    'start_at' => now()->addDays(7)->setTime(8, 0),
                    'duration' => 120,
                    'max_participants' => 35,
                ]
            );
        }

        if (!empty($lessonSeed['scorm']) && $flags['scorm_packages']) {
            ScormPackage::query()->updateOrCreate(
                ['lesson_id' => $lesson->id],
                [
                    'uuid' => (string) Str::uuid(),
                    'version' => '1.2',
                    'entry_url' => "scorm/{$course->slug}/{$asset}/index.html",
                    'identifier' => Str::slug($course->slug . '-' . $asset . '-scorm'),
                    'title' => "SCORM - {$lesson->title}",
                ]
            );
        }
    }

    private function seedQuizData(Course $course, Quiz $quiz, array $lessonSeed, array $flags): void
    {
        $bank = null;

        if ($flags['question_banks']) {
            $bank = QuestionBank::query()->updateOrCreate(
                ['course_id' => $course->id, 'name' => 'Ngân hàng câu hỏi demo'],
                ['description' => 'Dùng để hiển thị dữ liệu mẫu cho hệ thống quiz và question bank.']
            );
        }

        if (!$flags['questions'] || !$flags['answers']) {
            return;
        }

        $questionSeeds = $lessonSeed['quiz_questions'] ?? [
            [
                'content' => "Đầu ra quan trọng nhất của bài học {$lessonSeed['title']} là gì?",
                'options' => ['Hiểu và áp dụng được nội dung chính', 'Chỉ ghi nhớ tên bài học', 'Bỏ qua phần thực hành', 'Không cần theo dõi kết quả'],
                'correct' => 0,
                'explanation' => 'Mục tiêu tốt nhất là hiểu và áp dụng được vào tình huống thật.',
            ],
            [
                'content' => 'Thành phần nào cần được ưu tiên khi xây nội dung bài học?',
                'options' => ['Mục tiêu rõ ràng và tài nguyên đi kèm', 'Chỉ có hình minh họa', 'Không cần mô tả bài học', 'Bỏ qua bài tập'],
                'correct' => 0,
                'explanation' => 'Bài học chất lượng cần mục tiêu và tài nguyên rõ ràng.',
            ],
            [
                'content' => 'Khi học viên hoàn thành bài này, hành động tiếp theo hợp lý là gì?',
                'options' => ['Làm quiz hoặc assignment liên quan', 'Dừng học luôn', 'Xóa tài liệu', 'Bỏ qua feedback'],
                'correct' => 0,
                'explanation' => 'Quiz hoặc assignment giúp củng cố và đo lường kết quả học tập.',
            ],
        ];

        foreach ($questionSeeds as $questionIndex => $questionSeed) {
            $questionOrder = $questionIndex + 1;
            $questionPayload = [
                'course_id' => $course->id,
                'content' => $questionSeed['content'],
                'type' => 'single_choice',
            ];

            if ($flags['questions.bank']) {
                $questionPayload['question_bank_id'] = $bank?->id;
            }
            if ($flags['questions.difficulty']) {
                $questionPayload['difficulty'] = $questionOrder;
            }
            if ($flags['questions.explanation']) {
                $questionPayload['explanation'] = $questionSeed['explanation'] ?? 'Đây là phần giải thích để hiển thị trong màn hình quản lý câu hỏi.';
            }

            $question = Question::query()->updateOrCreate(
                ['course_id' => $course->id, 'content' => $questionPayload['content']],
                $questionPayload
            );

            foreach ($questionSeed['options'] as $answerIndex => $answerContent) {
                $answerOrder = $answerIndex + 1;
                $answerPayload = [
                    'content' => $answerContent,
                    'is_correct' => $answerIndex === (int) ($questionSeed['correct'] ?? 0),
                ];

                if ($flags['answers.sub_content']) {
                    $answerPayload['sub_content'] = null;
                }
                if ($flags['answers.sort_order']) {
                    $answerPayload['sort_order'] = $answerOrder;
                }
                if ($flags['answers.order']) {
                    $answerPayload['order'] = $answerOrder;
                }

                DB::table('answers')->updateOrInsert(
                    ['question_id' => $question->id, 'content' => $answerPayload['content']],
                    array_merge($answerPayload, [
                        'question_id' => $question->id,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ])
                );
            }

            if ($flags['quiz_question']) {
                DB::table('quiz_question')->updateOrInsert(
                    ['quiz_id' => $quiz->id, 'question_id' => $question->id],
                    [
                        'order' => $questionOrder,
                        'points' => 10,
                        'updated_at' => now(),
                        'created_at' => now(),
                    ]
                );
            }
        }
    }

    private function seedCommerceAndSocialData($publishedCourses, $students, array $flags): void
    {
        foreach ($publishedCourses as $courseIndex => $course) {
            $enrolledStudents = $students->slice($courseIndex, 5)->values();

            foreach ($enrolledStudents as $studentIndex => $student) {
                $order = null;
                $paymentRef = sprintf('PAY-%04d-%04d', $course->id, $student->id);

                if ($flags['orders']) {
                    $order = Order::query()->updateOrCreate(
                        ['payment_ref' => $paymentRef],
                        [
                            'user_id' => $student->id,
                            'course_id' => $course->id,
                            'amount' => $course->price,
                            'status' => $course->price > 0 ? 'completed' : 'completed',
                            'payment_method' => $course->price > 0 ? 'payos' : 'free',
                            'gateway_response' => [
                                'transaction_no' => $paymentRef,
                                'message' => 'Seeded demo payment',
                            ],
                            'paid_at' => now()->subDays(10 - $studentIndex),
                        ]
                    );
                }

                if ($flags['enrollments']) {
                    Enrollment::query()->updateOrCreate(
                        ['user_id' => $student->id, 'course_id' => $course->id],
                        [
                            'order_id' => $order?->id,
                            'enrolled_at' => now()->subDays(12 - $studentIndex),
                        ]
                    );
                }

                if ($flags['reviews'] && $studentIndex < 3) {
                    Review::query()->updateOrCreate(
                        ['user_id' => $student->id, 'course_id' => $course->id],
                        [
                            'rating' => 5 - ($studentIndex % 2),
                            'comment' => 'Khóa học demo có nội dung rõ ràng, đủ dữ liệu để hiển thị ở homepage và trang chi tiết.',
                        ]
                    );
                }

                if ($flags['lesson_progress']) {
                    $course->lessons()->take(3)->get()->each(function (Lesson $lesson, int $lessonIndex) use ($student) {
                        LessonProgress::query()->updateOrCreate(
                            ['user_id' => $student->id, 'lesson_id' => $lesson->id],
                            [
                                'completed' => $lessonIndex < 2,
                                'watched_seconds' => $lessonIndex < 2 ? $lesson->duration : (int) floor($lesson->duration / 2),
                                'last_watched_at' => now()->subHours(4 + $lessonIndex),
                            ]
                        );
                    });
                }

                $quizLesson = $course->lessons()->where('order', 3)->first();
                $quiz = $quizLesson?->quiz;

                if ($quiz && $flags['quiz_attempts']) {
                    $attemptData = [
                        'user_id' => $student->id,
                        'quiz_id' => $quiz->id,
                        'score' => 75 + ($studentIndex * 5),
                        'passed' => true,
                    ];

                    if ($flags['quiz_attempts.answers_json']) {
                        $attemptData['answers_json'] = json_encode(['demo' => 'seed']);
                    }
                    if ($flags['quiz_attempts.answers_data']) {
                        $attemptData['answers_data'] = json_encode(['demo' => 'seed']);
                    }
                    if ($flags['quiz_attempts.started_at']) {
                        $attemptData['started_at'] = now()->subDays(1)->subMinutes(25);
                    }
                    if ($flags['quiz_attempts.completed_at']) {
                        $attemptData['completed_at'] = now()->subDays(1)->subMinutes(5);
                    }

                    DB::table('quiz_attempts')->updateOrInsert(
                        ['user_id' => $student->id, 'quiz_id' => $quiz->id],
                        array_merge($attemptData, [
                            'created_at' => now(),
                            'updated_at' => now(),
                        ])
                    );
                }
            }
        }
    }

    private function seedCareerDemoData($students, $publishedCourses, array $flags): void
    {
        if ($flags['job_postings']) {
            $jobs = [
                [
                    'title' => 'Junior Backend Laravel Developer',
                    'company' => 'PTIT Digital Lab',
                    'description' => 'Tham gia xây dựng API và tích hợp dịch vụ cho hệ thống đào tạo số.',
                    'required_skills' => ['PHP', 'Laravel', 'MySQL', 'REST API'],
                    'location' => 'Hà Nội',
                ],
                [
                    'title' => 'Frontend Nuxt Engineer',
                    'company' => 'Edu Product Studio',
                    'description' => 'Phát triển giao diện học tập tối ưu cho desktop và mobile.',
                    'required_skills' => ['Nuxt', 'Vue', 'Tailwind CSS', 'TypeScript'],
                    'location' => 'Remote',
                ],
            ];

            foreach ($jobs as $jobPayload) {
                JobPosting::query()->updateOrCreate(
                    ['title' => $jobPayload['title'], 'company' => $jobPayload['company']],
                    $jobPayload
                );
            }
        }

        if ($flags['user_cvs']) {
            foreach ($students->take(4) as $index => $student) {
                UserCV::query()->updateOrCreate(
                    ['user_id' => $student->id, 'file_name' => "cv-demo-{$student->id}.pdf"],
                    [
                        'file_path' => "career/cvs/cv-demo-{$student->id}.pdf",
                        'parsed_text' => 'Laravel, Nuxt, REST API, SQL, teamwork, presentation',
                        'skills' => ['Laravel', 'Nuxt', 'REST API', 'SQL', 'Communication'],
                    ]
                );
            }
        }

        if ($flags['career_recommendations'] && $flags['job_postings']) {
            $jobs = JobPosting::query()->take(2)->get();

            foreach ($students->take(4) as $index => $student) {
                $job = $jobs[$index % max($jobs->count(), 1)] ?? null;
                if (!$job) {
                    continue;
                }

                CareerRecommendation::query()->updateOrCreate(
                    ['user_id' => $student->id, 'job_id' => $job->id],
                    [
                        'match_score' => 78 + ($index * 4),
                        'skill_gaps' => ['Docker', 'Testing'],
                        'suggested_courses' => $publishedCourses->take(2)->pluck('id')->values()->all(),
                        'ai_summary' => 'Ứng viên có nền tảng phù hợp, nên bổ sung thêm kỹ năng triển khai và kiểm thử để tăng độ phù hợp.',
                    ]
                );
            }
        }
    }
}
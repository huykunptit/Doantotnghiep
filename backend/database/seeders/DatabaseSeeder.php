<?php

namespace Database\Seeders;

use App\Models\CareerRecommendation;
use App\Models\Category;
use App\Models\Course;
use App\Models\Curriculum;
use App\Models\Enrollment;
use App\Models\JobPosting;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Order;
use App\Models\Program;
use App\Models\Quiz;
use App\Models\Review;
use App\Models\User;
use App\Models\UserCV;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Master orchestrator. Order matters — academic structure must exist
 * before courses link to programs/majors, and courses must exist before
 * class sections + enrollments are wired up.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            CategorySeeder::class,
        ]);

        $users = $this->seedUsers();

        $this->call(OrgAcademicSeeder::class);

        $courses = $this->seedCourses($users['instructors']);

        $this->call([
            CourseContentSeeder::class,
            QuestionBankSeeder::class,
            TrainingProgramSeeder::class,
            AcademicSeeder::class,
        ]);

        $this->seedMarketplace($courses['extension'], $users['students']);
        $this->seedCareerData($users['students'], $courses['extension']);
    }

    /**
     * @return array{admin: User, instructors: EloquentCollection<int, User>, students: EloquentCollection<int, User>}
     */
    private function seedUsers(): array
    {
        $admin = User::query()->updateOrCreate(
            ['email' => 'admin@lms.com'],
            [
                'name' => 'Admin LMS',
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
                    'name' => "Giảng viên {$i}",
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'avatar' => 'https://i.pravatar.cc/300?img=' . (10 + $i),
                    'bio' => "Giảng viên demo số {$i} phụ trách khóa học mẫu cho hệ thống.",
                ]
            );
            $user->syncRoles(['instructor']);

            return $user;
        });

        $students = collect(range(1, 18))->map(function (int $i) {
            $user = User::query()->updateOrCreate(
                ['email' => "student{$i}@lms.com"],
                [
                    'name' => "Sinh viên {$i}",
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                    'avatar' => 'https://i.pravatar.cc/300?img=' . (40 + $i),
                    'bio' => "Sinh viên demo số {$i} dùng để hiển thị dữ liệu mẫu.",
                ]
            );
            $user->syncRoles(['student']);

            return $user;
        });

        return [
            'admin' => $admin,
            'instructors' => new EloquentCollection($instructors->all()),
            'students' => new EloquentCollection($students->all()),
        ];
    }

    /**
     * Create courses split into:
     * - Core (4): "Nhập môn ..." per root category, price 0, linked to IT program (both cohorts auto-enroll)
     * - Extension (12): paid marketplace courses, no academic linkage.
     *
     * @return array{core: Collection, extension: Collection}
     */
    private function seedCourses(EloquentCollection $instructors): array
    {
        $rootCategories = Category::query()
            ->whereNull('parent_id')
            ->with(['children' => fn ($q) => $q->orderBy('sort_order')])
            ->orderBy('sort_order')
            ->get();

        if ($rootCategories->isEmpty()) {
            return ['core' => collect(), 'extension' => collect()];
        }

        $itProgram = Program::query()->where('code', 'CNTT')->first();
        $itCurriculum = $itProgram
            ? Curriculum::query()->where('program_id', $itProgram->id)->where('code', 'CTDT-CNTT')->first()
            : null;

        $thumbnails = [
            'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?auto=format&fit=crop&w=1200&q=80',
            'https://images.unsplash.com/photo-1498050108023-c5249f4df085?auto=format&fit=crop&w=1200&q=80',
            'https://images.unsplash.com/photo-1522542550221-31fd19575a2d?auto=format&fit=crop&w=1200&q=80',
            'https://images.unsplash.com/photo-1460925895917-afdab827c52f?auto=format&fit=crop&w=1200&q=80',
        ];

        $extensionTemplates = [
            ['title' => 'Thực chiến', 'price' => 399000],
            ['title' => 'Chuyên sâu', 'price' => 549000],
            ['title' => 'Dự án cuối khóa', 'price' => 699000],
        ];

        $coreCourses = collect();
        $extensionCourses = collect();
        $cursor = 0;

        foreach ($rootCategories as $rootIndex => $rootCategory) {
            $leafCategories = $rootCategory->children->isNotEmpty()
                ? $rootCategory->children->values()
                : collect([$rootCategory]);

            // 1. CORE course (1 per root category)
            $coreTitle = "Nhập môn {$rootCategory->name}";
            $coreCategory = $leafCategories->first();
            $coreInstructor = $instructors[$cursor % $instructors->count()];
            $cursor++;

            $coreCourse = Course::query()->updateOrCreate(
                ['slug' => Str::slug($coreTitle)],
                [
                    'user_id' => $coreInstructor->id,
                    'category_id' => $coreCategory->id,
                    'program_id' => $itProgram?->id,
                    'program_type_id' => $itProgram?->program_type_id,
                    'major_id' => null, // core mở rộng — cả 2 cohort itProgram đều auto-enroll
                    'curriculum_id' => $itCurriculum?->id,
                    'title' => $coreTitle,
                    'description' => "Học phần chính quy: {$coreTitle}. Tự động ghi danh cho sinh viên khóa thuộc CTĐT.",
                    'price' => 0,
                    'course_mode' => 'core',
                    'is_credit_bearing' => true,
                    'credit_value' => 3,
                    'status' => 'published',
                    'thumbnail' => $thumbnails[$rootIndex % count($thumbnails)],
                    'published_at' => now()->subDays(30),
                ]
            );
            $coreCourses->push($coreCourse);

            // 2. EXTENSION courses (3 per root category)
            foreach ($extensionTemplates as $tmplIndex => $template) {
                $title = "{$template['title']} {$rootCategory->name}";
                $category = $leafCategories[$tmplIndex % $leafCategories->count()];
                $instructor = $instructors[$cursor % $instructors->count()];
                $cursor++;

                $course = Course::query()->updateOrCreate(
                    ['slug' => Str::slug($title)],
                    [
                        'user_id' => $instructor->id,
                        'category_id' => $category->id,
                        'program_id' => null,
                        'program_type_id' => null,
                        'major_id' => null,
                        'curriculum_id' => null,
                        'title' => $title,
                        'description' => "Khóa mở rộng: {$title}. Bán trên marketplace, có thể thanh toán qua PayOS.",
                        'price' => $template['price'],
                        'course_mode' => 'extension',
                        'is_credit_bearing' => false,
                        'credit_value' => null,
                        'status' => 'published',
                        'thumbnail' => $thumbnails[($rootIndex + $tmplIndex + 1) % count($thumbnails)],
                        'published_at' => now()->subDays(random_int(5, 25)),
                    ]
                );
                $extensionCourses->push($course);
            }
        }

        return [
            'core' => $coreCourses,
            'extension' => $extensionCourses,
        ];
    }

    /**
     * Marketplace data ONLY for extension courses (paid).
     * Each extension course: ~5 students enroll via Order, 3 leave reviews,
     * 3 lessons of progress, 1 quiz attempt.
     */
    private function seedMarketplace(Collection $extensionCourses, EloquentCollection $students): void
    {
        if ($extensionCourses->isEmpty() || $students->isEmpty()) {
            return;
        }

        foreach ($extensionCourses->values() as $courseIndex => $course) {
            $enrolledStudents = $students->slice($courseIndex % $students->count(), 5)->values();

            foreach ($enrolledStudents as $studentIndex => $student) {
                $paymentRef = sprintf('PAY-%04d-%04d', $course->id, $student->id);

                $order = Order::query()->updateOrCreate(
                    ['payment_ref' => $paymentRef],
                    [
                        'user_id' => $student->id,
                        'course_id' => $course->id,
                        'amount' => $course->price,
                        'status' => 'paid',
                        'payment_method' => 'payos',
                        'gateway_response' => [
                            'transaction_no' => $paymentRef,
                            'message' => 'Seeded demo payment',
                        ],
                        'paid_at' => now()->subDays(10 - $studentIndex),
                    ]
                );

                Enrollment::query()->updateOrCreate(
                    ['user_id' => $student->id, 'course_id' => $course->id],
                    [
                        'order_id' => $order->id,
                        'enrollment_source' => 'marketplace',
                        'enrolled_at' => now()->subDays(12 - $studentIndex),
                    ]
                );

                if ($studentIndex < 3) {
                    Review::query()->updateOrCreate(
                        ['user_id' => $student->id, 'course_id' => $course->id],
                        [
                            'rating' => 5 - ($studentIndex % 2),
                            'comment' => 'Khóa học có nội dung rõ ràng, đủ dữ liệu demo cho marketplace.',
                        ]
                    );
                }

                $course->lessons()->take(3)->get()->each(function (Lesson $lesson, int $li) use ($student) {
                    LessonProgress::query()->updateOrCreate(
                        ['user_id' => $student->id, 'lesson_id' => $lesson->id],
                        [
                            'completed' => $li < 2,
                            'watched_seconds' => $li < 2 ? $lesson->duration : (int) floor($lesson->duration / 2),
                            'last_watched_at' => now()->subHours(4 + $li),
                        ]
                    );
                });

                $quizLesson = $course->lessons()->where('order', 3)->first();
                $quiz = $quizLesson ? Quiz::query()->where('lesson_id', $quizLesson->id)->first() : null;
                if ($quiz) {
                    DB::table('quiz_attempts')->updateOrInsert(
                        ['user_id' => $student->id, 'quiz_id' => $quiz->id],
                        [
                            'score' => 75 + ($studentIndex * 5),
                            'passed' => true,
                            'answers_data' => json_encode(['demo' => 'seed']),
                            'started_at' => now()->subDays(1)->subMinutes(25),
                            'completed_at' => now()->subDays(1)->subMinutes(5),
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]
                    );
                }

            }
        }
    }

    private function seedCareerData(EloquentCollection $students, Collection $extensionCourses): void
    {
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

        $jobModels = collect();
        foreach ($jobs as $jobPayload) {
            $jobModels->push(JobPosting::query()->updateOrCreate(
                ['title' => $jobPayload['title'], 'company' => $jobPayload['company']],
                $jobPayload
            ));
        }

        $students->take(4)->each(function (User $student, int $index) use ($jobModels, $extensionCourses) {
            UserCV::query()->updateOrCreate(
                ['user_id' => $student->id, 'file_name' => "cv-demo-{$student->id}.pdf"],
                [
                    'file_path' => "career/cvs/cv-demo-{$student->id}.pdf",
                    'parsed_text' => 'Laravel, Nuxt, REST API, SQL, teamwork, presentation',
                    'skills' => ['Laravel', 'Nuxt', 'REST API', 'SQL', 'Communication'],
                ]
            );

            $job = $jobModels[$index % max($jobModels->count(), 1)] ?? null;
            if (!$job) {
                return;
            }

            CareerRecommendation::query()->updateOrCreate(
                ['user_id' => $student->id, 'job_id' => $job->id],
                [
                    'match_score' => 78 + ($index * 4),
                    'skill_gaps' => ['Docker', 'Testing'],
                    'suggested_courses' => $extensionCourses->take(2)->pluck('id')->values()->all(),
                    'ai_summary' => 'Ứng viên có nền tảng phù hợp, nên bổ sung thêm kỹ năng triển khai và kiểm thử.',
                ]
            );
        });
    }
}

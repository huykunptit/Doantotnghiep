<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Course;
use App\Models\Lesson;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);
        $this->call(CategorySeeder::class);

        $admin = User::query()->updateOrCreate(['email' => 'admin@lms.com'], [
            'name' => 'Admin User',
            'email' => 'admin@lms.com',
            'password' => Hash::make('password'),
        ]);
        $admin->syncRoles(['admin']);

        // Create exactly 10 instructors (idempotent by email)
        $instructors = collect(range(1, 10))->map(function (int $i) {
            $user = User::query()->updateOrCreate(
                ['email' => "instructor{$i}@lms.com"],
                [
                    'name' => "Instructor {$i}",
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ]
            );
            $user->syncRoles(['instructor']);
            return $user;
        });

        // Create exactly 10 students (idempotent by email)
        collect(range(1, 10))->each(function (int $i) {
            $user = User::query()->updateOrCreate(
                ['email' => "student{$i}@lms.com"],
                [
                    'name' => "Student {$i}",
                    'password' => Hash::make('password'),
                    'email_verified_at' => now(),
                ]
            );
            $user->syncRoles(['student']);
        });

        $categoryIds = Category::query()->pluck('id')->values();
        if ($categoryIds->isEmpty()) {
            return;
        }

        $statuses = ['draft', 'pending_review', 'published', 'rejected'];

        $hasCourseSlug = Schema::hasColumn('courses', 'slug');
        $hasCourseCategoryId = Schema::hasColumn('courses', 'category_id');
        $hasCourseRejectReason = Schema::hasColumn('courses', 'reject_reason');
        $hasCoursePublishedAt = Schema::hasColumn('courses', 'published_at');
        $hasLessonDescription = Schema::hasColumn('lessons', 'description');
        $hasLessonPreview = Schema::hasColumn('lessons', 'is_preview');
        $hasLessonVideoUrl = Schema::hasColumn('lessons', 'video_url');

        // Create around 20 demo courses and 4-8 lessons each
        collect(range(1, 20))->each(function (int $i) use (
            $instructors,
            $categoryIds,
            $statuses,
            $hasCourseSlug,
            $hasCourseCategoryId,
            $hasCourseRejectReason,
            $hasCoursePublishedAt,
            $hasLessonDescription,
            $hasLessonPreview,
            $hasLessonVideoUrl
        ) {
            $title = "Khóa học mẫu số {$i}";
            $slug = 'khoa-hoc-mau-' . str_pad((string) $i, 2, '0', STR_PAD_LEFT);
            $status = $statuses[$i % count($statuses)];

            /** @var \App\Models\User $instructor */
            $instructor = $instructors[($i - 1) % $instructors->count()];
            $price = $i % 4 === 0 ? 0 : rand(199000, 1499000);

            $attrs = [
                'user_id' => $instructor->id,
                'title' => $title,
                'description' => "Mô tả demo cho {$title}. Nội dung gồm kiến thức nền tảng và thực hành.",
                'price' => $price,
                'status' => $status,
                'thumbnail' => "https://picsum.photos/seed/lms-course-{$i}/1200/675",
            ];

            if ($hasCourseSlug) {
                $attrs['slug'] = $slug;
            }
            if ($hasCourseCategoryId) {
                $attrs['category_id'] = $categoryIds[($i - 1) % $categoryIds->count()];
            }
            if ($hasCourseRejectReason) {
                $attrs['reject_reason'] = $status === 'rejected' ? 'Nội dung cần cải thiện chất lượng video và đề cương.' : null;
            }
            if ($hasCoursePublishedAt) {
                $attrs['published_at'] = $status === 'published' ? now()->subDays(rand(1, 90)) : null;
            }

            $course = Course::query()->updateOrCreate(
                $hasCourseSlug ? ['slug' => $slug] : ['title' => $title],
                $attrs
            );

            $lessonCount = 4 + ($i % 5); // 4..8 lessons
            collect(range(1, $lessonCount))->each(function (int $n) use ($course, $i, $hasLessonDescription, $hasLessonPreview, $hasLessonVideoUrl) {
                $lessonAttrs = [
                    'title' => "Bài {$n}: Chủ đề {$i}.{$n}",
                    'duration' => rand(300, 1800),
                ];
                if ($hasLessonDescription) {
                    $lessonAttrs['description'] = "Nội dung bài học {$n} của khóa {$course->title}.";
                }
                if ($hasLessonPreview) {
                    $lessonAttrs['is_preview'] = $n === 1;
                }
                if ($hasLessonVideoUrl) {
                    $lessonAttrs['video_url'] = null;
                }

                Lesson::query()->updateOrCreate(
                    ['course_id' => $course->id, 'order' => $n],
                    $lessonAttrs
                );
            });
        });
    }
}



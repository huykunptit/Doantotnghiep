<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\GradeComponent;
use App\Models\GradeEntry;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Order;
use App\Models\Quiz;
use App\Models\Review;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Tạo dữ liệu marketplace cho extension courses:
 *  - Mỗi extension course: ~5 student ghi danh qua Order đã thanh toán
 *  - 3 student đầu để lại review
 *  - 3 lesson progress đầu
 *  - 1 quiz attempt nếu có quiz ở lesson order=3
 *
 * Chạy độc lập: php artisan db:seed --class=MarketplaceSeeder
 */
class MarketplaceSeeder extends Seeder
{
    public function run(): void
    {
        $extensionCourses = Course::query()
            ->where('course_mode', 'extension')
            ->where('status', 'published')
            ->orderBy('id')
            ->get();

        $students = UserSeeder::getStudents();

        if ($extensionCourses->isEmpty() || $students->isEmpty()) {
            $this->command?->warn('MarketplaceSeeder: không có extension course hoặc student.');
            return;
        }

        $total = ['orders' => 0, 'enrollments' => 0, 'reviews' => 0, 'progress' => 0];

        foreach ($extensionCourses->values() as $courseIndex => $course) {
            $enrolledStudents = $students->slice($courseIndex % $students->count(), 5)->values();

            foreach ($enrolledStudents as $studentIndex => $student) {
                $paymentRef = sprintf('PAY-%04d-%04d', $course->id, $student->id);

                $order = Order::query()->updateOrCreate(
                    ['payment_ref' => $paymentRef],
                    [
                        'user_id'          => $student->id,
                        'course_id'        => $course->id,
                        'amount'           => $course->price,
                        'status'           => 'paid',
                        'payment_method'   => 'payos',
                        'gateway_response' => json_encode([
                            'transaction_no' => $paymentRef,
                            'message'        => 'Seeded demo payment',
                        ]),
                        'paid_at'          => now()->subDays(10 - $studentIndex),
                    ]
                );
                $total['orders']++;

                Enrollment::query()->updateOrCreate(
                    ['user_id' => $student->id, 'course_id' => $course->id],
                    [
                        'order_id'          => $order->id,
                        'enrollment_source' => 'marketplace',
                        'enrolled_at'       => now()->subDays(12 - $studentIndex),
                    ]
                );
                $total['enrollments']++;

                // Điểm ngoài CTĐT: seed đầu điểm cho 3 SV đầu mỗi khóa (để bảng điểm demo).
                if ($studentIndex < 3) {
                    $enrollment = Enrollment::query()
                        ->where('user_id', $student->id)
                        ->where('course_id', $course->id)
                        ->first();
                    if ($enrollment) {
                        $this->seedExtensionGrades($enrollment, $student->id + $course->id);
                    }
                }

                if ($studentIndex < 3) {
                    Review::query()->updateOrCreate(
                        ['user_id' => $student->id, 'course_id' => $course->id],
                        [
                            'rating'  => 5 - ($studentIndex % 2),
                            'comment' => 'Khóa học có nội dung rõ ràng, đủ dữ liệu demo cho marketplace.',
                        ]
                    );
                    $total['reviews']++;
                }

                $course->lessons()->take(3)->get()->each(function (Lesson $lesson, int $li) use ($student, &$total) {
                    LessonProgress::query()->updateOrCreate(
                        ['user_id' => $student->id, 'lesson_id' => $lesson->id],
                        [
                            'completed'       => $li < 2,
                            'watched_seconds' => $li < 2 ? $lesson->duration : (int) floor($lesson->duration / 2),
                            'last_watched_at' => now()->subHours(4 + $li),
                        ]
                    );
                    $total['progress']++;
                });

                $quizLesson = $course->lessons()->where('order', 3)->first();
                $quiz = $quizLesson ? Quiz::query()->where('lesson_id', $quizLesson->id)->first() : null;
                if ($quiz) {
                    DB::table('quiz_attempts')->updateOrInsert(
                        ['user_id' => $student->id, 'quiz_id' => $quiz->id],
                        [
                            'score'        => 75 + ($studentIndex * 5),
                            'passed'       => true,
                            'answers_data' => json_encode(['demo' => 'seed']),
                            'started_at'   => now()->subDays(1)->subMinutes(25),
                            'completed_at' => now()->subDays(1)->subMinutes(5),
                            'created_at'   => now(),
                            'updated_at'   => now(),
                        ]
                    );
                }
            }
        }

        $this->command?->info(sprintf(
            'MarketplaceSeeder: %d orders, %d enrollments, %d reviews, %d lesson-progress records.',
            $total['orders'], $total['enrollments'], $total['reviews'], $total['progress']
        ));
    }

    /** Template đầu điểm gần mẫu PTIT (ảnh chi tiết). */
    private function seedExtensionGrades(Enrollment $enrollment, int $seed): void
    {
        $template = [
            ['name' => 'Bài tập', 'weight' => 0, 'max_score' => 10, 'position' => 1],
            ['name' => 'Kiểm tra', 'weight' => 10, 'max_score' => 10, 'position' => 2],
            ['name' => 'Thực hành', 'weight' => 10, 'max_score' => 10, 'position' => 3],
            ['name' => 'Chuyên cần', 'weight' => 10, 'max_score' => 10, 'position' => 4],
            ['name' => 'Điểm thi', 'weight' => 70, 'max_score' => 10, 'position' => 5],
        ];

        foreach ($template as $row) {
            GradeComponent::query()->updateOrCreate(
                ['course_id' => $enrollment->course_id, 'name' => $row['name']],
                array_merge($row, ['course_id' => $enrollment->course_id, 'is_required' => true]),
            );
        }

        $base = 4.0 + (($seed % 50) / 10); // ~4.0–8.9
        $components = GradeComponent::query()
            ->where('course_id', $enrollment->course_id)
            ->whereIn('name', collect($template)->pluck('name'))
            ->get();

        foreach ($components as $component) {
            $score = match ($component->name) {
                'Bài tập' => min(10, round($base + 1.5, 1)),
                'Kiểm tra' => round(max(4, $base - 0.5), 1),
                'Thực hành' => min(10, round($base + 1.0, 1)),
                'Chuyên cần' => min(10, max(7, round($base + 2, 1))),
                'Điểm thi' => round(max(3.5, $base), 1),
                default => round($base, 1),
            };
            GradeEntry::query()->updateOrCreate(
                [
                    'enrollment_id' => $enrollment->id,
                    'grade_component_id' => $component->id,
                ],
                [
                    'score' => $score,
                    'graded_at' => now()->subDays(mt_rand(3, 20)),
                ]
            );
        }
    }
}


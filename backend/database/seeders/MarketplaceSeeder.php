<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\Enrollment;
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
}


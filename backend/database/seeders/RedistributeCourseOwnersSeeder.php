<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * San sẻ owner (user_id) của toàn bộ courses cho các tài khoản instructor1…8
 * theo round-robin — tránh dồn hết về 1 GV / admin.
 *
 * Chạy: php artisan db:seed --class=RedistributeCourseOwnersSeeder
 */
class RedistributeCourseOwnersSeeder extends Seeder
{
    public function run(): void
    {
        $instructors = User::query()
            ->where('email', 'like', 'instructor%@lms.com')
            ->orderBy('id')
            ->get();

        if ($instructors->isEmpty()) {
            $this->command?->error('RedistributeCourseOwnersSeeder: không tìm thấy instructor%@lms.com');
            return;
        }

        $courses = Course::query()->orderBy('id')->get(['id', 'user_id', 'title']);
        $n = $instructors->count();
        $updated = 0;

        foreach ($courses as $index => $course) {
            $ownerId = (int) $instructors[$index % $n]->id;
            if ((int) $course->user_id !== $ownerId) {
                $course->update(['user_id' => $ownerId]);
                $updated++;
            }
        }

        $counts = Course::query()
            ->selectRaw('user_id, COUNT(*) as c')
            ->groupBy('user_id')
            ->pluck('c', 'user_id');

        $this->command?->info("RedistributeCourseOwnersSeeder: cập nhật {$updated}/{$courses->count()} khoá.");
        foreach ($instructors as $ins) {
            $this->command?->info(sprintf('  - %s: %d khoá', $ins->email, (int) ($counts[$ins->id] ?? 0)));
        }
    }
}

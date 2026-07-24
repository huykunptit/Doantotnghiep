<?php

namespace App\Services;

use App\Models\CareerPath;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Notification;
use App\Models\Order;
use App\Models\UserCareerPath;
use App\Models\UserCertificate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CareerPathFulfillmentService
{
    /**
     * After a path order is paid (or free): mark user path + enroll required courses.
     */
    public function fulfillPaidOrder(Order $order): void
    {
        if (!$order->career_path_id) {
            return;
        }

        $path = CareerPath::with(['pathCourses' => fn ($q) => $q->where('is_required', true)->orderBy('sort_order')])
            ->find($order->career_path_id);

        if (!$path) {
            return;
        }

        DB::transaction(function () use ($order, $path) {
            $ucp = UserCareerPath::updateOrCreate(
                [
                    'user_id' => $order->user_id,
                    'career_path_id' => $path->id,
                ],
                [
                    'order_id' => $order->id,
                    'status' => 'purchased',
                    'started_at' => now(),
                ]
            );

            foreach ($path->pathCourses as $item) {
                Enrollment::firstOrCreate(
                    [
                        'user_id' => $order->user_id,
                        'course_id' => $item->course_id,
                    ],
                    [
                        'enrolled_at' => now(),
                        'order_id' => $order->id,
                        'enrollment_source' => 'marketplace',
                    ]
                );
            }

            $this->refreshProgress($ucp->fresh(['careerPath.pathCourses']));
        });

        $order->loadMissing('user');
        Notification::send(
            $order->user_id,
            'enrollment',
            'Mua lộ trình thành công',
            "Bạn đã mở khóa lộ trình \"{$path->title}\".",
            "/paths/{$path->slug}"
        );
    }

    /**
     * Follow a published path without purchase (does not enroll paid courses).
     */
    public function follow(int $userId, CareerPath $path): UserCareerPath
    {
        return UserCareerPath::firstOrCreate(
            [
                'user_id' => $userId,
                'career_path_id' => $path->id,
            ],
            [
                'status' => 'following',
                'started_at' => now(),
            ]
        );
    }

    public function refreshProgress(?UserCareerPath $ucp): UserCareerPath
    {
        if (!$ucp) {
            return new UserCareerPath;
        }

        $ucp->loadMissing('careerPath.pathCourses');
        $path = $ucp->careerPath;
        if (!$path) {
            return $ucp;
        }

        $required = $path->pathCourses->where('is_required', true);
        $total = $required->count();
        if ($total === 0) {
            $ucp->update(['progress_percent' => 0]);

            return $ucp->fresh();
        }

        $completed = 0;
        foreach ($required as $item) {
            if ($this->isCourseCompleted($ucp->user_id, $item->course_id)) {
                $completed++;
            }
        }

        $percent = (int) round(($completed / $total) * 100);
        $updates = ['progress_percent' => $percent];

        if ($percent >= 100 && $ucp->status !== 'completed') {
            $updates['status'] = 'completed';
            $updates['completed_at'] = now();
            $this->issuePathCertificate($ucp->user_id, $path);
        }

        $ucp->update($updates);

        return $ucp->fresh();
    }

    public function refreshProgressForUserCourse(int $userId, int $courseId): void
    {
        $pathIds = CareerPath::query()
            ->whereHas('pathCourses', fn ($q) => $q->where('course_id', $courseId)->where('is_required', true))
            ->pluck('id');

        if ($pathIds->isEmpty()) {
            return;
        }

        $rows = UserCareerPath::query()
            ->where('user_id', $userId)
            ->whereIn('career_path_id', $pathIds)
            ->get();

        foreach ($rows as $row) {
            $this->refreshProgress($row);
        }
    }

    public function isCourseCompleted(int $userId, int $courseId): bool
    {
        if (UserCertificate::where('user_id', $userId)->where('course_id', $courseId)->exists()) {
            return true;
        }

        $lessonIds = Lesson::where('course_id', $courseId)->pluck('id');
        if ($lessonIds->isEmpty()) {
            return false;
        }

        $done = LessonProgress::where('user_id', $userId)
            ->whereIn('lesson_id', $lessonIds)
            ->where('completed', true)
            ->count();

        return $done >= $lessonIds->count();
    }

    public function issuePathCertificate(int $userId, CareerPath $path): ?UserCertificate
    {
        if (!$path->certificate_template_id) {
            return null;
        }

        return UserCertificate::firstOrCreate(
            [
                'user_id' => $userId,
                'career_path_id' => $path->id,
            ],
            [
                'course_id' => null,
                'certificate_template_id' => $path->certificate_template_id,
                'credential_id' => 'PATH-' . strtoupper(Str::random(10)),
                'issued_at' => now(),
            ]
        );
    }
}

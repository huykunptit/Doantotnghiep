<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LessonProgress;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InstructorController extends Controller
{
    private function ensureInstructorAccess(Request $request, Course $course): ?JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if (!$user->hasRole('admin') && $course->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return null;
    }

    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();

        $courseIds = Course::where('user_id', $user->id)->pluck('id');

        $paidStatuses = ['paid', 'completed'];

        $totalStudents = Enrollment::whereIn('course_id', $courseIds)
            ->distinct('user_id')
            ->count('user_id');

        $totalRevenue = (int) Order::whereIn('course_id', $courseIds)
            ->whereIn('status', $paidStatuses)
            ->sum('amount');

        $coursesByStatus = Course::where('user_id', $user->id)
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        return response()->json([
            'total_courses'     => $courseIds->count(),
            'total_students'    => $totalStudents,
            'total_revenue'     => $totalRevenue,
            'courses_by_status' => $coursesByStatus,
        ]);
    }

    public function students(Request $request, Course $course): JsonResponse
    {
        if ($forbidden = $this->ensureInstructorAccess($request, $course)) {
            return $forbidden;
        }

        $enrollmentsQuery = Enrollment::query()
            ->with('user:id,name,email,avatar')
            ->where('course_id', $course->id);

        if ($request->filled('search')) {
            $search = trim((string) $request->query('search'));
            $enrollmentsQuery->whereHas('user', function ($query) use ($search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $enrollments = $enrollmentsQuery
            ->orderByDesc('enrolled_at')
            ->paginate((int) $request->query('per_page', 15));

        $lessonIds = $course->lessons()->pluck('id');
        $totalLessons = $lessonIds->count();

        $enrollments->getCollection()->transform(function (Enrollment $enrollment) use ($lessonIds, $totalLessons) {
            $progressQuery = LessonProgress::query()
                ->where('user_id', $enrollment->user_id)
                ->whereIn('lesson_id', $lessonIds);

            $completedLessons = (clone $progressQuery)->where('completed', true)->count();
            $lastWatchedAt = (clone $progressQuery)->max('last_watched_at');

            return [
                'id' => $enrollment->id,
                'user_id' => $enrollment->user_id,
                'enrolled_at' => $enrollment->enrolled_at,
                'user' => $enrollment->user,
                'total_lessons' => $totalLessons,
                'completed_lessons' => $completedLessons,
                'progress_percent' => $totalLessons > 0
                    ? round(($completedLessons / $totalLessons) * 100, 1)
                    : 0,
                'last_watched_at' => $lastWatchedAt,
            ];
        });

        return response()->json([
            'course' => [
                'id' => $course->id,
                'title' => $course->title,
            ],
            ...$enrollments->toArray(),
        ]);
    }

    public function revenue(Request $request, Course $course): JsonResponse
    {
        if ($forbidden = $this->ensureInstructorAccess($request, $course)) {
            return $forbidden;
        }

        $paidStatuses = ['paid', 'completed'];

        $ordersQuery = Order::query()
            ->with('user:id,name,email,avatar')
            ->where('course_id', $course->id)
            ->whereIn('status', $paidStatuses);

        $totalRevenue = (int) (clone $ordersQuery)->sum('amount');
        $paidOrdersCount = (int) (clone $ordersQuery)->count();
        $latestPaidAt = (clone $ordersQuery)->max('paid_at');

        $monthlyRows = Order::query()
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, SUM(amount) as total")
            ->where('course_id', $course->id)
            ->whereIn('status', $paidStatuses)
            ->where('created_at', '>=', now()->subMonths(5)->startOfMonth())
            ->groupBy('month')
            ->pluck('total', 'month');

        $months = collect(range(5, 0))->map(fn (int $i) => now()->subMonths($i)->format('Y-m'));
        $revenueByMonth = $months->map(function (string $month) use ($monthlyRows) {
            [$year, $monthNum] = explode('-', $month);
            return [
                'month' => $month,
                'label' => sprintf('%s/%s', $monthNum, substr($year, -2)),
                'value' => (int) ($monthlyRows[$month] ?? 0),
            ];
        })->values();

        $orders = $ordersQuery
            ->orderByDesc('paid_at')
            ->paginate((int) $request->query('per_page', 15));

        return response()->json([
            'course' => [
                'id' => $course->id,
                'title' => $course->title,
                'price' => (int) $course->price,
            ],
            'summary' => [
                'total_revenue' => $totalRevenue,
                'paid_orders' => $paidOrdersCount,
                'average_order_value' => $paidOrdersCount > 0 ? (int) round($totalRevenue / $paidOrdersCount) : 0,
                'latest_paid_at' => $latestPaidAt,
            ],
            'revenue_by_month' => $revenueByMonth,
            'orders' => $orders,
        ]);
    }
}

<?php

namespace App\Http\Controllers\UserManagement;

use App\Http\Controllers\Controller;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LessonProgressController extends Controller
{
    public function updateProgress(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        if ($lesson->course_id !== $course->id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        /** @var \App\Models\User $user */
        $user = $request->user();

        $isEnrolled = Enrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)->exists();

        $isOwner = $user->hasRole('admin') || $course->user_id === $user->id;

        if (!$isEnrolled && !$isOwner) {
            return response()->json(['message' => 'Enrollment required'], 403);
        }

        $validated = $request->validate([
            'watched_seconds' => ['required', 'integer', 'min:0'],
            'completed'       => ['sometimes', 'boolean'],
        ]);

        $completed = $validated['completed']
            ?? ($lesson->duration > 0 && $validated['watched_seconds'] >= $lesson->duration * 0.9);

        $progress = LessonProgress::updateOrCreate(
            ['user_id' => $user->id, 'lesson_id' => $lesson->id],
            [
                'watched_seconds'  => $validated['watched_seconds'],
                'completed'        => $completed,
                'last_watched_at'  => now(),
            ]
        );

        return response()->json(['message' => 'Progress saved', 'progress' => $progress]);
    }

    public function courseProgress(Request $request, Course $course): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $isEnrolled = Enrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)->exists();

        $isOwner = $user->hasRole('admin') || $course->user_id === $user->id;

        if (!$isEnrolled && !$isOwner) {
            return response()->json(['message' => 'Enrollment required'], 403);
        }

        $totalLessons = $course->lessons()->count();

        if ($totalLessons === 0) {
            return response()->json([
                'total_lessons'     => 0,
                'completed_lessons' => 0,
                'percent'           => 0,
                'lessons'           => [],
            ]);
        }

        $lessonIds = $course->lessons()->pluck('id');

        $progressRecords = LessonProgress::where('user_id', $user->id)
            ->whereIn('lesson_id', $lessonIds)
            ->get()
            ->keyBy('lesson_id');

        $completedCount = $progressRecords->where('completed', true)->count();

        $lessonsWithProgress = $course->lessons()
            ->orderBy('order')
            ->get()
            ->map(function (Lesson $lesson) use ($progressRecords) {
                $prog = $progressRecords->get($lesson->id);
                return [
                    'id'              => $lesson->id,
                    'title'           => $lesson->title,
                    'order'           => $lesson->order,
                    'duration'        => $lesson->duration,
                    'completed'       => $prog?->completed ?? false,
                    'watched_seconds' => $prog?->watched_seconds ?? 0,
                    'last_watched_at' => $prog?->last_watched_at,
                ];
            });

        return response()->json([
            'total_lessons'     => $totalLessons,
            'completed_lessons' => $completedCount,
            'percent'           => round($completedCount / $totalLessons * 100, 1),
            'lessons'           => $lessonsWithProgress,
        ]);
    }

    public function enrollments(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $enrollments = Enrollment::with(['course:id,title,thumbnail,price,status,category_id', 'course.category:id,name'])
            ->where('user_id', $user->id)
            ->orderByDesc('enrolled_at')
            ->get();

        // Attach progress for each course
        $enriched = $enrollments->map(function (Enrollment $enrollment) use ($user) {
            $course = $enrollment->course;
            $total = $course->lessons()->count();
            $completed = LessonProgress::whereIn(
                'lesson_id',
                $course->lessons()->pluck('id')
            )->where('user_id', $user->id)->where('completed', true)->count();

            return [
                ...$enrollment->toArray(),
                'progress' => $total > 0 ? round($completed / $total * 100, 1) : 0,
            ];
        });

        return response()->json($enriched);
    }
}

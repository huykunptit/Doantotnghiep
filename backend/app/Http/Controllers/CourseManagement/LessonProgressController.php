<?php

namespace App\Http\Controllers\CourseManagement;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LessonProgressController extends Controller
{
    public function show(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        $user = $request->user();
        $gate = $this->authorizeLessonAccess($user, $course, $lesson);
        if ($gate) {
            return $gate;
        }

        $progress = LessonProgress::firstOrCreate(
            ['user_id' => $user->id, 'lesson_id' => $lesson->id],
            ['progress_percent' => 0, 'last_position' => 0, 'watched_seconds' => 0, 'completed' => false]
        );

        return response()->json($progress);
    }

    public function upsert(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        $user = $request->user();
        $gate = $this->authorizeLessonAccess($user, $course, $lesson);
        if ($gate) {
            return $gate;
        }

        $data = $request->validate([
            'progress_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'last_position' => ['nullable', 'integer', 'min:0'],
            'watched_seconds' => ['nullable', 'integer', 'min:0'],
            'completed' => ['nullable', 'boolean'],
        ]);

        $progress = LessonProgress::firstOrNew(['user_id' => $user->id, 'lesson_id' => $lesson->id]);
        $progress->fill([
            'progress_percent' => $data['progress_percent'] ?? $progress->progress_percent ?? 0,
            'last_position' => $data['last_position'] ?? $progress->last_position ?? 0,
            'watched_seconds' => $data['watched_seconds'] ?? $progress->watched_seconds ?? 0,
            'completed' => $data['completed'] ?? (($data['progress_percent'] ?? $progress->progress_percent ?? 0) >= 100),
            'last_watched_at' => now(),
        ]);
        $progress->completed_at = $progress->completed ? ($progress->completed_at ?? now()) : null;
        $progress->save();

        return response()->json([
            'message' => 'Lesson progress saved successfully.',
            'progress' => $progress,
        ]);
    }

    private function authorizeLessonAccess($user, Course $course, Lesson $lesson): ?JsonResponse
    {
        if ($lesson->course_id !== $course->id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $isOwner = $user->hasRole('admin') || $course->user_id === $user->id;
        $isEnrolled = Enrollment::where('user_id', $user->id)->where('course_id', $course->id)->exists();

        if (! $isOwner && ! $isEnrolled) {
            return response()->json(['message' => 'Enrollment required'], 403);
        }

        return null;
    }
}


<?php

namespace App\Http\Controllers\CourseManagement;

use App\Http\Controllers\Controller;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonProgress;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ScormController extends Controller
{
    /**
     * Track SCORM progress/completion.
     * Often SCORM just sends 'completed' or a score.
     */
    public function track(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if ($lesson->course_id !== $course->id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $validated = $request->validate([
            'status' => 'required|string|in:completed,passed,failed,browsed,incomplete',
            'score' => 'nullable|numeric|min:0|max:100',
            'lesson_location' => 'nullable|string',
            'suspend_data' => 'nullable|string',
        ]);

        // Integrate with existing LessonProgress
        $progress = LessonProgress::updateOrCreate(
            [
                'user_id' => $user->id,
                'course_id' => $course->id,
                'lesson_id' => $lesson->id,
            ],
            [
                'is_completed' => in_array($validated['status'], ['completed', 'passed']),
                'completed_at' => in_array($validated['status'], ['completed', 'passed']) ? now() : null,
                'metadata' => [
                    'scorm_status' => $validated['status'],
                    'scorm_score' => $validated['score'],
                    'last_location' => $validated['lesson_location'],
                    'suspend_data' => $validated['suspend_data'],
                ]
            ]
        );

        return response()->json([
            'message' => 'Progress tracked',
            'is_completed' => $progress->is_completed
        ]);
    }
}

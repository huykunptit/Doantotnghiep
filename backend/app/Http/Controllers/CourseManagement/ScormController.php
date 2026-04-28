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
            // SCORM 1.2 uses lesson_status; SCORM 2004 splits into completion_status + success_status.
            'version'           => 'nullable|string|in:1.2,2004',
            'status'            => 'nullable|string|in:completed,passed,failed,browsed,incomplete,unknown',
            'completion_status' => 'nullable|string|in:completed,incomplete,not_attempted,unknown',
            'success_status'    => 'nullable|string|in:passed,failed,unknown',
            'score'             => 'nullable|numeric|min:0|max:100',
            'lesson_location'   => 'nullable|string',
            'suspend_data'      => 'nullable|string',
        ]);

        // Normalize completion across both SCORM versions: a lesson counts as
        // completed if 1.2 status is completed/passed, OR if 2004 completion is
        // 'completed' (regardless of success_status).
        $version = $validated['version'] ?? '1.2';
        $statusValue = $validated['status'] ?? null;
        $completionValue = $validated['completion_status'] ?? null;
        $successValue = $validated['success_status'] ?? null;

        $isCompleted = $version === '2004'
            ? $completionValue === 'completed' || $successValue === 'passed'
            : in_array($statusValue, ['completed', 'passed'], true);

        $progress = LessonProgress::updateOrCreate(
            [
                'user_id'   => $user->id,
                'lesson_id' => $lesson->id,
            ],
            [
                'completed'    => $isCompleted,
                'completed_at' => $isCompleted ? now() : null,
                'metadata'     => [
                    'scorm_version'       => $version,
                    'scorm_status'        => $statusValue,
                    'scorm_completion'    => $completionValue,
                    'scorm_success'       => $successValue,
                    'scorm_score'         => $validated['score'] ?? null,
                    'last_location'       => $validated['lesson_location'] ?? null,
                    'suspend_data'        => $validated['suspend_data'] ?? null,
                ],
            ]
        );

        return response()->json([
            'message' => 'Progress tracked',
            'completed' => $progress->completed,
        ]);
    }
}

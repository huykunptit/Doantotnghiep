<?php

namespace App\Http\Controllers\CourseManagement;

use App\Http\Controllers\Controller;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\VirtualClass;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VirtualClassController extends Controller
{
    /**
     * Get virtual class details for a lesson.
     */
    public function show(Course $course, Lesson $lesson): JsonResponse
    {
        if ($lesson->course_id !== $course->id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $virtualClass = $lesson->virtualClass;
        
        if (!$virtualClass) {
            return response()->json(['message' => 'No virtual class set for this lesson'], 404);
        }

        return response()->json($virtualClass);
    }

    /**
     * Create or update virtual class details.
     */
    public function store(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if (!$user->hasRole('admin') && $course->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($lesson->course_id !== $course->id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $validated = $request->validate([
            'provider' => 'required|string|in:zoom,google_meet,jitsi,other',
            'meeting_id' => 'nullable|string|max:255',
            'meeting_password' => 'nullable|string|max:255',
            'join_url' => 'required|url',
            'start_url' => 'nullable|url',
            'start_at' => 'required|date',
            'duration' => 'required|integer|min:1',
        ]);

        $virtualClass = VirtualClass::updateOrCreate(
            ['lesson_id' => $lesson->id],
            $validated
        );

        // Update lesson type to ensure front-end renders correctly
        if ($lesson->type !== 'virtual_class') {
            $lesson->update(['type' => 'virtual_class']);
        }

        return response()->json([
            'message' => 'Virtual class updated successfully',
            'virtual_class' => $virtualClass
        ]);
    }

    /**
     * Delete virtual class details.
     */
    public function destroy(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if (!$user->hasRole('admin') && $course->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $lesson->virtualClass()->delete();
        
        return response()->json(['message' => 'Virtual class details removed']);
    }
}

<?php

namespace App\Http\Controllers\CourseManagement;

use App\Http\Controllers\Controller;

use App\Models\Course;
use App\Models\Lesson;
use App\Models\LessonAssignment;
use App\Models\AssignmentSubmission;
use App\Services\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssignmentController extends Controller
{
    protected $mediaService;

    public function __construct(MediaService $mediaService)
    {
        $this->mediaService = $mediaService;
    }

    /**
     * Show assignment details.
     */
    public function show(Course $course, Lesson $lesson): JsonResponse
    {
        if ($lesson->course_id !== $course->id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $assignment = $lesson->assignment;
        if (!$assignment) {
            return response()->json(['message' => 'No assignment found'], 404);
        }

        return response()->json($assignment);
    }

    /**
     * Create or update an assignment (Instructor).
     */
    public function store(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if (!$user->hasRole('admin') && $course->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'instructions' => 'required|string',
            'max_file_size' => 'nullable|integer|min:1',
            'allowed_extensions' => 'nullable|string|max:255',
            'due_at' => 'nullable|date',
        ]);

        $assignment = LessonAssignment::updateOrCreate(
            ['lesson_id' => $lesson->id],
            [
                'instructions' => $validated['instructions'],
                'max_file_size' => $validated['max_file_size'] ?? 10240,
                'allowed_extensions' => $validated['allowed_extensions'] ?? 'pdf,doc,docx,zip',
                'due_at' => $validated['due_at'] ?? null,
            ]
        );

        if ($lesson->type !== 'assignment') {
            $lesson->update(['type' => 'assignment']);
        }

        return response()->json([
            'message' => 'Assignment saved successfully',
            'assignment' => $assignment
        ]);
    }

    /**
     * Student: Submit an assignment.
     */
    public function submit(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        
        $assignment = $lesson->assignment;
        if (!$assignment) {
            return response()->json(['message' => 'No assignment for this lesson'], 404);
        }

        $request->validate([
            'file' => 'required|file|max:51200', // 50MB
            'student_note' => 'nullable|string',
        ]);

        $file = $request->file('file');
        
        $uploadResult = $this->mediaService->upload(
            $file, 
            "courses/{$course->id}/lessons/{$lesson->id}/assignments/submissions/{$user->id}"
        );

        $submission = AssignmentSubmission::updateOrCreate(
            [
                'lesson_assignment_id' => $assignment->id,
                'user_id' => $user->id,
            ],
            [
                'file_url' => $this->mediaService->getUrl($uploadResult['path']),
                'student_note' => $request->student_note,
                'submitted_at' => now(),
            ]
        );

        return response()->json([
            'message' => 'Assignment submitted successfully',
            'submission' => $submission
        ], 201);
    }

    /**
     * Instructor: List submissions for an assignment.
     */
    public function indexSubmissions(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if (!$user->hasRole('admin') && $course->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $assignment = $lesson->assignment;
        if (!$assignment) {
            return response()->json(['message' => 'Assignment not found'], 404);
        }

        $submissions = $assignment->submissions()->with('user:id,name,email')->get();
        
        $submissions->map(function ($sub) {
            $sub->file_url = $this->mediaService->getUrl($sub->file_path);
            return $sub;
        });

        return response()->json($submissions);
    }
}

<?php

namespace App\Http\Controllers\CourseManagement;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonNote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LessonNoteController extends Controller
{
    public function index(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        $user = $request->user();
        $gate = $this->authorizeLessonAccess($user, $course, $lesson);
        if ($gate) {
            return $gate;
        }

        $notes = LessonNote::where('user_id', $user->id)
            ->where('lesson_id', $lesson->id)
            ->latest()
            ->get();

        return response()->json($notes);
    }

    public function store(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        $user = $request->user();
        $gate = $this->authorizeLessonAccess($user, $course, $lesson);
        if ($gate) {
            return $gate;
        }

        $data = $request->validate([
            'content' => ['required', 'string', 'max:5000'],
            'position_seconds' => ['nullable', 'integer', 'min:0'],
        ]);

        $note = LessonNote::create([
            'user_id' => $user->id,
            'lesson_id' => $lesson->id,
            'content' => $data['content'],
            'position_seconds' => $data['position_seconds'] ?? 0,
        ]);

        return response()->json(['message' => 'Lesson note created successfully.', 'note' => $note], 201);
    }

    public function update(Request $request, Course $course, Lesson $lesson, LessonNote $note): JsonResponse
    {
        $user = $request->user();
        $gate = $this->authorizeOwnedNote($user, $course, $lesson, $note);
        if ($gate) {
            return $gate;
        }

        $data = $request->validate([
            'content' => ['sometimes', 'string', 'max:5000'],
            'position_seconds' => ['nullable', 'integer', 'min:0'],
        ]);

        $note->fill($data)->save();

        return response()->json(['message' => 'Lesson note updated successfully.', 'note' => $note]);
    }

    public function destroy(Request $request, Course $course, Lesson $lesson, LessonNote $note): JsonResponse
    {
        $user = $request->user();
        $gate = $this->authorizeOwnedNote($user, $course, $lesson, $note);
        if ($gate) {
            return $gate;
        }

        $note->delete();

        return response()->json(['message' => 'Lesson note deleted successfully.']);
    }

    private function authorizeLessonAccess($user, Course $course, Lesson $lesson): ?JsonResponse
    {
        if ($lesson->course_id !== $course->id) {
            return response()->json(['message' => 'Not found'], 404);
        }
        $isOwner = $user->hasRole('admin') || $course->user_id === $user->id;
        $isEnrolled = Enrollment::where('user_id', $user->id)->where('course_id', $course->id)->exists();
        return $isOwner || $isEnrolled ? null : response()->json(['message' => 'Enrollment required'], 403);
    }

    private function authorizeOwnedNote($user, Course $course, Lesson $lesson, LessonNote $note): ?JsonResponse
    {
        if ($response = $this->authorizeLessonAccess($user, $course, $lesson)) {
            return $response;
        }
        if ($note->lesson_id !== $lesson->id || $note->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        return null;
    }
}


<?php

namespace App\Http\Controllers\QuizManagement;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Exam;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExamController extends Controller
{
    public function index(Request $request, Course $course): JsonResponse
    {
        $this->authorizeOwner($request, $course);

        $exams = $course->exams()
            ->with('quiz')
            ->get();

        return response()->json($exams);
    }

    public function store(Request $request, Course $course): JsonResponse
    {
        $this->authorizeOwner($request, $course);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'in:draft,scheduled,published,closed'],
            'duration' => ['nullable', 'integer', 'min:0'],
            'pass_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
        ]);

        $exam = $course->exams()->create($validated);

        return response()->json($exam->load('quiz'), 201);
    }

    public function show(Request $request, Course $course, Exam $exam): JsonResponse
    {
        $this->authorizeOwner($request, $course);
        abort_if($exam->course_id !== $course->id, 404);

        return response()->json($exam->load('quiz.questions.answers'));
    }

    public function update(Request $request, Course $course, Exam $exam): JsonResponse
    {
        $this->authorizeOwner($request, $course);
        abort_if($exam->course_id !== $course->id, 404);

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => ['nullable', 'in:draft,scheduled,published,closed'],
            'duration' => ['nullable', 'integer', 'min:0'],
            'pass_score' => ['nullable', 'integer', 'min:0', 'max:100'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date'],
        ]);

        $exam->update($validated);

        return response()->json($exam->fresh()->load('quiz'));
    }

    public function destroy(Request $request, Course $course, Exam $exam): JsonResponse
    {
        $this->authorizeOwner($request, $course);
        abort_if($exam->course_id !== $course->id, 404);

        $exam->delete();

        return response()->json(['message' => 'Exam deleted']);
    }

    private function authorizeOwner(Request $request, Course $course): void
    {
        $user = $request->user();

        abort_unless($user && ($user->hasRole('admin') || $course->user_id === $user->id), 403);
    }
}

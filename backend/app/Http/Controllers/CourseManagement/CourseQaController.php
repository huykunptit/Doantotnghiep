<?php

namespace App\Http\Controllers\CourseManagement;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseQa;
use App\Models\CourseQaReply;
use App\Models\Enrollment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourseQaController extends Controller
{
    /**
     * List questions for a course/lesson.
     */
    public function index(Request $request, Course $course): JsonResponse
    {
        $query = CourseQa::with(['user:id,name,avatar', 'replies.user:id,name,avatar'])
            ->where('course_id', $course->id);

        if ($request->has('lesson_id')) {
            $query->where('lesson_id', $request->lesson_id);
        }

        $qas = $query->orderByDesc('created_at')->paginate(20);

        return response()->json($qas);
    }

    /**
     * Store a new question.
     */
    public function store(Request $request, Course $course): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        // Check enrollment
        $enrolled = Enrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)->exists();

        if (!$enrolled && !$user->hasRole('admin') && $course->user_id !== $user->id) {
            return response()->json(['message' => 'Bạn cần đăng ký khóa học để đặt câu hỏi.'], 403);
        }

        $validated = $request->validate([
            'lesson_id' => ['nullable', 'exists:lessons,id'],
            'subject'   => ['required', 'string', 'max:255'],
            'content'   => ['required', 'string'],
        ]);

        $qa = CourseQa::create([
            'user_id'   => $user->id,
            'course_id' => $course->id,
            'lesson_id' => $validated['lesson_id'] ?? null,
            'subject'   => $validated['subject'],
            'content'   => $validated['content'],
        ]);

        $qa->load('user:id,name,avatar');

        return response()->json($qa, 201);
    }

    /**
     * Show a single question with replies.
     */
    public function show(Course $course, CourseQa $qa): JsonResponse
    {
        if ($qa->course_id !== $course->id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $qa->load(['user:id,name,avatar', 'replies.user:id,name,avatar']);
        return response()->json($qa);
    }

    /**
     * Store a reply.
     */
    public function storeReply(Request $request, Course $course, CourseQa $qa): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if ($qa->course_id !== $course->id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        // Check if user is enrolled or instructor or admin
        $enrolled = Enrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)->exists();

        if (!$enrolled && !$user->hasRole('admin') && $course->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'content' => ['required', 'string'],
        ]);

        $reply = CourseQaReply::create([
            'course_qa_id' => $qa->id,
            'user_id'      => $user->id,
            'content'      => $validated['content'],
        ]);

        $reply->load('user:id,name,avatar');

        return response()->json($reply, 201);
    }
}

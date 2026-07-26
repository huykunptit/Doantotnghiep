<?php

namespace App\Http\Controllers\CourseManagement;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseQa;
use App\Models\CourseQaReply;
use App\Models\Enrollment;
use App\Models\QaReaction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CourseQaController extends Controller
{
    /**
     * List questions for a course/lesson.
     */
    public function index(Request $request, Course $course): JsonResponse
    {
        $query = CourseQa::with([
                'user:id,name,avatar',
                'reactions',
                'replies.user:id,name,avatar',
                'replies.reactions',
            ])
            ->where('course_id', $course->id);

        if ($request->has('lesson_id')) {
            $query->where('lesson_id', $request->lesson_id);
        }

        $qas = $query->orderByDesc('created_at')->paginate(20);

        $userId = optional($request->user())->id;
        $qas->getCollection()->transform(function (CourseQa $qa) use ($userId) {
            $payload = $qa->toArray();
            $payload = array_merge($payload, $qa->appendReactionState($userId));
            $payload['replies'] = collect($qa->replies)->map(function (CourseQaReply $reply) use ($userId) {
                return array_merge($reply->toArray(), $reply->appendReactionState($userId));
            })->values()->all();
            return $payload;
        });

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

        if (!$enrolled && !\App\Support\Authorize::isAdmin($user) && (int) $course->user_id !== (int) $user->id) {
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

        if (!$enrolled && !\App\Support\Authorize::isAdmin($user) && (int) $course->user_id !== (int) $user->id) {
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

        return response()->json(array_merge(
            $reply->toArray(),
            $reply->appendReactionState($user->id),
        ), 201);
    }

    /**
     * Toggle a reaction (like/dislike) on a question or a reply.
     *
     * Body: { reactable_type: 'qa'|'reply', reactable_id: number, kind: 'like'|'dislike' }
     *
     * Behaviour:
     *   - No existing reaction → create with kind.
     *   - Same kind exists     → remove (toggle off).
     *   - Different kind exists→ flip kind.
     */
    public function react(Request $request, Course $course): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $validated = $request->validate([
            'reactable_type' => ['required', 'string', 'in:qa,reply'],
            'reactable_id'   => ['required', 'integer'],
            'kind'           => ['required', 'string', 'in:like,dislike'],
        ]);

        // Resolve target model and verify it belongs to this course.
        if ($validated['reactable_type'] === 'qa') {
            $target = CourseQa::where('id', $validated['reactable_id'])
                ->where('course_id', $course->id)
                ->first();
        } else {
            $target = CourseQaReply::where('id', $validated['reactable_id'])
                ->whereHas('qa', fn ($q) => $q->where('course_id', $course->id))
                ->first();
        }

        if (!$target) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $existing = QaReaction::where('user_id', $user->id)
            ->where('reactable_type', get_class($target))
            ->where('reactable_id', $target->id)
            ->first();

        if (!$existing) {
            QaReaction::create([
                'user_id'        => $user->id,
                'reactable_type' => get_class($target),
                'reactable_id'   => $target->id,
                'kind'           => $validated['kind'],
            ]);
        } elseif ($existing->kind === $validated['kind']) {
            $existing->delete(); // toggle off
        } else {
            $existing->update(['kind' => $validated['kind']]); // flip
        }

        // Return fresh counts for the affected item.
        $target->load('reactions');
        return response()->json($target->appendReactionState($user->id));
    }
}

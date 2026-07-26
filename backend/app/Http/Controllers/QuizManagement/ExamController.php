<?php

namespace App\Http\Controllers\QuizManagement;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Exam;
use App\Models\ExamEnrollment;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExamController extends Controller
{
    /**
     * List exams for a course.
     */
    public function index(Request $request, Course $course): JsonResponse
    {
        $this->authorizeOwner($request, $course);

        $exams = $course->exams()
            ->with('quiz')
            ->withCount('examEnrollments')
            ->get();

        return response()->json($exams);
    }

    /**
     * List all standalone exams (not bound to any course).
     */
    public function standaloneIndex(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && \App\Support\Authorize::allows($user, 'manage_exams'), 403);

        $query = Exam::standalone()->with('quiz', 'creator')->withCount('examEnrollments');

        // Instructors can only see their own standalone exams
        if (!\App\Support\Authorize::isAdmin($user)) {
            $query->where('created_by', $user->id);
        }

        $exams = $query->latest()->get();

        return response()->json($exams);
    }

    /**
     * Create a new exam (course-bound or standalone).
     */
    public function store(Request $request, ?Course $course = null): JsonResponse
    {
        $user = $request->user();

        if ($course) {
            $this->authorizeOwner($request, $course);
        } else {
            abort_unless($user && \App\Support\Authorize::allows($user, 'manage_exams'), 403);
        }

        $validated = $request->validate([
            'title'              => ['required', 'string', 'max:255'],
            'description'        => ['nullable', 'string'],
            'type'               => ['nullable', 'in:course_final,standalone'],
            'status'             => ['nullable', 'in:draft,scheduled,active,closed,archived'],
            'duration'           => ['nullable', 'integer', 'min:0'],
            'pass_score'         => ['nullable', 'integer', 'min:0', 'max:100'],
            'max_attempts'       => ['nullable', 'integer', 'min:1', 'max:99'],
            'shuffle_questions'  => ['nullable', 'boolean'],
            'shuffle_answers'    => ['nullable', 'boolean'],
            'variant_count'      => ['nullable', 'integer', 'min:1', 'max:26'],
            'review_options'     => ['nullable', 'array'],
            'starts_at'          => ['nullable', 'date'],
            'ends_at'            => ['nullable', 'date'],
            'proctoring_enabled' => ['nullable', 'boolean'],
        ]);

        $validated['created_by'] = $user->id;

        if ($course) {
            $validated['type'] = $validated['type'] ?? 'course_final';
            $exam = $course->exams()->create($validated);
        } else {
            $validated['type'] = 'standalone';
            $validated['course_id'] = null;
            $exam = Exam::create($validated);
        }

        return response()->json($exam->load('quiz'), 201);
    }

    /**
     * Create a standalone exam (dedicated route without course prefix).
     */
    public function storeStandalone(Request $request): JsonResponse
    {
        return $this->store($request, null);
    }

    public function show(Request $request, Course $course, Exam $exam): JsonResponse
    {
        $this->authorizeOwner($request, $course);
        abort_if($exam->course_id !== $course->id, 404);

        return response()->json($exam->load('quiz.questions.answers'));
    }

    /**
     * Show a standalone exam.
     */
    public function showStandalone(Request $request, Exam $exam): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && (\App\Support\Authorize::isAdmin($user) || (int) $exam->created_by === (int) $user->id), 403);
        abort_unless($exam->isStandalone(), 404);

        return response()->json($exam->load(['quiz.questions.answers', 'examEnrollments.user']));
    }

    public function update(Request $request, Course $course, Exam $exam): JsonResponse
    {
        $this->authorizeOwner($request, $course);
        abort_if($exam->course_id !== $course->id, 404);

        $validated = $request->validate([
            'title'              => ['sometimes', 'required', 'string', 'max:255'],
            'description'        => ['nullable', 'string'],
            'status'             => ['nullable', 'in:draft,scheduled,active,closed,archived'],
            'duration'           => ['nullable', 'integer', 'min:0'],
            'pass_score'         => ['nullable', 'integer', 'min:0', 'max:100'],
            'max_attempts'       => ['nullable', 'integer', 'min:1', 'max:99'],
            'shuffle_questions'  => ['nullable', 'boolean'],
            'shuffle_answers'    => ['nullable', 'boolean'],
            'variant_count'      => ['nullable', 'integer', 'min:1', 'max:26'],
            'review_options'     => ['nullable', 'array'],
            'starts_at'          => ['nullable', 'date'],
            'ends_at'            => ['nullable', 'date'],
            'proctoring_enabled' => ['nullable', 'boolean'],
        ]);

        $exam->update($validated);

        return response()->json($exam->fresh()->load('quiz'));
    }

    /**
     * Update a standalone exam.
     */
    public function updateStandalone(Request $request, Exam $exam): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && (\App\Support\Authorize::isAdmin($user) || (int) $exam->created_by === (int) $user->id), 403);
        abort_unless($exam->isStandalone(), 404);

        $validated = $request->validate([
            'title'              => ['sometimes', 'required', 'string', 'max:255'],
            'description'        => ['nullable', 'string'],
            'status'             => ['nullable', 'in:draft,scheduled,active,closed,archived'],
            'duration'           => ['nullable', 'integer', 'min:0'],
            'pass_score'         => ['nullable', 'integer', 'min:0', 'max:100'],
            'max_attempts'       => ['nullable', 'integer', 'min:1', 'max:99'],
            'shuffle_questions'  => ['nullable', 'boolean'],
            'shuffle_answers'    => ['nullable', 'boolean'],
            'variant_count'      => ['nullable', 'integer', 'min:1', 'max:26'],
            'review_options'     => ['nullable', 'array'],
            'starts_at'          => ['nullable', 'date'],
            'ends_at'            => ['nullable', 'date'],
            'proctoring_enabled' => ['nullable', 'boolean'],
        ]);

        $exam->update($validated);

        return response()->json($exam->fresh()->load(['quiz', 'examEnrollments.user']));
    }

    public function destroy(Request $request, Course $course, Exam $exam): JsonResponse
    {
        $this->authorizeOwner($request, $course);
        abort_if($exam->course_id !== $course->id, 404);

        $exam->delete();

        return response()->json(['message' => 'Exam deleted']);
    }

    /**
     * Delete a standalone exam.
     */
    public function destroyStandalone(Request $request, Exam $exam): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && (\App\Support\Authorize::isAdmin($user) || (int) $exam->created_by === (int) $user->id), 403);
        abort_unless($exam->isStandalone(), 404);

        $exam->delete();

        return response()->json(['message' => 'Standalone exam deleted']);
    }

    // ── Enrollment management (standalone exams) ────────────────────────

    /**
     * Enroll users into a standalone exam.
     */
    public function enrollUsers(Request $request, Exam $exam): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && (\App\Support\Authorize::isAdmin($user) || (int) $exam->created_by === (int) $user->id), 403);

        $validated = $request->validate([
            'user_ids'   => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $enrolled = 0;
        foreach ($validated['user_ids'] as $userId) {
            ExamEnrollment::firstOrCreate(
                ['exam_id' => $exam->id, 'user_id' => $userId],
                ['enrolled_by' => $user->id, 'enrolled_at' => now()]
            );
            $enrolled++;
        }

        return response()->json([
            'message'  => "Đã gán {$enrolled} thí sinh.",
            'enrolled' => $exam->fresh()->load('examEnrollments.user')->examEnrollments,
        ]);
    }

    /**
     * Remove a user from a standalone exam.
     */
    public function unenrollUser(Request $request, Exam $exam, User $targetUser): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && (\App\Support\Authorize::isAdmin($user) || (int) $exam->created_by === (int) $user->id), 403);

        ExamEnrollment::where('exam_id', $exam->id)
            ->where('user_id', $targetUser->id)
            ->delete();

        return response()->json(['message' => 'Đã xóa thí sinh khỏi kỳ thi.']);
    }

    /**
     * List enrolled users for an exam.
     */
    public function enrolledUsers(Request $request, Exam $exam): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && (\App\Support\Authorize::isAdmin($user) || (int) $exam->created_by === (int) $user->id), 403);

        $enrollments = $exam->examEnrollments()
            ->with('user:id,name,email')
            ->get();

        return response()->json($enrollments);
    }

    /**
     * BA 2026: ghi danh cả lớp hành chính vào kỳ thi (thi cuối kỳ).
     */
    public function enrollByAdminClass(Request $request, Exam $exam): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && (\App\Support\Authorize::isAdmin($user) || (int) $exam->created_by === (int) $user->id), 403);

        $validated = $request->validate([
            'administrative_class_id' => ['required', 'integer', 'exists:administrative_classes,id'],
        ]);

        $studentIds = User::query()
            ->where('administrative_class_id', $validated['administrative_class_id'])
            ->where(function ($q) {
                $q->where('user_type', 'student')
                    ->orWhereHas('roles', fn ($r) => $r->where('name', 'student'));
            })
            ->pluck('id');

        $enrolled = 0;
        foreach ($studentIds as $userId) {
            ExamEnrollment::firstOrCreate(
                ['exam_id' => $exam->id, 'user_id' => $userId],
                ['enrolled_by' => $user->id, 'enrolled_at' => now()]
            );
            $enrolled++;
        }

        return response()->json([
            'message'  => "Đã ghi danh {$enrolled} sinh viên từ lớp hành chính.",
            'enrolled' => $enrolled,
            'total'    => $exam->examEnrollments()->count(),
        ]);
    }

    private function authorizeOwner(Request $request, Course $course): void
    {
        $user = $request->user();

        abort_unless(
            $user
            && \App\Support\Authorize::allows($user, 'manage_exams')
            && (\App\Support\Authorize::isAdmin($user) || (int) $course->user_id === (int) $user->id),
            403
        );
    }

    // ── Print exam / variant codes (mã đề) ───────────────────────────────

    /**
     * Generate a printable, deterministically-shuffled question set for a given
     * exam variant code (A, B, C, ...). Same exam + same code always yields the
     * same question/answer order (seeded shuffle), so re-printing a lost paper
     * reproduces the exact same variant.
     */
    public function printExam(Request $request, Exam $exam): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && \App\Support\Authorize::allows($user, 'manage_exams'), 403);

        if (!\App\Support\Authorize::isAdmin($user)) {
            $course = $exam->course;
            $isOwner = ($course && (int) $course->user_id === (int) $user->id)
                || (int) $exam->created_by === (int) $user->id;
            abort_unless($isOwner, 403);
        }

        $validated = $request->validate([
            'code' => ['nullable', 'string', 'regex:/^[A-Za-z]{1,3}$/'],
        ]);
        $code = strtoupper($validated['code'] ?? 'A');

        $quiz = $exam->quiz;
        if (!$quiz) {
            return response()->json(['message' => 'Exam chưa có đề thi.'], 404);
        }

        $questions = $quiz->questions()->with('answers')->orderBy('quiz_question.order')->get();
        if ($questions->isEmpty()) {
            $questions = $quiz->resolveQuestions()->load('answers');
        }

        // Seed derived from exam id + code so the same code always reproduces
        // the same order (needed if a paper needs to be reprinted).
        $seed = crc32($exam->id . '|' . $code);

        if ($exam->shuffle_questions) {
            $questions = collect($this->seededShuffle($questions->all(), $seed));
        }

        $questions = $questions->values()->map(function ($question, $index) use ($exam, $seed) {
            $answers = $question->answers;
            if ($exam->shuffle_answers) {
                $answers = collect($this->seededShuffle($answers->all(), $seed + $question->id));
            }

            return [
                'number'  => $index + 1,
                'id'      => $question->id,
                'content' => $question->content,
                'type'    => $question->type,
                'answers' => $answers->values()->map(fn ($a, $i) => [
                    'label'      => chr(65 + $i),
                    'content'    => $a->content,
                    'is_correct' => (bool) $a->is_correct,
                ])->all(),
            ];
        });

        return response()->json([
            'exam' => $exam->only(['id', 'title', 'duration', 'pass_score', 'variant_count']),
            'code' => $code,
            'available_codes' => $this->variantCodes($exam->variant_count ?? 1),
            'questions' => $questions,
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    /** @return list<string> */
    private function variantCodes(int $count): array
    {
        $count = max(1, min(26, $count));

        return array_map(fn ($i) => chr(65 + $i), range(0, $count - 1));
    }

    /**
     * Deterministic Fisher-Yates shuffle seeded with a fixed integer, so the
     * result only depends on the seed (not on request order / PHP version RNG
     * warm-up state).
     *
     * @param array<int, mixed> $items
     * @return array<int, mixed>
     */
    private function seededShuffle(array $items, int $seed): array
    {
        mt_srand($seed);
        for ($i = count($items) - 1; $i > 0; $i--) {
            $j = mt_rand(0, $i);
            [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
        }
        mt_srand(); // reseed randomly so we don't leak determinism into unrelated code

        return $items;
    }
}

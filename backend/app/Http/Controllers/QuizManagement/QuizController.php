<?php

namespace App\Http\Controllers\QuizManagement;

use App\Http\Controllers\Controller;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\ExamEnrollment;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAttempt;
use App\Services\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuizController extends Controller
{
    /**
     * Get the quiz for a lesson and resolve randomized questions if necessary.
     */
    public function show(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        if ($lesson->course_id !== $course->id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        /** @var \App\Models\User|null $user */
        $user = $request->user();

        // Basic permission check
        $isOwner = $user && (\App\Support\Authorize::isAdmin($user) || (int) $course->user_id === (int) $user->id);
        $isEnrolled = $user && Enrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)->exists();

        if (!$isOwner && !$isEnrolled && $course->price > 0 && !$lesson->is_preview) {
            return response()->json(['message' => 'Enrollment required'], 403);
        }

        $quiz = Quiz::where('lesson_id', $lesson->id)->first();
        if (!$quiz) {
            return response()->json(['message' => 'No quiz for this lesson'], 404);
        }

        // Logic for picking questions: 
        // 1. Check if user has an active/recent uncompleted attempt
        // 2. If not, generate a new set of questions based on quiz rules
        $attempt = QuizAttempt::where('user_id', $user->id)
            ->where('quiz_id', $quiz->id)
            ->whereIn('status', ['in_progress', 'paused'])
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$attempt) {
            $questions = $quiz->resolveQuestions();
            $attempt = QuizAttempt::create([
                'user_id'      => $user->id,
                'quiz_id'      => $quiz->id,
                'status'       => 'in_progress',
                'question_ids' => $questions->pluck('id')->toArray(),
                'started_at'   => now(),
                'ip_address'   => $request->ip(),
                'user_agent'   => $request->userAgent(),
            ]);
        } else {
            $questions = Question::whereIn('id', $attempt->question_ids)
                ->with('answers')
                ->get()
                ->sortBy(function($q) use ($attempt) {
                    return array_search($q->id, $attempt->question_ids);
                });
        }

        // If not owner, hide 'is_correct' from answers
        if (!$isOwner) {
            $questions->each(function ($question) {
                $question->answers->each(function ($answer) {
                    $answer->makeHidden('is_correct');
                });
            });
        }

        return response()->json([
            'quiz'           => $quiz,
            'questions'      => $questions->values(),
            'attempt_id'     => $attempt->id,
            'remaining_time' => $attempt->remainingTime(),
            'status'         => $attempt->status,
        ]);
    }

    /**
     * Lightweight, side-effect-free check used by the client before starting
     * an exam: tells the UI whether a face-verification gate is required
     * *without* creating an attempt / starting the timer (unlike startExamQuiz).
     */
    public function examPreCheck(Request $request, Exam $exam, MediaService $mediaService): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        if ($exam->isCourseExam()) {
            $isEnrolled = Enrollment::where('user_id', $user->id)
                ->where('course_id', $exam->course_id)->exists();
            abort_unless($isEnrolled || \App\Support\Authorize::isAdmin($user), 403, 'Bạn chưa đăng ký khóa học này.');
        } else {
            $isEnrolled = ExamEnrollment::where('exam_id', $exam->id)
                ->where('user_id', $user->id)->exists();
            abort_unless($isEnrolled || \App\Support\Authorize::isAdmin($user), 403, 'Bạn chưa được gán vào kỳ thi này.');
        }

        $quiz = $exam->quiz;
        $hasActiveAttempt = $quiz && QuizAttempt::where('user_id', $user->id)
            ->where('quiz_id', $quiz->id)
            ->whereIn('status', ['in_progress', 'paused'])
            ->exists();

        $hasFaceUrl = ! empty($user->face_url);
        $faceUsable = $hasFaceUrl && $this->isUsableFacePhoto((string) $user->face_url, $mediaService);

        return response()->json([
            'exam' => $exam->only(['id', 'title', 'duration', 'proctoring_enabled']),
            'is_open' => $exam->isOpen() || \App\Support\Authorize::isAdmin($user),
            'has_face_url' => $hasFaceUrl,
            'face_photo_usable' => $faceUsable,
            // Resolved URL of the student's enrolled reference photo, needed
            // client-side to compute a face descriptor for comparison against
            // the live webcam capture (see FaceVerification.vue).
            'face_photo_url' => $hasFaceUrl ? $mediaService->getUrl($user->face_url) : null,
            // Only gate on face check for a *fresh* start — resuming an
            // already in-progress attempt shouldn't re-block the student.
            'requires_face_check' => (bool) $exam->proctoring_enabled && ! $hasActiveAttempt,
            // Seed placeholders / missing photos: first capture becomes face_url.
            'can_enroll_face' => ! $faceUsable,
        ]);
    }

    /**
     * Seed badges (~2–3KB / tiny canvas) are not usable for face matching.
     */
    private function isUsableFacePhoto(string $path, MediaService $mediaService): bool
    {
        $key = \App\Support\PublicMediaUrl::toStorageKey($path) ?? ltrim(str_replace('/storage/', '', $path), '/');
        $absolute = storage_path('app/public/'.$key);

        if (! is_file($absolute)) {
            // MinIO / remote disk — treat as usable if the media layer says it exists.
            return $mediaService->exists($path);
        }

        if (filesize($absolute) < 8000) {
            return false;
        }

        $info = @getimagesize($absolute);
        if (! $info || ($info[0] ?? 0) < 120 || ($info[1] ?? 0) < 120) {
            return false;
        }

        return true;
    }

    /**
     * Start/show an exam quiz for a student.
     */
    public function startExamQuiz(Request $request, Exam $exam): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        // Check access: course enrollment or exam enrollment
        if ($exam->isCourseExam()) {
            $isEnrolled = Enrollment::where('user_id', $user->id)
                ->where('course_id', $exam->course_id)->exists();
            abort_unless($isEnrolled || \App\Support\Authorize::isAdmin($user), 403, 'Bạn chưa đăng ký khóa học này.');
        } else {
            $isEnrolled = ExamEnrollment::where('exam_id', $exam->id)
                ->where('user_id', $user->id)->exists();
            abort_unless($isEnrolled || \App\Support\Authorize::isAdmin($user), 403, 'Bạn chưa được gán vào kỳ thi này.');
        }

        // Check if exam is open
        if (!$exam->isOpen() && !\App\Support\Authorize::isAdmin($user)) {
            return response()->json(['message' => 'Kỳ thi chưa mở hoặc đã đóng.'], 422);
        }

        $quiz = $exam->quiz;
        if (!$quiz) {
            return response()->json(['message' => 'Exam chưa có đề thi.'], 404);
        }

        // Check max attempts
        $attemptCount = QuizAttempt::where('user_id', $user->id)
            ->where('quiz_id', $quiz->id)
            ->whereIn('status', ['submitted', 'force_stopped'])
            ->count();

        if ($attemptCount >= ($exam->max_attempts ?? 1) && !\App\Support\Authorize::isAdmin($user)) {
            return response()->json([
                'message'      => 'Bạn đã hết lượt thi.',
                'max_attempts' => $exam->max_attempts,
                'used'         => $attemptCount,
            ], 422);
        }

        // Find active attempt or create new
        $attempt = QuizAttempt::where('user_id', $user->id)
            ->where('quiz_id', $quiz->id)
            ->whereIn('status', ['in_progress', 'paused'])
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$attempt) {
            $questions = $quiz->resolveQuestions();

            // Shuffle if enabled
            if ($exam->shuffle_questions) {
                $questions = $questions->shuffle();
            }

            $attempt = QuizAttempt::create([
                'user_id'      => $user->id,
                'quiz_id'      => $quiz->id,
                'status'       => 'in_progress',
                'question_ids' => $questions->pluck('id')->toArray(),
                'started_at'   => now(),
                'ip_address'   => $request->ip(),
                'user_agent'   => $request->userAgent(),
            ]);
        } else {
            $questions = Question::whereIn('id', $attempt->question_ids)
                ->with('answers')
                ->get()
                ->sortBy(fn ($q) => array_search($q->id, $attempt->question_ids));
        }

        // Shuffle answers if enabled
        if ($exam->shuffle_answers) {
            $questions->each(function ($question) {
                $question->setRelation('answers', $question->answers->shuffle());
            });
        }

        // Hide correct answers from student
        $questions->each(function ($question) {
            $question->answers->each(fn ($a) => $a->makeHidden('is_correct'));
        });

        return response()->json([
            'exam'           => $exam->only(['id', 'title', 'description', 'duration', 'pass_score', 'type', 'proctoring_enabled']),
            'quiz'           => $quiz->only(['id', 'title', 'time_limit']),
            'questions'      => $questions->values(),
            'attempt_id'     => $attempt->id,
            'remaining_time' => $attempt->remainingTime(),
            'status'         => $attempt->status,
            'saved_answers'  => $attempt->answers_json,
        ]);
    }

    public function storeOrUpdate(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        $user = $request->user();

        if (!$user || (!\App\Support\Authorize::isAdmin($user) && (int) $course->user_id !== (int) $user->id) || $lesson->course_id !== $course->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'title'       => 'required|string|max:255',
            'description' => 'nullable|string',
            'time_limit'  => 'nullable|integer|min:0',
            'pass_score'  => 'required|integer|min:0|max:100',
            'question_ids'  => 'nullable|array',
            'question_ids.*' => 'integer|exists:questions,id',
            'settings'    => 'nullable|array',
            'questions'   => 'nullable|array',
            'questions.*.content' => 'required|string',
            'questions.*.type'    => 'nullable|string',
            'questions.*.difficulty' => 'nullable|integer|min:1|max:5',
            'questions.*.explanation' => 'nullable|string',
            'questions.*.answers'     => 'required|array|min:1',
            'questions.*.answers.*.content'    => 'required|string',
            'questions.*.answers.*.is_correct' => 'required|boolean',
        ]);

        $quiz = Quiz::firstOrNew([
            'lesson_id' => $lesson->id,
            'scope'     => 'lesson',
        ]);

        DB::transaction(function () use ($validated, $course, $quiz, $request) {
            $quiz->fill([
                'course_id'   => $course->id,
                'scope'       => 'lesson',
                'title'       => $validated['title'],
                'description' => $validated['description'] ?? null,
                'time_limit'  => $validated['time_limit'] ?? null,
                'pass_score'  => $validated['pass_score'],
                'settings'    => $validated['settings'] ?? null,
            ]);
            $quiz->save();

            $attachIds = collect($validated['question_ids'] ?? []);

            if (!empty($validated['questions'])) {
                foreach ($validated['questions'] as $questionData) {
                    $question = Question::create([
                        'course_id'   => $course->id,
                        'content'     => $questionData['content'],
                        'type'        => $questionData['type'] ?? 'single_choice',
                        'difficulty'  => $questionData['difficulty'] ?? 1,
                        'explanation' => $questionData['explanation'] ?? null,
                    ]);

                    foreach ($questionData['answers'] as $index => $answerData) {
                        $question->answers()->create([
                            'content'    => $answerData['content'],
                            'is_correct' => $answerData['is_correct'],
                            'order'      => $index,
                        ]);
                    }

                    $attachIds->push($question->id);
                }
            }

            // Only sync when client explicitly sends question_ids or creates questions.
            // Omitting question_ids preserves the existing quiz bank (builder meta save).
            if ($request->exists('question_ids') || !empty($validated['questions'])) {
                $syncPayload = $attachIds
                    ->unique()
                    ->values()
                    ->mapWithKeys(fn ($id, $index) => [$id => ['order' => $index, 'points' => 10]])
                    ->all();

                $quiz->questions()->sync($syncPayload);
            }
        });

        return response()->json([
            'message' => 'Quiz saved',
            'quiz'    => $quiz->fresh()->load('questions.answers'),
        ]);
    }

    public function showExamQuiz(Request $request, Course $course, Exam $exam): JsonResponse
    {
        $user = $request->user();
        abort_if(!$user || (!\App\Support\Authorize::isAdmin($user) && (int) $course->user_id !== (int) $user->id), 403);
        abort_if($exam->course_id !== $course->id, 404);

        $quiz = Quiz::where('exam_id', $exam->id)
            ->where('scope', 'exam')
            ->with('questions.answers')
            ->first();

        if (!$quiz) {
            return response()->json(['message' => 'No quiz for this exam'], 404);
        }

        return response()->json([
            'quiz'      => $quiz,
            'questions' => $quiz->questions,
        ]);
    }

    public function storeOrUpdateExamQuiz(Request $request, Course $course, Exam $exam): JsonResponse
    {
        $user = $request->user();
        abort_if(!$user || (!\App\Support\Authorize::isAdmin($user) && (int) $course->user_id !== (int) $user->id), 403);
        abort_if($exam->course_id !== $course->id, 404);

        $validated = $request->validate([
            'title'          => 'required|string|max:255',
            'description'    => 'nullable|string',
            'time_limit'     => 'nullable|integer|min:0',
            'pass_score'     => 'required|integer|min:0|max:100',
            'question_ids'   => 'nullable|array',
            'question_ids.*' => 'integer|exists:questions,id',
            'settings'       => 'nullable|array',
        ]);

        $quiz = Quiz::firstOrNew([
            'exam_id' => $exam->id,
            'scope'   => 'exam',
        ]);

        $quiz->fill([
            'course_id'   => $course->id,
            'lesson_id'   => null,
            'scope'       => 'exam',
            'title'       => $validated['title'],
            'description' => $validated['description'] ?? null,
            'time_limit'  => $validated['time_limit'] ?? $exam->duration,
            'pass_score'  => $validated['pass_score'] ?? $exam->pass_score,
            'settings'    => $validated['settings'] ?? null,
        ]);
        $quiz->save();

        $syncPayload = collect($validated['question_ids'] ?? [])
            ->values()
            ->mapWithKeys(fn ($id, $index) => [$id => ['order' => $index, 'points' => 10]])
            ->all();

        $quiz->questions()->sync($syncPayload);

        return response()->json([
            'message' => 'Exam quiz saved',
            'quiz'    => $quiz->fresh()->load('questions.answers'),
        ]);
    }

    public function showStandaloneExamQuiz(Request $request, Exam $exam): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && (\App\Support\Authorize::isAdmin($user) || (int) $exam->created_by === (int) $user->id), 403);
        abort_unless($exam->isStandalone(), 404);

        $quiz = Quiz::where('exam_id', $exam->id)->where('scope', 'exam')->first();
        if (!$quiz) {
            return response()->json(['quiz' => null]);
        }

        return response()->json([
            'quiz'      => $quiz->load('questions.answers'),
            'questions' => $quiz->questions,
        ]);
    }

    public function storeOrUpdateStandaloneExamQuiz(Request $request, Exam $exam): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && (\App\Support\Authorize::isAdmin($user) || (int) $exam->created_by === (int) $user->id), 403);
        abort_unless($exam->isStandalone(), 404);

        $validated = $request->validate([
            'title'          => 'required|string|max:255',
            'description'    => 'nullable|string',
            'time_limit'     => 'nullable|integer|min:0',
            'pass_score'     => 'required|integer|min:0|max:100',
            'question_ids'   => 'nullable|array',
            'question_ids.*' => 'integer|exists:questions,id',
            'settings'       => 'nullable|array',
        ]);

        $quiz = Quiz::firstOrNew(['exam_id' => $exam->id, 'scope' => 'exam']);

        $quiz->fill([
            'course_id'   => null,
            'lesson_id'   => null,
            'scope'       => 'exam',
            'title'       => $validated['title'],
            'description' => $validated['description'] ?? null,
            'time_limit'  => $validated['time_limit'] ?? $exam->duration,
            'pass_score'  => $validated['pass_score'] ?? $exam->pass_score,
            'settings'    => $validated['settings'] ?? null,
        ]);
        $quiz->save();

        $syncPayload = collect($validated['question_ids'] ?? [])
            ->values()
            ->mapWithKeys(fn ($id, $index) => [$id => ['order' => $index, 'points' => 10]])
            ->all();

        $quiz->questions()->sync($syncPayload);

        return response()->json([
            'message' => 'Exam quiz saved',
            'quiz'    => $quiz->fresh()->load('questions.answers'),
        ]);
    }

    /**
     * Submit an attempt — supports multiple question types.
     */
    public function submit(Request $request, Course $course, Lesson $lesson, Quiz $quiz): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if ($quiz->lesson_id !== $lesson->id || $lesson->course_id !== $course->id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $validated = $request->validate([
            'attempt_id' => 'required|exists:quiz_attempts,id',
            'answers'    => 'required|array', // key: question_id, value: array of answer_ids or text
        ]);

        $attempt = QuizAttempt::findOrFail($validated['attempt_id']);
        if ($attempt->user_id !== $user->id || $attempt->isCompleted()) {
            return response()->json(['message' => 'Invalid or completed attempt'], 403);
        }

        return $this->gradeAndSubmit($attempt, $validated['answers'], $quiz);
    }

    /**
     * Submit an exam attempt.
     */
    public function submitExam(Request $request, Exam $exam): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        $quiz = $exam->quiz;
        abort_unless($quiz, 404);

        $validated = $request->validate([
            'attempt_id' => 'required|exists:quiz_attempts,id',
            'answers'    => 'required|array',
        ]);

        $attempt = QuizAttempt::findOrFail($validated['attempt_id']);
        if ($attempt->user_id !== $user->id || $attempt->isCompleted()) {
            return response()->json(['message' => 'Invalid or completed attempt'], 403);
        }

        $result = $this->gradeAndSubmit($attempt, $validated['answers'], $quiz);

        // Apply review options filtering
        $responseData = json_decode($result->getContent(), true);

        if (!\App\Support\Authorize::isAdmin($user) && $exam->review_options) {
            $reviewOpts = $exam->review_options['after_submit'] ?? [];

            if (!($reviewOpts['marks'] ?? true)) {
                unset($responseData['score']);
            }
            if (!($reviewOpts['correctness'] ?? true)) {
                unset($responseData['passed']);
            }
            if (!($reviewOpts['right_answer'] ?? false)) {
                unset($responseData['correct_answers']);
            }
        }

        return response()->json($responseData);
    }

    /**
     * Get exam results with review options applied.
     */
    public function examResults(Request $request, Exam $exam, QuizAttempt $attempt): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        $isOwner = \App\Support\Authorize::isAdmin($user) || (int) $exam->created_by === (int) $user->id;
        $isStudent = $attempt->user_id === $user->id;
        abort_unless($isOwner || $isStudent, 403);
        abort_unless($attempt->isCompleted(), 422);

        $quiz = $exam->quiz;
        $questions = Question::whereIn('id', $attempt->question_ids ?? [])
            ->with('answers')
            ->get();

        $reviewOpts = $exam->review_options['after_submit'] ?? Exam::defaultReviewOptions()['after_submit'];

        $result = [
            'attempt_id' => $attempt->id,
            'status'     => $attempt->status,
        ];

        if ($isOwner || ($reviewOpts['marks'] ?? false)) {
            $result['score'] = $attempt->score;
            $result['passed'] = $attempt->passed;
        }

        if ($isOwner || ($reviewOpts['attempt'] ?? false)) {
            $result['student_answers'] = $attempt->answers_json;
        }

        if ($isOwner || ($reviewOpts['right_answer'] ?? false)) {
            $result['questions'] = $questions->map(function ($q) {
                return [
                    'id'      => $q->id,
                    'content' => $q->content,
                    'type'    => $q->type,
                    'answers' => $q->answers,
                ];
            });
        } elseif ($isOwner || ($reviewOpts['correctness'] ?? false)) {
            // Show which were right/wrong but not the correct answers
            $result['questions'] = $questions->map(function ($q) use ($attempt) {
                $studentAnswer = ($attempt->answers_json ?? [])[$q->id] ?? null;
                return [
                    'id'         => $q->id,
                    'content'    => $q->content,
                    'type'       => $q->type,
                    'is_correct' => $this->isAnswerCorrect($q, $studentAnswer),
                ];
            });
        }

        if ($isOwner || ($reviewOpts['specific_feedback'] ?? false)) {
            $result['feedback'] = $questions->pluck('feedback', 'id')->filter();
        }

        if ($isOwner || ($reviewOpts['general_feedback'] ?? false)) {
            $result['general_feedback'] = $questions->pluck('general_feedback', 'id')->filter();
        }

        if ($isOwner || ($reviewOpts['overall_feedback'] ?? false)) {
            $result['overall_feedback'] = $attempt->passed
                ? 'Chúc mừng, bạn đã vượt qua bài thi!'
                : 'Bạn chưa đạt điểm tối thiểu. Hãy ôn tập và thử lại.';
        }

        return response()->json($result);
    }

    // ── Private grading helpers ─────────────────────────────────────────

    private function gradeAndSubmit(QuizAttempt $attempt, array $studentAnswers, Quiz $quiz): JsonResponse
    {
        $questions = Question::whereIn('id', $attempt->question_ids)->with('answers')->get();
        $totalPoints = 0;
        $earnedPoints = 0;

        foreach ($questions as $question) {
            $submitted = $studentAnswers[$question->id] ?? null;
            $qScore = $question->default_score ?? 1;
            $totalPoints += $qScore;

            if ($this->isAnswerCorrect($question, $submitted)) {
                $earnedPoints += $qScore;
            }
        }

        $score = ($totalPoints > 0) ? ($earnedPoints / $totalPoints) * 100 : 0;
        $passed = $score >= $quiz->pass_score;

        $attempt->update([
            'status'       => 'submitted',
            'score'        => round($score, 2),
            'passed'       => $passed,
            'answers_json' => $studentAnswers,
            'completed_at' => now(),
        ]);

        $review = $questions->map(function ($question) use ($studentAnswers) {
            $submitted = $studentAnswers[$question->id] ?? null;

            return [
                'id'              => $question->id,
                'content'         => $question->content,
                'type'            => $question->type,
                'is_correct'      => $this->isAnswerCorrect($question, $submitted),
                'student_answer'  => $submitted,
                'answers'         => $question->answers->map(fn ($a) => [
                    'id'         => $a->id,
                    'content'    => $a->content,
                    'is_correct' => (bool) $a->is_correct,
                ])->values(),
            ];
        })->values();

        return response()->json([
            'message' => 'Đã nộp bài thi.',
            'score'   => round($score, 2),
            'passed'  => $passed,
            'attempt' => $attempt,
            'review'  => $review,
        ]);
    }

    /**
     * Check if a student's answer is correct for a given question.
     */
    private function isAnswerCorrect(Question $question, mixed $submitted): bool
    {
        if ($submitted === null) return false;

        $correctAnswers = $question->answers->where('is_correct', true);

        switch ($question->type) {
            case 'single_choice':
            case 'true_false':
                $correctId = $correctAnswers->first()?->id;
                $submittedId = is_array($submitted) ? ($submitted[0] ?? null) : $submitted;
                return (int) $submittedId === (int) $correctId;

            case 'multiple_choice':
                $correctIds = $correctAnswers->pluck('id')->sort()->values()->toArray();
                $submittedIds = collect(is_array($submitted) ? $submitted : [$submitted])
                    ->map(fn ($v) => (int) $v)->sort()->values()->toArray();
                return $correctIds === $submittedIds;

            case 'short_answer':
                $correctTexts = $correctAnswers->pluck('content')
                    ->map(fn ($c) => mb_strtolower(trim($c)));
                $submittedText = mb_strtolower(trim(is_array($submitted) ? ($submitted[0] ?? '') : $submitted));
                return $correctTexts->contains($submittedText);

            case 'numerical':
                $correctAnswer = $correctAnswers->first();
                if (!$correctAnswer) return false;
                $correctValue = (float) $correctAnswer->content;
                $tolerance = (float) ($question->metadata['tolerance'] ?? 0);
                $submittedValue = (float) (is_array($submitted) ? ($submitted[0] ?? 0) : $submitted);
                return abs($submittedValue - $correctValue) <= $tolerance;

            case 'matching':
                // Submitted as { left_id: right_content }
                $pairs = $question->answers->mapWithKeys(fn ($a) => [$a->id => $a->sub_content]);
                if (!is_array($submitted)) return false;
                foreach ($pairs as $id => $expectedRight) {
                    if (($submitted[$id] ?? null) !== $expectedRight) return false;
                }
                return true;

            case 'essay':
                // Essay requires manual grading, mark as pending
                return false;

            default:
                return false;
        }
    }
}

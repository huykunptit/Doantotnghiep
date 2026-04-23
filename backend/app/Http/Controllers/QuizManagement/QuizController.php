<?php

namespace App\Http\Controllers\QuizManagement;

use App\Http\Controllers\Controller;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Exam;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\Quiz;
use App\Models\QuizAttempt;
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
        $isOwner = $user && ($user->hasRole('admin') || $course->user_id === $user->id);
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
            ->whereNull('completed_at')
            ->orderBy('created_at', 'desc')
            ->first();

        if (!$attempt) {
            $questions = $quiz->resolveQuestions();
            $attempt = QuizAttempt::create([
                'user_id' => $user->id,
                'quiz_id' => $quiz->id,
                'question_ids' => $questions->pluck('id')->toArray(),
                'started_at' => now(),
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
            'quiz' => $quiz,
            'questions' => $questions->values(),
            'attempt_id' => $attempt->id
        ]);
    }

    public function storeOrUpdate(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        $user = $request->user();

        if (!$user || (!$user->hasRole('admin') && $course->user_id !== $user->id) || $lesson->course_id !== $course->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'time_limit' => 'nullable|integer|min:0',
            'pass_score' => 'required|integer|min:0|max:100',
            'question_ids' => 'nullable|array',
            'question_ids.*' => 'integer|exists:questions,id',
            'settings' => 'nullable|array',
            'questions' => 'nullable|array',
            'questions.*.content' => 'required|string',
            'questions.*.type' => 'nullable|string',
            'questions.*.difficulty' => 'nullable|integer|min:1|max:5',
            'questions.*.explanation' => 'nullable|string',
            'questions.*.answers' => 'required|array|min:1',
            'questions.*.answers.*.content' => 'required|string',
            'questions.*.answers.*.is_correct' => 'required|boolean',
        ]);

        $quiz = Quiz::firstOrNew([
            'lesson_id' => $lesson->id,
            'scope' => 'lesson',
        ]);

        DB::transaction(function () use ($validated, $course, $quiz) {
            $quiz->fill([
                'course_id' => $course->id,
                'scope' => 'lesson',
                'title' => $validated['title'],
                'description' => $validated['description'] ?? null,
                'time_limit' => $validated['time_limit'] ?? null,
                'pass_score' => $validated['pass_score'],
                'settings' => $validated['settings'] ?? null,
            ]);
            $quiz->save();

            $attachIds = collect($validated['question_ids'] ?? []);

            if (!empty($validated['questions'])) {
                foreach ($validated['questions'] as $questionData) {
                    $question = Question::create([
                        'course_id' => $course->id,
                        'content' => $questionData['content'],
                        'type' => $questionData['type'] ?? 'single_choice',
                        'difficulty' => $questionData['difficulty'] ?? 1,
                        'explanation' => $questionData['explanation'] ?? null,
                    ]);

                    foreach ($questionData['answers'] as $index => $answerData) {
                        $question->answers()->create([
                            'content' => $answerData['content'],
                            'is_correct' => $answerData['is_correct'],
                            'order' => $index,
                        ]);
                    }

                    $attachIds->push($question->id);
                }
            }

            $syncPayload = $attachIds
                ->unique()
                ->values()
                ->mapWithKeys(fn ($id, $index) => [$id => ['order' => $index, 'points' => 10]])
                ->all();

            $quiz->questions()->sync($syncPayload);
        });

        return response()->json([
            'message' => 'Quiz saved',
            'quiz' => $quiz->fresh()->load('questions.answers'),
        ]);
    }

    public function showExamQuiz(Request $request, Course $course, Exam $exam): JsonResponse
    {
        $user = $request->user();
        abort_if(!$user || (!$user->hasRole('admin') && $course->user_id !== $user->id), 403);
        abort_if($exam->course_id !== $course->id, 404);

        $quiz = Quiz::where('exam_id', $exam->id)
            ->where('scope', 'exam')
            ->with('questions.answers')
            ->first();

        if (!$quiz) {
            return response()->json(['message' => 'No quiz for this exam'], 404);
        }

        return response()->json([
            'quiz' => $quiz,
            'questions' => $quiz->questions,
        ]);
    }

    public function storeOrUpdateExamQuiz(Request $request, Course $course, Exam $exam): JsonResponse
    {
        $user = $request->user();
        abort_if(!$user || (!$user->hasRole('admin') && $course->user_id !== $user->id), 403);
        abort_if($exam->course_id !== $course->id, 404);

        $validated = $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'time_limit' => 'nullable|integer|min:0',
            'pass_score' => 'required|integer|min:0|max:100',
            'question_ids' => 'nullable|array',
            'question_ids.*' => 'integer|exists:questions,id',
            'settings' => 'nullable|array',
        ]);

        $quiz = Quiz::firstOrNew([
            'exam_id' => $exam->id,
            'scope' => 'exam',
        ]);

        $quiz->fill([
            'course_id' => $course->id,
            'lesson_id' => null,
            'scope' => 'exam',
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'time_limit' => $validated['time_limit'] ?? $exam->duration,
            'pass_score' => $validated['pass_score'] ?? $exam->pass_score,
            'settings' => $validated['settings'] ?? null,
        ]);
        $quiz->save();

        $syncPayload = collect($validated['question_ids'] ?? [])
            ->values()
            ->mapWithKeys(fn ($id, $index) => [$id => ['order' => $index, 'points' => 10]])
            ->all();

        $quiz->questions()->sync($syncPayload);

        return response()->json([
            'message' => 'Exam quiz saved',
            'quiz' => $quiz->fresh()->load('questions.answers'),
        ]);
    }

    /**
     * Submit an attempt.
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
            'answers' => 'required|array', // key: question_id, value: array of answer_ids or text
        ]);

        $attempt = QuizAttempt::findOrFail($validated['attempt_id']);
        if ($attempt->user_id !== $user->id || $attempt->completed_at !== null) {
            return response()->json(['message' => 'Invalid or completed attempt'], 403);
        }

        $questions = Question::whereIn('id', $attempt->question_ids)->with('answers')->get();
        $totalQuestions = $questions->count();
        $correctCount = 0;
        $studentAnswers = $validated['answers'];

        foreach ($questions as $question) {
            $submitted = $studentAnswers[$question->id] ?? null;

            if ($question->type === 'single_choice' || $question->type === 'multiple_choice') {
                $correctIds = $question->answers->where('is_correct', true)->pluck('id')->toArray();
                $submittedIds = is_array($submitted) ? $submitted : [$submitted];
                
                sort($correctIds);
                sort($submittedIds);
                
                if ($correctIds === $submittedIds) {
                    $correctCount++;
                }
            } 
            // Prepared for expansion: matching, ordering, short_answer
        }

        $score = ($totalQuestions > 0) ? ($correctCount / $totalQuestions) * 100 : 0;
        $passed = $score >= $quiz->pass_score;

        $attempt->update([
            'score' => round($score, 2),
            'passed' => $passed,
            'answers_json' => $studentAnswers,
            'completed_at' => now(),
        ]);

        return response()->json([
            'message' => 'Quiz submitted',
            'score' => round($score, 2),
            'passed' => $passed,
            'attempt' => $attempt
        ]);
    }
}

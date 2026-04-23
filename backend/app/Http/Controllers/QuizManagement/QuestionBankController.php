<?php

namespace App\Http\Controllers\QuizManagement;

use App\Http\Controllers\Controller;

use App\Models\Course;
use App\Models\QuestionBank;
use App\Models\QuestionGroup;
use App\Models\Question;
use App\Models\Answer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuestionBankController extends Controller
{
    /**
     * List all banks for a course.
     */
    public function index(Course $course): JsonResponse
    {
        $banks = QuestionBank::where('course_id', $course->id)
            ->withCount('questions', 'groups')
            ->with(['groups' => fn ($query) => $query->withCount('questions')])
            ->orderBy('created_at')
            ->get();

        $groups = QuestionGroup::where('course_id', $course->id)
            ->whereNull('question_bank_id')
            ->withCount('questions')
            ->orderBy('sort_order')
            ->get();

        return response()->json([
            'banks' => $banks,
            'ungrouped_sets' => $groups,
        ]);
    }

    public function store(Request $request, Course $course): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $bank = QuestionBank::create([
            'course_id' => $course->id,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
        ]);

        return response()->json($bank, 201);
    }

    public function update(Request $request, Course $course, QuestionBank $bank): JsonResponse
    {
        if ($bank->course_id !== $course->id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
        ]);

        $bank->update($validated);

        return response()->json($bank->fresh()->loadCount('questions', 'groups'));
    }

    public function destroy(Course $course, QuestionBank $bank): JsonResponse
    {
        if ($bank->course_id !== $course->id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $bank->delete();

        return response()->json(['message' => 'Bank deleted']);
    }

    public function storeGroup(Request $request, Course $course): JsonResponse
    {
        $validated = $request->validate([
            'question_bank_id' => 'nullable|integer|exists:question_banks,id',
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        if (!empty($validated['question_bank_id'])) {
            $bank = QuestionBank::findOrFail($validated['question_bank_id']);
            if ($bank->course_id !== $course->id) {
                return response()->json(['message' => 'Bank not found'], 404);
            }
        }

        $group = QuestionGroup::create([
            'course_id' => $course->id,
            'question_bank_id' => $validated['question_bank_id'] ?? null,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'sort_order' => $validated['sort_order'] ?? 0,
        ]);

        return response()->json($group->loadCount('questions'), 201);
    }

    public function updateGroup(Request $request, Course $course, QuestionGroup $group): JsonResponse
    {
        if ($group->course_id !== $course->id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $validated = $request->validate([
            'question_bank_id' => 'nullable|integer|exists:question_banks,id',
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        if (array_key_exists('question_bank_id', $validated) && !empty($validated['question_bank_id'])) {
            $bank = QuestionBank::findOrFail($validated['question_bank_id']);
            if ($bank->course_id !== $course->id) {
                return response()->json(['message' => 'Bank not found'], 404);
            }
        }

        $group->update($validated);

        return response()->json($group->fresh()->loadCount('questions'));
    }

    public function destroyGroup(Course $course, QuestionGroup $group): JsonResponse
    {
        if ($group->course_id !== $course->id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $group->delete();

        return response()->json(['message' => 'Group deleted']);
    }

    public function show(Course $course, QuestionBank $bank): JsonResponse
    {
        if ($bank->course_id !== $course->id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $banks = QuestionBank::where('course_id', $course->id)
            ->withCount('questions')
            ->get();

        return response()->json($bank->load([
            'groups.questions.answers',
            'questions.answers',
        ]));
    }

    public function storeQuestion(Request $request, Course $course, QuestionBank $bank): JsonResponse
    {
        return $this->persistQuestion($request, $course, $bank, null);
    }

    public function updateQuestion(Request $request, Course $course, QuestionBank $bank, Question $question): JsonResponse
    {
        if ($question->question_bank_id !== $bank->id || $question->course_id !== $course->id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        return $this->persistQuestion($request, $course, $bank, $question);
    }

    public function destroyQuestion(Course $course, QuestionBank $bank, Question $question): JsonResponse
    {
        if ($question->question_bank_id !== $bank->id || $question->course_id !== $course->id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $question->delete();

        return response()->json(['message' => 'Question deleted']);
    }

    private function persistQuestion(Request $request, Course $course, QuestionBank $bank, ?Question $question): JsonResponse
    {
        if ($bank->course_id !== $course->id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $validated = $request->validate([
            'question_group_id' => 'nullable|integer|exists:question_groups,id',
            'content' => 'required|string',
            'type' => 'required|string',
            'difficulty' => 'nullable|integer|min:1|max:5',
            'explanation' => 'nullable|string',
            'answers' => 'required|array|min:1',
            'answers.*.content' => 'required|string',
            'answers.*.is_correct' => 'required|boolean',
            'answers.*.sub_content' => 'nullable|string',
            'answers.*.sort_order' => 'nullable|integer',
        ]);

        if (!empty($validated['question_group_id'])) {
            $group = QuestionGroup::findOrFail($validated['question_group_id']);
            if ($group->course_id !== $course->id) {
                return response()->json(['message' => 'Question group not found'], 404);
            }
        }

        DB::beginTransaction();
        try {
            $question = $question ?? new Question();
            $question->fill([
                'course_id' => $course->id,
                'question_bank_id' => $bank->id,
                'question_group_id' => $validated['question_group_id'] ?? null,
                'content' => $validated['content'],
                'type' => $validated['type'],
                'difficulty' => $validated['difficulty'] ?? 1,
                'explanation' => $validated['explanation'] ?? null,
            ]);
            $question->save();

            $question->answers()->delete();
            foreach ($validated['answers'] as $index => $aData) {
                $question->answers()->create([
                    'content' => $aData['content'],
                    'is_correct' => $aData['is_correct'],
                    'sub_content' => $aData['sub_content'] ?? null,
                    'sort_order' => $aData['sort_order'] ?? null,
                    'order' => $index,
                ]);
            }

            DB::commit();

            return response()->json($question->fresh()->load(['answers', 'group']), $request->isMethod('post') ? 201 : 200);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json(['message' => 'Error', 'error' => $e->getMessage()], 500);
        }
    }
}

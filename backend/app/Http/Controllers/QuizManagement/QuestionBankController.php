<?php

namespace App\Http\Controllers\QuizManagement;

use App\Http\Controllers\Controller;

use App\Models\Course;
use App\Models\QuestionBank;
use App\Models\QuestionGroup;
use App\Models\Question;
use App\Models\QuestionAttachment;
use App\Models\Answer;
use App\Services\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class QuestionBankController extends Controller
{
    /**
     * List all question banks across all courses (admin/instructor).
     * Returns each bank with its course info and difficulty distribution stats.
     */
    public function allBanks(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && ($user->hasRole('admin') || $user->hasRole('instructor')), 403);

        $query = QuestionBank::with('course:id,title')
            ->withCount('questions');

        if (!$user->hasRole('admin')) {
            $query->whereHas('course', fn ($q) => $q->where('user_id', $user->id));
        }

        $banks = $query->orderBy('created_at')->get()->map(function ($bank) {
            $distrib = DB::table('questions')
                ->where('question_bank_id', $bank->id)
                ->selectRaw('difficulty, count(*) as cnt')
                ->groupBy('difficulty')
                ->pluck('cnt', 'difficulty')
                ->mapWithKeys(fn ($v, $k) => [(string) $k => (int) $v]);
            $bank->difficulty_distribution = $distrib;
            return $bank;
        });

        return response()->json(['banks' => $banks]);
    }

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
            'groups.questions.attachments',
            'questions.answers',
            'questions.attachments',
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
            'code'              => 'nullable|string|max:50',
            'content'           => 'required|string',
            'type'              => 'required|string|in:single_choice,multiple_choice,true_false,essay,matching,ordering,short_answer,numerical',
            'difficulty'        => 'nullable|integer|min:1|max:5',
            'default_score'     => 'nullable|numeric|min:0',
            'explanation'       => 'nullable|string',
            'feedback'          => 'nullable|string',
            'general_feedback'  => 'nullable|string',
            'metadata'          => 'nullable|array',
            'answers'           => 'nullable|array',
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
                'course_id'         => $course->id,
                'question_bank_id'  => $bank->id,
                'question_group_id' => $validated['question_group_id'] ?? null,
                'code'              => $validated['code'] ?? null,
                'content'           => $validated['content'],
                'type'              => $validated['type'],
                'difficulty'        => $validated['difficulty'] ?? 1,
                'default_score'     => $validated['default_score'] ?? 1.00,
                'explanation'       => $validated['explanation'] ?? null,
                'feedback'          => $validated['feedback'] ?? null,
                'general_feedback'  => $validated['general_feedback'] ?? null,
                'metadata'          => $validated['metadata'] ?? null,
            ]);
            $question->save();

            // Auto-create answers for true_false type
            $question->answers()->delete();

            if ($validated['type'] === 'true_false') {
                $correctAnswer = collect($validated['answers'] ?? [])->firstWhere('is_correct', true);
                $correctIsTrue = $correctAnswer ? strtolower(trim($correctAnswer['content'])) !== 'sai' : true;

                $question->answers()->create([
                    'content'    => 'Đúng',
                    'is_correct' => $correctIsTrue,
                    'order'      => 0,
                ]);
                $question->answers()->create([
                    'content'    => 'Sai',
                    'is_correct' => !$correctIsTrue,
                    'order'      => 1,
                ]);
            } else {
                $answers = $validated['answers'] ?? [];
                foreach ($answers as $index => $aData) {
                    $question->answers()->create([
                        'content'     => $aData['content'],
                        'is_correct'  => $aData['is_correct'],
                        'sub_content' => $aData['sub_content'] ?? null,
                        'sort_order'  => $aData['sort_order'] ?? null,
                        'order'       => $index,
                    ]);
                }
            }

            DB::commit();

            return response()->json($question->fresh()->load(['answers', 'group', 'attachments']), $request->isMethod('post') ? 201 : 200);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json(['message' => 'Error', 'error' => $e->getMessage()], 500);
        }
    }

    /**
     * Upload a file attachment for a question.
     */
    public function uploadAttachment(Request $request, Course $course, QuestionBank $bank, Question $question, MediaService $mediaService): JsonResponse
    {
        if ($question->question_bank_id !== $bank->id || $question->course_id !== $course->id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $request->validate([
            'file' => ['required', 'file', 'max:10240'], // 10MB max
        ]);

        $file = $request->file('file');
        $uploaded = $mediaService->upload($file, 'questions');

        $attachment = QuestionAttachment::create([
            'question_id'   => $question->id,
            'original_name' => $file->getClientOriginalName(),
            'file_path'     => $uploaded['path'],
            'file_size'     => $this->formatFileSize($file->getSize()),
            'mime_type'     => $file->getMimeType(),
            'type'          => $this->detectAttachmentType($file->getMimeType()),
        ]);

        $attachment->url = $mediaService->getUrl($attachment->file_path);

        return response()->json($attachment, 201);
    }

    /**
     * Delete a question attachment.
     */
    public function destroyAttachment(Course $course, QuestionBank $bank, Question $question, QuestionAttachment $attachment, MediaService $mediaService): JsonResponse
    {
        if ($question->question_bank_id !== $bank->id || $question->course_id !== $course->id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        if ($attachment->question_id !== $question->id) {
            return response()->json(['message' => 'Attachment not found'], 404);
        }

        if ($mediaService->exists($attachment->file_path)) {
            $mediaService->delete($attachment->file_path);
        }

        $attachment->delete();

        return response()->json(['message' => 'Attachment deleted']);
    }

    private function formatFileSize(int $bytes): string
    {
        if ($bytes >= 1048576) {
            return round($bytes / 1048576, 1) . ' MB';
        }
        return round($bytes / 1024, 1) . ' KB';
    }

    private function detectAttachmentType(?string $mimeType): string
    {
        if (!$mimeType) return 'file';
        if (str_starts_with($mimeType, 'image/')) return 'image';
        if (str_starts_with($mimeType, 'audio/')) return 'audio';
        return 'file';
    }
}

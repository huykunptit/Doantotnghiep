<?php

namespace App\Http\Controllers\UserManagement;

use App\Http\Controllers\Controller;
use App\Models\Curriculum;
use App\Models\CurriculumCourse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CurriculumBuilderController extends Controller
{
    public function index(Request $request, Curriculum $curriculum): JsonResponse
    {
        if (!$request->user()?->hasAnyRole(['admin', 'academic_manager', 'instructor'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $items = $curriculum->curriculumCourses()
            ->with('course:id,title,slug,credit_value,course_mode')
            ->orderBy('term_number')
            ->orderBy('position')
            ->get();

        // Parse notes JSON để FE dùng được trực tiếp
        $items->transform(function ($cc) {
            $cc->parsed_notes = null;
            if (is_string($cc->notes) && $cc->notes !== '') {
                $decoded = json_decode($cc->notes, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $cc->parsed_notes = $decoded;
                }
            }
            return $cc;
        });

        $grouped = $items->groupBy('term_number');
        $totalCredits = $items->where('is_required', true)->sum('credits');
        $totalElectiveCredits = $items->where('is_required', false)->sum('credits');

        return response()->json([
            'curriculum' => $curriculum->load(['program:id,code,name', 'major:id,code,name']),
            'by_term' => $grouped,
            'summary' => [
                'total_subjects' => $items->count(),
                'required_subjects' => $items->where('is_required', true)->count(),
                'elective_subjects' => $items->where('is_required', false)->count(),
                'total_credits_required' => $totalCredits,
                'total_credits_elective' => $totalElectiveCredits,
                'term_count' => $grouped->keys()->max(),
            ],
        ]);
    }

    public function bulkUpsert(Request $request, Curriculum $curriculum): JsonResponse
    {
        if (!$request->user()?->hasAnyRole(['admin', 'academic_manager', 'instructor'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            '*.course_id' => ['required', 'integer', 'exists:courses,id'],
            '*.term_number' => ['required', 'integer', 'min:1', 'max:12'],
            '*.is_required' => ['nullable', 'boolean'],
            '*.credits' => ['nullable', 'integer', 'min:0'],
            '*.position' => ['nullable', 'integer', 'min:0'],
            '*.notes' => ['nullable', 'string'],
        ]);

        foreach ($validated as $index => $item) {
            CurriculumCourse::updateOrCreate(
                [
                    'curriculum_id' => $curriculum->id,
                    'course_id' => $item['course_id'],
                ],
                [
                    'term_number' => $item['term_number'],
                    'is_required' => (bool) ($item['is_required'] ?? true),
                    'credits' => $item['credits'] ?? null,
                    'position' => $item['position'] ?? $index,
                    'notes' => $item['notes'] ?? null,
                ],
            );
        }

        return response()->json(['message' => 'Đã cập nhật khung chương trình.']);
    }

    public function destroy(Request $request, Curriculum $curriculum, CurriculumCourse $curriculumCourse): JsonResponse
    {
        if (!$request->user()?->hasAnyRole(['admin', 'academic_manager', 'instructor'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($curriculumCourse->curriculum_id !== $curriculum->id) {
            return response()->json(['message' => 'Curriculum course not found'], 404);
        }

        $curriculumCourse->delete();

        return response()->json(['message' => 'Đã xóa môn khỏi khung chương trình.']);
    }
}


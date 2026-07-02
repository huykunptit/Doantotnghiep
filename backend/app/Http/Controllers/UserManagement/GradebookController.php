<?php

namespace App\Http\Controllers\UserManagement;

use App\Concerns\ScopesByUnit;
use App\Helpers\GpaCalculator;
use App\Http\Controllers\Controller;
use App\Models\ClassSection;
use App\Models\Enrollment;
use App\Models\GradeComponent;
use App\Models\GradeEntry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Instructor-side gradebook for a single class section.
 * Routes:
 *  GET  /instructor/sections/{classSection}/grades
 *  PUT  /instructor/sections/{classSection}/grades
 *  POST /instructor/courses/{course}/grade-components  (manage component template)
 */
class GradebookController extends Controller
{
    use ScopesByUnit;

    public function show(Request $request, ClassSection $classSection): JsonResponse
    {
        $user = $request->user();
        if (!$this->canAccess($user, $classSection)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $components = GradeComponent::query()
            ->where('course_id', $classSection->course_id)
            ->orderBy('position')
            ->get();

        $enrollments = Enrollment::query()
            ->where('class_section_id', $classSection->id)
            ->with([
                'user:id,name,email,student_code,cohort_id',
                'gradeEntries' => fn ($q) => $q->select('id', 'enrollment_id', 'grade_component_id', 'score', 'graded_by', 'graded_at', 'note'),
            ])
            ->orderBy('id')
            ->get()
            ->map(function (Enrollment $enrollment) use ($components) {
                $byComponent = $enrollment->gradeEntries->keyBy('grade_component_id');
                return [
                    'enrollment_id' => $enrollment->id,
                    'student' => $enrollment->user,
                    'final_score' => $enrollment->final_score,
                    'entries' => $components->map(fn ($c) => [
                        'grade_component_id' => $c->id,
                        'score' => $byComponent->get($c->id)?->score,
                        'note' => $byComponent->get($c->id)?->note,
                        'graded_at' => $byComponent->get($c->id)?->graded_at,
                    ])->values(),
                ];
            })->values();

        return response()->json([
            'class_section' => $classSection->load(['course:id,title,course_mode', 'term:id,name,code', 'cohort:id,name,code']),
            'components' => $components,
            'students' => $enrollments,
        ]);
    }

    public function update(Request $request, ClassSection $classSection): JsonResponse
    {
        $user = $request->user();
        if (!$this->canAccess($user, $classSection)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'entries' => ['required', 'array', 'min:1'],
            'entries.*.enrollment_id' => ['required', 'integer', 'exists:enrollments,id'],
            'entries.*.grade_component_id' => ['required', 'integer', 'exists:grade_components,id'],
            'entries.*.score' => ['nullable', 'numeric', 'min:0'],
            'entries.*.note' => ['nullable', 'string'],
        ]);

        // Belt-and-braces: only allow entries that belong to this section's enrollments + course components.
        $validEnrollmentIds = Enrollment::where('class_section_id', $classSection->id)->pluck('id')->all();
        $validComponentIds = GradeComponent::where('course_id', $classSection->course_id)->pluck('id')->all();

        $written = 0;
        DB::transaction(function () use ($validated, $validEnrollmentIds, $validComponentIds, $user, &$written) {
            foreach ($validated['entries'] as $row) {
                if (!in_array($row['enrollment_id'], $validEnrollmentIds, true)) continue;
                if (!in_array($row['grade_component_id'], $validComponentIds, true)) continue;

                GradeEntry::updateOrCreate(
                    [
                        'enrollment_id' => $row['enrollment_id'],
                        'grade_component_id' => $row['grade_component_id'],
                    ],
                    [
                        'score' => $row['score'] ?? null,
                        'note' => $row['note'] ?? null,
                        'graded_by' => $user->id,
                        'graded_at' => now(),
                    ],
                );
                $written++;
            }
        });

        return response()->json(['message' => 'Đã lưu điểm', 'written' => $written]);
    }

    public function listComponents(Request $request, int $courseId): JsonResponse
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthorized'], 401);

        $components = GradeComponent::query()
            ->where('course_id', $courseId)
            ->orderBy('position')
            ->get();

        return response()->json($components);
    }

    public function upsertComponents(Request $request, int $courseId): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->hasAnyRole(['admin', 'instructor'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'components' => ['required', 'array', 'min:1'],
            'components.*.id' => ['nullable', 'integer'],
            'components.*.name' => ['required', 'string', 'max:150'],
            'components.*.weight' => ['required', 'numeric', 'min:0', 'max:100'],
            'components.*.max_score' => ['required', 'numeric', 'min:0'],
            'components.*.position' => ['nullable', 'integer'],
            'components.*.note' => ['nullable', 'string'],
        ]);

        $kept = [];
        DB::transaction(function () use ($validated, $courseId, &$kept) {
            foreach ($validated['components'] as $idx => $row) {
                $payload = [
                    'course_id' => $courseId,
                    'name' => $row['name'],
                    'weight' => $row['weight'],
                    'max_score' => $row['max_score'],
                    'position' => $row['position'] ?? $idx,
                    'note' => $row['note'] ?? null,
                ];
                if (!empty($row['id'])) {
                    $component = GradeComponent::find($row['id']);
                    if ($component && $component->course_id === $courseId) {
                        $component->update($payload);
                        $kept[] = $component->id;
                        continue;
                    }
                }
                $created = GradeComponent::create($payload);
                $kept[] = $created->id;
            }

            // Delete components not present in the payload.
            GradeComponent::where('course_id', $courseId)
                ->whereNotIn('id', $kept)
                ->delete();
        });

        return response()->json([
            'message' => 'Đã cập nhật cấu trúc điểm',
            'components' => GradeComponent::where('course_id', $courseId)->orderBy('position')->get(),
        ]);
    }

    public function presetComponents(Request $request, int $courseId): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->hasAnyRole(['admin', 'instructor'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Only apply preset if no components exist yet
        $existing = GradeComponent::where('course_id', $courseId)->count();
        if ($existing > 0) {
            return response()->json([
                'message'    => 'Khóa học đã có cấu trúc điểm, dùng API upsert để chỉnh sửa.',
                'components' => GradeComponent::where('course_id', $courseId)->orderBy('position')->get(),
            ], 422);
        }

        $presets = [
            ['name' => 'Chuyên cần',    'weight' => 10, 'max_score' => 10, 'position' => 1],
            ['name' => 'Giữa kỳ',       'weight' => 20, 'max_score' => 10, 'position' => 2],
            ['name' => 'Kiểm tra/BTL',  'weight' => 20, 'max_score' => 10, 'position' => 3],
            ['name' => 'Cuối kỳ',       'weight' => 50, 'max_score' => 10, 'position' => 4],
        ];

        DB::transaction(function () use ($courseId, $presets) {
            foreach ($presets as $p) {
                GradeComponent::create(['course_id' => $courseId, ...$p]);
            }
        });

        return response()->json([
            'message'    => 'Đã áp dụng cấu trúc điểm mặc định.',
            'components' => GradeComponent::where('course_id', $courseId)->orderBy('position')->get(),
        ], 201);
    }

    public function sectionGpaReport(Request $request, ClassSection $classSection): JsonResponse
    {
        $user = $request->user();
        if (!$this->canAccess($user, $classSection)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $components = GradeComponent::where('course_id', $classSection->course_id)
            ->orderBy('position')->get();

        $enrollments = Enrollment::where('class_section_id', $classSection->id)
            ->with([
                'user:id,name,email,student_code',
                'gradeEntries.component',
            ])
            ->orderBy('id')
            ->get();

        $rows = $enrollments->map(function (Enrollment $enrollment) use ($components) {
            $finalScore = $enrollment->final_score;
            $gradeInfo  = $finalScore !== null
                ? GpaCalculator::gradeInfo((float) $finalScore)
                : ['letter' => null, 'gpa4' => null];

            $byComponent = $enrollment->gradeEntries->keyBy('grade_component_id');

            return [
                'enrollment_id' => $enrollment->id,
                'student'       => $enrollment->user,
                'entries'       => $components->map(fn ($c) => [
                    'component_id'   => $c->id,
                    'component_name' => $c->name,
                    'weight'         => $c->weight,
                    'max_score'      => $c->max_score,
                    'score'          => $byComponent->get($c->id)?->score,
                ])->values(),
                'final_score'  => $finalScore,
                'letter_grade' => $gradeInfo['letter'],
                'gpa4'         => $gradeInfo['gpa4'],
            ];
        })->values();

        $passCount = $rows->filter(fn ($r) => $r['gpa4'] !== null && $r['gpa4'] >= 1.0)->count();
        $avg       = $rows->where('final_score', '!==', null)->avg('final_score');

        return response()->json([
            'class_section' => $classSection->load(['course:id,title', 'term:id,name,code']),
            'components'    => $components,
            'students'      => $rows,
            'summary'       => [
                'total'      => $rows->count(),
                'passed'     => $passCount,
                'failed'     => $rows->count() - $passCount,
                'avg_score'  => $avg !== null ? round($avg, 2) : null,
                'class_gpa'  => GpaCalculator::cumulativeGpa(
                    $rows->map(fn ($r) => [
                        'final_score'  => $r['final_score'],
                        'credit_value' => $classSection->course->credit_value ?? 3,
                    ])->all()
                ),
            ],
        ]);
    }

    private function canAccess($user, ClassSection $classSection): bool
    {
        if (!$user) return false;
        if ($user->hasRole('admin')) return true;
        if ($user->hasAnyRole(['instructor', 'academic_manager'])) {
            // Instructor can grade only sections they teach.
            if ($user->hasRole('instructor') && $classSection->lecturer_id !== $user->id) {
                return false;
            }
            return true;
        }
        return false;
    }
}

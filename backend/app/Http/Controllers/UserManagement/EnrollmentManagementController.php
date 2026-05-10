<?php

namespace App\Http\Controllers\UserManagement;

use App\Concerns\ScopesByUnit;
use App\Http\Controllers\Controller;
use App\Models\ClassSection;
use App\Models\Cohort;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Notification;
use App\Models\Term;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EnrollmentManagementController extends Controller
{
    use ScopesByUnit;

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (!$user || !$user->hasAnyRole(['admin', 'instructor'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $query = Enrollment::query()
            ->with([
                'user:id,name,email,student_code,cohort_id',
                'course:id,title,course_mode,program_id,major_id',
                'term:id,name,code',
                'cohort:id,name,code',
                'classSection:id,code,lecturer_id,term_id',
            ])
            ->latest('id');

        if ($request->filled('term_id')) {
            $query->where('term_id', (int) $request->input('term_id'));
        }
        if ($request->filled('cohort_id')) {
            $query->where('cohort_id', (int) $request->input('cohort_id'));
        }
        if ($request->filled('course_id')) {
            $query->where('course_id', (int) $request->input('course_id'));
        }
        if ($request->filled('class_section_id')) {
            $query->where('class_section_id', (int) $request->input('class_section_id'));
        }
        if ($request->filled('source')) {
            $query->where('enrollment_source', $request->input('source'));
        }
        if ($request->filled('user_id')) {
            $query->where('user_id', (int) $request->input('user_id'));
        }

        $query = $this->scopeEnrollmentsByUnits($query, $user);

        $perPage = max(1, min(100, (int) $request->integer('per_page', 20)));

        return response()->json($query->paginate($perPage));
    }

    public function bulkEnrollCore(Request $request, Cohort $cohort): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (!$user || !$user->hasRole('admin')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'term_id' => ['required', 'integer', 'exists:terms,id'],
            'curriculum_id' => ['nullable', 'integer', 'exists:curricula,id'],
            'course_ids' => ['nullable', 'array'],
            'course_ids.*' => ['integer', 'exists:courses,id'],
        ]);

        $term = Term::findOrFail($validated['term_id']);

        $studentIds = User::query()
            ->where('cohort_id', $cohort->id)
            ->pluck('id');

        if ($studentIds->isEmpty()) {
            return response()->json([
                'message' => 'Cohort has no students',
                'cohort_id' => $cohort->id,
                'created' => 0,
                'skipped' => 0,
            ]);
        }

        $coursesQuery = Course::query()
            ->where('course_mode', 'core')
            ->where('status', 'published')
            ->where(function (Builder $q) use ($cohort) {
                $q->where('program_id', $cohort->program_id)
                  ->orWhereNull('program_id');
            });

        if ($cohort->major_id) {
            $coursesQuery->where(function (Builder $q) use ($cohort) {
                $q->where('major_id', $cohort->major_id)->orWhereNull('major_id');
            });
        }

        if (!empty($validated['curriculum_id'])) {
            $coursesQuery->where(function (Builder $q) use ($validated) {
                $q->where('curriculum_id', $validated['curriculum_id'])->orWhereNull('curriculum_id');
            });
        }

        if (!empty($validated['course_ids'])) {
            $coursesQuery->whereIn('id', $validated['course_ids']);
        }

        $courses = $coursesQuery->get(['id', 'title', 'course_mode']);

        if ($courses->isEmpty()) {
            return response()->json([
                'message' => 'No core courses match this cohort',
                'cohort_id' => $cohort->id,
                'created' => 0,
                'skipped' => 0,
            ]);
        }

        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($studentIds, $courses, $cohort, $term, &$created, &$skipped) {
            foreach ($courses as $course) {
                foreach ($studentIds as $studentId) {
                    $existing = Enrollment::where('user_id', $studentId)
                        ->where('course_id', $course->id)
                        ->first();

                    if ($existing) {
                        $skipped++;
                        continue;
                    }

                    Enrollment::create([
                        'user_id' => $studentId,
                        'course_id' => $course->id,
                        'term_id' => $term->id,
                        'cohort_id' => $cohort->id,
                        'enrollment_source' => 'academic',
                        'enrolled_at' => now(),
                    ]);
                    $created++;
                }
            }
        });

        return response()->json([
            'message' => 'Bulk enrollment processed',
            'cohort_id' => $cohort->id,
            'term_id' => $term->id,
            'students' => $studentIds->count(),
            'courses' => $courses->count(),
            'created' => $created,
            'skipped' => $skipped,
        ]);
    }

    public function students(Request $request, Cohort $cohort): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (!$user || !$user->hasAnyRole(['admin', 'instructor'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $students = User::query()
            ->where('cohort_id', $cohort->id)
            ->select('id', 'name', 'email', 'student_code', 'cohort_id', 'major_id', 'advisor_id')
            ->orderBy('student_code')
            ->paginate(50);

        return response()->json($students);
    }

    public function classSections(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (!$user || !$user->hasAnyRole(['admin', 'instructor'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $query = ClassSection::query()
            ->with([
                'course:id,title,course_mode,program_id',
                'term:id,name,code',
                'cohort:id,name,code',
                'lecturer:id,name,email',
            ])
            ->latest('id');

        if ($request->filled('term_id')) {
            $query->where('term_id', (int) $request->input('term_id'));
        }
        if ($request->filled('cohort_id')) {
            $query->where('cohort_id', (int) $request->input('cohort_id'));
        }
        if ($request->filled('course_id')) {
            $query->where('course_id', (int) $request->input('course_id'));
        }
        if ($request->filled('lecturer_id')) {
            $query->where('lecturer_id', (int) $request->input('lecturer_id'));
        }

        if (!$user->hasRole('admin')) {
            $activeUnitIds = $this->activeUnitIdsFor($user);
            if ($activeUnitIds->isEmpty()) {
                $query->whereRaw('1=0');
            } else {
                $query->where(function (Builder $q) use ($activeUnitIds, $user) {
                    $q->where('lecturer_id', $user->id)
                      ->orWhereHas('course', function (Builder $c) use ($activeUnitIds) {
                          $c->whereHas('program', fn (Builder $p) => $p->whereIn('unit_id', $activeUnitIds))
                            ->orWhereHas('major', fn (Builder $m) => $m->whereIn('unit_id', $activeUnitIds));
                      });
                });
            }
        }

        $perPage = max(1, min(100, (int) $request->integer('per_page', 20)));

        return response()->json($query->paginate($perPage));
    }

    public function storeClassSection(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (!$user || !$user->hasRole('admin')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'term_id' => ['nullable', 'integer', 'exists:terms,id'],
            'cohort_id' => ['nullable', 'integer', 'exists:cohorts,id'],
            'lecturer_id' => ['nullable', 'integer', 'exists:users,id'],
            'code' => ['required', 'string', 'max:100'],
            'name' => ['nullable', 'string', 'max:255'],
            'capacity' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', 'string', 'in:planned,open,closed,cancelled'],
            'description' => ['nullable', 'string'],
        ]);

        $section = ClassSection::create($validated);

        return response()->json($section->load(['course:id,title', 'term:id,name', 'cohort:id,name', 'lecturer:id,name']), 201);
    }

    public function updateClassSection(Request $request, ClassSection $classSection): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (!$user || !$user->hasRole('admin')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'term_id' => ['nullable', 'integer', 'exists:terms,id'],
            'cohort_id' => ['nullable', 'integer', 'exists:cohorts,id'],
            'lecturer_id' => ['nullable', 'integer', 'exists:users,id'],
            'code' => ['sometimes', 'string', 'max:100'],
            'name' => ['nullable', 'string', 'max:255'],
            'capacity' => ['nullable', 'integer', 'min:0'],
            'status' => ['sometimes', 'string', 'in:planned,open,closed,cancelled'],
            'description' => ['nullable', 'string'],
        ]);

        $classSection->fill($validated)->save();

        return response()->json($classSection->load(['course:id,title', 'term:id,name', 'cohort:id,name', 'lecturer:id,name']));
    }

    public function destroyClassSection(Request $request, ClassSection $classSection): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (!$user || !$user->hasRole('admin')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $classSection->delete();

        return response()->json(['message' => 'Class section deleted']);
    }
}

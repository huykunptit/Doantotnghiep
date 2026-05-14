<?php

namespace App\Http\Controllers\UserManagement;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\AdministrativeClass;
use App\Models\Cohort;
use App\Models\CourseLearningOutcome;
use App\Models\Curriculum;
use App\Models\CurriculumCourse;
use App\Models\Institution;
use App\Models\Major;
use App\Models\Program;
use App\Models\ProgramLearningOutcome;
use App\Models\ProgramType;
use App\Models\Skill;
use App\Models\Term;
use App\Models\Unit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AcademicManagementController extends Controller
{
    private const RESOURCE_MODELS = [
        'institutions' => Institution::class,
        'units' => Unit::class,
        'program-types' => ProgramType::class,
        'academic-years' => AcademicYear::class,
        'terms' => Term::class,
        'programs' => Program::class,
        'majors' => Major::class,
        'curricula' => Curriculum::class,
        'curriculum-courses' => CurriculumCourse::class,
        'cohorts' => Cohort::class,
        'administrative-classes' => AdministrativeClass::class,
        'plos' => ProgramLearningOutcome::class,
        'clos' => CourseLearningOutcome::class,
        'skills' => Skill::class,
    ];

    public function index(Request $request, string $resource): JsonResponse
    {
        $modelClass = $this->resolveModelClass($resource);
        if (!$modelClass) {
            return response()->json(['message' => 'Resource not found'], 404);
        }

        $user = $request->user();
        if (!$user || !$user->hasAnyRole(['admin', 'academic_manager'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $query = $modelClass::query()->latest('id');
        $query = $this->applyScope($query, $resource, $user->id, $user->hasRole('admin'));
        $query = $this->applyEagerLoad($query, $resource);
        $query = $this->applyFilters($query, $resource, $request);

        return response()->json($query->paginate((int) $request->integer('per_page', 20)));
    }

    /**
     * Eager-load relations để list view có đủ dữ liệu hiển thị (tránh N+1).
     */
    private function applyEagerLoad($query, string $resource)
    {
        return match ($resource) {
            'curricula' => $query
                ->with(['program:id,code,name', 'major:id,code,name'])
                ->withCount('curriculumCourses'),
            'administrative-classes' => $query->with([
                'program:id,code,name',
                'unit:id,code,name',
                'cohort:id,code,name,start_year',
                'advisor:id,name,email,staff_code',
            ])->withCount('students'),
            'cohorts' => $query->with(['program:id,code,name', 'major:id,code,name']),
            'majors' => $query->with(['program:id,code,name']),
            'programs' => $query->with(['unit:id,code,name', 'programType:id,code,name']),
            default => $query,
        };
    }

    private function applyFilters($query, string $resource, Request $request)
    {
        if ($resource === 'curricula') {
            if ($pid = $request->integer('program_id')) {
                $query->where('program_id', $pid);
            }
            if ($mid = $request->integer('major_id')) {
                $query->where('major_id', $mid);
            }
        }
        if ($resource === 'administrative-classes') {
            foreach (['program_id', 'unit_id', 'cohort_id', 'major_id', 'advisor_id'] as $k) {
                if ($v = $request->integer($k)) {
                    $query->where($k, $v);
                }
            }
            if ($s = $request->string('status')->toString()) {
                $query->where('status', $s);
            }
            if ($q = $request->string('q')->toString()) {
                $query->where(fn ($w) => $w->where('code', 'like', "%{$q}%")->orWhere('name', 'like', "%{$q}%"));
            }
        }
        return $query;
    }

    public function store(Request $request, string $resource): JsonResponse
    {
        $modelClass = $this->resolveModelClass($resource);
        if (!$modelClass) {
            return response()->json(['message' => 'Resource not found'], 404);
        }

        $user = $request->user();
        if (!$user || !$user->hasAnyRole(['admin', 'academic_manager'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validate($this->validationRules($resource, false));
        /** @var Model $record */
        $record = $modelClass::query()->create($data);

        return response()->json($record, 201);
    }

    public function update(Request $request, string $resource, int $id): JsonResponse
    {
        $modelClass = $this->resolveModelClass($resource);
        if (!$modelClass) {
            return response()->json(['message' => 'Resource not found'], 404);
        }

        $user = $request->user();
        if (!$user || !$user->hasAnyRole(['admin', 'academic_manager'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $query = $modelClass::query()->whereKey($id);
        $query = $this->applyScope($query, $resource, $user->id, true);
        $record = $query->first();
        if (!$record) {
            return response()->json(['message' => 'Record not found'], 404);
        }

        $data = $request->validate($this->validationRules($resource, true, $id));
        $record->fill($data)->save();

        return response()->json($record);
    }

    public function destroy(Request $request, string $resource, int $id): JsonResponse
    {
        $modelClass = $this->resolveModelClass($resource);
        if (!$modelClass) {
            return response()->json(['message' => 'Resource not found'], 404);
        }

        $user = $request->user();
        if (!$user || !$user->hasAnyRole(['admin', 'academic_manager'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $query = $modelClass::query()->whereKey($id);
        $query = $this->applyScope($query, $resource, $user->id, true);
        $record = $query->first();
        if (!$record) {
            return response()->json(['message' => 'Record not found'], 404);
        }

        $record->delete();

        return response()->json(['message' => 'Deleted']);
    }

    private function resolveModelClass(string $resource): ?string
    {
        return self::RESOURCE_MODELS[$resource] ?? null;
    }

    private function applyScope(Builder $query, string $resource, int $userId, bool $isAdmin): Builder
    {
        if ($isAdmin) {
            return $query;
        }

        $activeUnitIds = \App\Models\UserAssignment::query()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->pluck('unit_id')
            ->values();

        if ($activeUnitIds->isEmpty()) {
            return $query->whereRaw('1=0');
        }

        return match ($resource) {
            'units' => $query->where(function (Builder $q) use ($activeUnitIds) {
                $q->whereIn('id', $activeUnitIds)->orWhereIn('parent_id', $activeUnitIds);
            }),
            'programs', 'majors', 'administrative-classes' => $query->whereIn('unit_id', $activeUnitIds),
            'curricula', 'cohorts' => $query->whereHas('major', fn (Builder $q) => $q->whereIn('unit_id', $activeUnitIds)),
            'curriculum-courses' => $query->whereHas('curriculum.major', fn (Builder $q) => $q->whereIn('unit_id', $activeUnitIds)),
            'institutions' => $query->whereIn('id', function ($sub) use ($activeUnitIds) {
                $sub->select('institution_id')->from('units')->whereIn('id', $activeUnitIds);
            }),
            'academic-years' => $query->whereIn('institution_id', function ($sub) use ($activeUnitIds) {
                $sub->select('institution_id')->from('units')->whereIn('id', $activeUnitIds);
            }),
            'terms' => $query->whereHas('academicYear', fn (Builder $q) => $q->whereIn('institution_id', function ($sub) use ($activeUnitIds) {
                $sub->select('institution_id')->from('units')->whereIn('id', $activeUnitIds);
            })),
            default => $query,
        };
    }

    private function validationRules(string $resource, bool $isUpdate, ?int $id = null): array
    {
        $required = $isUpdate ? 'sometimes' : 'required';
        $uniqueSuffix = $id ? ',' . $id : '';

        return match ($resource) {
            'institutions' => [
                'name' => [$required, 'string', 'max:255'],
                'code' => [$required, 'string', 'max:100', 'unique:institutions,code' . $uniqueSuffix],
                'institution_type' => ['sometimes', 'string', 'max:100'],
                'is_active' => ['sometimes', 'boolean'],
            ],
            'units' => [
                'institution_id' => [$required, 'exists:institutions,id'],
                'parent_id' => ['nullable', 'exists:units,id'],
                'name' => [$required, 'string', 'max:255'],
                'code' => [$required, 'string', 'max:100'],
                'unit_type' => [$required, 'string', 'max:100'],
                'level' => ['sometimes', 'integer', 'min:1'],
                'is_active' => ['sometimes', 'boolean'],
            ],
            'program-types' => [
                'name' => [$required, 'string', 'max:255'],
                'code' => [$required, 'string', 'max:100', 'unique:program_types,code' . $uniqueSuffix],
                'description' => ['nullable', 'string'],
                'is_active' => ['sometimes', 'boolean'],
            ],
            'academic-years' => [
                'institution_id' => [$required, 'exists:institutions,id'],
                'name' => [$required, 'string', 'max:100'],
                'start_date' => [$required, 'date'],
                'end_date' => [$required, 'date', 'after:start_date'],
                'is_current' => ['sometimes', 'boolean'],
                'status' => ['sometimes', 'string', 'max:50'],
            ],
            'terms' => [
                'academic_year_id' => [$required, 'exists:academic_years,id'],
                'name' => [$required, 'string', 'max:100'],
                'code' => [$required, 'string', 'max:100'],
                'start_date' => [$required, 'date'],
                'end_date' => [$required, 'date', 'after:start_date'],
                'enrollment_start_at' => ['nullable', 'date'],
                'enrollment_end_at' => ['nullable', 'date', 'after:enrollment_start_at'],
                'exam_start_at' => ['nullable', 'date'],
                'exam_end_at' => ['nullable', 'date', 'after:exam_start_at'],
                'is_current' => ['sometimes', 'boolean'],
                'status' => ['sometimes', 'string', 'max:50'],
            ],
            'programs' => [
                'institution_id' => [$required, 'exists:institutions,id'],
                'unit_id' => ['nullable', 'exists:units,id'],
                'program_type_id' => [$required, 'exists:program_types,id'],
                'name' => [$required, 'string', 'max:255'],
                'code' => [$required, 'string', 'max:100'],
                'duration_months' => ['nullable', 'integer', 'min:1'],
                'is_active' => ['sometimes', 'boolean'],
            ],
            'majors' => [
                'program_id' => [$required, 'exists:programs,id'],
                'unit_id' => ['nullable', 'exists:units,id'],
                'name' => [$required, 'string', 'max:255'],
                'code' => [$required, 'string', 'max:100'],
                'is_active' => ['sometimes', 'boolean'],
            ],
            'curricula' => [
                'program_id' => [$required, 'exists:programs,id'],
                'major_id' => ['nullable', 'exists:majors,id'],
                'specialization_id' => ['nullable', 'exists:specializations,id'],
                'name' => [$required, 'string', 'max:255'],
                'code' => [$required, 'string', 'max:100'],
                'effective_from' => ['nullable', 'date'],
                'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
                'is_active' => ['sometimes', 'boolean'],
            ],
            'curriculum-courses' => [
                'curriculum_id' => [$required, 'exists:curricula,id'],
                'course_id' => [$required, 'exists:courses,id'],
                'term_number' => [$required, 'integer', 'min:1', 'max:12'],
                'is_required' => ['sometimes', 'boolean'],
                'credits' => ['nullable', 'integer', 'min:0'],
                'position' => ['nullable', 'integer', 'min:0'],
                'notes' => ['nullable', 'string'],
            ],
            'cohorts' => [
                'institution_id' => [$required, 'exists:institutions,id'],
                'program_id' => [$required, 'exists:programs,id'],
                'major_id' => ['nullable', 'exists:majors,id'],
                'name' => [$required, 'string', 'max:255'],
                'code' => [$required, 'string', 'max:100'],
                'start_year' => [$required, 'integer', 'min:2000'],
                'end_year' => ['nullable', 'integer', 'gte:start_year'],
                'status' => ['sometimes', 'string', 'max:50'],
            ],
            'administrative-classes' => [
                'institution_id' => [$required, 'exists:institutions,id'],
                'unit_id' => [$required, 'exists:units,id'],
                'program_id' => [$required, 'exists:programs,id'],
                'major_id' => ['nullable', 'exists:majors,id'],
                'cohort_id' => [$required, 'exists:cohorts,id'],
                'advisor_id' => ['nullable', 'exists:users,id'],
                'code' => [$required, 'string', 'max:100'],
                'name' => [$required, 'string', 'max:255'],
                'expected_graduation_year' => ['nullable', 'integer', 'min:2000'],
                'capacity' => ['nullable', 'integer', 'min:1'],
                'status' => ['sometimes', 'string', 'max:50'],
                'description' => ['nullable', 'string'],
            ],
            'plos' => [
                'program_id' => [$required, 'exists:programs,id'],
                'code' => [$required, 'string', 'max:32'],
                'description' => [$required, 'string'],
                'level' => ['sometimes', 'string', 'in:knowledge,skill,attitude'],
                'position' => ['sometimes', 'integer', 'min:0'],
            ],
            'clos' => [
                'course_id' => [$required, 'exists:courses,id'],
                'code' => [$required, 'string', 'max:32'],
                'description' => [$required, 'string'],
                'position' => ['sometimes', 'integer', 'min:0'],
            ],
            'skills' => [
                'code' => [$required, 'string', 'max:64', 'unique:skills,code' . $uniqueSuffix],
                'name' => [$required, 'string', 'max:150'],
                'category' => ['nullable', 'string', 'max:64'],
                'description' => ['nullable', 'string'],
            ],
            default => [],
        };
    }
}

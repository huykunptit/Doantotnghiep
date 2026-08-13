<?php

namespace App\Http\Controllers\UserManagement;

use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\AdministrativeClass;
use App\Models\ClassSchedule;
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
        'class-schedules' => ClassSchedule::class,
        'plos' => ProgramLearningOutcome::class,
        'clos' => CourseLearningOutcome::class,
        'skills' => Skill::class,
        'exam-enrollments' => \App\Models\ExamEnrollment::class,
    ];

    public function index(Request $request, string $resource): JsonResponse
    {
        $modelClass = $this->resolveModelClass($resource);
        if (!$modelClass) {
            return response()->json(['message' => 'Resource not found'], 404);
        }

        $user = $request->user();
        if (!$user || !\App\Support\Authorize::allows($user, ['manage_academic', 'manage_enrollments', 'manage_courses'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $query = $modelClass::query()->latest('id');
        $query = $this->applyScope($query, $resource, $user->id, \App\Support\Authorize::isAdmin($user));
        $query = $this->applyEagerLoad($query, $resource);
        $query = $this->applyFilters($query, $resource, $request);
        $query = $this->applyDefaultOrder($query, $resource);

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
                'cohort:id,code,name,start_year,end_year',
                'advisor:id,name,email,staff_code',
                'curriculum:id,name,code',
            ])->withCount('students'),
            'terms' => $query->with(['academicYear:id,name']),
            'exam-enrollments' => $query->with([
                'exam:id,title,type',
                'user:id,name,email,student_code',
                'enrolledByUser:id,name',
            ]),
            'cohorts' => $query->with(['program:id,code,name', 'major:id,code,name']),
            'majors' => $query->with(['program:id,code,name']),
            'programs' => $query->with(['unit:id,code,name', 'programType:id,code,name']),
            'class-schedules' => $query->with([
                'administrativeClass:id,code,name',
                'course:id,title',
                'term:id,name,code',
                'lecturer:id,name,email',
            ]),
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
        if ($resource === 'terms') {
            if ($yid = $request->integer('academic_year_id')) {
                $query->where('academic_year_id', $yid);
            }
        }
        if ($resource === 'administrative-classes') {
            foreach (['program_id', 'unit_id', 'cohort_id', 'major_id', 'advisor_id'] as $k) {
                $vals = $request->input($k);
                if (is_array($vals) && count($vals)) {
                    $query->whereIn($k, array_map('intval', $vals));
                } elseif ($v = $request->integer($k)) {
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
        if ($resource === 'class-schedules') {
            if ($cid = $request->integer('administrative_class_id')) {
                $query->where('administrative_class_id', $cid);
            }
            if ($tid = $request->integer('term_id')) {
                $query->where('term_id', $tid);
            }
        }
        return $query;
    }

    private function applyDefaultOrder($query, string $resource)
    {
        return match ($resource) {
            'academic-years' => $query->reorder()->orderBy('start_date')->orderBy('id'),
            'terms' => $query->reorder()->orderBy('academic_year_id')->orderBy('code')->orderBy('id'),
            default => $query,
        };
    }

    public function store(Request $request, string $resource): JsonResponse
    {
        $modelClass = $this->resolveModelClass($resource);
        if (!$modelClass) {
            return response()->json(['message' => 'Resource not found'], 404);
        }

        $user = $request->user();
        if (!$user || !\App\Support\Authorize::allows($user, ['manage_academic', 'manage_enrollments', 'manage_courses'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = $request->validate($this->validationRules($resource, false));

        // Normalize nullable integer fields that have NOT NULL + default in DB
        if ($resource === 'administrative-classes') {
            $data['capacity'] = $data['capacity'] ?? 0;
        }

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
        if (!$user || !\App\Support\Authorize::allows($user, ['manage_academic', 'manage_enrollments', 'manage_courses'])) {
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
        if (!$user || !\App\Support\Authorize::allows($user, ['manage_academic', 'manage_enrollments', 'manage_courses'])) {
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

        // Năm học / học kỳ / cơ sở: GV có manage_academic được xem toàn bộ (không phụ thuộc unit).
        if (in_array($resource, ['institutions', 'academic-years', 'terms', 'program-types'], true)) {
            return $query;
        }

        $activeUnitIds = \App\Models\UserAssignment::query()
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->pluck('unit_id')
            ->values();

        if ($activeUnitIds->isEmpty()) {
            // Vẫn cho xem lớp HC / chương trình toàn trường nếu không gán unit (demo GV).
            if (in_array($resource, ['programs', 'majors', 'administrative-classes', 'cohorts', 'curricula'], true)) {
                return $query;
            }

            return $query->whereRaw('1=0');
        }

        return match ($resource) {
            'units' => $query->where(function (Builder $q) use ($activeUnitIds) {
                $q->whereIn('id', $activeUnitIds)->orWhereIn('parent_id', $activeUnitIds);
            }),
            'programs', 'majors', 'administrative-classes' => $query->whereIn('unit_id', $activeUnitIds),
            'curricula', 'cohorts' => $query->whereHas('major', fn (Builder $q) => $q->whereIn('unit_id', $activeUnitIds))
                ->orWhereHas('program', fn (Builder $q) => $q->whereIn('unit_id', $activeUnitIds)),
            'curriculum-courses' => $query->whereHas('curriculum.program', fn (Builder $q) => $q->whereIn('unit_id', $activeUnitIds)),
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
                'curriculum_id' => ['nullable', 'exists:curricula,id'],
                'code' => [$required, 'string', 'max:100'],
                'name' => [$required, 'string', 'max:255'],
                'expected_graduation_year' => ['nullable', 'integer', 'min:2000'],
                'capacity' => ['nullable', 'integer', 'min:1'],
                'status' => ['sometimes', 'string', 'max:50'],
                'description' => ['nullable', 'string'],
            ],
            'exam-enrollments' => [
                'exam_id' => [$required, 'exists:exams,id'],
                'user_id' => [$required, 'exists:users,id'],
                'enrolled_by' => ['nullable', 'exists:users,id'],
                'enrolled_at' => ['nullable', 'date'],
            ],
            'class-schedules' => [
                'administrative_class_id' => [$required, 'exists:administrative_classes,id'],
                'course_id' => ['nullable', 'exists:courses,id'],
                'term_id' => ['nullable', 'exists:terms,id'],
                'lecturer_id' => ['nullable', 'exists:users,id'],
                'weekday' => [$required, 'integer', 'min:1', 'max:7'],
                'start_time' => [$required, 'date_format:H:i'],
                'end_time' => [$required, 'date_format:H:i', 'after:start_time'],
                'room' => ['nullable', 'string', 'max:100'],
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

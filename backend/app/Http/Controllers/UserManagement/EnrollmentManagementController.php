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

        if (!$user || !$user->hasAnyRole(['admin', 'instructor'])) {
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

    public function enrollManual(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (!$user || !$user->hasAnyRole(['admin', 'instructor'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'class_section_id' => ['nullable', 'integer', 'exists:class_sections,id'],
            'user_ids' => ['required', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'term_id' => ['nullable', 'integer', 'exists:terms,id'],
        ]);

        $course = Course::findOrFail($validated['course_id']);
        $termId = $validated['term_id'] ?? null;
        $classSectionId = $validated['class_section_id'] ?? null;

        $created = 0;
        $skipped = 0;

        DB::transaction(function () use ($validated, $course, $termId, $classSectionId, &$created, &$skipped) {
            foreach ($validated['user_ids'] as $userId) {
                $existing = Enrollment::where('user_id', $userId)
                    ->where('course_id', $course->id)
                    ->first();

                if ($existing) {
                    if ($classSectionId && $existing->class_section_id !== $classSectionId) {
                        $existing->class_section_id = $classSectionId;
                        if ($termId) {
                            $existing->term_id = $termId;
                        }
                        $existing->save();
                        $created++;
                    } else {
                        $skipped++;
                    }
                    continue;
                }

                $student = User::find($userId);

                Enrollment::create([
                    'user_id' => $userId,
                    'course_id' => $course->id,
                    'term_id' => $termId,
                    'cohort_id' => $student->cohort_id ?? null,
                    'class_section_id' => $classSectionId,
                    'enrollment_source' => 'manual',
                    'enrolled_at' => now(),
                ]);

                Notification::send($userId, 'enrollment', 'Được ghi danh thủ công', "Bạn đã được ban quản trị ghi danh vào khóa học \"{$course->title}\".", "/learn/{$course->id}");

                $created++;
            }
        });

        return response()->json([
            'message' => 'Ghi danh thủ công hoàn tất',
            'created' => $created,
            'skipped' => $skipped,
        ]);
    }

    public function importPreview(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (!$user || !$user->hasAnyRole(['admin', 'instructor'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $file = $request->file('file');
        $filePath = $file->getRealPath();

        $rows = [];
        if (($handle = fopen($filePath, 'r')) !== false) {
            $header = fgetcsv($handle, 1000, ',');
            
            $rowNum = 1;
            while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                $rowNum++;
                if (count($data) < 2) continue;

                $studentCode = trim($data[0]);
                $courseCode = trim($data[1]);

                if (empty($studentCode) || empty($courseCode)) {
                    continue;
                }

                $rows[] = [
                    'row_number' => $rowNum,
                    'student_code' => $studentCode,
                    'course_code' => $courseCode,
                ];
            }
            fclose($handle);
        }

        if (empty($rows)) {
            return response()->json(['message' => 'Tệp nhập rỗng hoặc định dạng không đúng. Cột 1 phải là Mã sinh viên, cột 2 là Mã môn học.'], 422);
        }

        $previewData = [];
        $validRowsToSave = [];
        $totalRows = count($rows);
        $validCount = 0;
        $invalidCount = 0;

        foreach ($rows as $row) {
            $student = User::where('student_code', $row['student_code'])->first();
            $course = Course::where('id', $row['course_code'])
                ->orWhere('title', 'like', '%' . $row['course_code'] . '%')
                ->first();

            if (!$student) {
                $previewData[] = [
                    'row_number' => $row['row_number'],
                    'student_code' => $row['student_code'],
                    'student_name' => null,
                    'course_code' => $row['course_code'],
                    'course_title' => null,
                    'status' => 'invalid',
                    'message' => 'Mã sinh viên không tồn tại.',
                ];
                $invalidCount++;
                continue;
            }

            if (!$course) {
                $previewData[] = [
                    'row_number' => $row['row_number'],
                    'student_code' => $row['student_code'],
                    'student_name' => $student->name,
                    'course_code' => $row['course_code'],
                    'course_title' => null,
                    'status' => 'invalid',
                    'message' => 'Mã học phần/Khóa học không tồn tại.',
                ];
                $invalidCount++;
                continue;
            }

            $exists = Enrollment::where('user_id', $student->id)
                ->where('course_id', $course->id)
                ->exists();

            if ($exists) {
                $previewData[] = [
                    'row_number' => $row['row_number'],
                    'student_code' => $row['student_code'],
                    'student_name' => $student->name,
                    'course_code' => $row['course_code'],
                    'course_title' => $course->title,
                    'status' => 'invalid',
                    'message' => 'Học viên đã được ghi danh vào môn này trước đó.',
                ];
                $invalidCount++;
                continue;
            }

            $previewData[] = [
                'row_number' => $row['row_number'],
                'student_code' => $row['student_code'],
                'student_name' => $student->name,
                'course_code' => $row['course_code'],
                'course_title' => $course->title,
                'status' => 'valid',
                'message' => 'Hợp lệ',
            ];
            
            $validRowsToSave[] = [
                'student_id' => $student->id,
                'student_code' => $student->student_code,
                'student_name' => $student->name,
                'course_id' => $course->id,
                'course_title' => $course->title,
            ];
            $validCount++;
        }

        $importToken = 'temp_import_' . \Illuminate\Support\Str::random(16);
        \Illuminate\Support\Facades\Cache::put($importToken, $validRowsToSave, 3600);

        return response()->json([
            'import_token' => $importToken,
            'total_rows' => $totalRows,
            'valid_rows' => $validCount,
            'invalid_rows' => $invalidCount,
            'preview_data' => $previewData,
        ]);
    }

    public function importExecute(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (!$user || !$user->hasAnyRole(['admin', 'instructor'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'import_token' => ['required', 'string'],
            'term_id' => ['nullable', 'integer', 'exists:terms,id'],
        ]);

        $importToken = $validated['import_token'];
        $validRows = \Illuminate\Support\Facades\Cache::get($importToken);

        if (!$validRows || !is_array($validRows)) {
            return response()->json(['message' => 'Token xác thực đã hết hạn hoặc không tồn tại. Vui lòng tải lên lại file.'], 422);
        }

        $termId = $validated['term_id'] ?? null;
        $created = 0;

        DB::transaction(function () use ($validRows, $termId, &$created) {
            foreach ($validRows as $row) {
                $exists = Enrollment::where('user_id', $row['student_id'])
                    ->where('course_id', $row['course_id'])
                    ->exists();

                if (!$exists) {
                    Enrollment::create([
                        'user_id' => $row['student_id'],
                        'course_id' => $row['course_id'],
                        'term_id' => $termId,
                        'enrollment_source' => 'excel_import',
                        'enrolled_at' => now(),
                    ]);

                    Notification::send($row['student_id'], 'enrollment', 'Ghi danh thành công qua Excel', "Bạn đã được ghi danh vào môn học \"{$row['course_title']}\".", "/learn/{$row['course_id']}");
                    
                    $created++;
                }
            }
        });

        \Illuminate\Support\Facades\Cache::forget($importToken);

        return response()->json([
            'message' => "Đã hoàn tất ghi danh cho {$created} học viên thành công.",
            'created' => $created,
        ]);
    }

    public function destroyEnrollment(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (!$user || !$user->hasAnyRole(['admin', 'instructor'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'enrollment_ids' => ['required', 'array', 'min:1'],
            'enrollment_ids.*' => ['integer', 'exists:enrollments,id'],
        ]);

        $deletedCount = Enrollment::whereIn('id', $validated['enrollment_ids'])->delete();

        return response()->json([
            'message' => "Đã xóa thành công {$deletedCount} bản ghi ghi danh.",
            'deleted' => $deletedCount,
        ]);
    }

    public function importDeletePreview(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (!$user || !$user->hasAnyRole(['admin', 'instructor'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $file = $request->file('file');
        $filePath = $file->getRealPath();

        $rows = [];
        if (($handle = fopen($filePath, 'r')) !== false) {
            $header = fgetcsv($handle, 1000, ',');
            
            $rowNum = 1;
            while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                $rowNum++;
                if (count($data) < 2) continue;

                $studentCode = trim($data[0]);
                $courseCode = trim($data[1]);

                if (empty($studentCode) || empty($courseCode)) {
                    continue;
                }

                $rows[] = [
                    'row_number' => $rowNum,
                    'student_code' => $studentCode,
                    'course_code' => $courseCode,
                ];
            }
            fclose($handle);
        }

        if (empty($rows)) {
            return response()->json(['message' => 'Tệp nhập rỗng hoặc định dạng không đúng. Cột 1 phải là Mã sinh viên, cột 2 là Mã môn học.'], 422);
        }

        $previewData = [];
        $validEnrollmentIds = [];
        $totalRows = count($rows);
        $validCount = 0;
        $invalidCount = 0;

        foreach ($rows as $row) {
            $student = User::where('student_code', $row['student_code'])->first();
            $course = Course::where('id', $row['course_code'])
                ->orWhere('title', 'like', '%' . $row['course_code'] . '%')
                ->first();

            if (!$student) {
                $previewData[] = [
                    'row_number' => $row['row_number'],
                    'student_code' => $row['student_code'],
                    'student_name' => null,
                    'course_code' => $row['course_code'],
                    'course_title' => null,
                    'status' => 'invalid',
                    'message' => 'Mã sinh viên không tồn tại.',
                ];
                $invalidCount++;
                continue;
            }

            if (!$course) {
                $previewData[] = [
                    'row_number' => $row['row_number'],
                    'student_code' => $row['student_code'],
                    'student_name' => $student->name,
                    'course_code' => $row['course_code'],
                    'course_title' => null,
                    'status' => 'invalid',
                    'message' => 'Mã học phần/Khóa học không tồn tại.',
                ];
                $invalidCount++;
                continue;
            }

            $enrollment = Enrollment::where('user_id', $student->id)
                ->where('course_id', $course->id)
                ->first();

            if (!$enrollment) {
                $previewData[] = [
                    'row_number' => $row['row_number'],
                    'student_code' => $row['student_code'],
                    'student_name' => $student->name,
                    'course_code' => $row['course_code'],
                    'course_title' => $course->title,
                    'status' => 'invalid',
                    'message' => 'Học viên chưa được ghi danh vào môn này.',
                ];
                $invalidCount++;
                continue;
            }

            $previewData[] = [
                'row_number' => $row['row_number'],
                'student_code' => $row['student_code'],
                'student_name' => $student->name,
                'course_code' => $row['course_code'],
                'course_title' => $course->title,
                'status' => 'valid',
                'message' => 'Hợp lệ để xóa',
            ];
            
            $validEnrollmentIds[] = $enrollment->id;
            $validCount++;
        }

        $importToken = 'temp_delete_' . \Illuminate\Support\Str::random(16);
        \Illuminate\Support\Facades\Cache::put($importToken, $validEnrollmentIds, 3600);

        return response()->json([
            'import_token' => $importToken,
            'total_rows' => $totalRows,
            'valid_rows' => $validCount,
            'invalid_rows' => $invalidCount,
            'preview_data' => $previewData,
        ]);
    }

    public function importDeleteExecute(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (!$user || !$user->hasAnyRole(['admin', 'instructor'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'import_token' => ['required', 'string'],
        ]);

        $importToken = $validated['import_token'];
        $enrollmentIds = \Illuminate\Support\Facades\Cache::get($importToken);

        if (!$enrollmentIds || !is_array($enrollmentIds)) {
            return response()->json(['message' => 'Token xác thực đã hết hạn hoặc không tồn tại. Vui lòng tải lại file.'], 422);
        }

        $deleted = 0;
        DB::transaction(function () use ($enrollmentIds, &$deleted) {
            $deleted = Enrollment::whereIn('id', $enrollmentIds)->delete();
        });

        \Illuminate\Support\Facades\Cache::forget($importToken);

        return response()->json([
            'message' => "Đã hoàn tất xóa ghi danh cho {$deleted} bản ghi thành công.",
            'deleted' => $deleted,
        ]);
    }

    public function classProgressReport(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (!$user || !$user->hasAnyRole(['admin', 'instructor'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'administrative_class_id' => ['required', 'integer', 'exists:administrative_classes,id'],
        ]);

        $classId = $validated['administrative_class_id'];
        $class = \App\Models\AdministrativeClass::with(['cohort', 'program', 'major'])->findOrFail($classId);

        if (!$class->curriculum_id) {
            return response()->json([
                'has_curriculum' => false,
                'message' => 'Lớp hành chính này chưa được gán lộ trình đào tạo.',
                'stats' => null,
                'students' => []
            ]);
        }

        $curriculum = \App\Models\Curriculum::with([
            'curriculumCourses.course:id,title,credit_value,is_credit_bearing'
        ])->find($class->curriculum_id);

        if (!$curriculum) {
            return response()->json([
                'has_curriculum' => false,
                'message' => 'Không tìm thấy chương trình đào tạo tương ứng.',
                'stats' => null,
                'students' => []
            ]);
        }

        // Calculate total curriculum credits
        $totalCreditsRequired = 0;
        $curriculumCourses = $curriculum->curriculumCourses;
        $courseIds = [];

        foreach ($curriculumCourses as $cc) {
            $course = $cc->course;
            if (!$course) continue;
            $courseIds[] = $course->id;
            if ($course->is_credit_bearing) {
                $totalCreditsRequired += ($course->credit_value ?? $cc->credits ?? 0);
            }
        }

        // Get class students
        $students = User::where('administrative_class_id', $classId)
            ->where('user_type', 'student')
            ->get(['id', 'name', 'student_code', 'email']);

        $studentsData = [];
        $totalCompletionSum = 0;
        $atRiskCount = 0;

        foreach ($students as $student) {
            // Get student enrollments for curriculum courses
            $enrollments = Enrollment::where('user_id', $student->id)
                ->whereIn('course_id', $courseIds)
                ->get();

            $completedCount = 0;
            $creditsEarned = 0;
            $progressSum = 0;

            if ($enrollments->isNotEmpty()) {
                foreach ($enrollments as $enrollment) {
                    $progressVal = $enrollment->progress ?? 0;
                    $progressSum += $progressVal;
                    if ($progressVal >= 100) {
                        $completedCount++;
                        // Lookup course credits
                        $curCourse = $curriculumCourses->firstWhere('course_id', $enrollment->course_id);
                        $courseObj = $curCourse ? $curCourse->course : null;
                        $cVal = $courseObj ? ($courseObj->credit_value ?? $curCourse->credits ?? 0) : 0;
                        $creditsEarned += $cVal;
                    }
                }
            }

            $avgProgress = count($courseIds) > 0 ? round($progressSum / count($courseIds), 1) : 0;
            $completionRate = count($courseIds) > 0 ? round(($completedCount / count($courseIds)) * 100, 1) : 0;

            $isSlow = ($avgProgress < 50 && count($courseIds) > 0);
            if ($isSlow) {
                $atRiskCount++;
            }

            $totalCompletionSum += $completionRate;

            $studentsData[] = [
                'id' => $student->id,
                'name' => $student->name,
                'student_code' => $student->student_code,
                'email' => $student->email,
                'completed_courses_count' => $completedCount,
                'credits_earned' => $creditsEarned,
                'average_progress' => $avgProgress,
                'completion_rate' => $completionRate,
                'is_at_risk' => $isSlow
            ];
        }

        $classAverageCompletion = 0;
        if ($students->isNotEmpty()) {
            $classAverageCompletion = round($totalCompletionSum / $students->count(), 1);
        }

        // Aggregate course-by-course enrollment stats inside this class
        $courseStats = [];
        foreach ($curriculumCourses as $cc) {
            $course = $cc->course;
            if (!$course) continue;

            $enrolledCount = Enrollment::whereIn('user_id', $students->pluck('id'))
                ->where('course_id', $course->id)
                ->count();

            $completedCount = Enrollment::whereIn('user_id', $students->pluck('id'))
                ->where('course_id', $course->id)
                ->where('progress', '>=', 100)
                ->count();

            $courseStats[] = [
                'course_id' => $course->id,
                'title' => $course->title,
                'term_number' => $cc->term_number,
                'is_required' => $cc->is_required,
                'enrolled_students' => $enrolledCount,
                'completed_students' => $completedCount,
                'total_students' => $students->count()
            ];
        }

        return response()->json([
            'has_curriculum' => true,
            'class_info' => [
                'id' => $class->id,
                'code' => $class->code,
                'name' => $class->name,
                'curriculum_name' => $curriculum->name,
                'total_courses' => count($courseIds),
                'total_credits_required' => $totalCreditsRequired
            ],
            'stats' => [
                'total_students' => $students->count(),
                'at_risk_students' => $atRiskCount,
                'average_completion_rate' => $classAverageCompletion
            ],
            'students' => $studentsData,
            'courses' => $courseStats
        ]);
    }
}


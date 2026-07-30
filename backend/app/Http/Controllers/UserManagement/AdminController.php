<?php

namespace App\Http\Controllers\UserManagement;

use App\Http\Controllers\Controller;

use App\Models\Category;
use App\Models\ClassSection;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\Review;
use App\Models\SiteSetting;
use App\Models\User;
use App\Services\MediaService;
use App\Support\Enums\StudyStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;

class AdminController extends Controller
{
    private function ensureAdmin(Request $request): ?JsonResponse
    {
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return null;
    }

    public function uploadAsset(Request $request, MediaService $mediaService): JsonResponse
    {
        if ($forbidden = $this->ensureAdmin($request)) {
            return $forbidden;
        }

        $validated = $request->validate([
            'file' => ['required', 'file', 'image', 'max:5120'],
            'folder' => ['nullable', 'string', 'in:users,settings,courses,faces'],
            'old_path' => ['nullable', 'string', 'max:2048'],
        ]);

        $folder = match ($validated['folder'] ?? 'users') {
            'settings' => 'admin/settings',
            'courses' => 'admin/courses',
            'faces' => 'admin/faces',
            default => 'admin/users',
        };

        $uploaded = $mediaService->upload($request->file('file'), $folder);

        if (!empty($validated['old_path']) && $mediaService->exists($validated['old_path'])) {
            $mediaService->delete($validated['old_path']);
        }

        return response()->json([
            'message' => 'Upload successful',
            'path' => $uploaded['path'],
            'url' => $mediaService->getUrl($uploaded['path']),
            'meta' => $uploaded,
        ], 201);
    }


    public function stats(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensureAdmin($request)) {
            return $forbidden;
        }

        $paidStatuses = ['paid', 'completed'];
        $totalRevenue = Order::whereIn('status', $paidStatuses)->sum('amount');

        $coursesByStatus = Course::query()
            ->selectRaw('status, count(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $monthKeys = collect(range(5, 0))->map(fn (int $i) => now()->subMonths($i)->format('Y-m'));

        $revenueRows = Order::query()
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, SUM(amount) as total")
            ->whereIn('status', $paidStatuses)
            ->where('created_at', '>=', now()->subMonths(5)->startOfMonth())
            ->groupBy('month')
            ->pluck('total', 'month');

        $userRows = User::query()
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as total")
            ->where('created_at', '>=', now()->subMonths(5)->startOfMonth())
            ->groupBy('month')
            ->pluck('total', 'month');

        return response()->json([
            'total_users'       => User::count(),
            'total_courses'     => Course::count(),
            'total_orders'      => Order::count(),
            'total_revenue'     => (int) $totalRevenue,
            'total_students'    => User::role('student')->count(),
            'total_instructors' => User::role('instructor')->count(),
            'courses_by_status' => $coursesByStatus,
            'revenue_by_month'  => $this->hydrateMonthlySeries($monthKeys, $revenueRows),
            'new_users_by_month' => $this->hydrateMonthlySeries($monthKeys, $userRows),
            'top_courses'       => $this->getTopCourses(),
            'engagement'        => $this->getEngagementStats(),
            'pending_courses'   => Course::where('status', 'pending_review')->count(),
            'published_courses' => Course::where('status', 'published')->count(),
            'paid_orders'       => Order::whereIn('status', $paidStatuses)->count(),
            'enrollments_week'  => Enrollment::where('enrolled_at', '>=', now()->subDays(7))->count(),
            'enrollments_today' => Enrollment::whereDate('enrolled_at', now()->toDateString())->count(),
            'new_users_week'    => User::where('created_at', '>=', now()->subDays(7))->count(),
            'reviews_count'     => Review::count(),
            'open_sections'     => ClassSection::where('status', 'open')->count(),
        ]);
    }

    private function getTopCourses(): Collection
    {
        return Course::query()
            ->withCount('enrollments')
            ->orderByDesc('enrollments_count')
            ->limit(5)
            ->get(['id', 'title', 'enrollments_count']);
    }

    private function getEngagementStats(): array
    {
        $lessonProgressQuery = \App\Models\LessonProgress::query();

        if (Schema::hasColumn('lesson_progress', 'is_completed')) {
            $lessonProgressQuery->where('is_completed', true);
        } elseif (Schema::hasColumn('lesson_progress', 'completed_at')) {
            $lessonProgressQuery->whereNotNull('completed_at');
        } else {
            $lessonProgressQuery->whereRaw('1 = 0');
        }

        $activeStudentsQuery = User::role('student');

        if (Schema::hasColumn('users', 'last_login_at')) {
            $activeStudentsQuery->where('last_login_at', '>=', now()->subWeek());
        } else {
            $activeStudentsQuery->whereNotNull('created_at');
        }

        return [
            'avg_quiz_score' => \App\Models\QuizAttempt::avg('score') ?? 0,
            'total_completions' => $lessonProgressQuery->count(),
            'active_students_this_week' => $activeStudentsQuery->count(),
        ];
    }

    public function users(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensureAdmin($request)) {
            return $forbidden;
        }

        $users = $this->filteredUsersQuery($request)
            ->paginate($request->integer('per_page', 15));

        return response()->json($users);
    }

    public function exportUsers(Request $request): StreamedResponse|JsonResponse
    {
        if ($forbidden = $this->ensureAdmin($request)) {
            return $forbidden;
        }

        $query = $this->filteredUsersQuery($request);
        $filename = 'users_' . now()->format('Ymd_His') . '.csv';

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, [
                'id', 'name', 'email', 'role', 'student_code', 'staff_code', 'phone',
                'gender', 'date_of_birth', 'study_status', 'unit', 'program', 'major',
                'cohort', 'administrative_class', 'created_at',
            ]);

            $query->chunk(500, function ($users) use ($out) {
                foreach ($users as $user) {
                    fputcsv($out, [
                        $user->id,
                        $user->name ?? '',
                        $user->email ?? '',
                        $user->roles->pluck('name')->implode('|'),
                        $user->student_code ?? '',
                        $user->staff_code ?? '',
                        $user->phone ?? '',
                        $user->gender ?? '',
                        $user->date_of_birth ? $user->date_of_birth->format('Y-m-d') : '',
                        $user->study_status ?? '',
                        $user->unit?->code ?: ($user->unit?->name ?? ''),
                        $user->program?->code ?: ($user->program?->name ?? ''),
                        $user->major?->code ?: ($user->major?->name ?? ''),
                        $user->cohort?->code ?: ($user->cohort?->name ?? ''),
                        $user->administrativeClass?->code ?: ($user->administrativeClass?->name ?? ''),
                        $user->created_at?->toDateTimeString() ?? '',
                    ]);
                }
            });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    public function importUsersTemplate(Request $request): StreamedResponse|JsonResponse
    {
        if ($forbidden = $this->ensureAdmin($request)) {
            return $forbidden;
        }

        $filename = 'users_import_template.csv';

        return response()->streamDownload(function () {
            $out = fopen('php://output', 'w');
            fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
            fputcsv($out, [
                'name', 'email', 'password', 'role', 'student_code', 'staff_code',
                'phone', 'gender', 'date_of_birth', 'study_status',
                'cohort_code', 'class_code', 'program_code', 'major_code',
            ]);
            fputcsv($out, [
                'Nguyen Van A', 'sv.a@example.com', 'Password1', 'student', 'SV001', '',
                '0901234567', 'male', '2004-01-15', 'dang_hoc',
                'K20', 'CNTT-K20-01', 'CNTT', 'KTPM',
            ]);
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function importUsersPreview(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensureAdmin($request)) {
            return $forbidden;
        }

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $path = $request->file('file')->getRealPath();
        $handle = fopen($path, 'r');
        if ($handle === false) {
            return response()->json(['message' => 'Không đọc được file CSV.'], 422);
        }

        $header = null;
        $rows = [];
        $errors = [];
        $valid = [];
        $rowNum = 1;

        while (($data = fgetcsv($handle)) !== false) {
            if ($header === null) {
                $header = array_map(fn ($h) => Str::of((string) $h)->trim()->lower()->replace("\xEF\xBB\xBF", '')->toString(), $data);
                continue;
            }
            $rowNum++;
            if (count(array_filter($data, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $row = [];
            foreach ($header as $i => $key) {
                $row[$key] = isset($data[$i]) ? trim((string) $data[$i]) : '';
            }

            $rowErrors = [];
            $name = $row['name'] ?? '';
            $email = $row['email'] ?? '';
            $password = $row['password'] ?? '';
            $role = $row['role'] ?? 'student';

            if ($name === '') {
                $rowErrors[] = 'Thiếu name';
            }
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $rowErrors[] = 'Email không hợp lệ';
            } elseif (User::where('email', $email)->exists()) {
                $rowErrors[] = 'Email đã tồn tại';
            }
            if (strlen($password) < 6) {
                $rowErrors[] = 'Password tối thiểu 6 ký tự';
            }
            if (!in_array($role, ['admin', 'instructor', 'student', 'academic_manager'], true)) {
                $rowErrors[] = 'Role không hợp lệ';
            }

            $payload = [
                'name' => $name,
                'email' => $email,
                'password' => $password,
                'role' => $role,
                'student_code' => $row['student_code'] ?: null,
                'staff_code' => $row['staff_code'] ?: null,
                'phone' => $row['phone'] ?: null,
                'gender' => in_array($row['gender'] ?? '', ['male', 'female', 'other'], true) ? $row['gender'] : null,
                'date_of_birth' => ($row['date_of_birth'] ?? '') !== '' ? $row['date_of_birth'] : null,
                'study_status' => in_array($row['study_status'] ?? '', StudyStatus::all(), true) ? $row['study_status'] : null,
                'cohort_id' => null,
                'administrative_class_id' => null,
                'program_id' => null,
                'major_id' => null,
            ];

            if (!empty($row['cohort_code'])) {
                $cohort = DB::table('cohorts')->where('code', $row['cohort_code'])->first();
                if (!$cohort) {
                    $rowErrors[] = 'Không tìm thấy cohort_code';
                } else {
                    $payload['cohort_id'] = $cohort->id;
                }
            }
            if (!empty($row['class_code'])) {
                $class = DB::table('administrative_classes')->where('code', $row['class_code'])->first();
                if (!$class) {
                    $rowErrors[] = 'Không tìm thấy class_code';
                } else {
                    $payload['administrative_class_id'] = $class->id;
                }
            }
            if (!empty($row['program_code'])) {
                $program = DB::table('programs')->where('code', $row['program_code'])->first();
                if (!$program) {
                    $rowErrors[] = 'Không tìm thấy program_code';
                } else {
                    $payload['program_id'] = $program->id;
                }
            }
            if (!empty($row['major_code'])) {
                $major = DB::table('majors')->where('code', $row['major_code'])->first();
                if (!$major) {
                    $rowErrors[] = 'Không tìm thấy major_code';
                } else {
                    $payload['major_id'] = $major->id;
                }
            }

            $preview = [
                'row' => $rowNum,
                'name' => $name,
                'email' => $email,
                'role' => $role,
                'student_code' => $payload['student_code'],
                'errors' => $rowErrors,
            ];
            $rows[] = $preview;

            if ($rowErrors) {
                $errors[] = $preview;
            } else {
                $valid[] = $payload;
            }
        }
        fclose($handle);

        if (count($valid) === 0) {
            return response()->json([
                'message' => 'Không có dòng hợp lệ để import.',
                'total' => count($rows),
                'valid_count' => 0,
                'error_count' => count($errors),
                'rows' => $rows,
                'errors' => $errors,
            ], 422);
        }

        $token = 'user_import_' . Str::random(24);
        Cache::put($token, $valid, 3600);

        return response()->json([
            'message' => 'Xem trước import thành công',
            'import_token' => $token,
            'total' => count($rows),
            'valid_count' => count($valid),
            'error_count' => count($errors),
            'rows' => $rows,
            'errors' => $errors,
        ]);
    }

    public function importUsersExecute(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensureAdmin($request)) {
            return $forbidden;
        }

        $validated = $request->validate([
            'import_token' => ['required', 'string'],
        ]);

        $payloads = Cache::get($validated['import_token']);
        if (!is_array($payloads) || count($payloads) === 0) {
            return response()->json(['message' => 'Token import hết hạn hoặc không hợp lệ.'], 422);
        }

        $created = 0;
        DB::transaction(function () use ($payloads, &$created) {
            foreach ($payloads as $payload) {
                $user = User::create([
                    'name' => $payload['name'],
                    'email' => $payload['email'],
                    'password' => Hash::make($payload['password']),
                    'email_verified_at' => now(),
                    'student_code' => $payload['student_code'] ?? null,
                    'staff_code' => $payload['staff_code'] ?? null,
                    'phone' => $payload['phone'] ?? null,
                    'gender' => $payload['gender'] ?? null,
                    'date_of_birth' => $payload['date_of_birth'] ?? null,
                    'study_status' => $payload['study_status'] ?? null,
                    'cohort_id' => $payload['cohort_id'] ?? null,
                    'administrative_class_id' => $payload['administrative_class_id'] ?? null,
                    'program_id' => $payload['program_id'] ?? null,
                    'major_id' => $payload['major_id'] ?? null,
                ]);
                $user->syncRoles([$payload['role']]);
                $created++;
            }
        });

        Cache::forget($validated['import_token']);

        return response()->json([
            'message' => "Đã import {$created} người dùng",
            'created' => $created,
        ]);
    }

    public function bulkDestroyUsers(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensureAdmin($request)) {
            return $forbidden;
        }

        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:users,id'],
        ]);

        $actorId = $request->user()->id;
        $ids = collect($validated['ids'])->unique()->values();
        $deleted = 0;
        $skipped = [];

        foreach ($ids as $id) {
            if ((int) $id === (int) $actorId) {
                $skipped[] = ['id' => $id, 'reason' => 'Không thể tự xóa chính mình'];
                continue;
            }
            $user = User::find($id);
            if (!$user) {
                continue;
            }
            if ($user->hasRole('admin') && User::role('admin')->count() <= 1) {
                $skipped[] = ['id' => $id, 'reason' => 'Không thể xóa admin cuối cùng'];
                continue;
            }
            $user->delete();
            $deleted++;
        }

        return response()->json([
            'message' => "Đã xóa {$deleted} người dùng",
            'deleted' => $deleted,
            'skipped' => $skipped,
        ]);
    }

    /**
     * Bulk-assign face photos to students: each uploaded file's name (without
     * extension) must match a `student_code` — e.g. "SV001.jpg" → student SV001.
     * MVP alternative to a full CSV/zip mapper; admin just multi-selects files
     * that were named after the student codes.
     */
    public function importFaces(Request $request, MediaService $mediaService): JsonResponse
    {
        if ($forbidden = $this->ensureAdmin($request)) {
            return $forbidden;
        }

        $validated = $request->validate([
            'files' => ['required', 'array', 'min:1'],
            'files.*' => ['file', 'image', 'max:5120'],
        ]);

        $updated = [];
        $skipped = [];

        foreach ($validated['files'] as $file) {
            $code = trim(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));

            if ($code === '') {
                $skipped[] = ['filename' => $file->getClientOriginalName(), 'reason' => 'Tên file rỗng'];
                continue;
            }

            $user = User::where('student_code', $code)->orWhere('staff_code', $code)->first();
            if (!$user) {
                $skipped[] = ['filename' => $file->getClientOriginalName(), 'reason' => "Không tìm thấy mã {$code}"];
                continue;
            }

            $uploaded = $mediaService->upload($file, 'admin/faces');

            if (!empty($user->face_url) && $mediaService->exists($user->face_url)) {
                $mediaService->delete($user->face_url);
            }

            $user->face_url = $uploaded['path'];
            $user->save();

            $updated[] = [
                'user_id' => $user->id,
                'student_code' => $code,
                'name' => $user->name,
                'face_url' => $mediaService->getUrl($uploaded['path']),
            ];
        }

        return response()->json([
            'message' => 'Đã cập nhật ảnh khuôn mặt cho ' . count($updated) . ' sinh viên/nhân viên.',
            'updated' => $updated,
            'skipped' => $skipped,
        ]);
    }

    private function filteredUsersQuery(Request $request)
    {
        $query = User::with([
            'roles',
            'administrativeClass:id,name,code',
            'cohort:id,name,code',
            'program:id,name,code',
            'major:id,name,code',
            'unit:id,name,code',
        ]);

        if ($request->filled('role')) {
            $query->role($request->string('role')->toString());
        }

        $search = trim((string) ($request->query('q') ?: $request->query('search', '')));
        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('student_code', 'like', "%{$search}%")
                    ->orWhere('staff_code', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        foreach (['unit_id', 'program_id', 'major_id', 'cohort_id', 'advisor_id'] as $field) {
            $ids = $this->requestIdList($request, $field);
            if (count($ids) === 1) {
                $query->where($field, $ids[0]);
            } elseif (count($ids) > 1) {
                $query->whereIn($field, $ids);
            }
        }

        $classValues = $this->requestRawList($request, 'administrative_class_id');
        if (count($classValues) > 0) {
            $includeNone = in_array('none', $classValues, true) || in_array('0', $classValues, true) || in_array(0, $classValues, true);
            $classIds = array_values(array_filter(array_map(
                fn ($v) => is_numeric($v) ? (int) $v : null,
                $classValues
            ), fn ($v) => $v !== null && $v > 0));

            $query->where(function ($q) use ($includeNone, $classIds) {
                $started = false;
                if ($includeNone) {
                    $q->whereNull('administrative_class_id');
                    $started = true;
                }
                if (count($classIds) === 1) {
                    $started ? $q->orWhere('administrative_class_id', $classIds[0]) : $q->where('administrative_class_id', $classIds[0]);
                } elseif (count($classIds) > 1) {
                    $started ? $q->orWhereIn('administrative_class_id', $classIds) : $q->whereIn('administrative_class_id', $classIds);
                }
            });
        }

        if ($request->filled('study_status')) {
            $status = $request->string('study_status')->toString();
            if (in_array($status, StudyStatus::all(), true)) {
                $query->where('study_status', $status);
            }
        }

        if ($request->filled('gender')) {
            $query->where('gender', $request->string('gender')->toString());
        }

        if ($request->filled('created_from')) {
            $query->whereDate('created_at', '>=', $request->string('created_from')->toString());
        }
        if ($request->filled('created_to')) {
            $query->whereDate('created_at', '<=', $request->string('created_to')->toString());
        }

        if ($request->boolean('verified_only')) {
            $query->whereNotNull('email_verified_at');
        }
        if ($request->boolean('unverified_only')) {
            $query->whereNull('email_verified_at');
        }

        $sortBy = $request->string('sort_by')->toString();
        $sortDir = strtolower($request->string('sort_dir')->toString()) === 'asc' ? 'asc' : 'desc';
        $allowedSorts = ['name', 'email', 'created_at', 'student_code', 'staff_code', 'study_status'];
        if (in_array($sortBy, $allowedSorts, true)) {
            $query->orderBy($sortBy, $sortDir);
        } else {
            $query->orderByDesc('created_at');
        }

        return $query;
    }

    /** @return list<int> */
    private function requestIdList(Request $request, string $key): array
    {
        return array_values(array_filter(array_map(
            fn ($v) => is_numeric($v) ? (int) $v : null,
            $this->requestRawList($request, $key)
        ), fn ($v) => $v !== null && $v > 0));
    }

    /** @return list<mixed> */
    private function requestRawList(Request $request, string $key): array
    {
        $val = $request->input($key);
        if ($val === null || $val === '') {
            // also accept plural: cohort_ids
            $plural = str_ends_with($key, '_id') ? substr($key, 0, -3) . '_ids' : $key . 's';
            $val = $request->input($plural);
        }
        if ($val === null || $val === '') {
            return [];
        }
        if (!is_array($val)) {
            $val = preg_split('/\s*,\s*/', (string) $val) ?: [(string) $val];
        }

        return array_values(array_filter($val, fn ($v) => $v !== null && $v !== ''));
    }

    public function listStudents(Request $request): JsonResponse
    {
        $request->merge(['role' => 'student']);

        return $this->users($request);
    }

    public function listInstructors(Request $request): JsonResponse
    {
        $request->merge(['role' => 'instructor']);

        return $this->users($request);
    }


    public function updateUserRole(Request $request, User $user): JsonResponse
    {
        if ($forbidden = $this->ensureAdmin($request)) {
            return $forbidden;
        }

        $validated = $request->validate([
            'role' => ['required', 'string', 'in:admin,instructor,student'],
        ]);

        if ($request->user()->id === $user->id && $validated['role'] !== 'admin') {
            return response()->json([
                'message' => 'Không thể tự gỡ quyền admin của chính bạn.',
            ], 422);
        }

        $user->syncRoles([$validated['role']]);
        $user->load('roles');

        return response()->json([
            'message' => 'Role updated',
            'user'    => $user,
        ]);
    }

    public function storeUser(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensureAdmin($request)) {
            return $forbidden;
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'avatar' => ['nullable', 'string', 'max:2048'],
            'face_url' => ['nullable', 'string', 'max:2048'],
            'role' => ['required', 'string', 'in:admin,instructor,student,academic_manager'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'institution_id' => ['nullable', 'exists:institutions,id'],
            'unit_id' => ['nullable', 'exists:units,id'],
            'program_id' => ['nullable', 'exists:programs,id'],
            'major_id' => ['nullable', 'exists:majors,id'],
            'cohort_id' => ['nullable', 'exists:cohorts,id'],
            'administrative_class_id' => ['nullable', 'exists:administrative_classes,id'],
            'advisor_id' => ['nullable', 'exists:users,id'],
            'student_code' => ['nullable', 'string', 'max:50'],
            'staff_code' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:32'],
            'id_card_number' => ['nullable', 'string', 'max:20', 'unique:users,id_card_number'],
            'gender' => ['nullable', 'in:male,female,other'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'nationality' => ['nullable', 'string', 'max:64'],
            'hometown' => ['nullable', 'string', 'max:255'],
            'permanent_address' => ['nullable', 'string', 'max:255'],
            'study_status' => ['nullable', 'in:' . implode(',', StudyStatus::all())],
        ]);

        $user = User::create(array_merge(
            collect($validated)->except(['password', 'role'])->toArray(),
            [
                'password' => Hash::make($validated['password']),
                'email_verified_at' => now(),
            ]
        ));
        $user->syncRoles([$validated['role']]);
        $user->load(['roles', 'administrativeClass:id,name,code', 'cohort:id,name,code', 'program:id,name,code', 'major:id,name,code', 'unit:id,name,code']);

        return response()->json([
            'message' => 'User created',
            'user' => $user,
        ], 201);
    }

    public function userAcademicSummary(Request $request, User $user): JsonResponse
    {
        if ($forbidden = $this->ensureAdmin($request)) {
            return $forbidden;
        }

        $enrollments = Enrollment::query()
            ->where('user_id', $user->id)
            ->with([
                'course:id,title,credit_value,is_credit_bearing',
                'term:id,name,code,start_date,end_date',
                'classSection:id,code,name,term_id',
            ])
            ->whereNotNull('term_id')
            ->orderBy('term_id')
            ->get();

        $byTerm = $enrollments->groupBy(fn ($e) => $e->term_id ?? 0);

        $terms = $byTerm->map(function ($items) {
            $term = $items->first()->term;
            $finalScores = $items->pluck('final_score')->filter(fn ($s) => $s !== null);
            return [
                'term' => $term,
                'course_count' => $items->count(),
                'credit_count' => $items->sum(fn ($e) => (int) ($e->course->credit_value ?? 0)),
                'gpa' => $finalScores->count() ? round($finalScores->avg(), 2) : null,
                'courses' => $items->map(fn ($e) => [
                    'title' => $e->course?->title,
                    'credit_value' => $e->course?->credit_value,
                    'final_score' => $e->final_score,
                    'class_section' => $e->classSection?->code,
                ])->values(),
            ];
        })->values();

        $gpas = $terms->pluck('gpa')->filter(fn ($g) => $g !== null);

        return response()->json([
            'overall_gpa' => $gpas->count() ? round($gpas->avg(), 2) : null,
            'total_credits' => $terms->sum('credit_count'),
            'total_courses' => $enrollments->count(),
            'terms' => $terms,
        ]);
    }

    public function updateUser(Request $request, User $user): JsonResponse
    {
        if ($forbidden = $this->ensureAdmin($request)) {
            return $forbidden;
        }

        $validated = $request->validate([
            'name' => ['sometimes', 'required', 'string', 'max:255'],
            'email' => ['sometimes', 'required', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'password' => ['nullable', 'string', 'min:6'],
            'avatar' => ['nullable', 'string', 'max:2048'],
            'face_url' => ['nullable', 'string', 'max:2048'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'role' => ['sometimes', 'required', 'string', 'in:admin,instructor,student,academic_manager'],
            'institution_id' => ['nullable', 'exists:institutions,id'],
            'unit_id' => ['nullable', 'exists:units,id'],
            'program_id' => ['nullable', 'exists:programs,id'],
            'major_id' => ['nullable', 'exists:majors,id'],
            'cohort_id' => ['nullable', 'exists:cohorts,id'],
            'administrative_class_id' => ['nullable', 'exists:administrative_classes,id'],
            'advisor_id' => ['nullable', 'exists:users,id'],
            'student_code' => ['nullable', 'string', 'max:50'],
            'staff_code' => ['nullable', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'max:32'],
            'id_card_number' => ['nullable', 'string', 'max:20', 'unique:users,id_card_number,' . $user->id],
            'gender' => ['nullable', 'in:male,female,other'],
            'date_of_birth' => ['nullable', 'date', 'before:today'],
            'nationality' => ['nullable', 'string', 'max:64'],
            'hometown' => ['nullable', 'string', 'max:255'],
            'permanent_address' => ['nullable', 'string', 'max:255'],
            'study_status' => ['nullable', 'in:' . implode(',', StudyStatus::all())],
        ]);

        if (
            isset($validated['role'])
            && $request->user()->id === $user->id
            && $validated['role'] !== 'admin'
        ) {
            return response()->json([
                'message' => 'Không thể tự gỡ quyền admin của chính bạn.',
            ], 422);
        }

        $user->fill(collect($validated)->except(['password', 'role'])->toArray());
        if (!empty($validated['password'])) {
            $user->password = Hash::make($validated['password']);
        }
        $user->save();

        if (isset($validated['role'])) {
            $user->syncRoles([$validated['role']]);
        }

        $user->load(['roles', 'administrativeClass:id,name,code', 'cohort:id,name,code', 'program:id,name,code', 'major:id,name,code', 'unit:id,name,code']);

        return response()->json([
            'message' => 'User updated',
            'user' => $user,
        ]);
    }

    public function destroyUser(Request $request, User $user): JsonResponse
    {
        if ($forbidden = $this->ensureAdmin($request)) {
            return $forbidden;
        }

        if ($request->user()->id === $user->id) {
            return response()->json(['message' => 'Không thể tự xóa tài khoản của chính bạn.'], 422);
        }

        if ($user->hasRole('admin') && User::role('admin')->count() <= 1) {
            return response()->json(['message' => 'Không thể xóa admin cuối cùng của hệ thống.'], 422);
        }

        $user->delete();

        return response()->json([
            'message' => 'User deleted',
        ]);
    }

    // ─── Roles & Permissions ────────────────────────────────────────────────────

    public function roles(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensureAdmin($request)) {
            return $forbidden;
        }

        if (Permission::count() === 0) {
            \Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => 'RoleSeeder']);
        }

        $roles = Role::with('permissions')->get();
        $permissions = Permission::all();

        return response()->json([
            'roles' => $roles,
            'permissions' => $permissions,
        ]);
    }

    public function updateRolePermissions(Request $request, Role $role): JsonResponse
    {
        if ($forbidden = $this->ensureAdmin($request)) {
            return $forbidden;
        }

        if ($role->name === 'admin') {
            return response()->json([
                'message' => 'Không thể sửa quyền của Admin tối cao.',
            ], 422);
        }

        $validated = $request->validate([
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string', 'exists:permissions,name'],
        ]);

        $role->syncPermissions($validated['permissions']);
        $role->load('permissions');

        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();

        return response()->json([
            'message' => 'Role permissions updated successfully',
            'role' => $role,
        ]);
    }

    public function courses(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensureAdmin($request)) {
            return $forbidden;
        }

        $query = Course::with('instructor:id,name,avatar', 'category')
            ->withCount('lessons', 'enrollments');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('title', 'like', '%' . $request->search . '%')
                  ->orWhere('description', 'like', '%' . $request->search . '%');
            });
        }

        $courses = $query->orderByDesc('created_at')
            ->paginate($request->integer('per_page', 15));

        return response()->json($courses);
    }

    public function showCourse(Request $request, Course $course): JsonResponse
    {
        if ($forbidden = $this->ensureAdmin($request)) {
            return $forbidden;
        }

        $course->load([
            'instructor:id,name,email,avatar',
            'category:id,name,slug',
            'sections.lessons' => fn ($q) => $q->orderBy('order'),
            'lessons' => fn($q) => $q->select('id','course_id','title','description','video_url','duration','order','is_preview','created_at')->orderBy('order'),
        ]);
        $course->loadCount('lessons', 'enrollments');

        $previewLesson = $course->lessons->firstWhere('is_preview', true) ?? $course->lessons->first();

        return response()->json([
            ...$course->toArray(),
            'preview_urls' => [
                'course' => "/courses/{$course->id}",
                'learn' => $previewLesson ? "/learn/{$course->id}/{$previewLesson->id}" : null,
                'curriculum' => "/admin/courses/{$course->id}/curriculum",
            ],
        ]);
    }

    public function approveCourse(Request $request, Course $course): JsonResponse
    {
        if ($forbidden = $this->ensureAdmin($request)) {
            return $forbidden;
        }

        $course->update([
            'status'       => 'published',
            'published_at' => now(),
            'reject_reason' => null,
        ]);

        // Notify instructor
        \App\Models\Notification::send(
            $course->user_id,
            'course_approved',
            'Khóa học đã được duyệt',
            "Khóa học \"{$course->title}\" đã được phê duyệt và xuất bản thành công.",
            "/courses/{$course->id}"
        );

        return response()->json([
            'message' => 'Course approved',
            'course'  => $course,
        ]);
    }

    public function rejectCourse(Request $request, Course $course): JsonResponse
    {
        if ($forbidden = $this->ensureAdmin($request)) {
            return $forbidden;
        }

        $validated = $request->validate([
            'reject_reason' => ['required', 'string', 'max:1000'],
        ]);

        $course->update([
            'status'        => 'rejected',
            'reject_reason' => $validated['reject_reason'],
        ]);

        // Notify instructor
        \App\Models\Notification::send(
            $course->user_id,
            'course_rejected',
            'Khóa học bị từ chối',
            "Khóa học \"{$course->title}\" bị từ chối: {$validated['reject_reason']}",
            "/instructor/courses/{$course->id}/curriculum"
        );

        return response()->json([
            'message' => 'Course rejected',
            'course'  => $course,
        ]);
    }

    private function hydrateMonthlySeries(Collection $monthKeys, Collection $rows): array
    {
        return $monthKeys->map(function (string $monthKey) use ($rows) {
            return [
                'month' => $monthKey,
                'label' => $this->formatMonthLabel($monthKey),
                'value' => (int) ($rows[$monthKey] ?? 0),
            ];
        })->values()->all();
    }

    private function formatMonthLabel(string $monthKey): string
    {
        [$year, $month] = explode('-', $monthKey);

        return sprintf('%s/%s', $month, substr($year, -2));
    }

    // ─── Categories ─────────────────────────────────────────────────────────────

    public function categories(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensureAdmin($request)) {
            return $forbidden;
        }

        $categories = Category::withCount('courses')
            ->with('parent:id,name')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return response()->json($categories);
    }

    public function storeCategory(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensureAdmin($request)) {
            return $forbidden;
        }

        $validated = $request->validate([
            'name'       => ['required', 'string', 'max:255'],
            'icon'       => ['nullable', 'string', 'max:10'],
            'parent_id'  => ['nullable', 'integer', 'exists:categories,id'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        $validated['slug'] = Str::slug($validated['name']) . '-' . Str::random(4);

        $category = Category::create($validated);
        $category->load('parent:id,name');
        $category->loadCount('courses');

        return response()->json(['message' => 'Category created', 'category' => $category], 201);
    }

    public function updateCategory(Request $request, Category $category): JsonResponse
    {
        if ($forbidden = $this->ensureAdmin($request)) {
            return $forbidden;
        }

        $validated = $request->validate([
            'name'       => ['sometimes', 'required', 'string', 'max:255'],
            'icon'       => ['nullable', 'string', 'max:10'],
            'parent_id'  => ['nullable', 'integer', 'exists:categories,id'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ]);

        if (isset($validated['name'])) {
            $validated['slug'] = Str::slug($validated['name']) . '-' . Str::random(4);
        }

        $category->update($validated);
        $category->load('parent:id,name');
        $category->loadCount('courses');

        return response()->json(['message' => 'Category updated', 'category' => $category]);
    }

    public function destroyCategory(Request $request, Category $category): JsonResponse
    {
        if ($forbidden = $this->ensureAdmin($request)) {
            return $forbidden;
        }

        if ($category->courses()->exists()) {
            return response()->json(['message' => 'Không thể xóa danh mục đang có khóa học.'], 422);
        }

        $category->delete();

        return response()->json(['message' => 'Category deleted']);
    }

    // ─── Orders ─────────────────────────────────────────────────────────────────

    public function orders(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensureAdmin($request)) {
            return $forbidden;
        }

        $query = Order::with([
            'user:id,name,email,avatar',
            'course:id,title,thumbnail',
        ]);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->whereHas('user', fn ($u) => $u->where('name', 'like', '%' . $request->search . '%')
                    ->orWhere('email', 'like', '%' . $request->search . '%'))
                  ->orWhereHas('course', fn ($c) => $c->where('title', 'like', '%' . $request->search . '%'));
            });
        }

        $orders = $query->orderByDesc('created_at')
            ->paginate((int) $request->query('per_page', 20));

        return response()->json($orders);
    }

    public function showOrder(Request $request, Order $order): JsonResponse
    {
        if ($forbidden = $this->ensureAdmin($request)) {
            return $forbidden;
        }

        $order->load(['user:id,name,email,avatar', 'course:id,title,thumbnail,price']);

        return response()->json($order);
    }

    // ─── Reviews ────────────────────────────────────────────────────────────────

    public function reviews(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensureAdmin($request)) {
            return $forbidden;
        }

        $query = Review::with([
            'user:id,name,email,avatar',
            'course:id,title',
        ]);

        if ($request->filled('rating')) {
            $query->where('rating', (int) $request->rating);
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('comment', 'like', '%' . $request->search . '%')
                  ->orWhereHas('user', fn ($u) => $u->where('name', 'like', '%' . $request->search . '%'))
                  ->orWhereHas('course', fn ($c) => $c->where('title', 'like', '%' . $request->search . '%'));
            });
        }

        $reviews = $query->orderByDesc('created_at')
            ->paginate((int) $request->query('per_page', 20));

        return response()->json($reviews);
    }

    public function destroyReview(Request $request, Review $review): JsonResponse
    {
        if ($forbidden = $this->ensureAdmin($request)) {
            return $forbidden;
        }

        $review->delete();

        return response()->json(['message' => 'Review deleted']);
    }

    // ─── Site Settings ─────────────────────────────────────────────────────────

    public function siteSettings(Request $request, MediaService $mediaService): JsonResponse
    {
        if ($forbidden = $this->ensureAdmin($request)) {
            return $forbidden;
        }

        $settings = SiteSetting::getAll();
        $settings['brand_logo_url'] = !empty($settings['brand_logo']) ? $mediaService->getUrl($settings['brand_logo']) : null;
        $settings['site_logo_url'] = !empty($settings['site_logo']) ? $mediaService->getUrl($settings['site_logo']) : $settings['brand_logo_url'];
        $settings['site_favicon_url'] = !empty($settings['site_favicon']) ? $mediaService->getUrl($settings['site_favicon']) : null;
        $settings['auth_page_image_url'] = !empty($settings['auth_page_image']) ? $mediaService->getUrl($settings['auth_page_image']) : null;

        return response()->json($settings);
    }

    public function updateSiteSettings(Request $request, MediaService $mediaService): JsonResponse
    {
        if ($forbidden = $this->ensureAdmin($request)) {
            return $forbidden;
        }

        $validated = $request->validate([
            'theme_color_primary' => ['sometimes', 'nullable', 'string', 'max:32'],
            'theme_color_deep'    => ['sometimes', 'nullable', 'string', 'max:32'],
            'brand_name'       => ['sometimes', 'nullable', 'string', 'max:255'],
            'brand_mark'       => ['sometimes', 'nullable', 'string', 'max:32'],
            'brand_logo'       => ['sometimes', 'nullable', 'string', 'max:2048'],
            'site_title'       => ['sometimes', 'nullable', 'string', 'max:255'],
            'auth_page_image'  => ['sometimes', 'nullable', 'string', 'max:2048'],
            'site_name'        => ['sometimes', 'nullable', 'string', 'max:255'],
            'site_tagline'     => ['sometimes', 'nullable', 'string', 'max:255'],
            'site_description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'site_logo'        => ['sometimes', 'nullable', 'string', 'max:2048'],
            'site_favicon'     => ['sometimes', 'nullable', 'string', 'max:2048'],
            'contact_email'    => ['sometimes', 'nullable', 'email', 'max:255'],
            'contact_phone'    => ['sometimes', 'nullable', 'string', 'max:255'],
            'contact_address'  => ['sometimes', 'nullable', 'string', 'max:500'],
            'support_hours'    => ['sometimes', 'nullable', 'string', 'max:255'],
            'social_facebook'  => ['sometimes', 'nullable', 'string', 'max:2048'],
            'social_youtube'   => ['sometimes', 'nullable', 'string', 'max:2048'],
            'social_tiktok'    => ['sometimes', 'nullable', 'string', 'max:2048'],
            'social_linkedin'  => ['sometimes', 'nullable', 'string', 'max:2048'],
            'social_zalo'      => ['sometimes', 'nullable', 'string', 'max:2048'],
            'smtp_host'        => ['sometimes', 'nullable', 'string', 'max:255'],
            'smtp_port'        => ['sometimes', 'nullable', 'string', 'max:10'],
            'smtp_username'    => ['sometimes', 'nullable', 'string', 'max:255'],
            'smtp_password'    => ['sometimes', 'nullable', 'string', 'max:255'],
            'smtp_encryption'  => ['sometimes', 'nullable', 'string', 'in:tls,ssl,none'],
            'smtp_from_address' => ['sometimes', 'nullable', 'email', 'max:255'],
            'smtp_from_name'   => ['sometimes', 'nullable', 'string', 'max:255'],
            'footer_copyright' => ['sometimes', 'nullable', 'string', 'max:255'],
            'legal_company_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'legal_tax_code'   => ['sometimes', 'nullable', 'string', 'max:255'],
            'terms_url'        => ['sometimes', 'nullable', 'string', 'max:2048'],
            'privacy_url'      => ['sometimes', 'nullable', 'string', 'max:2048'],
            'default_locale'   => ['sometimes', 'nullable', 'string', 'max:32'],
            'default_currency' => ['sometimes', 'nullable', 'string', 'max:32'],
            'timezone'         => ['sometimes', 'nullable', 'string', 'max:64'],
        ]);

        if (array_key_exists('brand_name', $validated) && !array_key_exists('site_name', $validated)) {
            $validated['site_name'] = $validated['brand_name'];
        }
        if (array_key_exists('site_name', $validated) && !array_key_exists('brand_name', $validated)) {
            $validated['brand_name'] = $validated['site_name'];
        }
        if (array_key_exists('brand_logo', $validated) && !array_key_exists('site_logo', $validated)) {
            $validated['site_logo'] = $validated['brand_logo'];
        }
        if (array_key_exists('site_logo', $validated) && !array_key_exists('brand_logo', $validated)) {
            $validated['brand_logo'] = $validated['site_logo'];
        }
        if (array_key_exists('site_title', $validated) && empty($validated['site_title'])) {
            $validated['site_title'] = $validated['brand_name'] ?? $validated['site_name'] ?? null;
        }

        SiteSetting::setMany($validated);
        $settings = SiteSetting::getAll();
        $settings['brand_name'] ??= $settings['site_name'] ?? null;
        $settings['site_name'] ??= $settings['brand_name'] ?? null;
        $settings['brand_logo'] ??= $settings['site_logo'] ?? null;
        $settings['site_logo'] ??= $settings['brand_logo'] ?? null;
        $settings['site_title'] ??= $settings['brand_name'] ?? $settings['site_name'] ?? null;
        $settings['brand_logo_url'] = !empty($settings['brand_logo']) ? $mediaService->getUrl($settings['brand_logo']) : null;
        $settings['site_logo_url'] = !empty($settings['site_logo']) ? $mediaService->getUrl($settings['site_logo']) : $settings['brand_logo_url'];
        $settings['site_favicon_url'] = !empty($settings['site_favicon']) ? $mediaService->getUrl($settings['site_favicon']) : null;

        return response()->json([
            'message' => 'Cập nhật cài đặt thành công',
            'settings' => $settings,
        ]);
    }

    public function publicSiteSettings(MediaService $mediaService): JsonResponse
    {
        $keys = [
            'theme_color_primary', 'theme_color_deep',
            'brand_name', 'brand_mark', 'brand_logo', 'site_title', 'auth_page_image',
            'site_name', 'site_tagline', 'site_description', 'site_logo', 'site_favicon',
            'contact_email', 'contact_phone', 'contact_address', 'support_hours',
            'social_facebook', 'social_youtube', 'social_tiktok', 'social_linkedin', 'social_zalo',
            'footer_copyright', 'legal_company_name', 'legal_tax_code', 'terms_url', 'privacy_url',
            'default_locale', 'default_currency', 'timezone',
        ];
        $settings = SiteSetting::whereIn('key', $keys)->pluck('value', 'key')->toArray();

        $brandName = $settings['brand_name'] ?? $settings['site_name'] ?? null;
        $brandLogo = $settings['brand_logo'] ?? $settings['site_logo'] ?? null;
        $siteTitle = $settings['site_title'] ?? $brandName;

        return response()->json([
            'theme_color_primary' => $settings['theme_color_primary'] ?? '#0f766e',
            'theme_color_deep'    => $settings['theme_color_deep'] ?? '#0d655e',
            'brand_name' => $brandName,
            'brand_mark' => $settings['brand_mark'] ?? null,
            'brand_logo' => !empty($brandLogo) ? $mediaService->getUrl($brandLogo) : null,
            'site_title' => $siteTitle,
            'auth_page_image' => !empty($settings['auth_page_image']) ? $mediaService->getUrl($settings['auth_page_image']) : null,
            'site_name' => $settings['site_name'] ?? $brandName,
            'site_tagline' => $settings['site_tagline'] ?? null,
            'site_description' => $settings['site_description'] ?? null,
            'site_logo' => !empty($brandLogo) ? $mediaService->getUrl($brandLogo) : null,
            'site_favicon' => !empty($settings['site_favicon']) ? $mediaService->getUrl($settings['site_favicon']) : null,
            'contact_email' => $settings['contact_email'] ?? null,
            'contact_phone' => $settings['contact_phone'] ?? null,
            'contact_address' => $settings['contact_address'] ?? null,
            'support_hours' => $settings['support_hours'] ?? null,
            'social_facebook' => $settings['social_facebook'] ?? null,
            'social_youtube' => $settings['social_youtube'] ?? null,
            'social_tiktok' => $settings['social_tiktok'] ?? null,
            'social_linkedin' => $settings['social_linkedin'] ?? null,
            'social_zalo' => $settings['social_zalo'] ?? null,
            'footer_copyright' => $settings['footer_copyright'] ?? null,
            'legal_company_name' => $settings['legal_company_name'] ?? null,
            'legal_tax_code' => $settings['legal_tax_code'] ?? null,
            'terms_url' => $settings['terms_url'] ?? null,
            'privacy_url' => $settings['privacy_url'] ?? null,
            'default_locale' => $settings['default_locale'] ?? null,
            'default_currency' => $settings['default_currency'] ?? null,
            'timezone' => $settings['timezone'] ?? null,
        ]);
    }

    public function dashboardExtra(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensureAdmin($request)) {
            return $forbidden;
        }

        // ── 1. Enrollments by day (last 14 days) ──────────────────────────────
        $days = collect(range(13, 0))->map(fn (int $i) => now()->subDays($i)->format('Y-m-d'));

        $enrollRows = Enrollment::query()
            ->selectRaw("DATE(enrolled_at) as day, COUNT(*) as total")
            ->where('enrolled_at', '>=', now()->subDays(13)->startOfDay())
            ->groupBy('day')
            ->pluck('total', 'day');

        $dailyEnrollments = $days->map(fn (string $day) => [
            'date'  => $day,
            'label' => \Carbon\Carbon::parse($day)->locale('vi')->isoFormat('D/M'),
            'value' => (int) ($enrollRows[$day] ?? 0),
        ])->values();

        // ── 2. Class progress (top 6 lớp hành chính có nhiều SV nhất) ─────────
        $topClasses = \App\Models\AdministrativeClass::query()
            ->withCount('students')
            ->orderByDesc('students_count')
            ->limit(6)
            ->get(['id', 'code', 'name']);

        $classProgress = $topClasses->map(function ($cls) {
            $total = $cls->students_count;
            if ($total === 0) return ['label' => $cls->code, 'value' => 0];

            // % SV đã có ít nhất 1 enrollment
            $enrolled = Enrollment::query()
                ->whereIn('user_id', function ($q) use ($cls) {
                    $q->select('id')->from('users')->where('administrative_class_id', $cls->id);
                })
                ->distinct('user_id')
                ->count('user_id');

            return [
                'label' => $cls->code,
                'value' => $total > 0 ? (int) round(($enrolled / $total) * 100) : 0,
            ];
        })->values();

        // ── 3. Upcoming class sections (current open sections) ────────────────
        $upcomingSections = ClassSection::query()
            ->with([
                'course:id,title',
                'term:id,name,code',
                'cohort:id,name,code',
                'lecturer:id,name',
            ])
            ->where('status', 'open')
            ->orderByDesc('id')
            ->limit(5)
            ->get(['id', 'code', 'name', 'course_id', 'term_id', 'cohort_id', 'lecturer_id', 'enrolled_count', 'capacity']);

        // ── 4. Latest notifications for admin ─────────────────────────────────
        $notifications = \App\Models\Notification::query()
            ->where('user_id', $request->user()->id)
            ->latest()
            ->limit(5)
            ->get(['id', 'title', 'message', 'type', 'created_at', 'read_at']);

        return response()->json([
            'daily_enrollments' => $dailyEnrollments,
            'class_progress'    => $classProgress,
            'upcoming_sections' => $upcomingSections,
            'notifications'     => $notifications,
        ]);
    }
}

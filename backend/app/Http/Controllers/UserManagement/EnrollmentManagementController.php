<?php

namespace App\Http\Controllers\UserManagement;

use App\Concerns\ScopesByUnit;
use App\Http\Controllers\Controller;
use App\Models\AcademicYear;
use App\Models\AdministrativeClass;
use App\Models\AdministrativeClassTerm;
use App\Models\ClassSection;
use App\Models\Cohort;
use App\Models\Course;
use App\Models\CurriculumCourse;
use App\Models\Enrollment;
use App\Models\Notification;
use App\Models\Term;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class EnrollmentManagementController extends Controller
{
    use ScopesByUnit;

    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (!$user || !\App\Support\Authorize::allows($user, ['manage_courses', 'manage_lessons', 'manage_exams', 'manage_grades', 'view_dashboard'])) {
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
        if ($request->filled('search')) {
            $q = $request->input('search');
            $query->whereHas('user', fn ($u) => $u->where('name', 'like', "%{$q}%")
                ->orWhere('student_code', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%"));
        }

        $query = $this->scopeEnrollmentsByUnits($query, $user);

        $perPage = max(1, min(100, (int) $request->integer('per_page', 20)));

        return response()->json($query->paginate($perPage));
    }

    public function bulkEnrollCore(Request $request, Cohort $cohort): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (!$user || !\App\Support\Authorize::allows($user, ['manage_courses', 'manage_lessons', 'manage_exams', 'manage_grades', 'view_dashboard'])) {
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

    /**
     * List term_number 1..N (N = highest term_number in the class's curriculum)
     * alongside whichever real Term is currently mapped to each.
     *
     * Tự tạo năm học + HK1/HK2 theo cohort.start_year (mỗi năm 2 kỳ),
     * rồi gợi ý ánh xạ: Kỳ 1→HK1 năm đầu, Kỳ 2→HK2 năm đầu, Kỳ 3→HK1 năm sau…
     */
    public function classTermMap(Request $request, AdministrativeClass $administrativeClass): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (!$user || !\App\Support\Authorize::allows($user, ['manage_courses', 'manage_lessons', 'manage_exams', 'manage_grades', 'view_dashboard'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if (!$administrativeClass->curriculum_id) {
            return response()->json(['message' => 'Lớp hành chính chưa được gán CTĐT.'], 422);
        }

        $administrativeClass->loadMissing('cohort:id,code,name,start_year,end_year');

        $maxTermNumber = (int) CurriculumCourse::where('curriculum_id', $administrativeClass->curriculum_id)->max('term_number');
        if ($maxTermNumber < 1) {
            return response()->json([
                'curriculum_id' => $administrativeClass->curriculum_id,
                'term_count' => 0,
                'map' => [],
                'term_options' => [],
                'cohort' => $administrativeClass->cohort,
                'auto_filled' => false,
            ]);
        }

        $startYear = (int) ($administrativeClass->cohort?->start_year
            ?: ($administrativeClass->expected_graduation_year
                ? ((int) $administrativeClass->expected_graduation_year - 4)
                : (int) date('Y')));

        $institutionId = (int) ($administrativeClass->institution_id
            ?: AcademicYear::query()->value('institution_id')
            ?: 1);

        $calendarSlots = $this->ensureAcademicCalendarForCohort($institutionId, $startYear, $maxTermNumber);

        $existing = AdministrativeClassTerm::where('administrative_class_id', $administrativeClass->id)
            ->get()
            ->keyBy('term_number');

        $existingIds = $existing->pluck('term_id')->filter()->values();
        $hasCollision = $existingIds->count() !== $existingIds->unique()->count();
        // Chỉ có 1 năm học trong map cũ (vd. lặp HK1/HK2) → coi như chưa gắn đúng năm.
        $existingYearCount = Term::query()
            ->whereIn('id', $existingIds)
            ->distinct('academic_year_id')
            ->count('academic_year_id');
        $needsYearRepair = $existingIds->isNotEmpty()
            && $maxTermNumber > 2
            && $existingYearCount < (int) ceil($maxTermNumber / 2);

        $forceSuggest = $hasCollision || $needsYearRepair || $existingIds->isEmpty();

        $map = [];
        $autoFilled = false;
        for ($n = 1; $n <= $maxTermNumber; $n++) {
            $suggested = $this->suggestTermForCurriculumKy($calendarSlots, $n);
            $currentId = $existing->get($n)?->term_id;

            if ($forceSuggest && $suggested) {
                $currentId = $suggested->id;
                $autoFilled = true;
            } elseif (!$currentId && $suggested) {
                $currentId = $suggested->id;
                $autoFilled = true;
            }

            $currentTerm = $currentId
                ? ($calendarSlots->firstWhere('id', (int) $currentId)
                    ?: Term::query()->with('academicYear:id,name')->find($currentId))
                : null;
            if ($currentTerm && !$currentTerm->relationLoaded('academicYear')) {
                $currentTerm->load('academicYear:id,name');
            }

            $map[] = [
                'term_number' => $n,
                'term_id' => $currentId ? (int) $currentId : null,
                'academic_year_id' => $currentTerm?->academic_year_id ? (int) $currentTerm->academic_year_id : null,
                'suggested_term_id' => $suggested?->id,
                'suggested_academic_year_id' => $suggested?->academic_year_id,
                'suggested_label' => $suggested ? $suggested->displayName() : null,
                'start_date' => optional($currentTerm?->start_date)?->toDateString(),
                'end_date' => optional($currentTerm?->end_date)?->toDateString(),
            ];
        }

        $slotIds = $calendarSlots->pluck('id')->all();
        $yearsPayload = $this->buildYearTermOptions($slotIds);

        // Bổ sung năm/học kỳ ngoài range khoá (nếu cần chỉnh tay).
        $extraYearIds = Term::query()
            ->whereNotIn('id', $slotIds)
            ->orderByDesc('academic_year_id')
            ->limit(50)
            ->pluck('academic_year_id')
            ->unique()
            ->filter()
            ->values()
            ->all();

        if ($extraYearIds) {
            $extraYears = $this->buildYearTermOptions(
                Term::query()->whereIn('academic_year_id', $extraYearIds)->pluck('id')->all()
            );
            $knownYearIds = collect($yearsPayload)->pluck('id')->all();
            foreach ($extraYears as $yearRow) {
                if (!in_array($yearRow['id'], $knownYearIds, true)) {
                    $yearsPayload[] = $yearRow;
                }
            }
        }

        return response()->json([
            'curriculum_id' => $administrativeClass->curriculum_id,
            'term_count' => $maxTermNumber,
            'start_year' => $startYear,
            'end_year' => $administrativeClass->cohort?->end_year,
            'map' => $map,
            'years' => $yearsPayload,
            'term_options' => collect($yearsPayload)->flatMap(fn ($y) => collect($y['terms'])->map(fn ($term) => [
                'value' => $term['id'],
                'label' => $term['label'],
                'code' => $term['code'],
                'academic_year_id' => $y['id'],
                'academic_year' => $y['name'],
                'start_date' => $term['start_date'],
                'end_date' => $term['end_date'],
            ]))->values(),
            'cohort' => $administrativeClass->cohort,
            'auto_filled' => $autoFilled,
        ]);
    }

    /**
     * Đảm bảo có đủ năm học liên tiếp từ $startYear, mỗi năm HK1 + HK2,
     * đủ để phủ $termCount kỳ CTĐT (2 kỳ / năm).
     *
     * @return Collection<int, Term> indexed list of terms in order: Y0-HK1, Y0-HK2, Y1-HK1…
     */
    private function ensureAcademicCalendarForCohort(int $institutionId, int $startYear, int $termCount): Collection
    {
        $yearsNeeded = max(1, (int) ceil($termCount / 2));
        $ordered = collect();

        for ($i = 0; $i < $yearsNeeded; $i++) {
            $yStart = $startYear + $i;
            $yEnd = $yStart + 1;
            $yearName = "{$yStart}-{$yEnd}";

            $year = AcademicYear::query()->updateOrCreate(
                ['institution_id' => $institutionId, 'name' => $yearName],
                [
                    'start_date' => sprintf('%d-08-01', $yStart),
                    'end_date' => sprintf('%d-07-31', $yEnd),
                    'is_current' => $yearName === '2025-2026',
                    'status' => 'active',
                ]
            );

            // Chỉ một năm hiện hành.
            if ($year->is_current) {
                AcademicYear::query()
                    ->where('institution_id', $institutionId)
                    ->where('id', '!=', $year->id)
                    ->update(['is_current' => false]);
            }

            $hk1 = Term::query()->updateOrCreate(
                ['academic_year_id' => $year->id, 'code' => 'HK1'],
                [
                    'name' => 'Học kỳ 1',
                    'start_date' => sprintf('%d-09-01', $yStart),
                    'end_date' => sprintf('%d-01-15', $yEnd),
                    'is_current' => $year->is_current,
                    'status' => 'active',
                ]
            );

            $hk2 = Term::query()->updateOrCreate(
                ['academic_year_id' => $year->id, 'code' => 'HK2'],
                [
                    'name' => 'Học kỳ 2',
                    'start_date' => sprintf('%d-02-01', $yEnd),
                    'end_date' => sprintf('%d-06-30', $yEnd),
                    'is_current' => false,
                    'status' => 'active',
                ]
            );

            $hk1->setRelation('academicYear', $year);
            $hk2->setRelation('academicYear', $year);

            $ordered->push($hk1);
            $ordered->push($hk2);
        }

        return $ordered->values();
    }

    private function suggestTermForCurriculumKy(Collection $orderedTerms, int $termNumber): ?Term
    {
        $index = $termNumber - 1; // 0-based: 0=HK1 năm 1, 1=HK2 năm 1, 2=HK1 năm 2…
        return $orderedTerms->get($index);
    }

    /** Label dropdown: "HK1 — 2024-2025 (01/09/2024 – 15/01/2025)" */
    private function formatTermOptionLabel(Term $term): string
    {
        $code = trim((string) ($term->code ?: $term->name));
        $year = $term->academicYear?->name;
        $base = $year ? "{$code} — {$year}" : $term->displayName();

        if ($term->start_date && $term->end_date) {
            $from = $term->start_date->format('d/m/Y');
            $to = $term->end_date->format('d/m/Y');
            return "{$base} ({$from} – {$to})";
        }

        return $base;
    }

    /**
     * @param  list<int|string>  $termIds
     * @return list<array{id:int,name:string,start_date:?string,end_date:?string,terms:list<array<string,mixed>>}>
     */
    private function buildYearTermOptions(array $termIds): array
    {
        if ($termIds === []) {
            return [];
        }

        $terms = Term::query()
            ->with('academicYear:id,name,start_date,end_date')
            ->whereIn('id', $termIds)
            ->get();

        return $terms
            ->groupBy('academic_year_id')
            ->map(function (Collection $group) {
                /** @var Term $first */
                $first = $group->first();
                $year = $first->academicYear;
                $sorted = $group->sortBy(fn (Term $t) => $t->code === 'HK1' ? 0 : ($t->code === 'HK2' ? 1 : 2))->values();

                return [
                    'id' => (int) $year?->id,
                    'name' => (string) ($year?->name ?: '—'),
                    'start_date' => optional($year?->start_date)?->toDateString(),
                    'end_date' => optional($year?->end_date)?->toDateString(),
                    'terms' => $sorted->map(fn (Term $term) => [
                        'id' => $term->id,
                        'code' => $term->code,
                        'name' => $term->name,
                        'label' => $this->formatTermOptionLabel($term),
                        'start_date' => optional($term->start_date)?->toDateString(),
                        'end_date' => optional($term->end_date)?->toDateString(),
                    ])->values()->all(),
                ];
            })
            ->sortBy('name')
            ->values()
            ->all();
    }

    /**
     * Bulk upsert the term_number -> Term mapping for one class. Sending a
     * null term_id for a term_number clears that mapping.
     */
    public function saveClassTermMap(Request $request, AdministrativeClass $administrativeClass): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (!$user || !\App\Support\Authorize::allows($user, ['manage_courses', 'manage_lessons', 'manage_exams', 'manage_grades', 'view_dashboard'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'map' => ['required', 'array'],
            'map.*.term_number' => ['required', 'integer', 'min:1', 'max:12'],
            'map.*.term_id' => ['nullable', 'integer', 'exists:terms,id'],
        ]);

        foreach ($validated['map'] as $item) {
            if (empty($item['term_id'])) {
                AdministrativeClassTerm::where('administrative_class_id', $administrativeClass->id)
                    ->where('term_number', $item['term_number'])
                    ->delete();
                continue;
            }

            AdministrativeClassTerm::updateOrCreate(
                ['administrative_class_id' => $administrativeClass->id, 'term_number' => $item['term_number']],
                ['term_id' => $item['term_id']],
            );
        }

        return response()->json(['message' => 'Đã lưu ánh xạ học kỳ.']);
    }

    /**
     * Enroll every student currently in the class into every required course
     * of its curriculum, using each course's term_number to look up the real
     * Term via the class's term map (classTermMap/saveClassTermMap above).
     * Courses whose term_number has no mapped Term yet are skipped and
     * reported back so the admin knows what's still missing.
     */
    public function enrollClassToCurriculum(Request $request, AdministrativeClass $administrativeClass): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (!$user || !\App\Support\Authorize::allows($user, ['manage_courses', 'manage_lessons', 'manage_exams', 'manage_grades', 'view_dashboard'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if (!$administrativeClass->curriculum_id) {
            return response()->json(['message' => 'Lớp hành chính chưa được gán CTĐT.'], 422);
        }

        $studentIds = $administrativeClass->students()->pluck('users.id');

        if ($studentIds->isEmpty()) {
            return response()->json([
                'message' => 'Lớp hành chính chưa có sinh viên.',
                'administrative_class_id' => $administrativeClass->id,
                'created' => 0,
                'skipped' => 0,
            ]);
        }

        $courses = CurriculumCourse::where('curriculum_id', $administrativeClass->curriculum_id)
            ->where('is_required', true)
            ->get(['course_id', 'term_number']);

        if ($courses->isEmpty()) {
            return response()->json([
                'message' => 'CTĐT của lớp này chưa có môn bắt buộc nào trong khung chương trình.',
                'administrative_class_id' => $administrativeClass->id,
                'created' => 0,
                'skipped' => 0,
            ]);
        }

        $termMap = AdministrativeClassTerm::where('administrative_class_id', $administrativeClass->id)
            ->pluck('term_id', 'term_number');

        $missingTermNumbers = $courses->pluck('term_number')->unique()->diff($termMap->keys())->sort()->values();

        $created = 0;
        $skipped = 0;
        $coursesSkippedNoTerm = 0;

        DB::transaction(function () use ($courses, $studentIds, $termMap, $administrativeClass, &$created, &$skipped, &$coursesSkippedNoTerm) {
            foreach ($courses as $course) {
                $termId = $termMap->get($course->term_number);
                if (!$termId) {
                    $coursesSkippedNoTerm++;
                    continue;
                }

                foreach ($studentIds as $studentId) {
                    $existing = Enrollment::where('user_id', $studentId)
                        ->where('course_id', $course->course_id)
                        ->first();

                    if ($existing) {
                        $skipped++;
                        continue;
                    }

                    Enrollment::create([
                        'user_id' => $studentId,
                        'course_id' => $course->course_id,
                        'term_id' => $termId,
                        'cohort_id' => $administrativeClass->cohort_id,
                        'enrollment_source' => 'academic',
                        'enrolled_at' => now(),
                    ]);
                    $created++;
                }
            }
        });

        return response()->json([
            'message' => 'Đã ghi danh cả lớp vào CTĐT.',
            'administrative_class_id' => $administrativeClass->id,
            'curriculum_id' => $administrativeClass->curriculum_id,
            'students' => $studentIds->count(),
            'courses' => $courses->count(),
            'created' => $created,
            'skipped' => $skipped,
            'courses_skipped_no_term' => $coursesSkippedNoTerm,
            'missing_term_mapping' => $missingTermNumbers,
        ]);
    }

    public function students(Request $request, Cohort $cohort): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (!$user || !\App\Support\Authorize::allows($user, ['manage_courses', 'manage_lessons', 'manage_exams', 'manage_grades', 'view_dashboard'])) {
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

        if (!$user || !\App\Support\Authorize::allows($user, ['manage_courses', 'manage_lessons', 'manage_exams', 'manage_grades', 'view_dashboard'])) {
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
        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }
        if ($request->filled('q')) {
            $q = $request->input('q');
            $query->where(function (Builder $w) use ($q) {
                $w->where('code', 'like', "%{$q}%")
                    ->orWhere('name', 'like', "%{$q}%");
            });
        }

        if (!\App\Support\Authorize::isAdmin($user)) {
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

        if (!$user || !\App\Support\Authorize::isAdmin($user)) {
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

        if (!$user || !\App\Support\Authorize::isAdmin($user)) {
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

        if (!$user || !\App\Support\Authorize::isAdmin($user)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $classSection->delete();

        return response()->json(['message' => 'Class section deleted']);
    }

    public function enrollManual(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (!$user || !\App\Support\Authorize::allows($user, ['manage_courses', 'manage_lessons', 'manage_exams', 'manage_grades', 'view_dashboard'])) {
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

        if (!$user || !\App\Support\Authorize::allows($user, ['manage_courses', 'manage_lessons', 'manage_exams', 'manage_grades', 'view_dashboard'])) {
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

        if (!$user || !\App\Support\Authorize::allows($user, ['manage_courses', 'manage_lessons', 'manage_exams', 'manage_grades', 'view_dashboard'])) {
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

        if (!$user || !\App\Support\Authorize::allows($user, ['manage_courses', 'manage_lessons', 'manage_exams', 'manage_grades', 'view_dashboard'])) {
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

        if (!$user || !\App\Support\Authorize::allows($user, ['manage_courses', 'manage_lessons', 'manage_exams', 'manage_grades', 'view_dashboard'])) {
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

        if (!$user || !\App\Support\Authorize::allows($user, ['manage_courses', 'manage_lessons', 'manage_exams', 'manage_grades', 'view_dashboard'])) {
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
        if (!$user || !\App\Support\Authorize::allows($user, ['manage_courses', 'manage_lessons', 'manage_exams', 'manage_grades', 'view_dashboard'])) {
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

        // Pre-compute completed course ids per student using lesson_progress
        $completedCoursesByStudent = \DB::table('lesson_progress')
            ->join('lessons', 'lessons.id', '=', 'lesson_progress.lesson_id')
            ->whereIn('lesson_progress.user_id', $students->pluck('id'))
            ->whereIn('lessons.course_id', $courseIds)
            ->where('lesson_progress.completed', true)
            ->select('lesson_progress.user_id', 'lessons.course_id')
            ->distinct()
            ->get()
            ->groupBy('user_id');

        foreach ($students as $student) {
            $completedCount = 0;
            $creditsEarned = 0;

            $completedForStudent = $completedCoursesByStudent->get($student->id, collect());
            $completedCourseIdSet = $completedForStudent->pluck('course_id');

            foreach ($courseIds as $cid) {
                if ($completedCourseIdSet->contains($cid)) {
                    $completedCount++;
                    $curCourse = $curriculumCourses->firstWhere('course_id', $cid);
                    $courseObj = $curCourse ? $curCourse->course : null;
                    $creditsEarned += $courseObj ? ($courseObj->credit_value ?? $curCourse->credits ?? 0) : 0;
                }
            }

            $completionRate = count($courseIds) > 0 ? round(($completedCount / count($courseIds)) * 100, 1) : 0;
            $avgProgress = $completionRate; // use completion rate as proxy for progress

            $isSlow = ($completionRate < 50 && count($courseIds) > 0);
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
                ->whereExists(function ($q) use ($course) {
                    $q->from('lesson_progress')
                        ->join('lessons', 'lessons.id', '=', 'lesson_progress.lesson_id')
                        ->whereColumn('lesson_progress.user_id', 'enrollments.user_id')
                        ->where('lessons.course_id', $course->id)
                        ->where('lesson_progress.completed', true);
                })
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

    /**
     * Returns a 422 JsonResponse if adding $studentIds to $classId would exceed
     * the class's capacity, or null if there's room (or no capacity configured).
     * Only students not already in the class count as new additions.
     */
    private function capacityOverflowResponse(int $classId, array $studentIds): ?JsonResponse
    {
        $class = AdministrativeClass::find($classId);
        if (!$class || !$class->capacity || $class->capacity <= 0) {
            return null;
        }

        $studentIds = array_values(array_unique($studentIds));
        $existingInClass = User::where('administrative_class_id', $classId)->count();
        $alreadyInClass = User::where('administrative_class_id', $classId)
            ->whereIn('id', $studentIds)
            ->count();
        $newAdditions = count($studentIds) - $alreadyInClass;
        $projected = $existingInClass + $newAdditions;

        if ($projected > $class->capacity) {
            $remaining = max(0, $class->capacity - $existingInClass);
            return response()->json([
                'message' => "Lớp \"{$class->code}\" chỉ còn chỗ cho {$remaining} sinh viên (sĩ số tối đa {$class->capacity}, hiện có {$existingInClass}), nhưng bạn đang thêm {$newAdditions} sinh viên mới.",
                'capacity' => $class->capacity,
                'current_count' => $existingInClass,
                'remaining' => $remaining,
                'attempted_new' => $newAdditions,
            ], 422);
        }

        return null;
    }

    /**
     * Chi tiết 1 lớp hành chính + toàn bộ danh sách sinh viên đang thuộc lớp
     * (dùng cho trang xem chi tiết lớp / gỡ SV khỏi lớp / đổi trạng thái học vụ).
     */
    public function adminClassDetail(\Illuminate\Http\Request $request, AdministrativeClass $administrativeClass): \Illuminate\Http\JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        if (!$user || !\App\Support\Authorize::allows($user, ['manage_courses', 'manage_lessons', 'manage_exams', 'manage_grades', 'view_dashboard'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $administrativeClass->load(['unit:id,name,code', 'program:id,name,code', 'major:id,name,code', 'cohort:id,name,code', 'advisor:id,name,email', 'curriculum:id,name,code']);
        $administrativeClass->loadCount('students');

        $students = User::query()
            ->where('administrative_class_id', $administrativeClass->id)
            ->orderBy('name')
            ->get([
                'id', 'name', 'email', 'avatar', 'student_code', 'study_status',
                'gender', 'phone', 'date_of_birth',
            ]);

        return response()->json([
            'class' => $administrativeClass,
            'students' => $students,
        ]);
    }

    public function enrollStudentsToAdminClass(\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        if (!$user || !\App\Support\Authorize::allows($user, ['manage_courses', 'manage_lessons', 'manage_exams', 'manage_grades', 'view_dashboard'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'administrative_class_id' => ['required', 'integer', 'exists:administrative_classes,id'],
            'student_ids' => ['required', 'array'],
            'student_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $classId = $validated['administrative_class_id'];
        $studentIds = $validated['student_ids'];

        if ($overflow = $this->capacityOverflowResponse($classId, $studentIds)) {
            return $overflow;
        }

        // Cập nhật lớp hành chính cho danh sách student
        $updated = \App\Models\User::whereIn('id', $studentIds)
            ->where('user_type', 'student')
            ->update(['administrative_class_id' => $classId]);

        return response()->json([
            'message' => "Ghi danh thành công {$updated} sinh viên vào lớp hành chính.",
            'updated' => $updated,
        ]);
    }

    public function importStudentsToAdminClassPreview(\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        if (!$user || !\App\Support\Authorize::allows($user, ['manage_courses', 'manage_lessons', 'manage_exams', 'manage_grades', 'view_dashboard'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $request->validate([
            'administrative_class_id' => ['required', 'integer', 'exists:administrative_classes,id'],
            'file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $classId = $request->integer('administrative_class_id');
        $file = $request->file('file');
        $filePath = $file->getRealPath();

        $rows = [];
        if (($handle = fopen($filePath, 'r')) !== false) {
            $header = fgetcsv($handle, 1000, ',');
            
            $rowNum = 1;
            while (($data = fgetcsv($handle, 1000, ',')) !== false) {
                $rowNum++;
                if (count($data) < 1) continue;

                $studentCode = trim($data[0]);

                if (empty($studentCode)) {
                    continue;
                }

                $rows[] = [
                    'row_number' => $rowNum,
                    'student_code' => $studentCode,
                ];
            }
            fclose($handle);
        }

        if (empty($rows)) {
            return response()->json(['message' => 'Tệp nhập rỗng hoặc định dạng không đúng. Cột 1 phải là Mã sinh viên.'], 422);
        }

        $previewData = [];
        $validRowsToSave = [];
        $totalRows = count($rows);
        $validCount = 0;
        $invalidCount = 0;

        foreach ($rows as $row) {
            $student = \App\Models\User::where('student_code', $row['student_code'])->first();

            if (!$student) {
                $previewData[] = [
                    'row_number' => $row['row_number'],
                    'student_code' => $row['student_code'],
                    'student_name' => null,
                    'status' => 'invalid',
                    'message' => 'Mã sinh viên không tồn tại.',
                ];
                $invalidCount++;
                continue;
            }

            if ($student->administrative_class_id == $classId) {
                $previewData[] = [
                    'row_number' => $row['row_number'],
                    'student_code' => $row['student_code'],
                    'student_name' => $student->name,
                    'status' => 'invalid',
                    'message' => 'Sinh viên đã thuộc lớp hành chính này rồi.',
                ];
                $invalidCount++;
                continue;
            }

            $className = $student->administrativeClass ? $student->administrativeClass->code : '';
            $previewData[] = [
                'row_number' => $row['row_number'],
                'student_code' => $row['student_code'],
                'student_name' => $student->name,
                'status' => 'valid',
                'message' => 'Hợp lệ' . ($className ? " (Sẽ chuyển từ lớp " . $className . ")" : ""),
            ];
            
            $validRowsToSave[] = [
                'student_id' => $student->id,
                'student_code' => $student->student_code,
                'student_name' => $student->name,
            ];
            $validCount++;
        }

        $importToken = 'temp_admin_class_import_' . \Illuminate\Support\Str::random(16);
        \Illuminate\Support\Facades\Cache::put($importToken, [
            'class_id' => $classId,
            'students' => $validRowsToSave,
        ], 3600);

        return response()->json([
            'import_token' => $importToken,
            'total_rows' => $totalRows,
            'valid_rows' => $validCount,
            'invalid_rows' => $invalidCount,
            'preview_data' => $previewData,
        ]);
    }

    public function importStudentsToAdminClassExecute(\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        if (!$user || !\App\Support\Authorize::allows($user, ['manage_courses', 'manage_lessons', 'manage_exams', 'manage_grades', 'view_dashboard'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'import_token' => ['required', 'string'],
        ]);

        $importToken = $validated['import_token'];
        $cachedData = \Illuminate\Support\Facades\Cache::get($importToken);

        if (!$cachedData || !is_array($cachedData)) {
            return response()->json(['message' => 'Dữ liệu xem trước đã hết hạn hoặc không hợp lệ. Vui lòng tải lại tệp.'], 422);
        }

        $classId = $cachedData['class_id'];
        $students = $cachedData['students'];
        $studentIds = array_column($students, 'student_id');

        if ($overflow = $this->capacityOverflowResponse($classId, $studentIds)) {
            return $overflow;
        }

        $updated = \App\Models\User::whereIn('id', $studentIds)
            ->where('user_type', 'student')
            ->update(['administrative_class_id' => $classId]);

        \Illuminate\Support\Facades\Cache::forget($importToken);

        return response()->json([
            'message' => "Đã ghi danh thành công {$updated} sinh viên vào lớp hành chính.",
            'created' => $updated,
        ]);
    }

    /**
     * Import hàng loạt cho NHIỀU lớp hành chính cùng lúc: CSV gồm 2 cột
     * (Mã sinh viên, Mã lớp), khác với importStudentsToAdminClassPreview vốn
     * chỉ nhận 1 lớp đã chọn sẵn trên UI.
     */
    public function bulkImportAdminClassesPreview(\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        if (!$user || !\App\Support\Authorize::allows($user, ['manage_courses', 'manage_lessons', 'manage_exams', 'manage_grades', 'view_dashboard'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $request->validate([
            'file' => ['required', 'file', 'mimes:csv,txt'],
        ]);

        $filePath = $request->file('file')->getRealPath();
        $rows = [];
        if (($handle = fopen($filePath, 'r')) !== false) {
            fgetcsv($handle, 2000, ',');

            $rowNum = 1;
            while (($data = fgetcsv($handle, 2000, ',')) !== false) {
                $rowNum++;
                if (count(array_filter($data, fn ($v) => trim((string) $v) !== '')) === 0) {
                    continue;
                }

                $rows[] = [
                    'row_number' => $rowNum,
                    'student_code' => trim((string) ($data[0] ?? '')),
                    'class_code' => trim((string) ($data[1] ?? '')),
                ];
            }
            fclose($handle);
        }

        if (empty($rows)) {
            return response()->json(['message' => 'Tệp nhập rỗng hoặc định dạng không đúng. Cột 1: Mã sinh viên, cột 2: Mã lớp.'], 422);
        }

        $classByCode = AdministrativeClass::query()->pluck('id', 'code');
        $classCapacity = AdministrativeClass::query()->pluck('capacity', 'id');
        $currentCountByClass = User::query()
            ->whereNotNull('administrative_class_id')
            ->selectRaw('administrative_class_id, count(*) as c')
            ->groupBy('administrative_class_id')
            ->pluck('c', 'administrative_class_id');
        $projectedNewByClass = [];

        $previewData = [];
        $validRowsToSave = [];
        $validCount = 0;
        $invalidCount = 0;

        foreach ($rows as $row) {
            $studentCode = $row['student_code'];
            $classCode = $row['class_code'];

            if ($studentCode === '' || $classCode === '') {
                $previewData[] = [
                    'row_number' => $row['row_number'],
                    'student_code' => $studentCode,
                    'student_name' => null,
                    'status' => 'invalid',
                    'message' => 'Thiếu mã sinh viên hoặc mã lớp.',
                ];
                $invalidCount++;
                continue;
            }

            $student = User::where('student_code', $studentCode)->first();
            if (!$student) {
                $previewData[] = [
                    'row_number' => $row['row_number'],
                    'student_code' => $studentCode,
                    'student_name' => null,
                    'status' => 'invalid',
                    'message' => 'Mã sinh viên không tồn tại.',
                ];
                $invalidCount++;
                continue;
            }

            $classId = $classByCode[$classCode] ?? null;
            if (!$classId) {
                $previewData[] = [
                    'row_number' => $row['row_number'],
                    'student_code' => $studentCode,
                    'student_name' => $student->name,
                    'status' => 'invalid',
                    'message' => 'Mã lớp không tồn tại.',
                ];
                $invalidCount++;
                continue;
            }

            if ((int) $student->administrative_class_id === (int) $classId) {
                $previewData[] = [
                    'row_number' => $row['row_number'],
                    'student_code' => $studentCode,
                    'student_name' => $student->name,
                    'status' => 'invalid',
                    'message' => 'Sinh viên đã thuộc lớp này rồi.',
                ];
                $invalidCount++;
                continue;
            }

            $capacity = $classCapacity[$classId] ?? null;
            if ($capacity) {
                $existing = (int) ($currentCountByClass[$classId] ?? 0);
                $projected = $existing + ($projectedNewByClass[$classId] ?? 0) + 1;
                if ($projected > $capacity) {
                    $previewData[] = [
                        'row_number' => $row['row_number'],
                        'student_code' => $studentCode,
                        'student_name' => $student->name,
                        'status' => 'invalid',
                        'message' => "Lớp {$classCode} đã đủ sĩ số ({$capacity}).",
                    ];
                    $invalidCount++;
                    continue;
                }
                $projectedNewByClass[$classId] = ($projectedNewByClass[$classId] ?? 0) + 1;
            }

            $previewData[] = [
                'row_number' => $row['row_number'],
                'student_code' => $studentCode,
                'student_name' => $student->name,
                'status' => 'valid',
                'message' => "Hợp lệ → {$classCode}",
            ];
            $validRowsToSave[] = ['student_id' => $student->id, 'class_id' => $classId];
            $validCount++;
        }

        $importToken = 'admin_class_bulk_import_' . \Illuminate\Support\Str::random(16);
        \Illuminate\Support\Facades\Cache::put($importToken, $validRowsToSave, 3600);

        return response()->json([
            'import_token' => $importToken,
            'total_rows' => count($rows),
            'valid_rows' => $validCount,
            'invalid_rows' => $invalidCount,
            'preview_data' => $previewData,
        ]);
    }

    public function bulkImportAdminClassesExecute(\Illuminate\Http\Request $request): \Illuminate\Http\JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        if (!$user || !\App\Support\Authorize::allows($user, ['manage_courses', 'manage_lessons', 'manage_exams', 'manage_grades', 'view_dashboard'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'import_token' => ['required', 'string'],
        ]);

        $validRows = \Illuminate\Support\Facades\Cache::get($validated['import_token']);
        if (!is_array($validRows) || count($validRows) === 0) {
            return response()->json(['message' => 'Dữ liệu xem trước đã hết hạn hoặc không hợp lệ. Vui lòng tải lại tệp.'], 422);
        }

        $updated = 0;
        DB::transaction(function () use ($validRows, &$updated) {
            $byClass = collect($validRows)->groupBy('class_id');
            foreach ($byClass as $classId => $group) {
                $studentIds = $group->pluck('student_id')->all();
                $updated += User::whereIn('id', $studentIds)
                    ->where('user_type', 'student')
                    ->update(['administrative_class_id' => $classId]);
            }
        });

        \Illuminate\Support\Facades\Cache::forget($validated['import_token']);

        return response()->json([
            'message' => "Đã import thành công {$updated} sinh viên vào các lớp hành chính.",
            'created' => $updated,
        ]);
    }

    // -----------------------------------------------------------------------
    // Analytics endpoints
    // -----------------------------------------------------------------------

    public function analyticsOverview(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (!$user || !\App\Support\Authorize::allows($user, ['manage_courses', 'manage_lessons', 'manage_exams', 'manage_grades', 'view_dashboard'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Enrollment trend — last 30 days
        $enrollmentTrend = DB::table('enrollments')
            ->selectRaw('DATE(enrolled_at) as day, COUNT(*) as count')
            ->where('enrolled_at', '>=', now()->subDays(29)->startOfDay())
            ->groupByRaw('DATE(enrolled_at)')
            ->orderBy('day')
            ->get()
            ->keyBy('day');

        $trendDays = [];
        for ($i = 29; $i >= 0; $i--) {
            $d = now()->subDays($i)->format('Y-m-d');
            $trendDays[] = ['date' => $d, 'count' => $enrollmentTrend->get($d)?->count ?? 0];
        }

        // Total active enrollments
        $totalEnrollments = DB::table('enrollments')->count();

        // Total completed lessons
        $completedLessons = DB::table('lesson_progress')->where('completed', true)->count();

        // At-risk: students with no lesson_progress in last 14 days
        $activeStudentIds = DB::table('lesson_progress')
            ->where('updated_at', '>=', now()->subDays(14))
            ->distinct()
            ->pluck('user_id');

        $totalStudents = User::where('user_type', 'student')->count();
        $atRiskCount = max(0, $totalStudents - $activeStudentIds->count());

        // Course completion distribution
        $coursesWithStats = DB::table('courses')
            ->leftJoin('enrollments', 'enrollments.course_id', '=', 'courses.id')
            ->selectRaw('courses.id, courses.title,
                COUNT(DISTINCT enrollments.user_id) as enrolled,
                COUNT(DISTINCT CASE WHEN EXISTS(
                    SELECT 1 FROM lesson_progress lp
                    JOIN lessons l ON l.id = lp.lesson_id
                    WHERE lp.user_id = enrollments.user_id
                      AND l.course_id = courses.id
                      AND lp.completed = 1
                ) THEN enrollments.user_id END) as has_any_completion')
            ->groupBy('courses.id', 'courses.title')
            ->orderByDesc('enrolled')
            ->limit(10)
            ->get();

        return response()->json([
            'enrollment_trend' => $trendDays,
            'totals' => [
                'enrollments' => $totalEnrollments,
                'completed_lessons' => $completedLessons,
                'at_risk_students' => $atRiskCount,
                'total_students' => $totalStudents,
            ],
            'top_courses' => $coursesWithStats,
        ]);
    }

    public function analyticsEnrollmentByCohort(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (!$user || !\App\Support\Authorize::allows($user, ['manage_courses', 'manage_lessons', 'manage_exams', 'manage_grades', 'view_dashboard'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $data = DB::table('cohorts')
            ->leftJoin('users', 'users.cohort_id', '=', 'cohorts.id')
            ->leftJoin('enrollments', 'enrollments.user_id', '=', 'users.id')
            ->selectRaw('cohorts.id, cohorts.name, cohorts.code,
                COUNT(DISTINCT users.id) as student_count,
                COUNT(DISTINCT enrollments.id) as enrollment_count')
            ->where(function ($q) {
                $q->whereNull('users.user_type')->orWhere('users.user_type', 'student');
            })
            ->groupBy('cohorts.id', 'cohorts.name', 'cohorts.code')
            ->orderByDesc('enrollment_count')
            ->get();

        return response()->json(['data' => $data]);
    }

    public function analyticsAtRisk(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (!$user || !\App\Support\Authorize::allows($user, ['manage_courses', 'manage_lessons', 'manage_exams', 'manage_grades', 'view_dashboard'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $cohortId = $request->input('cohort_id');
        $classId = $request->input('administrative_class_id');

        // Students with enrollments but zero lesson completions in last 30 days
        $query = User::where('user_type', 'student')
            ->whereHas('enrollments')
            ->whereDoesntHave('lessonProgress', function ($q) {
                $q->where('completed', true)
                    ->where('updated_at', '>=', now()->subDays(30));
            });

        if ($cohortId) $query->where('cohort_id', $cohortId);
        if ($classId) $query->where('administrative_class_id', $classId);

        $students = $query->with(['cohort:id,name,code', 'administrativeClass:id,code,name'])
            ->select('id', 'name', 'student_code', 'email', 'cohort_id', 'administrative_class_id')
            ->limit(50)
            ->get();

        // Attach enrollment count and last activity
        $students = $students->map(function ($s) {
            $enrollCount = DB::table('enrollments')->where('user_id', $s->id)->count();
            $lastActivity = DB::table('lesson_progress')
                ->where('user_id', $s->id)
                ->max('updated_at');

            return [
                'id' => $s->id,
                'name' => $s->name,
                'student_code' => $s->student_code,
                'email' => $s->email,
                'cohort' => $s->cohort?->name,
                'admin_class' => $s->administrativeClass?->code,
                'enrollment_count' => $enrollCount,
                'last_activity' => $lastActivity,
                'days_inactive' => $lastActivity
                    ? now()->diffInDays(\Carbon\Carbon::parse($lastActivity))
                    : null,
            ];
        });

        return response()->json(['students' => $students, 'total' => $students->count()]);
    }

    public function analyticsCompletionRate(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        if (!$user || !\App\Support\Authorize::allows($user, ['manage_courses', 'manage_lessons', 'manage_exams', 'manage_grades', 'view_dashboard'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Completion rate by term (học kỳ)
        $byTerm = DB::table('enrollments')
            ->join('terms', 'terms.id', '=', 'enrollments.term_id')
            ->selectRaw('terms.id, terms.name as term_name,
                COUNT(DISTINCT enrollments.user_id) as enrolled_count,
                COUNT(DISTINCT CASE WHEN EXISTS(
                    SELECT 1 FROM lesson_progress lp
                    JOIN lessons l ON l.id = lp.lesson_id
                    WHERE lp.user_id = enrollments.user_id
                      AND l.course_id = enrollments.course_id
                      AND lp.completed = 1
                ) THEN enrollments.user_id END) as completed_count')
            ->whereNotNull('enrollments.term_id')
            ->groupBy('terms.id', 'terms.name')
            ->orderBy('terms.id')
            ->limit(8)
            ->get()
            ->map(function ($row) {
                $row->completion_rate = $row->enrolled_count > 0
                    ? round(($row->completed_count / $row->enrolled_count) * 100, 1)
                    : 0;
                return $row;
            });

        // Completion by program
        $byProgram = DB::table('enrollments')
            ->join('users', 'users.id', '=', 'enrollments.user_id')
            ->join('programs', 'programs.id', '=', 'users.program_id')
            ->selectRaw('programs.id, programs.name as program_name,
                COUNT(DISTINCT enrollments.user_id) as enrolled_count,
                COUNT(DISTINCT CASE WHEN EXISTS(
                    SELECT 1 FROM lesson_progress lp
                    JOIN lessons l ON l.id = lp.lesson_id
                    WHERE lp.user_id = enrollments.user_id
                      AND l.course_id = enrollments.course_id
                      AND lp.completed = 1
                ) THEN enrollments.user_id END) as completed_count')
            ->whereNotNull('users.program_id')
            ->groupBy('programs.id', 'programs.name')
            ->orderByDesc('enrolled_count')
            ->get()
            ->map(function ($row) {
                $row->completion_rate = $row->enrolled_count > 0
                    ? round(($row->completed_count / $row->enrolled_count) * 100, 1)
                    : 0;
                return $row;
            });

        return response()->json(['by_term' => $byTerm, 'by_program' => $byProgram]);
    }
}



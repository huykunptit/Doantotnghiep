<?php

namespace App\Http\Controllers\UserManagement;

use App\Http\Controllers\Controller;

use App\Models\Course;
use App\Models\CurriculumCourse;
use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Models\Term;
use App\Models\UserCertificate;
use App\Services\CareerPathFulfillmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class LessonProgressController extends Controller
{
    public function updateProgress(Request $request, Course $course, Lesson $lesson): JsonResponse
    {
        if ($lesson->course_id !== $course->id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        /** @var \App\Models\User $user */
        $user = $request->user();

        $isEnrolled = Enrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)->exists();

        $isOwner = \App\Support\Authorize::isAdmin($user) || (int) $course->user_id === (int) $user->id;

        if (!$isEnrolled && !$isOwner) {
            return response()->json(['message' => 'Enrollment required'], 403);
        }

        $validated = $request->validate([
            'watched_seconds' => ['required', 'integer', 'min:0'],
            'completed'       => ['sometimes', 'boolean'],
        ]);

        $completed = $validated['completed']
            ?? ($lesson->duration > 0 && $validated['watched_seconds'] >= $lesson->duration * 0.9);

        $progress = LessonProgress::updateOrCreate(
            ['user_id' => $user->id, 'lesson_id' => $lesson->id],
            [
                'watched_seconds'  => $validated['watched_seconds'],
                'completed'        => $completed,
                'last_watched_at'  => now(),
            ]
        );

        if ($completed && $course->certificate_template_id) {
            $totalLessons = $course->lessons()->count();
            $completedCount = LessonProgress::where('user_id', $user->id)
                ->whereIn('lesson_id', $course->lessons()->pluck('id'))
                ->where('completed', true)
                ->count();

            if ($completedCount >= $totalLessons) {
                UserCertificate::firstOrCreate([
                    'user_id' => $user->id,
                    'course_id' => $course->id,
                ], [
                    'certificate_template_id' => $course->certificate_template_id,
                    'credential_id' => 'CERT-' . strtoupper(Str::random(10)),
                    'issued_at' => now(),
                ]);
            }
        }

        if ($completed) {
            app(CareerPathFulfillmentService::class)
                ->refreshProgressForUserCourse($user->id, $course->id);
        }

        return response()->json(['message' => 'Progress saved', 'progress' => $progress]);
    }

    public function courseProgress(Request $request, Course $course): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $isEnrolled = Enrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)->exists();

        $isOwner = \App\Support\Authorize::isAdmin($user) || (int) $course->user_id === (int) $user->id;

        if (!$isEnrolled && !$isOwner) {
            return response()->json(['message' => 'Enrollment required'], 403);
        }

        $totalLessons = $course->lessons()->count();

        if ($totalLessons === 0) {
            return response()->json([
                'total_lessons'     => 0,
                'completed_lessons' => 0,
                'percent'           => 0,
                'lessons'           => [],
            ]);
        }

        $lessonIds = $course->lessons()->pluck('id');

        $progressRecords = LessonProgress::where('user_id', $user->id)
            ->whereIn('lesson_id', $lessonIds)
            ->get()
            ->keyBy('lesson_id');

        $completedCount = $progressRecords->where('completed', true)->count();

        $lessonsWithProgress = $course->lessons()
            ->orderBy('order')
            ->get()
            ->map(function (Lesson $lesson) use ($progressRecords) {
                $prog = $progressRecords->get($lesson->id);
                return [
                    'id'              => $lesson->id,
                    'title'           => $lesson->title,
                    'order'           => $lesson->order,
                    'duration'        => $lesson->duration,
                    'completed'       => $prog?->completed ?? false,
                    'watched_seconds' => $prog?->watched_seconds ?? 0,
                    'last_watched_at' => $prog?->last_watched_at,
                ];
            });

        return response()->json([
            'total_lessons'     => $totalLessons,
            'completed_lessons' => $completedCount,
            'percent'           => round($completedCount / $totalLessons * 100, 1),
            'lessons'           => $lessonsWithProgress,
        ]);
    }

    public function enrollments(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $enrollments = Enrollment::with([
                'course:id,user_id,title,thumbnail,price,status,category_id',
                'course.category:id,name',
                'course.instructor:id,name,avatar',
                'term.academicYear:id,name',
                'classSection.term.academicYear:id,name',
            ])
            ->where('user_id', $user->id)
            ->orderByDesc('enrolled_at')
            ->get();

        $enriched = $enrollments->map(function (Enrollment $enrollment) use ($user) {
            $course = $enrollment->course;
            $term = $enrollment->term ?? $enrollment->classSection?->term;
            $progress = $this->enrollmentCourseProgress($user->id, $course);

            $payload = $enrollment->toArray();
            unset($payload['class_section']);

            return array_merge($payload, $progress, $this->termWindow($term), [
                'is_planned' => false,
            ]);
        })->values();

        $enrolledCourseIds = $enrollments->pluck('course_id')->filter()->map(fn ($id) => (int) $id)->all();
        $planned = $this->plannedCurriculumCourses($user, $enrolledCourseIds);

        return response()->json($enriched->concat($planned)->values());
    }

    /**
     * @return array{progress: float|int, lessons_count: int, completed_lessons: int}
     */
    private function enrollmentCourseProgress(int $userId, ?Course $course): array
    {
        if (! $course) {
            return ['progress' => 0, 'lessons_count' => 0, 'completed_lessons' => 0];
        }

        $lessonIds = $course->lessons()->pluck('id');
        $total = $lessonIds->count();
        $completed = $total > 0
            ? LessonProgress::whereIn('lesson_id', $lessonIds)
                ->where('user_id', $userId)
                ->where('completed', true)
                ->count()
            : 0;

        return [
            'progress' => $total > 0 ? round($completed / $total * 100, 1) : 0,
            'lessons_count' => $total,
            'completed_lessons' => $completed,
        ];
    }

    /**
     * Môn CTĐT kỳ tới (chưa ghi danh) — để tab Sắp tới có dữ liệu mà không cần reseed.
     *
     * @param  list<int>  $enrolledCourseIds
     * @return list<array<string, mixed>>
     */
    private function plannedCurriculumCourses(\App\Models\User $user, array $enrolledCourseIds): array
    {
        $user->loadMissing(['administrativeClass.termMap.term.academicYear']);
        $class = $user->administrativeClass;
        if (! $class?->curriculum_id) {
            return [];
        }

        $termMap = $class->termMap->keyBy('term_number');
        $rows = CurriculumCourse::query()
            ->where('curriculum_id', $class->curriculum_id)
            ->with([
                'course:id,user_id,title,thumbnail,price,status,category_id',
                'course.category:id,name',
                'course.instructor:id,name,avatar',
            ])
            ->orderBy('term_number')
            ->orderBy('position')
            ->get();

        $planned = [];
        foreach ($rows as $row) {
            $course = $row->course;
            if (! $course || in_array((int) $course->id, $enrolledCourseIds, true)) {
                continue;
            }

            $term = $termMap->get($row->term_number)?->term;
            if (! $term || $term->windowStatus() !== 'upcoming') {
                continue;
            }

            $enrolledCourseIds[] = (int) $course->id;
            $planned[] = array_merge([
                'id' => -1 * (int) $course->id,
                'user_id' => $user->id,
                'course_id' => $course->id,
                'term_id' => $term->id,
                'cohort_id' => $class->cohort_id,
                'class_section_id' => null,
                'enrollment_source' => 'academic',
                'enrolled_at' => null,
                'course' => $course->toArray(),
                'is_planned' => true,
                'progress' => 0,
                'lessons_count' => 0,
                'completed_lessons' => 0,
            ], $this->termWindow($term));
        }

        return $planned;
    }

    /**
     * @return array{term: ?array<string, mixed>, starts_at: ?string, ends_at: ?string, window_status: string}
     */
    private function termWindow(?Term $term): array
    {
        if (! $term) {
            return [
                'term' => null,
                'starts_at' => null,
                'ends_at' => null,
                'window_status' => 'current',
            ];
        }

        $term->loadMissing('academicYear');

        return [
            'term' => [
                'id' => $term->id,
                'name' => $term->name,
                'code' => $term->code,
                'start_date' => $term->start_date?->toDateString(),
                'end_date' => $term->end_date?->toDateString(),
                'is_current' => (bool) $term->is_current,
                'status' => $term->status,
                'display_name' => $term->displayName(),
                'academic_year' => $term->academicYear
                    ? ['id' => $term->academicYear->id, 'name' => $term->academicYear->name]
                    : null,
            ],
            'starts_at' => $term->start_date?->toDateString(),
            'ends_at' => $term->end_date?->toDateString(),
            'window_status' => $term->windowStatus(),
        ];
    }
}

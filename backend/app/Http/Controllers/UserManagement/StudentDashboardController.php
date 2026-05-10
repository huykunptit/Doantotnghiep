<?php

namespace App\Http\Controllers\UserManagement;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Term;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Student-facing endpoints:
 *  GET /me/transcript                       — full grade history grouped by term
 *  GET /me/dashboard                        — current-term snapshot
 *  GET /me/recommendations/extensions       — extension marketplace suggestions
 */
class StudentDashboardController extends Controller
{
    public function transcript(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthorized'], 401);

        $enrollments = Enrollment::query()
            ->where('user_id', $user->id)
            ->with([
                'course:id,title,course_mode,credit_value,is_credit_bearing',
                'term:id,name,code,start_date,end_date,academic_year_id',
                'classSection:id,code,lecturer_id',
                'classSection.lecturer:id,name',
                'gradeEntries.component:id,name,weight,max_score',
            ])
            ->orderBy('term_id')
            ->orderBy('id')
            ->get();

        $byTerm = $enrollments->groupBy(fn ($e) => $e->term_id ?? 0);

        $terms = $byTerm->map(function (Collection $items, $termId) {
            $term = $items->first()->term;
            $courses = $items->map(function (Enrollment $enrollment) {
                return [
                    'enrollment_id' => $enrollment->id,
                    'course' => $enrollment->course,
                    'class_section' => $enrollment->classSection,
                    'enrollment_source' => $enrollment->enrollment_source,
                    'final_score' => $enrollment->final_score,
                    'entries' => $enrollment->gradeEntries->map(fn ($entry) => [
                        'component' => $entry->component?->name,
                        'weight' => $entry->component?->weight,
                        'max_score' => $entry->component?->max_score,
                        'score' => $entry->score,
                    ])->values(),
                ];
            })->values();

            $finalAvg = $courses
                ->pluck('final_score')
                ->filter(fn ($s) => $s !== null)
                ->avg();

            return [
                'term' => $term,
                'courses' => $courses,
                'gpa' => $finalAvg !== null ? round($finalAvg, 2) : null,
                'credits' => $items->sum(fn ($e) => (int) ($e->course->credit_value ?? 0)),
            ];
        })->values();

        return response()->json([
            'student' => $user->only(['id', 'name', 'email', 'student_code', 'cohort_id', 'major_id', 'program_id']),
            'terms' => $terms,
            'overall_gpa' => $terms->pluck('gpa')->filter(fn ($g) => $g !== null)->avg() ?: null,
        ]);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthorized'], 401);

        $currentTerm = Term::where('is_current', true)->latest('id')->first()
            ?? Term::latest('id')->first();

        $currentEnrollments = collect();
        if ($currentTerm) {
            $currentEnrollments = Enrollment::where('user_id', $user->id)
                ->where('term_id', $currentTerm->id)
                ->with(['course:id,title,course_mode,credit_value', 'classSection:id,code,lecturer_id', 'classSection.lecturer:id,name'])
                ->get();
        }

        $totalEnrollments = Enrollment::where('user_id', $user->id)->count();
        $completedCourses = Enrollment::where('user_id', $user->id)
            ->where('enrollment_source', 'academic')
            ->whereHas('gradeEntries')
            ->count();

        return response()->json([
            'student' => $user->only(['id', 'name', 'email', 'student_code', 'cohort_id', 'major_id', 'program_id', 'advisor_id']),
            'current_term' => $currentTerm,
            'current_enrollments' => $currentEnrollments,
            'totals' => [
                'enrollments' => $totalEnrollments,
                'in_progress' => $currentEnrollments->count(),
                'completed' => $completedCourses,
            ],
        ]);
    }

    public function recommendations(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthorized'], 401);

        $enrolledIds = Enrollment::where('user_id', $user->id)->pluck('course_id')->all();

        // Pull extension courses, score by program/major match + skill overlap.
        $skillIdsForUser = collect();
        if ($user->major_id) {
            // Use core courses of user's program/major as proxy for "what they've studied".
            $coreCourses = Course::query()
                ->where('course_mode', 'core')
                ->where(function ($q) use ($user) {
                    $q->where('program_id', $user->program_id)
                      ->orWhereNull('program_id');
                })
                ->with('skills:id')
                ->get();
            $skillIdsForUser = $coreCourses->flatMap->skills->pluck('id')->unique();
        }

        $candidates = Course::query()
            ->where('course_mode', 'extension')
            ->where('status', 'published')
            ->whereNotIn('id', $enrolledIds)
            ->with(['skills:id,name', 'category:id,name,slug', 'instructor:id,name,avatar'])
            ->limit(50)
            ->get();

        $scored = $candidates->map(function (Course $course) use ($skillIdsForUser, $user) {
            $courseSkillIds = $course->skills->pluck('id');
            $overlap = $skillIdsForUser->intersect($courseSkillIds)->count();
            $score = $overlap * 30;

            // Tiny bonus if instructor is in same unit as student's major.
            if ($user->major_id && $course->major_id && $course->major_id === $user->major_id) {
                $score += 25;
            } elseif ($user->program_id && $course->program_id === $user->program_id) {
                $score += 15;
            }

            // Cheap recency boost.
            if ($course->published_at) {
                $daysOld = max(1, now()->diffInDays($course->published_at));
                $score += max(0, 30 - min(30, $daysOld));
            }

            return [
                'course' => $course,
                'score' => $score,
                'matched_skills' => $course->skills->whereIn('id', $skillIdsForUser->all())->pluck('name')->values(),
            ];
        })
        ->sortByDesc('score')
        ->take(8)
        ->values();

        return response()->json([
            'recommendations' => $scored,
            'context' => [
                'program_id' => $user->program_id,
                'major_id' => $user->major_id,
                'skill_pool_size' => $skillIdsForUser->count(),
            ],
        ]);
    }
}

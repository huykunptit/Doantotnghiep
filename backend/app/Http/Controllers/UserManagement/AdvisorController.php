<?php

namespace App\Http\Controllers\UserManagement;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Term;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Advisor (cố vấn học tập) endpoints.
 *  GET /advisor/advisees     — list assigned students with quick stats
 *  GET /advisor/at-risk      — students whose final score in current term < threshold
 */
class AdvisorController extends Controller
{
    private const RISK_THRESHOLD = 5.0; // 10-scale

    public function advisees(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->hasAnyRole(['advisor', 'admin', 'instructor'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $advisees = User::query()
            ->where('advisor_id', $user->id)
            ->select('id', 'name', 'email', 'student_code', 'cohort_id', 'major_id', 'program_id')
            ->with(['cohort:id,name,code', 'major:id,name,code'])
            ->orderBy('student_code')
            ->get();

        $currentTerm = Term::where('is_current', true)->latest('id')->first()
            ?? Term::latest('id')->first();

        $rows = $advisees->map(function (User $student) use ($currentTerm) {
            $enrollments = Enrollment::query()
                ->where('user_id', $student->id)
                ->when($currentTerm, fn ($q) => $q->where('term_id', $currentTerm->id))
                ->with('gradeEntries.component:id,weight,max_score')
                ->get();

            $finalScores = $enrollments->map(fn ($e) => $e->final_score)->filter(fn ($s) => $s !== null);
            $avg = $finalScores->avg();

            return [
                'student' => $student,
                'enrollments' => $enrollments->count(),
                'graded' => $finalScores->count(),
                'avg_score' => $avg !== null ? round($avg, 2) : null,
                'is_at_risk' => $avg !== null && $avg < self::RISK_THRESHOLD,
            ];
        });

        return response()->json([
            'advisor_id' => $user->id,
            'current_term' => $currentTerm,
            'advisees' => $rows,
            'totals' => [
                'count' => $rows->count(),
                'at_risk' => $rows->where('is_at_risk', true)->count(),
                'avg_gpa' => $rows->pluck('avg_score')->filter(fn ($s) => $s !== null)->avg(),
            ],
        ]);
    }

    public function atRisk(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || !$user->hasAnyRole(['advisor', 'admin'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $threshold = (float) $request->input('threshold', self::RISK_THRESHOLD);
        $currentTerm = Term::where('is_current', true)->latest('id')->first()
            ?? Term::latest('id')->first();

        $studentsQuery = User::query()->whereNotNull('cohort_id');
        if ($user->hasRole('advisor')) {
            $studentsQuery->where('advisor_id', $user->id);
        }
        $students = $studentsQuery->select('id', 'name', 'email', 'student_code', 'cohort_id', 'advisor_id')
            ->with('cohort:id,name,code')
            ->get();

        $atRisk = $students->map(function (User $student) use ($currentTerm) {
            $enrollments = Enrollment::query()
                ->where('user_id', $student->id)
                ->when($currentTerm, fn ($q) => $q->where('term_id', $currentTerm->id))
                ->with('gradeEntries.component:id,weight,max_score', 'course:id,title')
                ->get();
            $weak = $enrollments->filter(fn ($e) => $e->final_score !== null);
            return [
                'student' => $student,
                'avg_score' => $weak->isEmpty() ? null : round($weak->avg(fn ($e) => $e->final_score), 2),
                'weak_courses' => $weak
                    ->filter(fn ($e) => $e->final_score < 5.0)
                    ->map(fn ($e) => ['course' => $e->course->title ?? null, 'final' => $e->final_score])
                    ->values(),
            ];
        })
        ->filter(fn ($row) => $row['avg_score'] !== null && $row['avg_score'] < $threshold)
        ->values();

        return response()->json([
            'threshold' => $threshold,
            'current_term' => $currentTerm,
            'students' => $atRisk,
        ]);
    }
}

<?php

namespace App\Http\Controllers\UserManagement;

use App\Http\Controllers\Controller;
use App\Models\ClassSection;
use App\Models\Enrollment;
use App\Models\Term;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Instructor dashboard endpoints.
 *  GET /instructor/dashboard         — current-term sections + enrollment counts + ungraded counts
 */
class InstructorDashboardController extends Controller
{
    public function dashboard(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user || !\App\Support\Authorize::allows($user, ['manage_courses', 'view_dashboard', 'manage_grades'])) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $currentTerm = Term::operational();

        $sectionsQuery = ClassSection::query()
            ->with([
                'course:id,title,course_mode',
                'term:id,name,code',
                'cohort:id,name,code',
            ])
            ->withCount('enrollments');

        if (!\App\Support\Authorize::isAdmin($user)) {
            $sectionsQuery->where('lecturer_id', $user->id);
        }
        if ($currentTerm) {
            $sectionsQuery->where('term_id', $currentTerm->id);
        }

        $sections = $sectionsQuery->orderByDesc('id')->get();

        $sectionRows = $sections->map(function (ClassSection $section) {
            $totalEnrollments = $section->enrollments_count ?? 0;
            $graded = Enrollment::where('class_section_id', $section->id)
                ->whereHas('gradeEntries')
                ->count();

            return [
                'section' => $section,
                'enrollments' => $totalEnrollments,
                'graded' => $graded,
                'pending' => max(0, $totalEnrollments - $graded),
            ];
        });

        return response()->json([
            'instructor' => $user->only(['id', 'name', 'email', 'staff_code']),
            'current_term' => $currentTerm,
            'sections' => $sectionRows,
            'totals' => [
                'sections' => $sectionRows->count(),
                'students' => $sectionRows->sum('enrollments'),
                'pending_grading' => $sectionRows->sum('pending'),
            ],
        ]);
    }
}

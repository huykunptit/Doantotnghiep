<?php

namespace App\Services;

use App\Models\User;
use App\Models\Enrollment;
use App\Models\GradeEntry;
use App\Models\QuizAttempt;
use App\Models\CurriculumCourse;
use App\Models\Course;
use Illuminate\Support\Facades\DB;

class StudentAnalyticsService
{
    /**
     * Build full academic and learning profile for a student.
     */
    public function buildStudentProfile(User $user): array
    {
        $enrolledCourses = $this->getEnrolledCoursesWithProgress($user);
        $transcript = $this->getGradeTranscript($user);
        $quizPerformance = $this->getQuizPerformance($user);
        $curriculumGaps = $this->getCurriculumGaps($user, $transcript);

        // Calculate GPA (on 10-scale and 4-scale) and credits
        $gpaData = $this->calculateGpaAndCredits($transcript);

        return [
            'user_id' => $user->id,
            'student_name' => $user->name,
            'major' => $user->major?->name ?? 'Chưa khai báo',
            'program' => $user->program?->name ?? 'Chưa khai báo',
            'current_term' => $user->cohort?->current_term ?? 1,
            'enrolled_courses' => $enrolledCourses,
            'grade_transcript' => $transcript,
            'quiz_performance' => $quizPerformance,
            'curriculum_gaps' => $curriculumGaps,
            'gpa' => $gpaData['gpa_10'],
            'gpa_4' => $gpaData['gpa_4'],
            'total_credits_earned' => $gpaData['total_credits_earned'],
            'total_completed_courses' => $gpaData['total_completed_courses'],
        ];
    }

    /**
     * Get enrolled marketplace courses and their lesson progress.
     */
    protected function getEnrolledCoursesWithProgress(User $user): array
    {
        return Enrollment::query()
            ->with(['course' => function ($q) {
                $q->withCount('lessons');
            }])
            ->where('user_id', $user->id)
            ->get()
            ->map(function (Enrollment $enrollment) use ($user) {
                $course = $enrollment->course;
                if (!$course) return null;

                // Calculate progress percent
                $totalLessons = $course->lessons_count;
                $completedLessons = DB::table('lesson_progress')
                    ->where('user_id', $user->id)
                    ->whereIn('lesson_id', $course->lessons()->pluck('id'))
                    ->where('is_completed', true)
                    ->count();

                $progress = $totalLessons > 0 ? round(($completedLessons / $totalLessons) * 100, 1) : 0;

                // Get average quiz score for this course
                $avgQuiz = QuizAttempt::query()
                    ->where('user_id', $user->id)
                    ->whereIn('quiz_id', $course->quizzes()->pluck('id'))
                    ->where('status', 'submitted')
                    ->avg('score');

                return [
                    'course_id' => $course->id,
                    'course_title' => $course->title,
                    'progress_percent' => (float)$progress,
                    'quiz_avg_score' => $avgQuiz !== null ? round((float)$avgQuiz, 1) : null,
                    'last_accessed' => $enrollment->updated_at?->toIso8601String(),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Get grade entries, calculate final score per course.
     */
    protected function getGradeTranscript(User $user): array
    {
        return Enrollment::query()
            ->with(['course', 'gradeEntries.component'])
            ->where('user_id', $user->id)
            ->get()
            ->map(function (Enrollment $enrollment) {
                $course = $enrollment->course;
                if (!$course) return null;

                $finalScore = $enrollment->final_score; // Custom attribute on Enrollment model
                
                if ($finalScore === null) return null;

                $gradeLetter = $this->convertToGradeLetter($finalScore);

                return [
                    'course_id' => $course->id,
                    'course_title' => $course->title,
                    'final_score' => (float)$finalScore,
                    'grade_letter' => $gradeLetter,
                    'credits' => $course->credit_value ?: 3,
                    'term_number' => $enrollment->term?->term_number,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Get quiz performance for the student.
     */
    protected function getQuizPerformance(User $user): array
    {
        return QuizAttempt::query()
            ->with('quiz')
            ->where('user_id', $user->id)
            ->where('status', 'submitted')
            ->get()
            ->groupBy('quiz_id')
            ->map(function ($attempts) {
                $first = $attempts->first();
                $quiz = $first->quiz;
                if (!$quiz) return null;

                return [
                    'quiz_title' => $quiz->title,
                    'score' => (float)$attempts->max('score'),
                    'passed' => (bool)$attempts->contains('passed', true),
                    'attempts' => $attempts->count(),
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * Find mandatory courses in student's curriculum that are not completed.
     */
    protected function getCurriculumGaps(User $user, array $transcript): array
    {
        // Find user's curriculum
        $curriculumId = null;
        if ($user->major_id) {
            $curriculumId = DB::table('curricula')
                ->where('major_id', $user->major_id)
                ->where('is_active', true)
                ->value('id');
        }

        if (!$curriculumId) {
            return [];
        }

        // Mandatory courses in this curriculum
        $mandatoryCourses = CurriculumCourse::query()
            ->with('course')
            ->where('curriculum_id', $curriculumId)
            ->where('is_required', true)
            ->get();

        $completedCourseIds = collect($transcript)
            ->where('final_score', '>=', 4.0)
            ->pluck('course_id')
            ->toArray();

        return $mandatoryCourses
            ->filter(fn (CurriculumCourse $cc) => !in_array($cc->course_id, $completedCourseIds))
            ->map(fn (CurriculumCourse $cc) => [
                'course_id' => $cc->course_id,
                'course_title' => $cc->course?->title ?? 'Môn học chưa rõ',
                'credits' => $cc->credits ?: 3,
                'term_number' => $cc->term_number,
                'is_required' => true,
            ])
            ->values()
            ->all();
    }

    /**
     * Calculate aggregate GPA (10-scale, 4-scale) and credits.
     */
    protected function calculateGpaAndCredits(array $transcript): array
    {
        $totalCredits = 0;
        $weightedScore10 = 0.0;
        $weightedScore4 = 0.0;
        $completedCoursesCount = 0;
        $creditsEarned = 0;

        foreach ($transcript as $record) {
            $score = $record['final_score'];
            $credits = $record['credits'];
            
            $totalCredits += $credits;
            $weightedScore10 += $score * $credits;

            // 4-scale conversion
            $score4 = $this->convertTo4Scale($score);
            $weightedScore4 += $score4 * $credits;

            if ($score >= 4.0) {
                $creditsEarned += $credits;
                $completedCoursesCount++;
            }
        }

        return [
            'gpa_10' => $totalCredits > 0 ? round($weightedScore10 / $totalCredits, 2) : 0.0,
            'gpa_4' => $totalCredits > 0 ? round($weightedScore4 / $totalCredits, 2) : 0.0,
            'total_credits_earned' => $creditsEarned,
            'total_completed_courses' => $completedCoursesCount,
        ];
    }

    protected function convertToGradeLetter(float $score): string
    {
        if ($score >= 9.0) return 'A+';
        if ($score >= 8.5) return 'A';
        if ($score >= 8.0) return 'B+';
        if ($score >= 7.0) return 'B';
        if ($score >= 6.5) return 'C+';
        if ($score >= 5.5) return 'C';
        if ($score >= 5.0) return 'D+';
        if ($score >= 4.0) return 'D';
        return 'F';
    }

    protected function convertTo4Scale(float $score): float
    {
        if ($score >= 9.0) return 4.0;
        if ($score >= 8.5) return 3.7;
        if ($score >= 8.0) return 3.5;
        if ($score >= 7.0) return 3.0;
        if ($score >= 6.5) return 2.5;
        if ($score >= 5.5) return 2.0;
        if ($score >= 5.0) return 1.5;
        if ($score >= 4.0) return 1.0;
        return 0.0;
    }
}

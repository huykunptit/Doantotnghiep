<?php

namespace App\Services;

use App\Helpers\GpaCalculator;
use App\Models\Curriculum;
use App\Models\Enrollment;
use App\Models\User;
use App\Support\StudyAdvisorScoreRule;
use Illuminate\Support\Collection;

/**
 * Đánh giá kết quả học CTĐT (rule-based) — nền cho AI Career Advisor sau này.
 */
class CurriculumEvaluationService
{
    public function __construct(
        private readonly LearnerProfileService $profiles,
        private readonly RecommendationService $recommendations,
    ) {}

    public function evaluate(User $user): array
    {
        $user->loadMissing([
            'administrativeClass',
            'major:id,code,name',
            'program:id,code,name',
        ]);

        $adminClass = $user->administrativeClass;
        if (!$adminClass?->curriculum_id) {
            return [
                'has_curriculum' => false,
                'message' => 'Chưa gán chương trình đào tạo cho lớp hành chính.',
                'ready_for_career_advice' => false,
            ];
        }

        $curriculum = Curriculum::with([
            'curriculumCourses.course:id,title,credit_value,is_credit_bearing,course_mode',
        ])->find($adminClass->curriculum_id);

        if (!$curriculum) {
            return [
                'has_curriculum' => false,
                'message' => 'Không tìm thấy CTĐT.',
                'ready_for_career_advice' => false,
            ];
        }

        // Chỉ lấy ghi danh CTĐT (không marketplace) — cùng nguồn với /me/transcript.
        $enrollments = Enrollment::where('user_id', $user->id)
            ->where('enrollment_source', '!=', 'marketplace')
            ->get()
            ->keyBy('course_id');
        $profile = $this->profiles->build($user);

        // Kỳ đang học = kỳ có ghi danh cao nhất (cùng quy ước bảng điểm PTIT).
        $currentTermNumber = $curriculum->curriculumCourses
            ->filter(fn ($cc) => $cc->course_id && $enrollments->has($cc->course_id))
            ->max(fn ($cc) => (int) $cc->term_number);

        $requiredTotal = 0;
        $requiredDone = 0;
        $creditsRequired = 0;
        $scoredCourses = []; // dùng cho CPA — loại kỳ đang học
        $termStats = [];

        foreach ($curriculum->curriculumCourses as $cc) {
            $course = $cc->course;
            if (!$course) {
                continue;
            }

            $term = max(1, min(8, (int) $cc->term_number));
            $isCurrentTerm = $currentTermNumber !== null && $term === (int) $currentTermNumber;

            if (!isset($termStats[$term])) {
                $termStats[$term] = [
                    'term_number' => $term,
                    'required' => 0,
                    'completed' => 0,
                    'avg_score' => null,
                    'scores' => [],
                ];
            }

            $isRequired = (bool) $cc->is_required;
            $credits = (int) ($course->credit_value ?? $cc->credits ?? 0);
            if ($course->is_credit_bearing) {
                $creditsRequired += $credits;
            }

            if ($isRequired) {
                $requiredTotal++;
                $termStats[$term]['required']++;
            }

            $enrollment = $enrollments->get($course->id);
            $done = $enrollment && (
                ($enrollment->progress ?? 0) >= 100
                || $enrollment->final_score !== null
            );

            if ($done && $isRequired) {
                $requiredDone++;
                $termStats[$term]['completed']++;
            }

            // CPA / tín chỉ tích lũy: chỉ môn đã có điểm ở kỳ đã kết thúc (không tính kỳ đang học).
            if (!$isCurrentTerm && $enrollment?->final_score !== null && $credits > 0) {
                $score = (float) $enrollment->final_score;
                $scoredCourses[] = [
                    'final_score' => $score,
                    'credit_value' => $credits,
                    'course_id' => $course->id,
                    'title' => $course->title,
                    'term_number' => $term,
                    'is_required' => $isRequired,
                ];
                $termStats[$term]['scores'][] = $score;
            }
        }

        foreach ($termStats as $t => $stat) {
            $scores = $stat['scores'];
            $termStats[$t]['avg_score'] = $scores
                ? round(array_sum($scores) / count($scores), 2)
                : null;
            unset($termStats[$t]['scores']);
            $termStats[$t]['completion_ratio'] = $stat['required'] > 0
                ? round($stat['completed'] / $stat['required'], 2)
                : null;
        }

        ksort($termStats);

        $overallGpa = GpaCalculator::cumulativeGpa($scoredCourses);
        $overallScore10 = GpaCalculator::cumulativeScore10($scoredCourses);
        $creditsEarned = GpaCalculator::earnedCredits($scoredCourses);
        $completionRatio = $requiredTotal > 0 ? $requiredDone / $requiredTotal : 0;
        $creditRatio = $creditsRequired > 0 ? $creditsEarned / $creditsRequired : 0;

        // Demo: sẵn sàng career advice khi ≥ 60% môn bắt buộc hoặc ≥ 50% tín chỉ
        $ready = $completionRatio >= 0.6 || $creditRatio >= 0.5;

        $strengths = collect($scoredCourses)
            ->filter(fn ($c) => $c['final_score'] >= 8.0)
            ->sortByDesc('final_score')
            ->take(5)
            ->values()
            ->all();

        // Điểm thấp: tuyệt đối < 6.5 HOẶC < GPA10 − 1.0 (cùng rule với gợi ý khóa)
        $weaknesses = collect($scoredCourses)
            ->filter(fn ($c) => StudyAdvisorScoreRule::classify((float) $c['final_score'], $overallScore10)['is_weak'])
            ->sortBy('final_score')
            ->take(5)
            ->values()
            ->all();

        $level = match (true) {
            $completionRatio >= 0.85 && ($overallGpa ?? 0) >= 3.2 => 'excellent',
            $completionRatio >= 0.6 && ($overallGpa ?? 0) >= 2.5 => 'good',
            $completionRatio >= 0.4 => 'developing',
            default => 'early',
        };

        $narrative = $this->buildNarrative(
            $user,
            $level,
            $completionRatio,
            $overallGpa,
            $strengths,
            $weaknesses,
            $ready
        );

        $recs = $this->recommendations->recommendForStudyAdvisor($user, 8);

        return [
            'has_curriculum' => true,
            'curriculum' => [
                'id' => $curriculum->id,
                'name' => $curriculum->name,
                'code' => $curriculum->code,
            ],
            'summary' => [
                'level' => $level,
                'required_courses_total' => $requiredTotal,
                'required_courses_completed' => $requiredDone,
                'completion_ratio' => round($completionRatio, 3),
                'credits_required' => $creditsRequired,
                'credits_earned' => $creditsEarned,
                'credit_ratio' => round($creditRatio, 3),
                'overall_gpa' => $overallGpa,
                'ready_for_career_advice' => $ready,
            ],
            'by_term' => array_values($termStats),
            'strengths' => $strengths,
            'weaknesses' => $weaknesses,
            'target_roles' => $profile['goal']['target_roles'] ?? [],
            'top_skills' => $profile['skills']['top'] ?? [],
            'narrative' => $narrative,
            'suggested_paths' => [],
            'suggested_courses' => $recs,
            // Payload sẵn sàng gửi AI Career Advisor (Phase AI)
            'career_advisor_context' => [
                'student' => $profile['user'],
                'academic_summary' => [
                    'level' => $level,
                    'gpa' => $overallGpa,
                    'completion_ratio' => round($completionRatio, 3),
                    'strengths' => $strengths,
                    'weaknesses' => $weaknesses,
                ],
                'skills' => $profile['skills']['top'] ?? [],
                'target_roles' => $profile['goal']['target_roles'] ?? [],
                'cv_skills' => $profile['goal']['cv_skills'] ?? [],
            ],
        ];
    }

    private function buildNarrative(
        User $user,
        string $level,
        float $completionRatio,
        ?float $gpa,
        array $strengths,
        array $weaknesses,
        bool $ready
    ): string {
        $pct = (int) round($completionRatio * 100);
        $major = $user->major?->name ?? 'ngành của bạn';
        $gpaText = $gpa !== null ? number_format($gpa, 2) : 'chưa đủ điểm';

        $parts = [
            "Bạn đã hoàn thành khoảng {$pct}% môn bắt buộc trong CTĐT {$major}, CPA tích lũy ~{$gpaText}.",
        ];

        if ($strengths) {
            $titles = collect($strengths)->pluck('title')->take(2)->implode(', ');
            $parts[] = "Điểm mạnh nổi bật ở: {$titles}.";
        }

        if ($weaknesses) {
            $titles = collect($weaknesses)->pluck('title')->take(2)->implode(', ');
            $parts[] = "Cần củng cố: {$titles}.";
        }

        $parts[] = $ready
            ? 'Hồ sơ đã đủ để nhận gợi ý lộ trình nghề / khóa học. AI Career Advisor sẽ diễn giải chi tiết ở bước tiếp theo.'
            : 'Hãy hoàn thành thêm các môn CTĐT trong kỳ hiện tại trước khi nhận tư vấn nghề sâu.';

        if ($level === 'excellent') {
            $parts[] = 'Kết quả học tập xuất sắc — phù hợp các lộ trình chuyên sâu / fullstack.';
        } elseif ($level === 'early') {
            $parts[] = 'Bạn đang ở giai đoạn đầu CTĐT — ưu tiên hoàn thành môn nền.';
        }

        return implode(' ', $parts);
    }
}

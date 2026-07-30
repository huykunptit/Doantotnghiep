<?php

namespace App\Support;

/**
 * Rule điểm thấp cho Cố vấn học tập (thang 10).
 *
 * Yếu nếu: score < absolute_low_score
 *        HOẶC score < GPA10 − relative_gpa_delta
 */
class StudyAdvisorScoreRule
{
    public static function absoluteThreshold(): float
    {
        return (float) config('lms.study_advisor.absolute_low_score', 6.5);
    }

    public static function relativeDelta(): float
    {
        return (float) config('lms.study_advisor.relative_gpa_delta', 1.0);
    }

    /**
     * @return array{
     *   is_weak: bool,
     *   absolute: bool,
     *   relative: bool,
     *   relative_threshold: float|null
     * }
     */
    public static function classify(float $score, ?float $gpa10): array
    {
        $abs = self::absoluteThreshold();
        $delta = self::relativeDelta();
        $relativeThreshold = $gpa10 !== null ? round($gpa10 - $delta, 2) : null;

        $absolute = $score < $abs;
        $relative = $relativeThreshold !== null && $score < $relativeThreshold;

        return [
            'is_weak' => $absolute || $relative,
            'absolute' => $absolute,
            'relative' => $relative,
            'relative_threshold' => $relativeThreshold,
        ];
    }

    public static function reason(float $score, ?float $gpa10, int $termNumber): string
    {
        $info = self::classify($score, $gpa10);
        $abs = self::absoluteThreshold();

        if ($info['absolute'] && $info['relative'] && $gpa10 !== null) {
            return sprintf(
                'Điểm thấp (%.1f/10) — dưới ngưỡng %.1f và thấp hơn GPA của bạn (%.1f) — củng cố môn CTĐT kỳ %d',
                $score,
                $abs,
                $gpa10,
                $termNumber
            );
        }

        if ($info['absolute']) {
            return sprintf(
                'Điểm thấp (%.1f/10) — dưới ngưỡng tuyệt đối %.1f — củng cố môn CTĐT kỳ %d',
                $score,
                $abs,
                $termNumber
            );
        }

        return sprintf(
            'Điểm thấp hơn mặt bằng của bạn (%.1f/10, GPA %.1f) — củng cố môn CTĐT kỳ %d',
            $score,
            (float) $gpa10,
            $termNumber
        );
    }
}

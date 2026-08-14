<?php

namespace App\Support;

/**
 * Xác định kỳ hiện tại / hồ sơ còn thưa để khỏi suy diễn từ dữ liệu rỗng.
 */
class StudyAdvisorTermResolver
{
    /**
     * Có ghi danh thì lấy kỳ cao nhất; chưa học môn nào thì lấy kỳ đầu khung CTĐT.
     */
    public static function currentTerm(?int $maxEnrolledTerm, int $minCurriculumTerm = 1): int
    {
        if ($maxEnrolledTerm !== null && $maxEnrolledTerm > 0) {
            return $maxEnrolledTerm;
        }

        return max(1, $minCurriculumTerm);
    }

    /**
     * Hồ sơ mới: chưa có điểm tổng kết nào thì không suy diễn môn yếu.
     *
     * @param  iterable<mixed>  $finalScores
     */
    public static function isSparseGrades(iterable $finalScores): bool
    {
        foreach ($finalScores as $score) {
            if ($score !== null && $score !== '') {
                return false;
            }
        }

        return true;
    }
}

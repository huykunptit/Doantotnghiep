<?php

namespace App\Helpers;

class GpaCalculator
{
    /**
     * PTIT grading scale (10-point system → letter → 4.0 GPA)
     */
    private static array $scale = [
        ['min' => 9.0,  'letter' => 'A+', 'gpa4' => 4.0],
        ['min' => 8.5,  'letter' => 'A',  'gpa4' => 3.7],
        ['min' => 8.0,  'letter' => 'B+', 'gpa4' => 3.5],
        ['min' => 7.0,  'letter' => 'B',  'gpa4' => 3.0],
        ['min' => 6.5,  'letter' => 'C+', 'gpa4' => 2.5],
        ['min' => 5.5,  'letter' => 'C',  'gpa4' => 2.0],
        ['min' => 5.0,  'letter' => 'D+', 'gpa4' => 1.5],
        ['min' => 4.0,  'letter' => 'D',  'gpa4' => 1.0],
        ['min' => 0.0,  'letter' => 'F',  'gpa4' => 0.0],
    ];

    public static function letterGrade(float $score): string
    {
        foreach (self::$scale as $tier) {
            if ($score >= $tier['min']) return $tier['letter'];
        }
        return 'F';
    }

    public static function gpa4(float $score): float
    {
        foreach (self::$scale as $tier) {
            if ($score >= $tier['min']) return $tier['gpa4'];
        }
        return 0.0;
    }

    public static function gradeInfo(float $score): array
    {
        foreach (self::$scale as $tier) {
            if ($score >= $tier['min']) {
                return ['letter' => $tier['letter'], 'gpa4' => $tier['gpa4']];
            }
        }
        return ['letter' => 'F', 'gpa4' => 0.0];
    }

    /**
     * Calculate cumulative GPA weighted by credits.
     * $courses = [['final_score' => float|null, 'credit_value' => int], ...]
     */
    public static function cumulativeGpa(array $courses): ?float
    {
        $totalCredits  = 0;
        $weightedTotal = 0.0;

        foreach ($courses as $c) {
            if ($c['final_score'] === null) continue;
            $credits        = (int) ($c['credit_value'] ?? 0);
            $totalCredits  += $credits;
            $weightedTotal += self::gpa4((float) $c['final_score']) * $credits;
        }

        return $totalCredits > 0 ? round($weightedTotal / $totalCredits, 2) : null;
    }

    /** Average score on the 10-point scale, weighted by credits. */
    public static function cumulativeScore10(array $courses): ?float
    {
        $totalCredits  = 0;
        $weightedTotal = 0.0;

        foreach ($courses as $c) {
            if ($c['final_score'] === null) continue;
            $credits        = (int) ($c['credit_value'] ?? 0);
            $totalCredits  += $credits;
            $weightedTotal += (float) $c['final_score'] * $credits;
        }

        return $totalCredits > 0 ? round($weightedTotal / $totalCredits, 2) : null;
    }

    /** Credits that count as earned (passed). Default pass threshold: 4.0. */
    public static function earnedCredits(array $courses, float $passThreshold = 4.0): int
    {
        $total = 0;
        foreach ($courses as $c) {
            if ($c['final_score'] === null) continue;
            if ((float) $c['final_score'] < $passThreshold) continue;
            $total += (int) ($c['credit_value'] ?? 0);
        }
        return $total;
    }

    public static function isPassed(?float $score, float $passThreshold = 4.0): bool
    {
        return $score !== null && $score >= $passThreshold;
    }

    /**
     * Haversine distance in meters between two lat/lng points.
     */
    public static function distanceMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R   = 6371000; // Earth radius in metres
        $phi1 = deg2rad($lat1);
        $phi2 = deg2rad($lat2);
        $dphi = deg2rad($lat2 - $lat1);
        $dlam = deg2rad($lng2 - $lng1);

        $a = sin($dphi / 2) ** 2 + cos($phi1) * cos($phi2) * sin($dlam / 2) ** 2;
        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}

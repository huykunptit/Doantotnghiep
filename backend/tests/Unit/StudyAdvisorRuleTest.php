<?php

namespace Tests\Unit;

use App\Support\StudyAdvisorScoreRule;
use App\Support\StudyAdvisorTermResolver;
use Tests\TestCase;

class StudyAdvisorRuleTest extends TestCase
{
    public function test_score_below_absolute_threshold_is_weak(): void
    {
        $info = StudyAdvisorScoreRule::classify(6.4, 8.0);

        $this->assertTrue($info['is_weak']);
        $this->assertTrue($info['absolute']);
    }

    public function test_score_below_personal_gpa_minus_delta_is_weak(): void
    {
        $info = StudyAdvisorScoreRule::classify(7.0, 8.5);

        $this->assertTrue($info['is_weak']);
        $this->assertTrue($info['relative']);
        $this->assertFalse($info['absolute']);
    }

    public function test_score_at_or_above_thresholds_is_not_weak(): void
    {
        $info = StudyAdvisorScoreRule::classify(7.5, 8.0);

        $this->assertFalse($info['is_weak']);
        $this->assertFalse($info['absolute']);
        $this->assertFalse($info['relative']);
    }

    public function test_new_learner_without_enrollments_starts_at_first_curriculum_term(): void
    {
        $this->assertSame(1, StudyAdvisorTermResolver::currentTerm(null, 1));
        $this->assertSame(2, StudyAdvisorTermResolver::currentTerm(null, 2));
    }

    public function test_enrolled_student_uses_highest_enrolled_term(): void
    {
        $this->assertSame(3, StudyAdvisorTermResolver::currentTerm(3, 1));
    }

    public function test_profile_without_any_final_score_is_sparse(): void
    {
        $this->assertTrue(StudyAdvisorTermResolver::isSparseGrades([null, null, '']));
        $this->assertFalse(StudyAdvisorTermResolver::isSparseGrades([null, 7.5]));
        $this->assertTrue(StudyAdvisorTermResolver::isSparseGrades([]));
    }
}

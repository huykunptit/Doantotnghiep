<?php

namespace App\Services;

use App\Helpers\GpaCalculator;
use App\Models\CareerPath;
use App\Models\Enrollment;
use App\Models\User;
use App\Models\UserCareerPath;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class LearnerProfileService
{
    /** Map major name/code keywords → career target roles for recommendations. */
    public const MAJOR_ROLE_HINTS = [
        'cntt' => ['fullstack_python', 'backend_laravel', 'frontend_vue', 'devops'],
        'công nghệ thông tin' => ['fullstack_python', 'backend_laravel', 'frontend_vue', 'devops'],
        'ktpm' => ['fullstack_python', 'backend_laravel', 'frontend_vue'],
        'phần mềm' => ['fullstack_python', 'backend_laravel', 'frontend_vue'],
        'qtkd' => ['business_analyst', 'digital_marketing', 'product_owner'],
        'quản trị' => ['business_analyst', 'digital_marketing', 'product_owner'],
        'dtvt' => ['iot_embedded', 'network_engineer', 'devops'],
        'điện tử' => ['iot_embedded', 'network_engineer'],
        'viễn thông' => ['network_engineer', 'devops'],
    ];

    public function build(User $user, bool $useCache = true): array
    {
        $cacheKey = "learner_profile:{$user->id}";
        if ($useCache) {
            $cached = Cache::get($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $user->loadMissing([
            'program:id,code,name',
            'major:id,code,name',
            'cohort:id,code,name',
            'administrativeClass:id,code,name,curriculum_id',
            'latestCv',
        ]);

        $enrollments = Enrollment::query()
            ->where('user_id', $user->id)
            ->with([
                'course:id,title,course_mode,credit_value,is_credit_bearing,status',
                'course.skills:id,name,code',
                'term:id,name,code',
            ])
            ->get();

        $skillCounts = [];
        foreach ($enrollments as $enrollment) {
            foreach ($enrollment->course?->skills ?? [] as $skill) {
                $name = (string) $skill->name;
                $skillCounts[$name] = ($skillCounts[$name] ?? 0) + 1;
            }
        }

        $cvSkills = collect($user->latestCv?->skills ?? [])
            ->map(fn ($s) => is_string($s) ? $s : (string) ($s['name'] ?? $s))
            ->filter()
            ->values()
            ->all();

        foreach ($cvSkills as $skill) {
            $skillCounts[$skill] = ($skillCounts[$skill] ?? 0) + 2;
        }

        $gpaCourses = $enrollments->map(fn (Enrollment $e) => [
            'final_score' => $e->final_score,
            'credit_value' => (int) ($e->course->credit_value ?? 0),
        ])->all();

        $completedCore = $enrollments->filter(function (Enrollment $e) {
            return $e->course?->course_mode === 'core'
                && (($e->progress ?? 0) >= 100 || $e->final_score !== null);
        });

        $weak = $enrollments
            ->filter(fn (Enrollment $e) => $e->final_score !== null && (float) $e->final_score < 5.5)
            ->map(fn (Enrollment $e) => [
                'course_id' => $e->course_id,
                'title' => $e->course?->title,
                'final_score' => $e->final_score,
            ])
            ->values()
            ->all();

        $pathFollows = UserCareerPath::query()
            ->where('user_id', $user->id)
            ->with('careerPath:id,title,slug,target_role,status')
            ->get();

        $targetRoles = $this->inferTargetRoles($user, $pathFollows);

        $profile = [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'student_code' => $user->student_code,
                'program' => $user->program,
                'major' => $user->major,
                'cohort' => $user->cohort,
                'administrative_class' => $user->administrativeClass
                    ? $user->administrativeClass->only(['id', 'code', 'name', 'curriculum_id'])
                    : null,
            ],
            'goal' => [
                'target_roles' => $targetRoles,
                'cv_skills' => $cvSkills,
            ],
            'skills' => [
                'weighted' => $skillCounts,
                'top' => collect($skillCounts)->sortDesc()->take(12)->keys()->values()->all(),
            ],
            'academic' => [
                'enrollment_count' => $enrollments->count(),
                'core_completed_count' => $completedCore->count(),
                'overall_gpa' => GpaCalculator::cumulativeGpa($gpaCourses),
                'weak_courses' => $weak,
            ],
            'marketplace' => [
                'extension_enrollments' => $enrollments->where(fn ($e) => $e->course?->course_mode === 'extension')->count(),
                'career_paths' => $pathFollows->map(fn (UserCareerPath $ucp) => [
                    'id' => $ucp->career_path_id,
                    'status' => $ucp->status,
                    'progress_percent' => $ucp->progress_percent,
                    'path' => $ucp->careerPath,
                ])->values()->all(),
            ],
            'enrolled_course_ids' => $enrollments->pluck('course_id')->unique()->values()->all(),
        ];

        Cache::put($cacheKey, $profile, now()->addMinutes(5));

        return $profile;
    }

    public function forget(User|int $user): void
    {
        $id = $user instanceof User ? $user->id : $user;
        Cache::forget("learner_profile:{$id}");
    }

    public function inferTargetRoles(User $user, ?Collection $pathFollows = null): array
    {
        $roles = [];

        $pathFollows ??= UserCareerPath::where('user_id', $user->id)
            ->with('careerPath:id,target_role')
            ->get();

        foreach ($pathFollows as $ucp) {
            if ($ucp->careerPath?->target_role) {
                $roles[] = $ucp->careerPath->target_role;
            }
        }

        $haystack = mb_strtolower(trim(
            ($user->major?->code ?? '') . ' ' . ($user->major?->name ?? '') . ' ' . ($user->program?->name ?? '')
        ));

        foreach (self::MAJOR_ROLE_HINTS as $needle => $hints) {
            if ($haystack !== '' && str_contains($haystack, $needle)) {
                $roles = array_merge($roles, $hints);
            }
        }

        if ($roles === []) {
            $roles = ['fullstack_python', 'business_analyst'];
        }

        return array_values(array_unique($roles));
    }
}

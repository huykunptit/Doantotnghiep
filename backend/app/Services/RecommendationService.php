<?php

namespace App\Services;

use App\Models\CareerPath;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\User;
use App\Models\UserCareerPath;
use Illuminate\Support\Collection;

class RecommendationService
{
    public function __construct(private readonly LearnerProfileService $profiles) {}

    /**
     * @return array{courses: array, paths: array, context: array}
     */
    public function recommend(User $user, int $courseLimit = 8, int $pathLimit = 6): array
    {
        $profile = $this->profiles->build($user);
        $enrolledIds = $profile['enrolled_course_ids'] ?? [];
        $skillPool = collect($profile['skills']['weighted'] ?? []);
        $targetRoles = collect($profile['goal']['target_roles'] ?? []);
        $ownedPathIds = collect($profile['marketplace']['career_paths'] ?? [])
            ->pluck('id')
            ->all();

        $courses = $this->scoreCourses($user, $enrolledIds, $skillPool, $targetRoles, $courseLimit);
        $paths = $this->scorePaths($ownedPathIds, $skillPool, $targetRoles, $enrolledIds, $pathLimit);

        // Boost next-step courses from purchased/followed paths
        $nextFromPaths = $this->nextStepCourses($user, $enrolledIds);
        if ($nextFromPaths->isNotEmpty()) {
            $courses = $nextFromPaths
                ->concat($courses)
                ->unique(fn ($row) => $row['course']->id)
                ->take($courseLimit)
                ->values()
                ->all();
        }

        return [
            'courses' => $courses,
            'paths' => $paths,
            'context' => [
                'program_id' => $user->program_id,
                'major_id' => $user->major_id,
                'target_roles' => $targetRoles->values()->all(),
                'skill_pool_size' => $skillPool->count(),
                'top_skills' => $profile['skills']['top'] ?? [],
            ],
        ];
    }

    private function scoreCourses(
        User $user,
        array $enrolledIds,
        Collection $skillPool,
        Collection $targetRoles,
        int $limit
    ): array {
        $candidates = Course::query()
            ->where('course_mode', 'extension')
            ->where('status', 'published')
            ->whereNotIn('id', $enrolledIds)
            ->with(['skills:id,name', 'category:id,name,slug', 'instructor:id,name,avatar'])
            ->limit(60)
            ->get();

        $scored = $candidates->map(function (Course $course) use ($skillPool, $user, $targetRoles) {
            $reasons = [];
            $courseSkillNames = $course->skills->pluck('name');
            $overlapNames = $courseSkillNames->filter(fn ($n) => $skillPool->has($n))->values();
            $score = $overlapNames->count() * 30;
            if ($overlapNames->isNotEmpty()) {
                $reasons[] = 'Khớp kỹ năng: ' . $overlapNames->take(3)->implode(', ');
            }

            if ($user->major_id && $course->major_id && $course->major_id === $user->major_id) {
                $score += 25;
                $reasons[] = 'Cùng ngành đào tạo';
            } elseif ($user->program_id && $course->program_id === $user->program_id) {
                $score += 15;
                $reasons[] = 'Cùng chương trình đào tạo';
            }

            $hay = mb_strtolower($course->title . ' ' . ($course->slug ?? ''));
            foreach ($targetRoles as $role) {
                $token = str_replace('_', ' ', (string) $role);
                if ($token !== '' && str_contains($hay, explode(' ', $token)[0])) {
                    $score += 20;
                    $reasons[] = "Phù hợp mục tiêu: {$role}";
                    break;
                }
            }

            if (!empty($course->published_at)) {
                $daysOld = max(1, now()->diffInDays($course->published_at));
                $score += max(0, 20 - min(20, (int) $daysOld));
            }

            if ($reasons === []) {
                $reasons[] = 'Khóa marketplace đang mở';
            }

            return [
                'course' => $course,
                'score' => $score,
                'matched_skills' => $overlapNames->values()->all(),
                'reasons' => array_values(array_unique($reasons)),
            ];
        })
            ->sortByDesc('score')
            ->take($limit)
            ->values();

        if ($scored->isEmpty()) {
            $fallback = Course::query()
                ->where('status', 'published')
                ->where('course_mode', 'extension')
                ->whereNotIn('id', $enrolledIds)
                ->with(['category:id,name,slug', 'instructor:id,name,avatar'])
                ->withCount('enrollments')
                ->orderByDesc('enrollments_count')
                ->limit($limit)
                ->get()
                ->map(fn (Course $course) => [
                    'course' => $course,
                    'score' => 10 + (int) ($course->enrollments_count ?? 0),
                    'matched_skills' => [],
                    'reasons' => ['Phổ biến trên marketplace'],
                ]);

            return $fallback->values()->all();
        }

        return $scored->all();
    }

    private function scorePaths(
        array $ownedPathIds,
        Collection $skillPool,
        Collection $targetRoles,
        array $enrolledIds,
        int $limit
    ): array {
        $paths = CareerPath::query()
            ->published()
            ->with(['pathCourses.course.skills:id,name'])
            ->withCount('pathCourses')
            ->orderByDesc('published_at')
            ->limit(40)
            ->get();

        return $paths->map(function (CareerPath $path) use ($ownedPathIds, $skillPool, $targetRoles, $enrolledIds) {
            $reasons = [];
            $score = 10;

            if (in_array($path->id, $ownedPathIds, true)) {
                $score -= 50;
                $reasons[] = 'Bạn đã theo dõi/mua lộ trình này';
            }

            if ($path->target_role && $targetRoles->contains($path->target_role)) {
                $score += 40;
                $reasons[] = "Khớp mục tiêu nghề: {$path->target_role}";
            }

            $pathSkills = $path->pathCourses
                ->flatMap(fn ($pc) => $pc->course?->skills?->pluck('name') ?? [])
                ->unique();
            $overlap = $pathSkills->filter(fn ($n) => $skillPool->has($n));
            $score += $overlap->count() * 8;
            if ($overlap->isNotEmpty()) {
                $reasons[] = 'Kỹ năng liên quan: ' . $overlap->take(4)->implode(', ');
            }

            $courseIds = $path->pathCourses->pluck('course_id');
            $already = $courseIds->intersect($enrolledIds)->count();
            if ($already > 0) {
                $score += min(30, $already * 10);
                $reasons[] = "Bạn đã học {$already} khóa trong lộ trình";
            }

            if ($reasons === [] || ($reasons === ['Bạn đã theo dõi/mua lộ trình này'])) {
                $reasons[] = 'Lộ trình nghề đang mở bán';
            }

            return [
                'path' => [
                    'id' => $path->id,
                    'title' => $path->title,
                    'slug' => $path->slug,
                    'description' => $path->description,
                    'target_role' => $path->target_role,
                    'price' => $path->price,
                    'cover_url' => $path->cover_url,
                    'status' => $path->status,
                    'path_courses_count' => $path->path_courses_count,
                ],
                'score' => $score,
                'reasons' => array_values(array_unique($reasons)),
                'matched_skills' => $overlap->values()->all(),
            ];
        })
            ->sortByDesc('score')
            ->take($limit)
            ->values()
            ->all();
    }

    private function nextStepCourses(User $user, array $enrolledIds): Collection
    {
        $ucps = UserCareerPath::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['following', 'purchased', 'completed'])
            ->with(['careerPath.pathCourses' => fn ($q) => $q->where('is_required', true)->orderBy('sort_order')
                ->with(['course' => fn ($cq) => $cq->with(['skills:id,name', 'category:id,name,slug', 'instructor:id,name,avatar'])])])
            ->get();

        $rows = collect();
        foreach ($ucps as $ucp) {
            foreach ($ucp->careerPath?->pathCourses ?? [] as $pc) {
                $course = $pc->course;
                if (!$course || in_array($course->id, $enrolledIds, true)) {
                    continue;
                }
                if ($course->status !== 'published') {
                    continue;
                }
                $rows->push([
                    'course' => $course,
                    'score' => 100,
                    'matched_skills' => $course->skills->pluck('name')->all(),
                    'reasons' => [
                        'Bước tiếp theo trên lộ trình: ' . ($ucp->careerPath?->title ?? ''),
                    ],
                ]);
                break; // only next unfinished required course per path
            }
        }

        return $rows;
    }
}

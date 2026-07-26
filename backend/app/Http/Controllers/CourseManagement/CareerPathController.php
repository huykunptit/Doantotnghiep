<?php

namespace App\Http\Controllers\CourseManagement;

use App\Http\Controllers\Controller;
use App\Models\CareerPath;
use App\Models\CareerPathCourse;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\UserCareerPath;
use App\Services\CareerPathFulfillmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CareerPathController extends Controller
{
    public function __construct(private readonly CareerPathFulfillmentService $fulfillment) {}

    // ─── Public / student catalog ───────────────────────────────────────────

    public function publicIndex(Request $request): JsonResponse
    {
        $q = CareerPath::query()
            ->published()
            ->withCount(['pathCourses'])
            ->with(['pathCourses' => fn ($qq) => $qq->orderBy('sort_order')->limit(4)
                ->with('course:id,title,thumbnail,price')])
            ->orderByDesc('published_at')
            ->orderByDesc('id');

        if ($search = trim((string) $request->query('search', ''))) {
            $q->where(function ($w) use ($search) {
                $w->where('title', 'like', "%{$search}%")
                    ->orWhere('target_role', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($role = trim((string) $request->query('target_role', ''))) {
            $q->where('target_role', $role);
        }

        return response()->json(
            $q->paginate(min(50, max(1, (int) $request->query('per_page', 12))))
        );
    }

    public function publicShow(Request $request, string $slug): JsonResponse
    {
        $path = CareerPath::query()
            ->published()
            ->where('slug', $slug)
            ->with([
                'pathCourses' => fn ($q) => $q->orderBy('sort_order'),
                'certificateTemplate:id,name',
            ])
            ->withCount('pathCourses')
            ->firstOrFail();

        $path->load(['pathCourses.course' => function ($q) {
            $q->select('id', 'title', 'slug', 'thumbnail', 'price', 'status', 'course_mode')
                ->withCount('lessons');
        }]);

        $payload = $path->toArray();
        $user = $request->user() ?? auth('sanctum')->user();
        $payload['is_following'] = false;
        $payload['is_purchased'] = false;
        $payload['user_progress'] = null;
        $payload['enrolled_course_ids'] = [];

        if ($user) {
            $ucp = UserCareerPath::where('user_id', $user->id)
                ->where('career_path_id', $path->id)
                ->first();
            if ($ucp) {
                $ucp = $this->fulfillment->refreshProgress($ucp);
                $payload['is_following'] = true;
                $payload['is_purchased'] = in_array($ucp->status, ['purchased', 'completed'], true);
                $payload['user_progress'] = $ucp;
            }

            $courseIds = $path->pathCourses->pluck('course_id');
            $payload['enrolled_course_ids'] = Enrollment::where('user_id', $user->id)
                ->whereIn('course_id', $courseIds)
                ->pluck('course_id')
                ->values();
        }

        return response()->json($payload);
    }

    public function follow(Request $request, CareerPath $careerPath): JsonResponse
    {
        if ($careerPath->status !== 'published') {
            return response()->json(['message' => 'Path is not available'], 422);
        }

        $ucp = $this->fulfillment->follow($request->user()->id, $careerPath);

        return response()->json(['message' => 'Following path', 'user_career_path' => $ucp]);
    }

    public function myPaths(Request $request): JsonResponse
    {
        $rows = UserCareerPath::query()
            ->where('user_id', $request->user()->id)
            ->with(['careerPath' => fn ($q) => $q->withCount('pathCourses')])
            ->orderByDesc('updated_at')
            ->get();

        return response()->json(['data' => $rows]);
    }

    // ─── Admin CRUD ─────────────────────────────────────────────────────────

    public function adminIndex(Request $request): JsonResponse
    {
        if (!($request->user() && \App\Support\Authorize::allows($request->user(), ['manage_academic', 'manage_courses']))) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $q = CareerPath::query()
            ->withCount('pathCourses')
            ->with('certificateTemplate:id,name')
            ->orderByDesc('id');

        if ($search = trim((string) $request->query('search', ''))) {
            $q->where(function ($w) use ($search) {
                $w->where('title', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('target_role', 'like', "%{$search}%");
            });
        }

        if ($status = $request->query('status')) {
            $q->where('status', $status);
        }

        return response()->json(
            $q->paginate(min(50, max(1, (int) $request->query('per_page', 15))))
        );
    }

    public function adminStore(Request $request): JsonResponse
    {
        if (!($request->user() && \App\Support\Authorize::allows($request->user(), ['manage_academic', 'manage_courses']))) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255', 'unique:career_paths,slug'],
            'description' => ['nullable', 'string'],
            'target_role' => ['nullable', 'string', 'max:100'],
            'price' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', Rule::in(['draft', 'published', 'archived'])],
            'cover_url' => ['nullable', 'string', 'max:500'],
            'certificate_template_id' => ['nullable', 'integer', 'exists:certificate_templates,id'],
        ]);

        $status = $validated['status'] ?? 'draft';
        $path = CareerPath::create([
            ...$validated,
            'slug' => $validated['slug'] ?? CareerPath::uniqueSlug($validated['title']),
            'price' => $validated['price'] ?? 0,
            'status' => $status,
            'created_by' => $request->user()->id,
            'published_at' => $status === 'published' ? now() : null,
        ]);

        return response()->json($path->load('certificateTemplate:id,name')->loadCount('pathCourses'), 201);
    }

    public function adminShow(Request $request, CareerPath $careerPath): JsonResponse
    {
        if (!($request->user() && \App\Support\Authorize::allows($request->user(), ['manage_academic', 'manage_courses']))) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $careerPath->load([
            'pathCourses' => fn ($q) => $q->orderBy('sort_order')
                ->with('course:id,title,slug,price,status,course_mode,thumbnail'),
            'certificateTemplate:id,name',
        ])->loadCount('pathCourses');

        return response()->json($careerPath);
    }

    public function adminUpdate(Request $request, CareerPath $careerPath): JsonResponse
    {
        if (!($request->user() && \App\Support\Authorize::allows($request->user(), ['manage_academic', 'manage_courses']))) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'slug' => ['sometimes', 'nullable', 'string', 'max:255', Rule::unique('career_paths', 'slug')->ignore($careerPath->id)],
            'description' => ['nullable', 'string'],
            'target_role' => ['nullable', 'string', 'max:100'],
            'price' => ['nullable', 'integer', 'min:0'],
            'status' => ['nullable', Rule::in(['draft', 'published', 'archived'])],
            'cover_url' => ['nullable', 'string', 'max:500'],
            'certificate_template_id' => ['nullable', 'integer', 'exists:certificate_templates,id'],
        ]);

        if (array_key_exists('status', $validated) && $validated['status'] === 'published' && !$careerPath->published_at) {
            $validated['published_at'] = now();
        }

        if (isset($validated['title']) && blank($validated['slug'] ?? $careerPath->slug)) {
            $validated['slug'] = CareerPath::uniqueSlug($validated['title']);
        }

        $careerPath->update($validated);

        return response()->json($careerPath->fresh(['certificateTemplate:id,name'])->loadCount('pathCourses'));
    }

    public function adminDestroy(Request $request, CareerPath $careerPath): JsonResponse
    {
        if (!($request->user() && \App\Support\Authorize::isAdmin($request->user()))) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $careerPath->delete();

        return response()->json(['message' => 'Deleted']);
    }

    public function syncCourses(Request $request, CareerPath $careerPath): JsonResponse
    {
        if (!($request->user() && \App\Support\Authorize::allows($request->user(), ['manage_academic', 'manage_courses']))) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'courses' => ['required', 'array', 'min:0'],
            'courses.*.course_id' => ['required', 'integer', 'exists:courses,id'],
            'courses.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'courses.*.is_required' => ['nullable', 'boolean'],
            'courses.*.milestone_label' => ['nullable', 'string', 'max:120'],
        ]);

        $courseIds = collect($validated['courses'])->pluck('course_id')->unique()->values();
        $invalid = Course::whereIn('id', $courseIds)
            ->where('course_mode', 'core')
            ->pluck('id');
        if ($invalid->isNotEmpty()) {
            return response()->json([
                'message' => 'Core curriculum courses cannot be added to marketplace career paths',
                'invalid_course_ids' => $invalid,
            ], 422);
        }

        DB::transaction(function () use ($careerPath, $validated) {
            $keep = [];
            foreach ($validated['courses'] as $index => $item) {
                $row = CareerPathCourse::updateOrCreate(
                    [
                        'career_path_id' => $careerPath->id,
                        'course_id' => $item['course_id'],
                    ],
                    [
                        'sort_order' => $item['sort_order'] ?? $index,
                        'is_required' => (bool) ($item['is_required'] ?? true),
                        'milestone_label' => $item['milestone_label'] ?? null,
                    ]
                );
                $keep[] = $row->id;
            }

            CareerPathCourse::where('career_path_id', $careerPath->id)
                ->whereNotIn('id', $keep)
                ->delete();
        });

        return $this->adminShow($request, $careerPath->fresh());
    }

    public function destroyCourse(Request $request, CareerPath $careerPath, CareerPathCourse $pathCourse): JsonResponse
    {
        if (!($request->user() && \App\Support\Authorize::allows($request->user(), ['manage_academic', 'manage_courses']))) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($pathCourse->career_path_id !== $careerPath->id) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $pathCourse->delete();

        return response()->json(['message' => 'Removed']);
    }
}

<?php

namespace App\Http\Controllers\CourseManagement;

use App\Http\Controllers\Controller;

use App\Models\CareerPathCourse;
use App\Models\Category;
use App\Models\Course;
use App\Models\Curriculum;
use App\Models\CurriculumCourse;
use App\Models\Enrollment;
use App\Services\MediaService;
use App\Support\SearchQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CourseController extends Controller
{
    public function __construct(
        protected MediaService $mediaService
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Course::with('instructor:id,name,avatar', 'category:id,name,slug')
            ->withCount('lessons', 'enrollments', 'reviews')
            ->withAvg('reviews', 'rating');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        } else {
            $query->where('status', 'published');
        }

        if ($request->filled('category')) {
            $category = is_numeric($request->category)
                ? Category::find($request->category)
                : Category::where('slug', $request->category)->first();

            if ($category) {
                $query->whereIn('category_id', $this->categoryAndDescendantIds($category));
            } else {
                // Không tìm thấy danh mục → không có kết quả thay vì bỏ qua filter.
                $query->whereRaw('1 = 0');
            }
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                SearchQuery::like($q, ['title', 'description'], (string) $request->search);
            });
        }

        if ($request->filled('featured')) {
            $query->where('is_featured', $request->boolean('featured'));
        }

        if ($request->filled('instructor_id')) {
            $query->where('user_id', $request->instructor_id);
        }

        if ($request->filled('level')) {
            $query->where('level', $request->string('level'));
        }

        $pricing = trim((string) $request->query('pricing', ''));
        if ($pricing === 'paid') {
            $query->where('price', '>', 0);
        } elseif ($pricing === 'free') {
            $query->where(function ($q) {
                $q->whereNull('price')->orWhere('price', '<=', 0);
            });
        }

        $perPage = max(1, min(100, $request->integer('per_page', 12)));

        switch ((string) $request->query('sort', 'newest')) {
            case 'popular':
                $query->orderByDesc('is_featured')->orderByDesc('enrollments_count');
                break;
            case 'price_asc':
                $query->orderBy('price');
                break;
            case 'price_desc':
                $query->orderByDesc('price');
                break;
            case 'rating':
                $query->orderByDesc('reviews_avg_rating')->orderByDesc('enrollments_count');
                break;
            default:
                $query->orderByDesc('is_featured')->orderByDesc('created_at');
                break;
        }

        $courses = $query->paginate($perPage);

        return response()->json($courses);
    }

    /** ID của category + toàn bộ danh mục con/cháu (tối đa 3 cấp), để filter theo danh mục cha vẫn ra khóa của danh mục con. */
    protected function categoryAndDescendantIds(Category $category): array
    {
        $ids = [$category->id];
        $childIds = Category::where('parent_id', $category->id)->pluck('id')->all();
        $ids = array_merge($ids, $childIds);
        if ($childIds) {
            $ids = array_merge($ids, Category::whereIn('parent_id', $childIds)->pluck('id')->all());
        }

        return $ids;
    }

    public function myCoures(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $query = Course::with('instructor:id,name,avatar', 'category:id,name,slug')
            ->withCount('lessons', 'enrollments')
            ->where('user_id', $user->id);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('course_mode')) {
            $query->where('course_mode', $request->course_mode);
        }

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->integer('category_id'));
        }

        $pricing = trim((string) $request->query('pricing', ''));
        if ($pricing === 'paid') {
            $query->where('price', '>', 0);
        } elseif ($pricing === 'free') {
            $query->where(function ($q) {
                $q->whereNull('price')->orWhere('price', '<=', 0);
            });
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $query->where(function ($q) use ($search) {
                SearchQuery::like($q, ['title', 'slug'], $search);
                $q->orWhereHas('category', fn ($cq) => SearchQuery::like($cq, ['name'], $search));
            });
        }

        $sort = (string) $request->query('sort', 'newest');
        match ($sort) {
            'price_asc' => $query->orderBy('price')->orderByDesc('id'),
            'price_desc' => $query->orderByDesc('price')->orderByDesc('id'),
            'title' => $query->orderBy('title')->orderByDesc('id'),
            'learners' => $query->orderByDesc('enrollments_count')->orderByDesc('id'),
            default => $query->orderByDesc('created_at'),
        };

        $perPage = max(1, min(100, $request->integer('per_page', 12)));

        return response()->json($query->paginate($perPage));
    }

    public function store(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if (!\App\Support\Authorize::allows($user, ['manage_courses', 'manage_lessons', 'manage_exams', 'manage_grades', 'view_dashboard'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price'       => ['required', 'numeric', 'min:0'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'thumbnail'   => ['nullable', 'url', 'max:2048'],
            'thumbnail_file' => ['nullable', 'image', 'max:5120'],
            'is_featured' => ['nullable', 'boolean'],
            'certificate_template_id' => ['nullable', 'integer', 'exists:certificate_templates,id'],
        ]);

        // Auto-generate unique slug from title
        $slug = Str::slug($validated['title']);
        $originalSlug = $slug;
        $counter = 1;
        while (Course::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter++;
        }

        $thumbnailPath = $validated['thumbnail'] ?? null;
        if ($request->hasFile('thumbnail_file')) {
            $upload = $this->mediaService->upload($request->file('thumbnail_file'), 'courses/thumbnails');
            $thumbnailPath = $this->mediaService->getUrl($upload['path']);
        }

        $course = Course::create([
            'user_id'     => $user->id,
            'title'       => $validated['title'],
            'slug'        => $slug,
            'description' => $validated['description'] ?? null,
            'price'       => $validated['price'],
            'category_id' => $validated['category_id'] ?? null,
            'thumbnail'   => $thumbnailPath,
            'status'      => 'draft',
            'is_featured' => (bool) ($validated['is_featured'] ?? false),
            'certificate_template_id' => $validated['certificate_template_id'] ?? null,
        ]);

        $course->load('instructor:id,name,avatar');
        $course->loadCount('lessons', 'enrollments');

        return response()->json([
            'message' => 'Course created',
            'course'  => $course,
        ], 201);
    }

    public function show(Request $request, Course $course): JsonResponse
    {
        $course->load([
            'instructor:id,name,avatar',
            'category:id,name,slug',
            'lessons',
            'sections' => fn ($q) => $q->orderBy('position')->with([
                'lessons' => fn ($lq) => $lq->orderBy('order')->select(
                    'id',
                    'section_id',
                    'course_id',
                    'title',
                    'type',
                    'duration',
                    'is_preview',
                    'order'
                ),
            ]),
        ]);
        $course->loadCount('enrollments', 'reviews');

        $isEnrolled = false;
        $user = $request->user() ?? auth('sanctum')->user();
        if ($user) {
            $isEnrolled = Enrollment::where('user_id', $user->id)
                ->where('course_id', $course->id)
                ->exists();
        }

        // Compute average rating
        $avgRating = $course->reviews()->avg('rating');

        // Get latest 5 reviews
        $latestReviews = $course->reviews()
            ->with('user:id,name,avatar')
            ->orderByDesc('created_at')
            ->limit(5)
            ->get();

        $hasReviewed = false;
        if ($user) {
            $hasReviewed = $course->reviews()->where('user_id', $user->id)->exists();
        }

        return response()->json([
            ...$course->toArray(),
            'is_enrolled'    => $isEnrolled,
            'has_reviewed'   => $hasReviewed,
            'avg_rating'     => round($avgRating ?? 0, 1),
            'latest_reviews' => $latestReviews,
            'path_suggestions' => $this->buildPathSuggestions($course, $user?->id),
        ]);
    }

    /**
     * Khi mua lẻ 1 khoá: gợi ý các khoá còn lại trong lộ trình chứa khoá này.
     *
     * @return list<array<string, mixed>>
     */
    private function buildPathSuggestions(Course $course, ?int $userId): array
    {
        if ($course->course_mode === 'core') {
            return [];
        }

        $links = CareerPathCourse::query()
            ->where('course_id', $course->id)
            ->whereHas('careerPath', fn ($q) => $q->published())
            ->with([
                'careerPath' => fn ($q) => $q->with([
                    'pathCourses' => fn ($pq) => $pq->orderBy('sort_order')->with('course:id,title,slug,thumbnail,price,status'),
                ]),
            ])
            ->get();

        if ($links->isEmpty()) {
            return [];
        }

        $enrolledIds = [];
        if ($userId) {
            $allCourseIds = $links
                ->flatMap(fn (CareerPathCourse $link) => $link->careerPath?->pathCourses?->pluck('course_id') ?? collect())
                ->unique()
                ->values()
                ->all();
            if ($allCourseIds !== []) {
                $enrolledIds = Enrollment::query()
                    ->where('user_id', $userId)
                    ->whereIn('course_id', $allCourseIds)
                    ->pluck('course_id')
                    ->all();
            }
        }
        $enrolledSet = array_fill_keys($enrolledIds, true);

        $suggestions = [];
        foreach ($links as $link) {
            $path = $link->careerPath;
            if (!$path) {
                continue;
            }

            $items = $path->pathCourses ?? collect();
            $remaining = [];
            $ownedCount = 0;
            foreach ($items as $item) {
                $c = $item->course;
                if (!$c || $c->status !== 'published') {
                    continue;
                }
                if (isset($enrolledSet[$c->id]) || (int) $c->id === (int) $course->id) {
                    // Khoá hiện tại tính như sắp sở hữu khi đang xem/mua
                    $ownedCount++;
                    continue;
                }
                $remaining[] = [
                    'id' => $c->id,
                    'title' => $c->title,
                    'slug' => $c->slug,
                    'thumbnail' => $c->thumbnail,
                    'price' => (int) $c->price,
                ];
            }

            if ($remaining === []) {
                continue;
            }

            $suggestions[] = [
                'id' => $path->id,
                'title' => $path->title,
                'slug' => $path->slug,
                'path_price' => (int) $path->price,
                'total_count' => $items->count(),
                'owned_count' => $ownedCount,
                'remaining_count' => count($remaining),
                'remaining_total_price' => array_sum(array_column($remaining, 'price')),
                'remaining_courses' => $remaining,
            ];
        }

        return $suggestions;
    }

    public function update(Request $request, Course $course): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if (!\App\Support\Authorize::isAdmin($user) && (int) $course->user_id !== (int) $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        // Allow FormData JSON-encoded list fields
        foreach (['learning_outcomes', 'benefits', 'requirements'] as $field) {
            $raw = $request->input($field);
            if (is_string($raw)) {
                $decoded = json_decode($raw, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $request->merge([$field => $decoded]);
                }
            }
        }

        if ($request->exists('certificate_template_id') && $request->input('certificate_template_id') === '') {
            $request->merge(['certificate_template_id' => null]);
        }

        $validated = $request->validate([
            'title'       => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'learning_outcomes'   => ['nullable', 'array'],
            'learning_outcomes.*' => ['string', 'max:500'],
            'benefits'            => ['nullable', 'array'],
            'benefits.*'          => ['string', 'max:500'],
            'requirements'        => ['nullable', 'array'],
            'requirements.*'      => ['string', 'max:500'],
            'level'       => ['nullable', 'string', 'in:beginner,intermediate,advanced'],
            'trailer_url' => ['nullable', 'string', 'max:2048'],
            'price'       => ['sometimes', 'numeric', 'min:0'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'thumbnail'   => ['nullable', 'url', 'max:2048'],
            'thumbnail_file' => ['nullable', 'image', 'max:5120'],
            'status'      => ['sometimes', 'in:draft,published,closed,pending_review,rejected'],
            'is_featured' => ['nullable', 'boolean'],
            'certificate_template_id' => ['nullable', 'integer', 'exists:certificate_templates,id'],
        ]);

        if ($request->hasFile('thumbnail_file')) {
            $upload = $this->mediaService->upload($request->file('thumbnail_file'), 'courses/thumbnails');
            $validated['thumbnail'] = $this->mediaService->getUrl($upload['path']);
        }

        unset($validated['thumbnail_file']);

        if ($request->exists('is_featured')) {
            $validated['is_featured'] = $request->boolean('is_featured');
        }

        if ($request->exists('certificate_template_id')) {
            $rawCert = $request->input('certificate_template_id');
            $validated['certificate_template_id'] = ($rawCert === '' || $rawCert === null) ? null : (int) $rawCert;
        }

        $course->fill($validated)->save();
        $course->load('instructor:id,name,avatar', 'category:id,name,slug');
        $course->loadCount('lessons', 'enrollments');

        return response()->json([
            'message' => 'Course updated',
            'course'  => $course,
        ]);
    }

    public function destroy(Request $request, Course $course): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if (!\App\Support\Authorize::isAdmin($user) && (int) $course->user_id !== (int) $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $course->delete();

        return response()->json(['message' => 'Course deleted']);
    }

    public function publish(Request $request, Course $course): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if (!\App\Support\Authorize::isAdmin($user) && (int) $course->user_id !== (int) $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if (in_array($course->status, ['pending_review', 'published'])) {
            return response()->json(['message' => 'Course has already been submitted or published'], 422);
        }

        if ($course->lessons()->count() === 0) {
            return response()->json(['message' => 'Course must have at least 1 lesson before publishing'], 422);
        }

        $course->status = 'pending_review';
        $course->save();

        return response()->json(['message' => 'Course submitted for review', 'course' => $course]);
    }

    public function categories(): JsonResponse
    {
        // Map danh mục ngành → mã chương trình CTĐT (đếm đúng số học phần theo khung).
        $programByCategorySlug = [
            'cong-nghe-thong-tin' => 'CNTT',
            'quan-tri-kinh-doanh' => 'QTKD',
            'dien-tu-vien-thong' => 'DTVT',
        ];

        $categories = Category::with('children')
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->get()
            ->map(function (Category $category) use ($programByCategorySlug) {
                $programCode = $programByCategorySlug[$category->slug] ?? null;
                $curriculumCount = 0;

                if ($programCode) {
                    $curriculumIds = Curriculum::query()
                        ->whereHas('program', fn ($q) => $q->where('code', $programCode))
                        ->pluck('id');

                    if ($curriculumIds->isNotEmpty()) {
                        $curriculumCount = CurriculumCourse::query()
                            ->whereIn('curriculum_id', $curriculumIds)
                            ->whereHas('course', fn ($q) => $q->where('status', 'published'))
                            ->pluck('course_id')
                            ->unique()
                            ->count();
                    }
                }

                // Cộng thêm khóa marketplace/extension gắn đúng ngành (không nằm trong CTĐT).
                $childIds = $category->children->pluck('id')->push($category->id);
                $extensionCount = Course::query()
                    ->whereIn('category_id', $childIds)
                    ->where('status', 'published')
                    ->where('course_mode', 'extension')
                    ->count();

                $category->setAttribute('courses_count', $curriculumCount + $extensionCount);
                $category->setAttribute('curriculum_courses_count', $curriculumCount);
                $category->setAttribute('extension_courses_count', $extensionCount);

                return $category;
            });

        return response()->json($categories);
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Course;
use App\Models\Enrollment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CourseController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Course::with('instructor:id,name,avatar', 'category:id,name,slug')
            ->withCount('lessons', 'enrollments')
            ->withAvg('reviews', 'rating');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        } else {
            $query->where('status', 'published');
        }

        if ($request->filled('category')) {
            if (is_numeric($request->category)) {
                $query->where('category_id', $request->category);
            } else {
                $query->whereHas('category', function ($q) use ($request) {
                    $q->where('slug', $request->category);
                });
            }
        }

        if ($request->filled('search')) {
            $query->where(function ($q) use ($request) {
                $q->where('title', 'like', '%' . $request->search . '%')
                  ->orWhere('description', 'like', '%' . $request->search . '%');
            });
        }

        if ($request->filled('instructor_id')) {
            $query->where('user_id', $request->instructor_id);
        }

        $courses = $query->orderByDesc('created_at')
            ->paginate($request->get('per_page', 12));

        return response()->json($courses);
    }

    public function myCoures(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $courses = Course::with('instructor:id,name,avatar')
            ->withCount('lessons', 'enrollments')
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate($request->get('per_page', 12));

        return response()->json($courses);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if (!$user->hasAnyRole(['admin', 'instructor'])) {
            return response()->json(['message' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'title'       => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price'       => ['required', 'numeric', 'min:0'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'thumbnail'   => ['nullable', 'url', 'max:2048'],
        ]);

        // Auto-generate unique slug from title
        $slug = Str::slug($validated['title']);
        $originalSlug = $slug;
        $counter = 1;
        while (Course::where('slug', $slug)->exists()) {
            $slug = $originalSlug . '-' . $counter++;
        }

        $course = Course::create([
            'user_id'     => $user->id,
            'title'       => $validated['title'],
            'slug'        => $slug,
            'description' => $validated['description'] ?? null,
            'price'       => $validated['price'],
            'category_id' => $validated['category_id'] ?? null,
            'thumbnail'   => $validated['thumbnail'] ?? null,
            'status'      => 'draft',
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
        $course->load('instructor:id,name,avatar', 'category:id,name,slug', 'lessons');
        $course->loadCount('enrollments', 'reviews');

        $isEnrolled = false;
        $user = $request->user();
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

        return response()->json([
            ...$course->toArray(),
            'is_enrolled'    => $isEnrolled,
            'avg_rating'     => round($avgRating ?? 0, 1),
            'latest_reviews' => $latestReviews,
        ]);
    }

    public function update(Request $request, Course $course): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if (!$user->hasRole('admin') && $course->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'title'       => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price'       => ['sometimes', 'numeric', 'min:0'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'thumbnail'   => ['nullable', 'url', 'max:2048'],
            'status'      => ['sometimes', 'in:draft,published,closed'],
        ]);

        $course->fill($validated)->save();
        $course->load('instructor:id,name,avatar');
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

        if (!$user->hasRole('admin') && $course->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $course->delete();

        return response()->json(['message' => 'Course deleted']);
    }

    public function publish(Request $request, Course $course): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if (!$user->hasRole('admin') && $course->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
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
        $categories = Category::with('children')
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->get();

        return response()->json($categories);
    }
}

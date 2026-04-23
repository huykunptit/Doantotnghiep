<?php

namespace App\Http\Controllers\CourseManagement;

use App\Http\Controllers\Controller;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Review;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    /**
     * List reviews for a course (public).
     */
    public function index(Course $course): JsonResponse
    {
        $reviews = Review::with('user:id,name,avatar')
            ->where('course_id', $course->id)
            ->orderByDesc('created_at')
            ->paginate(10);

        return response()->json($reviews);
    }

    /**
     * Store a review (must be enrolled).
     */
    public function store(Request $request, Course $course): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        // Must be enrolled
        $enrolled = Enrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)->exists();

        if (!$enrolled) {
            return response()->json(['message' => 'Bạn cần đăng ký khóa học trước khi đánh giá.'], 403);
        }

        // Only 1 review per user per course
        $existing = Review::where('user_id', $user->id)
            ->where('course_id', $course->id)->first();

        if ($existing) {
            return response()->json(['message' => 'Bạn đã đánh giá khóa học này rồi.'], 422);
        }

        $validated = $request->validate([
            'rating'  => ['required', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $review = Review::create([
            'user_id'   => $user->id,
            'course_id' => $course->id,
            'rating'    => $validated['rating'],
            'comment'   => $validated['comment'] ?? null,
        ]);

        $review->load('user:id,name,avatar');

        return response()->json([
            'message' => 'Đánh giá thành công!',
            'review'  => $review,
        ], 201);
    }

    /**
     * Update own review.
     */
    public function update(Request $request, Course $course, Review $review): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if ($review->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'rating'  => ['sometimes', 'integer', 'min:1', 'max:5'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $review->fill($validated)->save();
        $review->load('user:id,name,avatar');

        return response()->json([
            'message' => 'Cập nhật đánh giá thành công!',
            'review'  => $review,
        ]);
    }

    /**
     * Delete own review.
     */
    public function destroy(Request $request, Course $course, Review $review): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if ($review->user_id !== $user->id && !$user->hasRole('admin')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $review->delete();

        return response()->json(['message' => 'Đã xóa đánh giá.']);
    }
}


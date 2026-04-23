<?php

namespace App\Http\Controllers\AIManagement;

use App\Http\Controllers\Controller;

use App\Models\AiRequestLog;
use App\Models\AiSetting;
use App\Models\Category;
use App\Models\Course;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AIChatController extends Controller
{
    /**
     * Send message to AI service and return response.
     */
    public function chat(Request $request): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|max:1000',
            'course_id' => 'nullable|integer',
        ]);

        $aiSettings = AiSetting::current();
        $aiServiceUrl = config('services.ai_service.url') . '/chat';
        $startTime = microtime(true);

        try {
            $response = Http::timeout(10)->post($aiServiceUrl, [
                'message' => $request->message,
                'user_id' => $request->user()->id,
                'course_id' => $request->course_id,
                'provider' => $aiSettings->provider,
                'model' => $aiSettings->model,
                'api_key' => $aiSettings->api_key,
                'context' => $this->buildChatContext($request),
            ]);

            $elapsed = (int) ((microtime(true) - $startTime) * 1000);

            AiRequestLog::create([
                'user_id' => $request->user()->id,
                'endpoint' => '/chat',
                'provider' => $aiSettings->provider,
                'model' => $aiSettings->model,
                'tokens_used' => mb_strlen($request->message) + mb_strlen($response->body()),
                'response_time_ms' => $elapsed,
                'status' => $response->successful() ? 'success' : 'error',
                'error_message' => $response->successful() ? null : 'HTTP ' . $response->status(),
            ]);

            if ($response->successful()) {
                return response()->json($response->json());
            }

            return response()->json([
                'reply' => 'Tôi đang gặp khó khăn trong việc kết nối với máy chủ AI. Bạn hãy thử lại sau nhé!'
            ], 503);

        } catch (\Exception $e) {
            $elapsed = (int) ((microtime(true) - $startTime) * 1000);

            AiRequestLog::create([
                'user_id' => $request->user()->id,
                'endpoint' => '/chat',
                'provider' => $aiSettings->provider,
                'model' => $aiSettings->model,
                'tokens_used' => 0,
                'response_time_ms' => $elapsed,
                'status' => 'error',
                'error_message' => $e->getMessage(),
            ]);

            return response()->json([
                'reply' => 'Hệ thống AI hiện không khả dụng. Xin lỗi vì sự bất tiện này.'
            ], 500);
        }
    }

    protected function buildChatContext(Request $request): array
    {
        $categories = Category::query()
            ->with([
                'children:id,name,parent_id',
            ])
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'children' => $category->children->map(fn (Category $child) => [
                    'id' => $child->id,
                    'name' => $child->name,
                ])->values()->all(),
            ])
            ->values()
            ->all();

        $courses = Course::query()
            ->with(['category:id,name', 'instructor:id,name'])
            ->withCount('lessons', 'enrollments', 'reviews')
            ->withAvg('reviews', 'rating')
            ->where('status', 'published')
            ->orderByDesc('enrollments_count')
            ->orderByDesc('published_at')
            ->limit(24)
            ->get()
            ->map(fn (Course $course) => [
                'id' => $course->id,
                'title' => $course->title,
                'description' => $course->description,
                'price' => $course->price,
                'category' => $course->category?->name,
                'instructor' => $course->instructor?->name,
                'lessons_count' => $course->lessons_count,
                'enrollments_count' => $course->enrollments_count,
                'reviews_count' => $course->reviews_count,
                'rating' => round((float) ($course->reviews_avg_rating ?? 0), 1),
            ])
            ->values()
            ->all();

        $currentCourse = null;
        if ($request->filled('course_id')) {
            $course = Course::query()
                ->with(['category:id,name', 'instructor:id,name'])
                ->withCount('lessons', 'enrollments', 'reviews')
                ->withAvg('reviews', 'rating')
                ->find($request->integer('course_id'));

            if ($course) {
                $currentCourse = [
                    'id' => $course->id,
                    'title' => $course->title,
                    'description' => $course->description,
                    'price' => $course->price,
                    'category' => $course->category?->name,
                    'instructor' => $course->instructor?->name,
                    'lessons_count' => $course->lessons_count,
                    'enrollments_count' => $course->enrollments_count,
                    'reviews_count' => $course->reviews_count,
                    'rating' => round((float) ($course->reviews_avg_rating ?? 0), 1),
                ];
            }
        }

        return [
            'categories' => $categories,
            'courses' => $courses,
            'current_course' => $currentCourse,
        ];
    }
}

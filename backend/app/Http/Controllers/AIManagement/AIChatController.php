<?php

namespace App\Http\Controllers\AIManagement;

use App\Http\Controllers\Controller;

use App\Models\AiRequestLog;
use App\Models\AiSetting;
use App\Models\Category;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LessonProgress;
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
            'history' => 'nullable|array|max:20',
            'history.*.role' => 'required_with:history|string|in:user,assistant',
            'history.*.content' => 'required_with:history|string|max:2000',
        ]);

        $aiSettings = AiSetting::current();
        $aiServiceUrl = config('services.ai_service.url') . '/chat';
        $startTime = microtime(true);

        $user = $request->user();
        $role = 'student';
        if (\App\Support\Authorize::isAdmin($user)) {
            $role = 'admin';
        } elseif ($user->hasRole('instructor')) {
            $role = 'instructor';
        }

        $provider = $aiSettings->provider ?: 'chatgpt';
        $apiKey = $aiSettings->api_key;
        if (!$apiKey) {
            $apiKey = match ($provider) {
                'gemini' => config('services.ai_service.gemini_api_key'),
                'openrouter' => config('services.ai_service.openrouter_api_key'),
                'ollama' => 'local',
                default => config('services.ai_service.openai_api_key'),
            };
        }
        $model = $aiSettings->model
            ?: ($provider === 'gemini' ? config('services.ai_service.gemini_model') : null);

        try {
            $timeout = $provider === 'ollama' ? 180 : 60;
            $response = Http::timeout($timeout)->post($aiServiceUrl, [
                'message' => $request->message,
                'user_id' => $user->id,
                'course_id' => $request->course_id,
                'provider' => $provider,
                'model' => $model,
                'api_key' => $apiKey,
                'role' => $role,
                'history' => $request->input('history', []),
                'context' => $this->buildChatContext($request),
            ]);

            $elapsed = (int) ((microtime(true) - $startTime) * 1000);
            $responseData = $response->json();
            $tokensUsed = $response->successful()
                ? (int) ($responseData['tokens_used']['total'] ?? 0)
                : 0;

            AiRequestLog::create([
                'user_id' => $user->id,
                'endpoint' => '/chat',
                'provider' => $provider,
                'model' => $model,
                'tokens_used' => $tokensUsed,
                'response_time_ms' => $elapsed,
                'status' => $response->successful() ? 'success' : 'error',
                'error_message' => $response->successful()
                    ? null
                    : ('HTTP ' . $response->status() . ' ' . ($responseData['detail'] ?? $response->body())),
            ]);

            if ($tokensUsed > 0) {
                $aiSettings->increment('tokens_used', $tokensUsed);
            }

            if ($response->successful()) {
                return response()->json($responseData);
            }

            return response()->json([
                'reply' => 'Tôi đang gặp khó khăn trong việc kết nối với máy chủ AI. Bạn hãy thử lại sau nhé!',
                'detail' => is_array($responseData) ? ($responseData['detail'] ?? null) : null,
            ], 503);

        } catch (\Exception $e) {
            $elapsed = (int) ((microtime(true) - $startTime) * 1000);

            AiRequestLog::create([
                'user_id' => $user->id,
                'endpoint' => '/chat',
                'provider' => $provider,
                'model' => $model,
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

    /**
     * Personalized study tips via ai-service /tutoring/recommend,
     * with heuristic fallback when AI is unavailable.
     */
    public function tutoring(Request $request): JsonResponse
    {
        $request->validate([
            'course_id' => 'nullable|integer',
            'lesson_id' => 'nullable|integer',
            'lesson_title' => 'nullable|string|max:255',
            'lesson_type' => 'nullable|string|max:40',
            'progress_percent' => 'nullable|numeric|min:0|max:100',
        ]);

        $user = $request->user();
        $enrollments = Enrollment::query()
            ->where('user_id', $user->id)
            ->with(['course:id,title', 'course.lessons:id,course_id'])
            ->get()
            ->map(function (Enrollment $enrollment) use ($user, $request) {
                $lessonIds = $enrollment->course?->lessons?->pluck('id') ?? collect();
                $total = $lessonIds->count();
                $completed = $total > 0
                    ? LessonProgress::where('user_id', $user->id)
                        ->whereIn('lesson_id', $lessonIds)
                        ->where('completed', true)
                        ->count()
                    : 0;
                $percent = $total > 0 ? round(($completed / $total) * 100, 1) : 0;

                // Prefer client-reported progress for the active course context.
                if ($request->integer('course_id') === (int) $enrollment->course_id && $request->filled('progress_percent')) {
                    $percent = (float) $request->input('progress_percent');
                }

                return [
                    'course_id' => $enrollment->course_id,
                    'course_title' => $enrollment->course?->title ?? ('Course #' . $enrollment->course_id),
                    'progress_percent' => $percent,
                    'quiz_avg_score' => null,
                    'last_accessed' => optional($enrollment->updated_at)?->toIso8601String(),
                ];
            })
            ->values()
            ->all();

        $aiSettings = AiSetting::current();
        $payload = [
            'user_id' => $user->id,
            'enrolled_courses' => $enrollments,
            'quiz_scores' => [],
            'study_pattern' => [
                'course_id' => $request->integer('course_id') ?: null,
                'lesson_id' => $request->integer('lesson_id') ?: null,
                'lesson_title' => $request->input('lesson_title'),
                'lesson_type' => $request->input('lesson_type'),
                'progress_percent' => $request->input('progress_percent'),
            ],
            'provider' => $aiSettings->provider,
            'model' => $aiSettings->model,
            'api_key' => $aiSettings->api_key ?: ($aiSettings->provider === 'ollama' ? 'local' : null),
        ];

        $startTime = microtime(true);
        $aiServiceUrl = rtrim((string) config('services.ai_service.url'), '/') . '/tutoring/recommend';

        try {
            if ($aiSettings->has_api_key) {
                $timeout = $aiSettings->provider === 'ollama' ? 180 : 45;
                $response = Http::timeout($timeout)->post($aiServiceUrl, $payload);
                $elapsed = (int) ((microtime(true) - $startTime) * 1000);
                $data = $response->json() ?: [];

                AiRequestLog::create([
                    'user_id' => $user->id,
                    'endpoint' => '/tutoring/recommend',
                    'provider' => $aiSettings->provider,
                    'model' => $aiSettings->model,
                    'tokens_used' => 0,
                    'response_time_ms' => $elapsed,
                    'status' => $response->successful() ? 'success' : 'error',
                    'error_message' => $response->successful() ? null : 'HTTP ' . $response->status(),
                ]);

                if ($response->successful()) {
                    return response()->json(array_merge($data, ['source' => 'ai']));
                }
            }
        } catch (\Throwable $e) {
            AiRequestLog::create([
                'user_id' => $user->id,
                'endpoint' => '/tutoring/recommend',
                'provider' => $aiSettings->provider,
                'model' => $aiSettings->model,
                'tokens_used' => 0,
                'response_time_ms' => (int) ((microtime(true) - $startTime) * 1000),
                'status' => 'error',
                'error_message' => $e->getMessage(),
            ]);
        }

        return response()->json(array_merge(
            $this->heuristicTutoring($request, $enrollments),
            ['source' => 'heuristic']
        ));
    }

    protected function heuristicTutoring(Request $request, array $enrollments): array
    {
        $progress = (float) ($request->input('progress_percent') ?? 0);
        $lessonType = (string) ($request->input('lesson_type') ?? '');
        $lessonTitle = (string) ($request->input('lesson_title') ?? '');
        $courseId = $request->integer('course_id') ?: null;

        $current = collect($enrollments)->firstWhere('course_id', $courseId);
        $stalled = collect($enrollments)
            ->filter(fn ($e) => ($e['progress_percent'] ?? 0) > 0 && ($e['progress_percent'] ?? 0) < 40)
            ->pluck('course_title')
            ->take(3)
            ->values()
            ->all();

        $reviewLessons = [];
        if ($lessonTitle !== '') {
            $reviewLessons[] = $lessonTitle;
        }
        if ($lessonType === 'quiz') {
            $reviewLessons[] = 'Ôn lại lý thuyết trước khi làm quiz';
        }

        $tips = [];
        if ($progress < 25) {
            $tips[] = 'Học đều mỗi ngày 20–30 phút để giữ nhịp tiến độ.';
            $tips[] = 'Hoàn thành bài hiện tại rồi chuyển sang bài kế tiếp trong lộ trình.';
        } elseif ($progress < 70) {
            $tips[] = 'Bạn đang ở giữa khóa — ghi chú ngắn sau mỗi bài giúp nhớ lâu hơn.';
            $tips[] = 'Xem lại bài đã hoàn thành nếu cảm thấy hổng kiến thức.';
        } else {
            $tips[] = 'Sắp hoàn thành khóa — làm quiz/ôn tập để củng cố trước khi nhận chứng chỉ.';
            $tips[] = 'Khám phá khóa mở rộng liên quan sau khi kết thúc khóa này.';
        }

        if ($lessonType === 'video') {
            $tips[] = 'Tạm dừng video và tóm tắt 2–3 ý chính giúp ghi nhớ tốt hơn.';
        } elseif ($lessonType === 'quiz') {
            $tips[] = 'Đọc kỹ đề; nếu sai, quay lại bài lý thuyết tương ứng ngay sau quiz.';
        }

        $summary = $current
            ? sprintf('Tiến độ khóa "%s": %.0f%%. Tiếp tục bài hiện tại để giữ đà học.', $current['course_title'], $current['progress_percent'] ?? $progress)
            : 'Dựa trên tiến độ ghi danh, hãy tiếp tục bài học hiện tại và giữ nhịp học đều.';

        return [
            'review_lessons' => $reviewLessons,
            'next_courses' => [],
            'weak_skills' => $stalled,
            'study_tips' => array_values(array_unique($tips)),
            'summary' => $summary,
        ];
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

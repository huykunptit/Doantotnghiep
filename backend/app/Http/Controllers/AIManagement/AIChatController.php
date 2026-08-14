<?php

namespace App\Http\Controllers\AIManagement;

use App\Http\Controllers\Controller;

use App\Models\AiSetting;
use App\Models\CareerPath;
use App\Models\Category;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\LessonProgress;
use App\Models\QuizAttempt;
use App\Services\AiServiceClient;
use App\Services\CurriculumEvaluationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AIChatController extends Controller
{
    public function __construct(
        protected AiServiceClient $ai,
        protected CurriculumEvaluationService $curriculumEvaluation,
    ) {
    }

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
            'use_rag' => 'nullable|boolean',
            'subject_hint' => 'nullable|string|max:255',
        ]);

        $startTime = microtime(true);

        $user = $request->user();
        $role = 'student';
        if (\App\Support\Authorize::isAdmin($user)) {
            $role = 'admin';
        } elseif ($user->hasRole('instructor')) {
            $role = 'instructor';
        }

        // Sinh viên/GV: bật RAG hỏi đáp theo giáo trình (ai-service tự retrieve nếu đã ingest).
        $useRag = $request->has('use_rag')
            ? $request->boolean('use_rag')
            : in_array($role, ['student', 'instructor'], true);

        // Trong khóa học → chỉ RAG giáo trình môn đó; chat ngoài → mọi giáo trình.
        $courseId = $request->filled('course_id') ? $request->integer('course_id') : null;
        $ragScope = $courseId ? 'course' : 'global';
        $subjectHint = $request->input('subject_hint');
        if ($ragScope === 'course' && !$subjectHint) {
            $subjectHint = Course::query()->whereKey($courseId)->value('title');
        }

        $result = $this->ai->postWithFallback('chat', [
            'message' => $request->message,
            'user_id' => $user->id,
            'course_id' => $courseId,
            'role' => $role,
            'history' => $request->input('history', []),
            'context' => $this->buildChatContext($request),
            'use_rag' => $useRag,
            'rag_top_k' => 5,
            'rag_scope' => $ragScope,
            'subject_hint' => $subjectHint,
        ], $user->id, '/chat', maxTotalMs: 30000);

        $elapsed = (int) ((microtime(true) - $startTime) * 1000);
        if ($result['ok']) {
            return response()->json(array_merge($result['data'], [
                'provider_used' => $result['provider'],
                'fallback' => $result['fallback'],
                'response_time_ms' => $elapsed,
            ]));
        }

        return response()->json([
            'reply' => 'Tôi đang gặp khó khăn trong việc kết nối với máy chủ AI. Bạn hãy thử lại sau nhé!',
            'detail' => $result['error'],
        ], 503);
    }

    /**
     * Bản streaming của chat() — trả lời hiện dần theo thời gian thực (SSE) thay vì
     * đợi có đủ câu trả lời mới hiển thị.
     */
    public function chatStream(Request $request): StreamedResponse
    {
        $request->validate([
            'message' => 'required|string|max:1000',
            'course_id' => 'nullable|integer',
            'history' => 'nullable|array|max:20',
            'history.*.role' => 'required_with:history|string|in:user,assistant',
            'history.*.content' => 'required_with:history|string|max:2000',
            'use_rag' => 'nullable|boolean',
            'subject_hint' => 'nullable|string|max:255',
        ]);

        $user = $request->user();
        $role = 'student';
        if (\App\Support\Authorize::isAdmin($user)) {
            $role = 'admin';
        } elseif ($user->hasRole('instructor')) {
            $role = 'instructor';
        }

        $useRag = $request->has('use_rag')
            ? $request->boolean('use_rag')
            : in_array($role, ['student', 'instructor'], true);

        $courseId = $request->filled('course_id') ? $request->integer('course_id') : null;
        $ragScope = $courseId ? 'course' : 'global';
        $subjectHint = $request->input('subject_hint');
        if ($ragScope === 'course' && !$subjectHint) {
            $subjectHint = Course::query()->whereKey($courseId)->value('title');
        }

        $payload = [
            'message' => $request->message,
            'user_id' => $user->id,
            'course_id' => $courseId,
            'role' => $role,
            'history' => $request->input('history', []),
            'context' => $this->buildChatContext($request),
            'use_rag' => $useRag,
            'rag_top_k' => 5,
            'rag_scope' => $ragScope,
            'subject_hint' => $subjectHint,
        ];

        return response()->stream(function () use ($payload, $user) {
            foreach ($this->ai->streamChat('chat/stream', 'chat', $payload, $user->id, '/chat/stream') as $chunk) {
                echo $chunk;
                if (ob_get_level() > 0) {
                    ob_flush();
                }
                flush();
            }
        }, 200, $this->sseHeaders());
    }

    /**
     * Chat công khai (chưa đăng nhập): hướng dẫn dùng hệ thống + tư vấn sơ lược khóa/lộ trình.
     * Câu hỏi sâu được system prompt từ chối và mời đăng nhập.
     */
    public function guestChat(Request $request): JsonResponse
    {
        $request->validate([
            'message' => 'required|string|max:500',
            'history' => 'nullable|array|max:8',
            'history.*.role' => 'required_with:history|string|in:user,assistant',
            'history.*.content' => 'required_with:history|string|max:1000',
        ]);

        $message = (string) $request->message;

        // FAQ / câu hỏi chuẩn: trả lời heuristic ngay — không phụ thuộc AI service
        if ($this->isGuestFaqIntent($message)) {
            return response()->json([
                'reply' => $this->heuristicGuestChat($message),
                'source' => 'heuristic',
            ]);
        }

        $aiSettings = AiSetting::current();
        if (!$aiSettings->is_active) {
            return response()->json([
                'reply' => $this->heuristicGuestChat($message),
                'source' => 'heuristic',
            ]);
        }

        $result = $this->ai->postWithFallback('chat', [
            'message' => $message,
            'user_id' => null,
            'course_id' => null,
            'role' => 'guest',
            'history' => $request->input('history', []),
            'context' => $this->buildGuestChatContext(),
        ], null, '/chat/guest', connectTimeout: 2, timeout: 18, maxTotalMs: 15000);

        if ($result['ok'] && is_string($result['data']['reply'] ?? null) && $result['data']['reply'] !== '') {
            return response()->json(array_merge($result['data'], [
                'provider_used' => $result['provider'],
                'fallback' => $result['fallback'],
            ]));
        }

        return response()->json([
            'reply' => $this->heuristicGuestChat($message),
            'source' => 'heuristic',
        ]);
    }

    /**
     * Bản streaming của guestChat(). FAQ vẫn trả lời tức thì (không gọi AI); câu hỏi
     * thật sự cần AI thì stream theo thời gian thực, có heuristic nếu AI service lỗi
     * hoàn toàn (kể cả sau khi đã thử fallback non-stream).
     */
    public function guestChatStream(Request $request): StreamedResponse
    {
        $request->validate([
            'message' => 'required|string|max:500',
            'history' => 'nullable|array|max:8',
            'history.*.role' => 'required_with:history|string|in:user,assistant',
            'history.*.content' => 'required_with:history|string|max:1000',
        ]);

        $message = (string) $request->message;

        if ($this->isGuestFaqIntent($message)) {
            return response()->stream(
                fn () => $this->emitSingleShotSse($this->heuristicGuestChat($message), 'heuristic'),
                200,
                $this->sseHeaders(),
            );
        }

        $aiSettings = AiSetting::current();
        if (!$aiSettings->is_active) {
            return response()->stream(
                fn () => $this->emitSingleShotSse($this->heuristicGuestChat($message), 'heuristic'),
                200,
                $this->sseHeaders(),
            );
        }

        $payload = [
            'message' => $message,
            'user_id' => null,
            'course_id' => null,
            'role' => 'guest',
            'history' => $request->input('history', []),
            'context' => $this->buildGuestChatContext(),
        ];

        return response()->stream(function () use ($payload, $message) {
            $sentAny = false;
            try {
                foreach ($this->ai->streamChat('chat/stream', 'chat', $payload, null, '/chat/guest/stream', connectTimeout: 2, timeout: 20) as $chunk) {
                    $sentAny = true;
                    echo $chunk;
                    if (ob_get_level() > 0) {
                        ob_flush();
                    }
                    flush();
                }
            } catch (\Throwable $e) {
                $sentAny = false;
            }

            if (!$sentAny) {
                $this->emitSingleShotSse($this->heuristicGuestChat($message), 'heuristic');
            }
        }, 200, $this->sseHeaders());
    }

    /** @return array<string, string> */
    protected function sseHeaders(): array
    {
        return [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
            'Connection' => 'keep-alive',
        ];
    }

    /** Gửi 1 câu trả lời (không phải AI) dưới dạng SSE, giữ nguyên "hợp đồng" định dạng với client. */
    protected function emitSingleShotSse(string $reply, string $source): void
    {
        echo 'data: ' . json_encode(['delta' => $reply], JSON_UNESCAPED_UNICODE) . "\n\n";
        echo 'data: ' . json_encode(['done' => true, 'source' => $source], JSON_UNESCAPED_UNICODE) . "\n\n";
        if (ob_get_level() > 0) {
            ob_flush();
        }
        flush();
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
        $quizAverages = $this->courseQuizAverages($user->id);

        $enrollments = Enrollment::query()
            ->where('user_id', $user->id)
            ->with(['course:id,title', 'course.lessons:id,course_id'])
            ->get()
            ->map(function (Enrollment $enrollment) use ($user, $request, $quizAverages) {
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
                    'quiz_avg_score' => $quizAverages->get($enrollment->course_id),
                    'last_accessed' => optional($enrollment->updated_at)?->toIso8601String(),
                ];
            })
            ->values()
            ->all();

        $result = $this->ai->postWithFallback('tutoring/recommend', [
            'user_id' => $user->id,
            'enrolled_courses' => $enrollments,
            'quiz_scores' => $this->recentQuizScores($user->id),
            'study_pattern' => [
                'course_id' => $request->integer('course_id') ?: null,
                'lesson_id' => $request->integer('lesson_id') ?: null,
                'lesson_title' => $request->input('lesson_title'),
                'lesson_type' => $request->input('lesson_type'),
                'progress_percent' => $request->input('progress_percent'),
            ],
        ], $user->id, '/tutoring/recommend', timeout: 90, maxTotalMs: 70000);

        if ($result['ok']) {
            return response()->json(array_merge($result['data'], [
                'source' => 'ai',
                'provider_used' => $result['provider'],
                'fallback' => $result['fallback'],
            ]));
        }

        return response()->json(array_merge(
            $this->heuristicTutoring($request, $enrollments),
            ['source' => 'heuristic']
        ));
    }

    /** Điểm quiz trung bình theo từng khóa (chỉ lần làm đã hoàn thành), key = course_id. */
    protected function courseQuizAverages(int $userId): \Illuminate\Support\Collection
    {
        return QuizAttempt::query()
            ->join('quizzes', 'quizzes.id', '=', 'quiz_attempts.quiz_id')
            ->where('quiz_attempts.user_id', $userId)
            ->whereIn('quiz_attempts.status', ['submitted', 'force_stopped'])
            ->whereNotNull('quizzes.course_id')
            ->whereNotNull('quiz_attempts.score')
            ->selectRaw('quizzes.course_id as course_id, AVG(quiz_attempts.score) as avg_score')
            ->groupBy('quizzes.course_id')
            ->get()
            ->keyBy('course_id')
            ->map(fn ($row) => round((float) $row->avg_score, 1));
    }

    /**
     * Danh sách các lần làm quiz gần nhất đã hoàn thành, dùng làm ngữ cảnh chi tiết cho AI
     * (khác với courseQuizAverages() ở trên là điểm trung bình theo khóa).
     *
     * @return list<array{title: string, score: float}>
     */
    protected function recentQuizScores(int $userId, int $limit = 15): array
    {
        return QuizAttempt::query()
            ->join('quizzes', 'quizzes.id', '=', 'quiz_attempts.quiz_id')
            ->where('quiz_attempts.user_id', $userId)
            ->whereIn('quiz_attempts.status', ['submitted', 'force_stopped'])
            ->whereNotNull('quiz_attempts.score')
            ->orderByDesc('quiz_attempts.completed_at')
            ->limit($limit)
            ->get(['quizzes.title as title', 'quiz_attempts.score as score'])
            ->map(fn ($row) => ['title' => (string) $row->title, 'score' => (float) $row->score])
            ->values()
            ->all();
    }

    /**
     * Tư vấn học tập AI cho trang Cố vấn học tập — diễn giải tự nhiên kết quả đánh giá
     * CTĐT (rule-based, từ CurriculumEvaluationService) thay vì endpoint /ai/tutoring
     * (vốn chỉ nhận course_id/lesson_id, dùng cho widget mẹo học bài LearnTip.vue).
     */
    public function studyAdvisorAdvice(Request $request): JsonResponse
    {
        $user = $request->user();
        $evaluation = $this->curriculumEvaluation->evaluate($user);

        if (!($evaluation['has_curriculum'] ?? false)) {
            return response()->json([
                'narrative' => $evaluation['message'] ?? 'Chưa có dữ liệu chương trình đào tạo để tư vấn.',
                'study_tips' => [],
                'focus_courses' => [],
                'source' => 'rule',
            ]);
        }

        $result = $this->ai->postWithFallback('tutoring/study-advisor', [
            'user_id' => $user->id,
            'academic_summary' => $evaluation['summary'] ?? [],
            'strengths' => $evaluation['strengths'] ?? [],
            'weaknesses' => $evaluation['weaknesses'] ?? [],
            'by_term' => $evaluation['by_term'] ?? [],
            'target_roles' => $evaluation['target_roles'] ?? [],
            'top_skills' => $evaluation['top_skills'] ?? [],
            'quiz_scores' => $this->recentQuizScores($user->id),
            'rule_based_narrative' => $evaluation['narrative'] ?? '',
        ], $user->id, '/tutoring/study-advisor', timeout: 60, maxTotalMs: 55000);

        if ($result['ok'] && filled($result['data']['narrative'] ?? null)) {
            return response()->json([
                'narrative' => $result['data']['narrative'],
                'study_tips' => $result['data']['study_tips'] ?? [],
                'focus_courses' => $result['data']['focus_courses'] ?? [],
                'source' => 'ai',
                'provider_used' => $result['provider'],
            ]);
        }

        return response()->json([
            'narrative' => $evaluation['narrative'] ?? '',
            'study_tips' => [],
            'focus_courses' => [],
            'source' => 'rule',
        ]);
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

    /**
     * Fallback cho khách chưa đăng nhập khi AI service không sẵn sàng.
     */
    protected function heuristicGuestChat(string $message): string
    {
        $text = mb_strtolower(trim($message));
        $ascii = mb_strtolower(Str::ascii($text));

        $topCourses = Course::query()
            ->where('status', 'published')
            ->where('is_featured', true)
            ->orderByDesc('published_at')
            ->limit(3)
            ->pluck('title')
            ->filter()
            ->values()
            ->all();
        if (!count($topCourses)) {
            $topCourses = Course::query()
                ->where('status', 'published')
                ->orderByDesc('published_at')
                ->limit(3)
                ->pluck('title')
                ->filter()
                ->values()
                ->all();
        }
        $courseList = count($topCourses) ? implode(', ', $topCourses) : 'các khóa học nổi bật trên hệ thống';

        if ($text === '') {
            return 'Tôi có thể giúp bạn khám phá khóa học phù hợp, định hướng lộ trình nghề nghiệp và hướng dẫn đăng ký thật nhanh. Nếu bạn muốn, tôi cũng có thể gợi ý ngay vài khóa nổi bật để bạn bắt đầu.';
        }

        if ($this->isGuestWebsiteIntroIntent($message)) {
            return $this->formatGuestWebsiteIntro();
        }

        if (
            str_contains($text, 'quên mật khẩu')
            || str_contains($text, 'quên pass')
            || str_contains($ascii, 'quen mat khau')
            || str_contains($ascii, 'forgot password')
            || str_contains($ascii, 'reset password')
            || (str_contains($text, 'mật khẩu') && (str_contains($text, 'quên') || str_contains($text, 'không nhớ')))
            || (str_contains($ascii, 'mat khau') && (str_contains($ascii, 'quen') || str_contains($ascii, 'khong nho')))
        ) {
            return 'Bạn vào trang Đăng nhập → bấm "Quên mật khẩu", nhập email đã đăng ký rồi làm theo hướng dẫn trong email để đặt mật khẩu mới. Nếu không thấy email, hãy kiểm tra hộp thư rác/spam. Vẫn cần hỗ trợ thêm thì liên hệ bộ phận hỗ trợ của nhà trường nhé.';
        }

        if (str_contains($text, 'đăng ký') || str_contains($text, 'tạo tài khoản') || str_contains($ascii, 'dang ky') || str_contains($ascii, 'tao tai khoan') || str_contains($ascii, 'register')) {
            return 'Rất đơn giản: bạn chỉ cần bấm "Đăng ký" ở góc trên bên phải, nhập họ tên, email và mật khẩu rồi xác nhận theo hướng dẫn. Sau khi có tài khoản, bạn sẽ mở được tư vấn AI chi tiết hơn, theo dõi lộ trình học và nhận gợi ý khóa phù hợp sát mục tiêu của mình.';
        }

        if (str_contains($text, 'đăng nhập') || str_contains($ascii, 'dang nhap') || str_contains($ascii, 'login') || str_contains($ascii, 'log in')) {
            return 'Bạn bấm "Đăng nhập" ở góc trên bên phải, nhập email và mật khẩu đã đăng ký là có thể vào hệ thống ngay. Nếu quên mật khẩu, bạn dùng "Quên mật khẩu" để đặt lại. Khi đăng nhập xong, tôi có thể tư vấn sâu hơn theo đúng nhu cầu học của bạn.';
        }

        if (str_contains($text, 'khóa học') || str_contains($ascii, 'khoa hoc') || str_contains($ascii, 'course')) {
            return "Bạn có thể bắt đầu ở trang \"Khóa học\" để xem danh sách công khai, lọc theo danh mục và chọn khóa phù hợp. Hiện trên hệ thống có những lựa chọn nổi bật như {$courseList}. Nếu bạn muốn, hãy đăng ký tài khoản để tôi gợi ý chính xác hơn theo định hướng nghề nghiệp và trình độ hiện tại của bạn.";
        }

        if (str_contains($text, 'lộ trình') || str_contains($text, 'nghề nghiệp') || str_contains($ascii, 'lo trinh') || str_contains($ascii, 'nghe nghiep') || str_contains($ascii, 'career')) {
            return $this->formatGuestCareerPathsOverview();
        }

        if (
            str_contains($text, 'lĩnh vực đào tạo')
            || str_contains($text, 'linh vuc dao tao')
            || (str_contains($text, 'lĩnh vực') && str_contains($text, 'đào tạo'))
            || (str_contains($ascii, 'linh vuc') && str_contains($ascii, 'dao tao'))
            || str_contains($text, 'ngành')
            || str_contains($ascii, 'nganh')
            || str_contains($text, 'đào tạo gì')
            || str_contains($ascii, 'dao tao gi')
        ) {
            return $this->formatGuestTrainingAreas();
        }

        if (str_contains($text, 'học phí') || str_contains($text, 'điểm') || str_contains($ascii, 'hoc phi') || str_contains($ascii, 'diem') || str_contains($ascii, 'gpa') || str_contains($ascii, 'cv')) {
            return 'Nội dung này cần dữ liệu cá nhân hoặc mức tư vấn sâu hơn để trả lời thật chính xác. Bạn đăng nhập vào hệ thống nhé, khi đó tôi có thể hỗ trợ sát ngữ cảnh hơn và gợi ý hữu ích hơn nhiều.';
        }

        return 'Tôi có thể giúp bạn: giới thiệu Eript LMS và 3 ngành đào tạo, gợi ý khóa/lộ trình nổi bật, hoặc hướng dẫn đăng ký / đăng nhập / quên mật khẩu. Bạn hỏi cụ thể hơn giúp mình nhé — hoặc đăng ký tài khoản để được tư vấn sâu hơn.';
    }

    protected function isGuestFaqIntent(string $message): bool
    {
        $text = mb_strtolower(trim($message));
        $ascii = mb_strtolower(Str::ascii($text));

        if ($text === '') {
            return true;
        }

        if ($this->isGuestWebsiteIntroIntent($message)) {
            return true;
        }

        return str_contains($text, 'quên mật khẩu')
            || str_contains($text, 'quên pass')
            || str_contains($ascii, 'quen mat khau')
            || str_contains($ascii, 'forgot password')
            || str_contains($ascii, 'reset password')
            || (str_contains($text, 'mật khẩu') && (str_contains($text, 'quên') || str_contains($text, 'không nhớ')))
            || (str_contains($ascii, 'mat khau') && (str_contains($ascii, 'quen') || str_contains($ascii, 'khong nho')))
            || str_contains($text, 'đăng ký')
            || str_contains($text, 'tạo tài khoản')
            || str_contains($ascii, 'dang ky')
            || str_contains($ascii, 'tao tai khoan')
            || str_contains($ascii, 'register')
            || str_contains($text, 'đăng nhập')
            || str_contains($ascii, 'dang nhap')
            || str_contains($ascii, 'login')
            || str_contains($text, 'khóa học')
            || str_contains($ascii, 'khoa hoc')
            || str_contains($ascii, 'course')
            || str_contains($text, 'lộ trình')
            || str_contains($text, 'nghề nghiệp')
            || str_contains($ascii, 'lo trinh')
            || str_contains($ascii, 'nghe nghiep')
            || str_contains($ascii, 'career')
            || str_contains($text, 'lĩnh vực')
            || str_contains($ascii, 'linh vuc')
            || str_contains($text, 'ngành')
            || str_contains($ascii, 'nganh')
            || str_contains($text, 'đào tạo')
            || str_contains($ascii, 'dao tao');
    }

    protected function isGuestWebsiteIntroIntent(string $message): bool
    {
        $text = mb_strtolower(trim($message));
        $ascii = mb_strtolower(Str::ascii($text));

        if ($text === '') {
            return false;
        }

        return str_contains($text, 'giới thiệu web')
            || str_contains($text, 'giới thiệu website')
            || str_contains($text, 'giới thiệu hệ thống')
            || str_contains($text, 'giới thiệu nền tảng')
            || str_contains($text, 'giới thiệu eript')
            || str_contains($text, 'eript là gì')
            || str_contains($text, 'web này là gì')
            || str_contains($text, 'trang web này')
            || str_contains($ascii, 'gioi thieu web')
            || str_contains($ascii, 'gioi thieu website')
            || str_contains($ascii, 'gioi thieu he thong')
            || str_contains($ascii, 'gioi thieu nen tang')
            || str_contains($ascii, 'gioi thieu eript')
            || str_contains($ascii, 'eript la gi')
            || str_contains($ascii, 'about eript')
            || str_contains($ascii, 'what is eript')
            || (($text === 'giới thiệu' || $ascii === 'gioi thieu') && !str_contains($text, 'chat'));
    }

    /** Pitch seller khi khách hỏi giới thiệu web / Eript LMS. */
    protected function formatGuestWebsiteIntro(): string
    {
        $featured = Course::query()
            ->where('status', 'published')
            ->where('is_featured', true)
            ->orderByDesc('published_at')
            ->limit(5)
            ->pluck('title')
            ->filter()
            ->values()
            ->all();
        if (!count($featured)) {
            $featured = Course::query()
                ->where('status', 'published')
                ->orderByDesc('published_at')
                ->limit(5)
                ->pluck('title')
                ->filter()
                ->values()
                ->all();
        }
        $featuredText = count($featured)
            ? implode('; ', $featured)
            : 'các khóa nền tảng đang mở đăng ký';

        $lines = [
            'Eript LMS là nền tảng học tập trực tuyến giúp bạn học theo lộ trình rõ ràng, luyện kỹ năng thực chiến và theo dõi tiến độ ngay trên hệ thống.',
            'Chúng tôi tập trung 3 lĩnh vực chính: Công nghệ thông tin, Quản trị kinh doanh và Điện tử viễn thông.',
            "Một số khóa nổi bật bạn có thể khám phá ngay: {$featuredText}.",
        ];

        foreach ($this->guestMajorCatalog() as $major) {
            $courseText = count($major['courses'])
                ? implode(', ', $major['courses'])
                : 'các học phần nền tảng và chuyên sâu';
            $pathText = count($major['paths'])
                ? implode('; ', $major['paths'])
                : 'lộ trình từ cơ bản đến ứng dụng nghề';
            $lines[] = "- {$major['label']}: khóa tiêu biểu gồm {$courseText}. Lộ trình gợi ý: {$pathText}.";
        }

        $lines[] = 'Bạn muốn đi ngành nào trước? Cho tôi biết mục tiêu (ví dụ lập trình, marketing hay mạng/viễn thông) để tôi gợi ý khóa + lộ trình sát hơn — hoặc đăng ký tài khoản để được tư vấn chi tiết.';

        return implode("\n", $lines);
    }

    protected function formatGuestCareerPathsOverview(): string
    {
        $lines = ['Trên Eript LMS, mỗi ngành đều có lộ trình nghề rõ bước — vài gợi ý đang mở:'];
        foreach ($this->guestMajorCatalog() as $major) {
            $pathText = count($major['paths'])
                ? implode('; ', $major['paths'])
                : 'lộ trình nền tảng → chuyên sâu';
            $lines[] = "- {$major['label']}: {$pathText}.";
        }
        $lines[] = 'Bạn chọn một hướng, tôi sẽ gợi ý khóa bắt đầu phù hợp. Đăng ký tài khoản để mở theo dõi lộ trình và tư vấn sâu hơn.';

        return implode("\n", $lines);
    }

    protected function formatGuestTrainingAreas(): string
    {
        $lines = ['Hiện Eript LMS đang giới thiệu 3 lĩnh vực đào tạo nổi bật:'];

        foreach ($this->guestMajorCatalog() as $major) {
            $courseText = count($major['courses'])
                ? implode(', ', $major['courses'])
                : 'một số khóa nền tảng và ứng dụng thực tế';
            $pathText = count($major['paths'])
                ? implode('; ', $major['paths'])
                : null;

            $line = "- {$major['label']}: nổi bật với {$courseText}.";
            if ($pathText) {
                $line .= " Lộ trình gợi ý: {$pathText}.";
            }
            $lines[] = $line;
        }

        $lines[] = 'Nếu bạn muốn, tôi có thể gợi ý ngay ngành nào hợp với mục tiêu của bạn nhất, rồi bạn chỉ cần đăng ký tài khoản để nhận tư vấn sâu hơn.';

        return implode("\n", $lines);
    }

    /**
     * @return list<array{label: string, courses: list<string>, paths: list<string>}>
     */
    protected function guestMajorCatalog(): array
    {
        $majors = [
            [
                'slug' => 'cong-nghe-thong-tin',
                'label' => 'Công nghệ thông tin',
                'path_keywords' => ['fullstack', 'frontend', 'backend', 'devops', 'mobile', 'data', 'qa', 'cyber', 'python', 'laravel', 'vue', 'flutter'],
            ],
            [
                'slug' => 'quan-tri-kinh-doanh',
                'label' => 'Quản trị kinh doanh',
                'path_keywords' => ['business', 'marketing', 'product-owner', 'product_owner', 'project-manager', 'project_manager', 'agile', 'graphic', 'digital-marketing', 'ui-ux', 'ui/ux'],
            ],
            [
                'slug' => 'dien-tu-vien-thong',
                'label' => 'Điện tử viễn thông',
                'path_keywords' => ['network', 'iot', 'embedded', 'viễn thông', 'vien thong'],
            ],
        ];

        $allPaths = CareerPath::query()
            ->published()
            ->orderBy('title')
            ->get(['id', 'title', 'slug', 'target_role']);

        $catalog = [];
        foreach ($majors as $major) {
            $category = Category::query()->where('slug', $major['slug'])->first();
            $courses = Course::query()
                ->where('status', 'published')
                ->where('is_featured', true)
                ->when($category, fn ($q) => $q->where('category_id', $category->id))
                ->orderByDesc('published_at')
                ->limit(3)
                ->pluck('title')
                ->filter()
                ->values()
                ->all();

            if (!count($courses)) {
                $courses = Course::query()
                    ->where('status', 'published')
                    ->when($category, fn ($q) => $q->where('category_id', $category->id))
                    ->orderByDesc('published_at')
                    ->limit(3)
                    ->pluck('title')
                    ->filter()
                    ->values()
                    ->all();
            }

            $paths = $allPaths
                ->filter(function (CareerPath $path) use ($major) {
                    $hay = mb_strtolower(($path->title ?? '') . ' ' . ($path->slug ?? '') . ' ' . ($path->target_role ?? ''));
                    foreach ($major['path_keywords'] as $kw) {
                        if (str_contains($hay, mb_strtolower($kw))) {
                            return true;
                        }
                    }

                    return false;
                })
                ->take(3)
                ->pluck('title')
                ->values()
                ->all();

            $catalog[] = [
                'label' => $major['label'],
                'courses' => $courses,
                'paths' => $paths,
            ];
        }

        return $catalog;
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

    /** Context công khai rút gọn cho khách chưa đăng nhập. */
    protected function buildGuestChatContext(): array
    {
        $categories = Category::query()
            ->with(['children:id,name,parent_id'])
            ->whereNull('parent_id')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit(12)
            ->get(['id', 'name'])
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'name' => $category->name,
                'children' => $category->children->take(8)->map(fn (Category $child) => [
                    'id' => $child->id,
                    'name' => $child->name,
                ])->values()->all(),
            ])
            ->values()
            ->all();

        $courses = Course::query()
            ->with(['category:id,name'])
            ->withCount('lessons', 'enrollments')
            ->withAvg('reviews', 'rating')
            ->where('status', 'published')
            ->where('is_featured', true)
            ->orderByDesc('published_at')
            ->limit(12)
            ->get()
            ->map(fn (Course $course) => [
                'id' => $course->id,
                'title' => $course->title,
                'description' => mb_substr((string) ($course->description ?? ''), 0, 180),
                'price' => $course->price,
                'category' => $course->category?->name,
                'lessons_count' => $course->lessons_count,
                'enrollments_count' => $course->enrollments_count,
                'rating' => round((float) ($course->reviews_avg_rating ?? 0), 1),
            ])
            ->values()
            ->all();

        if (!count($courses)) {
            $courses = Course::query()
                ->with(['category:id,name'])
                ->withCount('lessons', 'enrollments')
                ->withAvg('reviews', 'rating')
                ->where('status', 'published')
                ->orderByDesc('published_at')
                ->limit(12)
                ->get()
                ->map(fn (Course $course) => [
                    'id' => $course->id,
                    'title' => $course->title,
                    'description' => mb_substr((string) ($course->description ?? ''), 0, 180),
                    'price' => $course->price,
                    'category' => $course->category?->name,
                    'lessons_count' => $course->lessons_count,
                    'enrollments_count' => $course->enrollments_count,
                    'rating' => round((float) ($course->reviews_avg_rating ?? 0), 1),
                ])
                ->values()
                ->all();
        }

        $careerPaths = CareerPath::query()
            ->published()
            ->orderBy('title')
            ->limit(12)
            ->get(['id', 'title', 'slug', 'target_role', 'description'])
            ->map(fn (CareerPath $path) => [
                'id' => $path->id,
                'title' => $path->title,
                'target_role' => $path->target_role,
                'description' => mb_substr((string) ($path->description ?? ''), 0, 160),
            ])
            ->values()
            ->all();

        return [
            'categories' => $categories,
            'courses' => $courses,
            'career_paths' => $careerPaths,
            'current_course' => null,
            'guest_mode' => true,
            'hint' => 'Khi khách hỏi "giới thiệu web/website/Eript/hệ thống": ĐÓNG VAI SELLER — giới thiệu Eript LMS đào tạo 3 ngành (CNTT, QTKD, ĐTVT), kể tên khóa nổi bật trong context, nêu vài lộ trình nghề theo từng ngành từ career_paths, rồi CTA mời đăng ký. Đừng giải thích công dụng của chatbot. Với câu hỏi khác: tư vấn khóa/danh mục/lộ trình ngắn gọn, thuyết phục, CTA mềm.',
        ];
    }
}

<?php

namespace App\Http\Controllers\AIManagement;

use App\Http\Controllers\Controller;

use App\Models\AiRequestLog;
use App\Models\AiSetting;
use App\Models\UserCV;
use App\Models\CareerRecommendation;
use App\Models\Course;
use App\Services\CVAnalysisService;
use App\Services\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CareerAdvisorController extends Controller
{
    protected $mediaService;
    protected $cvAnalysisService;

    public function __construct(MediaService $mediaService, CVAnalysisService $cvAnalysisService)
    {
        $this->mediaService = $mediaService;
        $this->cvAnalysisService = $cvAnalysisService;
    }

    /**
     * Get current student's career advisor status.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        
        $latestCV = $user->latestCv;
        $recommendations = $user->careerRecommendations()
            ->with('job')
            ->latest()
            ->take(5)
            ->get()
            ->map(fn (CareerRecommendation $recommendation) => $this->serializeRecommendation($recommendation));

        return response()->json([
            'cv' => $latestCV,
            'recommendations' => $recommendations
        ]);
    }

    /**
     * Upload and parse CV.
     */
    public function uploadCV(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $request->validate([
            'cv' => 'required|file|mimes:pdf,doc,docx|max:5120', // 5MB limit
        ]);

        try {
            $file = $request->file('cv');
            $uploadResult = $this->mediaService->upload($file, "users/{$user->id}/cvs");
            $disk = $this->mediaService->getDisk();
            $absolutePath = Storage::disk($disk)->path($uploadResult['path']);
            $analysis = $this->cvAnalysisService->analyze(
                $absolutePath,
                (string) $file->getClientOriginalName()
            );

            $userCv = UserCV::create([
                'user_id' => $user->id,
                'file_path' => $uploadResult['path'],
                'file_name' => $uploadResult['name'],
                'parsed_text' => $analysis['text'],
                'skills' => $analysis['skills'],
            ]);

            AiRequestLog::create([
                'user_id' => $user->id,
                'endpoint' => '/parse-cv',
                'provider' => 'local-pipeline',
                'model' => 'document-analysis-v1',
                'tokens_used' => mb_strlen((string) $analysis['text']),
                'response_time_ms' => 0,
                'status' => 'success',
            ]);

            return response()->json([
                'message' => 'CV uploaded and analyzed successfully',
                'cv' => $userCv,
                'pipeline' => $analysis['pipeline'],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to process CV',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get recommendations based on desired job title.
     */
    public function recommend(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $cv = $user->latestCv;

        if (!$cv) {
            return response()->json(['message' => 'Please upload a CV first'], 400);
        }

        $request->validate([
            'job_title' => 'required|string|max:255',
        ]);

        $data = $this->getRecommendationPayload($cv, $request->job_title);

        // Find relevant courses in our database based on AI suggestions
        $suggestedCourseIds = $this->resolveSuggestedCourses($data)->values();
        $expertAnalysis = $this->buildExpertAnalysis($cv, $request->job_title, $data);

        $recommendation = CareerRecommendation::create([
            'user_id' => $user->id,
            'match_score' => $data['match_score'] ?? 0,
            'skill_gaps' => $data['skill_gaps'] ?? [],
            'suggested_courses' => $suggestedCourseIds,
            'ai_summary' => json_encode($expertAnalysis, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        return response()->json([
            'recommendation' => $this->serializeRecommendation($recommendation->load('job'))
        ]);
    }

    private function getRecommendationPayload(UserCV $cv, string $jobTitle): array
    {
        $skills = $cv->skills ?? [];
        $aiSettings = AiSetting::current();
        $aiServiceUrl = rtrim((string) config('services.ai_service.url'), '/');

        if ($aiServiceUrl !== '') {
            try {
                $startTime = microtime(true);
                $response = Http::timeout(8)->post($aiServiceUrl . '/recommend', [
                    'skills' => $skills,
                    'cv_text' => $cv->parsed_text,
                    'target_job' => $jobTitle,
                    'provider' => $aiSettings->provider,
                    'model' => $aiSettings->model,
                    'api_key' => $aiSettings->api_key,
                ]);
                $elapsed = (int) ((microtime(true) - $startTime) * 1000);

                AiRequestLog::create([
                    'user_id' => auth()->id(),
                    'endpoint' => '/recommend',
                    'provider' => $aiSettings->provider,
                    'model' => $aiSettings->model,
                    'tokens_used' => mb_strlen($response->body()),
                    'response_time_ms' => $elapsed,
                    'status' => $response->successful() ? 'success' : 'error',
                ]);

                if ($response->successful()) {
                    return $response->json();
                }
            } catch (\Throwable $e) {
                // Fallback to local recommendation heuristics below.
            }
        }

        $normalizedSkills = collect($skills)->map(fn ($skill) => mb_strtolower((string) $skill));
        $keywordPool = collect(preg_split('/[\s,\/\-]+/u', mb_strtolower($jobTitle)) ?: [])
            ->filter(fn ($keyword) => mb_strlen($keyword) >= 3)
            ->merge($normalizedSkills->take(6))
            ->unique()
            ->values();

        $skillGaps = collect(['Git', 'REST API', 'UI/UX', 'Testing', 'Docker'])
            ->reject(fn ($gap) => $normalizedSkills->contains(fn ($skill) => str_contains($skill, mb_strtolower($gap))))
            ->take(4)
            ->values()
            ->all();

        return [
            'match_score' => 70,
            'skill_gaps' => $skillGaps,
            'summary' => "Lộ trình được dựng tự động cho vị trí {$jobTitle} dựa trên hồ sơ hiện tại của bạn.",
            'recommended_keyword_topics' => $keywordPool->all(),
        ];
    }

    private function buildExpertAnalysis(UserCV $cv, string $jobTitle, array $data): array
    {
        $skills = collect($cv->skills ?? [])
            ->filter(fn ($skill) => filled($skill))
            ->map(fn ($skill) => (string) $skill)
            ->values();

        $normalizedSkills = $skills->map(fn ($skill) => mb_strtolower($skill));
        $parsedText = trim((string) ($cv->parsed_text ?? ''));
        $skillGaps = collect($data['skill_gaps'] ?? [])
            ->filter()
            ->map(fn ($gap) => (string) $gap)
            ->values();

        $hasProjects = Str::contains(mb_strtolower($parsedText), ['project', 'dự án', 'portfolio']);
        $hasMetrics = (bool) preg_match('/\d+[%+]|\d+\s*(người dùng|users|dự án|projects|tháng|năm)/u', $parsedText);
        $hasCertificates = Str::contains(mb_strtolower($parsedText), ['certificate', 'chứng chỉ', 'certification']);
        $hasEnglish = Str::contains(mb_strtolower($parsedText), ['ielts', 'toeic', 'tiếng anh', 'english']);

        $strengths = collect();

        if ($skills->isNotEmpty()) {
            $strengths->push('Bạn đã thể hiện được nền tảng kỹ năng cốt lõi khá rõ trong CV, đặc biệt ở các mảng: ' . $skills->take(5)->implode(', ') . '.');
        }

        if ($skills->count() >= 6) {
            $strengths->push('Danh mục kỹ năng tương đối rộng, cho thấy bạn có khả năng làm việc đa nhiệm và tiếp cận được nhiều phần của quy trình phát triển sản phẩm.');
        }

        if ($hasProjects) {
            $strengths->push('CV đã có dấu hiệu nhắc tới dự án hoặc portfolio, đây là điểm rất quan trọng để nhà tuyển dụng đánh giá năng lực thực chiến.');
        }

        if ($hasMetrics) {
            $strengths->push('Bạn đã có sử dụng số liệu hoặc kết quả định lượng trong CV, điều này giúp hồ sơ thuyết phục hơn nhiều so với mô tả chung chung.');
        }

        if ($hasCertificates || $hasEnglish) {
            $strengths->push('Những tín hiệu về chứng chỉ hoặc năng lực ngoại ngữ sẽ là lợi thế cộng thêm khi ứng tuyển vào các team làm việc theo chuẩn quốc tế.');
        }

        if ($strengths->isEmpty()) {
            $strengths->push('CV đã bước đầu cho thấy định hướng nghề nghiệp tương đối rõ, nhưng cần bổ sung thêm minh chứng để biến lợi thế tiềm năng thành lợi thế cạnh tranh thực sự.');
        }

        $weaknesses = collect();

        if ($skillGaps->isNotEmpty()) {
            $weaknesses->push('Khoảng cách kỹ năng hiện tại với vị trí ' . $jobTitle . ' vẫn còn ở các mảng: ' . $skillGaps->implode(', ') . '.');
        }

        if (!$hasProjects) {
            $weaknesses->push('CV chưa làm nổi bật phần dự án thực tế, nên nhà tuyển dụng sẽ khó đánh giá năng lực triển khai ngoài lý thuyết.');
        }

        if (!$hasMetrics) {
            $weaknesses->push('Mô tả kinh nghiệm hiện chưa gắn với số liệu cụ thể, vì vậy tác động công việc của bạn chưa được thể hiện đủ mạnh.');
        }

        if ($skills->count() < 4) {
            $weaknesses->push('Danh sách kỹ năng còn khá mỏng so với kỳ vọng của các vị trí trung cấp hoặc cao hơn.');
        }

        $cvImprovements = collect([
            'Viết lại phần giới thiệu đầu CV theo đúng vị trí mục tiêu: nêu rõ bạn đang theo đuổi vai trò ' . $jobTitle . ', số năm kinh nghiệm hoặc mức độ sẵn sàng hiện tại, và giá trị bạn có thể đóng góp.',
            'Tách riêng mục kỹ năng theo nhóm rõ ràng như: ngôn ngữ/libraries, công cụ làm việc, triển khai, kiểm thử, kỹ năng mềm.',
            'Mỗi dự án nên mô tả theo cấu trúc: bối cảnh, vai trò của bạn, công nghệ sử dụng, kết quả đo được.',
            'Ưu tiên dùng động từ mạnh và kết quả cụ thể, ví dụ: tối ưu, xây dựng, triển khai, giảm thời gian, tăng tỷ lệ, phục vụ bao nhiêu người dùng.',
        ]);

        if (!$hasProjects) {
            $cvImprovements->push('Bổ sung ngay 2-3 dự án tiêu biểu, kể cả dự án cá nhân, đồ án, freelance hoặc sản phẩm mô phỏng sát nghiệp vụ thật.');
        }

        if (!$hasMetrics) {
            $cvImprovements->push('Thêm số liệu vào từng đầu việc chính, kể cả khi là số liệu ước lượng hợp lý như số màn hình, số API, số người dùng thử nghiệm, thời gian hoàn thành.');
        }

        $cvAdditions = collect([
            'Link GitHub, portfolio hoặc demo sản phẩm nếu có.',
            'Mô tả công nghệ bạn dùng ở từng dự án thay vì chỉ liệt kê một danh sách kỹ năng chung.',
            'Các chứng chỉ, khóa học, hoạt động ngoại khóa liên quan trực tiếp tới hướng nghề nghiệp.',
        ]);

        if (!$hasEnglish) {
            $cvAdditions->push('Một dòng ngắn về năng lực tiếng Anh hoặc mức độ đọc hiểu tài liệu kỹ thuật.');
        }

        $learningPriorities = $skillGaps->map(
            fn ($gap) => 'Ưu tiên bổ sung ' . $gap . ' theo hướng ứng dụng thực tế trong dự án, không chỉ học lý thuyết.'
        );

        if ($learningPriorities->isEmpty()) {
            $learningPriorities = collect([
                'Tăng chiều sâu ở các kỹ năng lõi bằng cách làm một dự án hoàn chỉnh bám sát vị trí mục tiêu.',
                'Rèn khả năng trình bày kinh nghiệm và tư duy giải quyết vấn đề, vì đây là yếu tố quyết định ở vòng CV và phỏng vấn.',
            ]);
        }

        $overview = $data['summary'] ?? '';

        if ($overview === '') {
            $overview = 'Nhìn tổng thể, hồ sơ của bạn có nền tảng phù hợp để phát triển theo hướng ' . $jobTitle . ', nhưng cần làm rõ hơn năng lực thực chiến, kết quả đạt được và các kỹ năng còn thiếu để tăng sức cạnh tranh khi ứng tuyển.';
        }

        return [
            'overview' => $overview,
            'strengths' => $strengths->take(4)->values()->all(),
            'weaknesses' => $weaknesses->take(4)->values()->all(),
            'cv_additions' => $cvAdditions->take(4)->values()->all(),
            'cv_improvements' => $cvImprovements->take(5)->values()->all(),
            'learning_priorities' => $learningPriorities->take(5)->values()->all(),
        ];
    }

    private function resolveSuggestedCourses(array $data): Collection
    {
        $topics = collect($data['recommended_keyword_topics'] ?? [])
            ->filter()
            ->unique()
            ->values();

        $query = Course::query()->where('status', 'published');

        if ($topics->isNotEmpty()) {
            $query->where(function ($builder) use ($topics) {
                foreach ($topics as $topic) {
                    $builder->orWhere('title', 'like', "%{$topic}%")
                        ->orWhere('description', 'like', "%{$topic}%");
                }
            });
        }

        $ids = $query->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->limit(5)
            ->pluck('id');

        if ($ids->isEmpty()) {
            $ids = Course::query()
                ->where('status', 'published')
                ->orderByDesc('created_at')
                ->limit(5)
                ->pluck('id');
        }

        return $ids;
    }

    private function serializeRecommendation(CareerRecommendation $recommendation): array
    {
        $courseIds = collect($recommendation->suggested_courses ?? [])->filter()->values();
        $courses = Course::query()
            ->with('instructor:id,name,avatar', 'category:id,name,slug')
            ->whereIn('id', $courseIds)
            ->get()
            ->sortBy(fn (Course $course) => $courseIds->search($course->id))
            ->values();

        $expertAnalysis = $this->decodeExpertAnalysis($recommendation->ai_summary);
        $skillGaps = collect($recommendation->skill_gaps ?? [])->filter()->values();
        $courses = $courses->map(function (Course $course) use ($skillGaps) {
            $reason = 'Khóa học này phù hợp để tăng chiều sâu kiến thức và bổ sung thêm ví dụ thực chiến cho CV.';

            foreach ($skillGaps as $gap) {
                if (Str::contains(
                    mb_strtolower(($course->title ?? '') . ' ' . ($course->description ?? '')),
                    mb_strtolower((string) $gap)
                )) {
                    $reason = 'Khóa học này bám khá sát khoảng trống kỹ năng "' . $gap . '" mà hồ sơ của bạn đang thiếu.';
                    break;
                }
            }

            $course->setAttribute('recommendation_reason', $reason);

            return $course;
        });

        return [
            ...$recommendation->toArray(),
            'ai_summary' => $expertAnalysis['overview'],
            'expert_analysis' => $expertAnalysis,
            'suggested_courses_data' => $courses,
        ];
    }

    private function decodeExpertAnalysis(?string $rawSummary): array
    {
        if (!$rawSummary) {
            return [
                'overview' => '',
                'strengths' => [],
                'weaknesses' => [],
                'cv_additions' => [],
                'cv_improvements' => [],
                'learning_priorities' => [],
            ];
        }

        $decoded = json_decode($rawSummary, true);

        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return [
                'overview' => (string) ($decoded['overview'] ?? ''),
                'strengths' => array_values(array_filter($decoded['strengths'] ?? [])),
                'weaknesses' => array_values(array_filter($decoded['weaknesses'] ?? [])),
                'cv_additions' => array_values(array_filter($decoded['cv_additions'] ?? [])),
                'cv_improvements' => array_values(array_filter($decoded['cv_improvements'] ?? [])),
                'learning_priorities' => array_values(array_filter($decoded['learning_priorities'] ?? [])),
            ];
        }

        return [
            'overview' => $rawSummary,
            'strengths' => [],
            'weaknesses' => [],
            'cv_additions' => [],
            'cv_improvements' => [],
            'learning_priorities' => [],
        ];
    }
}

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
     * Tạo / cập nhật CV bằng form (kiểu TopCV) khi SV chưa có file upload.
     */
    public function saveForm(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $validated = $request->validate([
            'full_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'headline' => ['nullable', 'string', 'max:255'],
            'summary' => ['nullable', 'string', 'max:5000'],
            'education' => ['nullable', 'array'],
            'education.*.school' => ['nullable', 'string', 'max:255'],
            'education.*.degree' => ['nullable', 'string', 'max:255'],
            'education.*.year' => ['nullable', 'string', 'max:50'],
            'experience' => ['nullable', 'array'],
            'experience.*.company' => ['nullable', 'string', 'max:255'],
            'experience.*.role' => ['nullable', 'string', 'max:255'],
            'experience.*.description' => ['nullable', 'string', 'max:2000'],
            'skills' => ['nullable', 'array'],
            'skills.*' => ['string', 'max:100'],
            'projects' => ['nullable', 'array'],
            'projects.*.name' => ['nullable', 'string', 'max:255'],
            'projects.*.description' => ['nullable', 'string', 'max:2000'],
            'target_role' => ['nullable', 'string', 'max:255'],
            'expected_salary' => ['nullable', 'integer', 'min:0'],
        ]);

        $skills = collect($validated['skills'] ?? [])
            ->map(fn ($s) => trim((string) $s))
            ->filter()
            ->values()
            ->all();

        $parsedParts = [
            'Họ tên: ' . $validated['full_name'],
            'Vị trí: ' . ($validated['headline'] ?? ''),
            'Tóm tắt: ' . ($validated['summary'] ?? ''),
            'Kỹ năng: ' . implode(', ', $skills),
        ];

        foreach ($validated['education'] ?? [] as $edu) {
            $parsedParts[] = sprintf(
                'Học vấn: %s — %s (%s)',
                $edu['school'] ?? '',
                $edu['degree'] ?? '',
                $edu['year'] ?? ''
            );
        }
        foreach ($validated['experience'] ?? [] as $exp) {
            $parsedParts[] = sprintf(
                'Kinh nghiệm: %s tại %s. %s',
                $exp['role'] ?? '',
                $exp['company'] ?? '',
                $exp['description'] ?? ''
            );
        }
        foreach ($validated['projects'] ?? [] as $proj) {
            $parsedParts[] = sprintf(
                'Dự án: %s. %s',
                $proj['name'] ?? '',
                $proj['description'] ?? ''
            );
        }

        $profile = [
            'full_name' => $validated['full_name'],
            'email' => $validated['email'] ?? $user->email,
            'phone' => $validated['phone'] ?? null,
            'headline' => $validated['headline'] ?? null,
            'summary' => $validated['summary'] ?? null,
            'education' => $validated['education'] ?? [],
            'experience' => $validated['experience'] ?? [],
            'projects' => $validated['projects'] ?? [],
            'skills' => $skills,
        ];

        $cv = UserCV::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'source' => 'form',
                'file_name' => 'cv-form.json',
            ],
            [
                'file_path' => null,
                'parsed_text' => implode("\n", array_filter($parsedParts)),
                'skills' => $skills,
                'profile_json' => $profile,
                'target_role' => $validated['target_role'] ?? null,
                'expected_salary' => $validated['expected_salary'] ?? null,
            ]
        );

        $evaluation = $this->evaluateCvLocally($cv);
        $cv->update(['evaluation_json' => $evaluation]);

        return response()->json([
            'message' => 'Đã lưu CV từ form.',
            'cv' => $cv->fresh(),
            'evaluation' => $evaluation,
        ], 201);
    }

    /**
     * Đánh giá CV: đúng chưa / thiếu gì + cảnh báo + gợi ý cải thiện.
     */
    public function evaluate(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();
        $cv = $user->latestCv;

        if (!$cv) {
            return response()->json(['message' => 'Chưa có CV để đánh giá. Hãy upload hoặc tạo form CV.'], 400);
        }

        $validated = $request->validate([
            'target_role' => ['nullable', 'string', 'max:255'],
            'expected_salary' => ['nullable', 'integer', 'min:0'],
        ]);

        if (!empty($validated)) {
            $cv->update([
                'target_role' => $validated['target_role'] ?? $cv->target_role,
                'expected_salary' => $validated['expected_salary'] ?? $cv->expected_salary,
            ]);
            $cv->refresh();
        }

        $evaluation = $this->evaluateCvLocally($cv);
        $cv->update(['evaluation_json' => $evaluation]);

        $courses = [];
        if ($cv->target_role) {
            $data = $this->getRecommendationPayload($cv, $cv->target_role);
            $courseIds = $this->resolveSuggestedCourses($data);
            $courses = Course::query()
                ->with('instructor:id,name', 'category:id,name')
                ->whereIn('id', $courseIds)
                ->where('status', 'published')
                ->get(['id', 'title', 'slug', 'price', 'thumbnail', 'level', 'user_id', 'category_id']);
        }

        return response()->json([
            'cv' => $cv->fresh(),
            'evaluation' => $evaluation,
            'suggested_courses' => $courses,
        ]);
    }

    private function evaluateCvLocally(UserCV $cv): array
    {
        $text = mb_strtolower(trim((string) ($cv->parsed_text ?? '')));
        $skills = collect($cv->skills ?? [])->filter()->values();
        $profile = $cv->profile_json ?? [];

        $checks = [];
        $warnings = [];
        $fixes = [];

        $hasContact = Str::contains($text, ['@', 'email', 'phone', 'sđt', 'điện thoại'])
            || filled($profile['email'] ?? null)
            || filled($profile['phone'] ?? null);
        $checks[] = [
            'key' => 'contact',
            'ok' => $hasContact,
            'label' => $hasContact ? 'Có thông tin liên hệ' : 'Thiếu email/SĐT',
        ];
        if (!$hasContact) {
            $fixes[] = 'Thêm email và số điện thoại rõ ràng ở đầu CV.';
            $warnings[] = 'Nhà tuyển dụng khó liên hệ nếu thiếu thông tin liên lạc.';
        }

        $hasSummary = filled($profile['summary'] ?? null) || mb_strlen($text) > 200;
        $checks[] = [
            'key' => 'summary',
            'ok' => $hasSummary,
            'label' => $hasSummary ? 'Có phần giới thiệu' : 'Thiếu phần tóm tắt',
        ];
        if (!$hasSummary) {
            $fixes[] = 'Viết 3–5 câu giới thiệu định hướng nghề nghiệp và điểm mạnh.';
        }

        $hasSkills = $skills->count() >= 3;
        $checks[] = [
            'key' => 'skills',
            'ok' => $hasSkills,
            'label' => $hasSkills ? 'Đã liệt kê kỹ năng' : 'Kỹ năng còn mỏng',
        ];
        if (!$hasSkills) {
            $fixes[] = 'Bổ sung ít nhất 5 kỹ năng liên quan vị trí mục tiêu.';
            $warnings[] = 'Danh sách kỹ năng quá ít sẽ làm giảm điểm matching.';
        }

        $hasProjects = Str::contains($text, ['project', 'dự án', 'portfolio'])
            || !empty($profile['projects']);
        $checks[] = [
            'key' => 'projects',
            'ok' => $hasProjects,
            'label' => $hasProjects ? 'Có dự án/portfolio' : 'Chưa có dự án',
        ];
        if (!$hasProjects) {
            $fixes[] = 'Thêm 2–3 dự án (đồ án, cá nhân, thực tập) với công nghệ và kết quả.';
            $warnings[] = 'Thiếu dự án khiến CV khó chứng minh năng lực thực chiến.';
        }

        $hasEducation = Str::contains($text, ['học', 'university', 'đại học', 'ptit', 'education'])
            || !empty($profile['education']);
        $checks[] = [
            'key' => 'education',
            'ok' => $hasEducation,
            'label' => $hasEducation ? 'Có học vấn' : 'Thiếu học vấn',
        ];
        if (!$hasEducation) {
            $fixes[] = 'Ghi rõ trường, ngành và năm học.';
        }

        $okCount = collect($checks)->where('ok', true)->count();
        $score = (int) round(($okCount / max(count($checks), 1)) * 100);

        $salaryNote = null;
        if ($cv->expected_salary) {
            $salaryNote = $cv->expected_salary >= 20_000_000
                ? 'Mức lương mong muốn khá cao — hãy đảm bảo CV có dự án mạnh và kỹ năng khớp JD.'
                : 'Mức lương mong muốn hợp lý cho giai đoạn sinh viên/fresher nếu CV có dự án minh chứng.';
        }

        return [
            'score' => $score,
            'checks' => $checks,
            'warnings' => $warnings,
            'fixes' => $fixes,
            'target_role' => $cv->target_role,
            'expected_salary' => $cv->expected_salary,
            'salary_note' => $salaryNote,
            'summary' => $score >= 80
                ? 'CV khá đầy đủ. Tiếp tục tinh chỉnh theo vị trí mục tiêu và bổ sung số liệu dự án.'
                : 'CV còn thiếu một số phần quan trọng. Hãy sửa theo danh sách gợi ý trước khi ứng tuyển.',
        ];
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
            'expected_salary' => 'nullable|integer|min:0',
        ]);

        if ($request->filled('expected_salary') || $request->filled('job_title')) {
            $cv->update([
                'target_role' => $request->job_title,
                'expected_salary' => $request->input('expected_salary'),
            ]);
        }

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

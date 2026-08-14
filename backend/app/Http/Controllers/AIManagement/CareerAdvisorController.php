<?php

namespace App\Http\Controllers\AIManagement;

use App\Http\Controllers\Controller;

use App\Models\AiRequestLog;
use App\Models\UserCV;
use App\Models\CareerRecommendation;
use App\Models\Course;
use App\Services\AiServiceClient;
use App\Services\CVAnalysisService;
use App\Services\MediaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class CareerAdvisorController extends Controller
{
    protected $mediaService;
    protected $cvAnalysisService;

    public function __construct(
        MediaService $mediaService,
        CVAnalysisService $cvAnalysisService,
        protected AiServiceClient $ai,
    ) {
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

        $usable = $latestCV ? $this->cvIsUsable($latestCV) : false;

        return response()->json([
            'cv' => $usable ? $latestCV : null,
            'has_unparsed_cv' => $latestCV && !$usable,
            'unparsed_file_name' => ($latestCV && !$usable) ? $latestCV->file_name : null,
            'recommendations' => $recommendations,
            'usable' => $usable,
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

            $profile = array_filter([
                'email' => $analysis['profile']['email'] ?? null,
                'phone' => $analysis['profile']['phone'] ?? null,
                'summary' => $analysis['profile']['summary'] ?? null,
                'has_education' => $analysis['profile']['has_education'] ?? false,
                'has_projects' => $analysis['profile']['has_projects'] ?? false,
                'has_experience' => $analysis['profile']['has_experience'] ?? false,
            ], fn ($v) => $v !== null && $v !== false);

            $userCv = UserCV::create([
                'user_id' => $user->id,
                'file_path' => $uploadResult['path'],
                'file_name' => $uploadResult['name'],
                'source' => 'upload',
                'parsed_text' => $analysis['text'],
                'skills' => $analysis['skills'],
                'profile_json' => $profile ?: null,
            ]);

            $usable = $this->cvIsUsable($userCv);
            $evaluation = null;
            if ($usable) {
                $evaluation = $this->evaluateCvLocally($userCv, $user);
                $userCv->update(['evaluation_json' => $evaluation]);
            }

            AiRequestLog::create([
                'user_id' => $user->id,
                'endpoint' => '/parse-cv',
                'provider' => 'local-pipeline',
                'model' => (string) ($analysis['pipeline']['text_extraction_method'] ?? 'document-analysis-v1'),
                'tokens_used' => mb_strlen((string) $analysis['text']),
                'response_time_ms' => 0,
                'status' => $usable ? 'success' : 'error',
                'error_message' => $usable ? null : 'Could not extract enough text from CV file',
            ]);

            return response()->json([
                'message' => $usable
                    ? 'CV uploaded and analyzed successfully'
                    : 'Đã nhận file nhưng chưa đọc được nội dung. Thử PDF/DOCX có chữ chọn được, hoặc điền form CV.',
                'cv' => $userCv->fresh(),
                'pipeline' => $analysis['pipeline'],
                'usable' => $usable,
                'evaluation' => $evaluation,
                'parse_failed' => !$usable,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Không xử lý được file CV. Vui lòng thử lại hoặc dùng form.',
            ], 500);
        }
    }

    /**
     * Xóa toàn bộ CV của user để upload/tạo lại.
     */
    public function deleteCV(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $cvs = UserCV::query()->where('user_id', $user->id)->get();
        foreach ($cvs as $cv) {
            if ($cv->file_path) {
                try {
                    $disk = $this->mediaService->getDisk();
                    if (Storage::disk($disk)->exists($cv->file_path)) {
                        Storage::disk($disk)->delete($cv->file_path);
                    }
                } catch (\Throwable) {
                    // ignore storage cleanup errors
                }
            }
            $cv->delete();
        }

        return response()->json([
            'message' => 'Đã xóa CV. Bạn có thể tải lên hoặc tạo lại.',
        ]);
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

        $education = collect($validated['education'] ?? [])
            ->filter(fn ($e) => filled($e['school'] ?? null) || filled($e['degree'] ?? null))
            ->values()
            ->all();
        $experience = collect($validated['experience'] ?? [])
            ->filter(fn ($e) => filled($e['company'] ?? null) || filled($e['role'] ?? null) || filled($e['description'] ?? null))
            ->values()
            ->all();
        $projects = collect($validated['projects'] ?? [])
            ->filter(fn ($p) => filled($p['name'] ?? null) || filled(strip_tags((string) ($p['description'] ?? ''))))
            ->values()
            ->all();

        $summaryPlain = trim(html_entity_decode(strip_tags((string) ($validated['summary'] ?? '')), ENT_QUOTES, 'UTF-8'));

        $parsedParts = [
            'Họ tên: ' . $validated['full_name'],
            'Email: ' . ($validated['email'] ?? $user->email ?? ''),
            'SĐT: ' . ($validated['phone'] ?? ''),
            'Vị trí: ' . ($validated['headline'] ?? ''),
            'Tóm tắt: ' . $summaryPlain,
            'Kỹ năng: ' . implode(', ', $skills),
        ];

        foreach ($education as $edu) {
            $parsedParts[] = sprintf(
                'Học vấn: %s — %s (%s)',
                $edu['school'] ?? '',
                $edu['degree'] ?? '',
                $edu['year'] ?? ''
            );
        }
        foreach ($experience as $exp) {
            $parsedParts[] = sprintf(
                'Kinh nghiệm: %s tại %s. %s',
                $exp['role'] ?? '',
                $exp['company'] ?? '',
                strip_tags((string) ($exp['description'] ?? ''))
            );
        }
        foreach ($projects as $proj) {
            $parsedParts[] = sprintf(
                'Dự án: %s. %s',
                $proj['name'] ?? '',
                strip_tags((string) ($proj['description'] ?? ''))
            );
        }

        $profile = [
            'full_name' => $validated['full_name'],
            'email' => $validated['email'] ?? $user->email,
            'phone' => $validated['phone'] ?? null,
            'headline' => $validated['headline'] ?? null,
            'summary' => $summaryPlain ?: null,
            'education' => $education,
            'experience' => $experience,
            'projects' => $projects,
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

        $evaluation = $this->evaluateCvLocally($cv, $user);
        $cv->update(['evaluation_json' => $evaluation]);

        return response()->json([
            'message' => 'Đã lưu CV từ form.',
            'cv' => $cv->fresh(),
            'evaluation' => $evaluation,
            'usable' => true,
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

        if (!$this->cvIsUsable($cv)) {
            return response()->json([
                'message' => 'CV hiện tại chưa có đủ nội dung để đánh giá (file PDF có thể không đọc được). Hãy điền form CV hoặc tải lại file DOCX.',
                'parse_failed' => true,
            ], 422);
        }

        $validated = $request->validate([
            'target_role' => ['required', 'string', 'max:255'],
            'expected_salary' => ['nullable', 'integer', 'min:0'],
        ]);

        $cv->update([
            'target_role' => $validated['target_role'],
            'expected_salary' => $validated['expected_salary'] ?? $cv->expected_salary,
        ]);
        $cv->refresh();

        $role = trim((string) $cv->target_role);
        $local = $this->evaluateCvLocally($cv, $user);
        $aiReview = $this->getEvaluationPayload($cv, $role);
        $evaluation = $this->mergeRecruiterEvaluation($local, $aiReview, $role);
        $cv->update(['evaluation_json' => $evaluation]);

        $courseIds = $this->resolveSuggestedCourses([
            'skill_gaps' => $evaluation['skill_gaps'] ?? [],
            'recommended_keyword_topics' => $evaluation['recommended_keyword_topics'] ?? ($evaluation['skill_gaps'] ?? []),
            'target_role' => $role,
        ]);
        $courses = Course::query()
            ->with('instructor:id,name', 'category:id,name')
            ->whereIn('id', $courseIds)
            ->where('status', 'published')
            ->get(['id', 'title', 'slug', 'price', 'thumbnail', 'level', 'user_id', 'category_id'])
            ->map(fn (Course $c) => [
                'id' => $c->id,
                'title' => $c->title,
                'slug' => $c->slug,
                'price' => (int) ($c->price ?? 0),
                'thumbnail' => $c->thumbnail,
                'level' => $c->level,
                'instructor' => $c->instructor,
                'category' => $c->category,
            ])
            ->values()
            ->all();

        return response()->json([
            'cv' => $cv->fresh(),
            'evaluation' => $evaluation,
            'suggested_courses' => $courses,
            'usable' => true,
        ]);
    }

    private function cvIsUsable(UserCV $cv): bool
    {
        $text = trim((string) ($cv->parsed_text ?? ''));
        if ($this->textLooksMeaningful($text)) {
            return true;
        }
        if (count(array_filter($cv->skills ?? [])) >= 1) {
            return true;
        }
        $profile = $cv->profile_json ?? [];
        if (filled($profile['summary'] ?? null) || filled($profile['full_name'] ?? null)) {
            return true;
        }

        return false;
    }

    private function textLooksMeaningful(string $text): bool
    {
        if (mb_strlen($text) < 40) {
            return false;
        }
        if (!preg_match_all('/[A-Za-zÀ-ỹ0-9]/u', $text, $m)) {
            return false;
        }
        $letters = count($m[0]);

        return $letters >= 40 && ($letters / max(mb_strlen($text), 1)) >= 0.35;
    }

    private function evaluateCvLocally(UserCV $cv, ?\App\Models\User $user = null): array
    {
        $text = mb_strtolower(trim((string) ($cv->parsed_text ?? '')));
        $skills = collect($cv->skills ?? [])->filter(fn ($s) => filled($s))->values();
        $profile = $cv->profile_json ?? [];
        $user = $user ?: $cv->user;

        $checks = [];
        $warnings = [];
        $fixes = [];

        $email = $profile['email'] ?? null;
        $phone = $profile['phone'] ?? null;
        $hasContact = filled($email)
            || filled($phone)
            || filled($user?->email)
            || (bool) preg_match('/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/i', $text)
            || (bool) preg_match('/(?:\+?84|0)(?:[\s.\-]?\d){8,11}/', $text)
            || Str::contains($text, ['email', 'sđt', 'điện thoại', 'phone']);
        $checks[] = [
            'key' => 'contact',
            'ok' => $hasContact,
            'label' => $hasContact ? 'Có thông tin liên hệ' : 'Thiếu email/SĐT',
        ];
        if (!$hasContact) {
            $fixes[] = 'Thêm email và số điện thoại rõ ràng ở đầu CV.';
            $warnings[] = 'Nhà tuyển dụng khó liên hệ nếu thiếu thông tin liên lạc.';
        }

        $hasSummary = filled($profile['summary'] ?? null)
            || filled($profile['headline'] ?? null)
            || mb_strlen($text) >= 160;
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
            'label' => $hasSkills
                ? ('Đã liệt kê ' . $skills->count() . ' kỹ năng')
                : 'Kỹ năng còn mỏng',
        ];
        if (!$hasSkills) {
            $fixes[] = 'Bổ sung ít nhất 5 kỹ năng liên quan vị trí mục tiêu.';
            $warnings[] = 'Danh sách kỹ năng quá ít sẽ làm giảm điểm matching.';
        }

        $projects = collect($profile['projects'] ?? [])
            ->filter(fn ($p) => filled($p['name'] ?? null) || filled($p['description'] ?? null));
        $hasProjects = $projects->isNotEmpty()
            || !empty($profile['has_projects'])
            || (bool) preg_match('/dự án|project|portfolio|đồ án|github/iu', $text);
        $checks[] = [
            'key' => 'projects',
            'ok' => $hasProjects,
            'label' => $hasProjects ? 'Có dự án/portfolio' : 'Chưa có dự án',
        ];
        if (!$hasProjects) {
            $fixes[] = 'Thêm 2–3 dự án (đồ án, cá nhân, thực tập) với công nghệ và kết quả.';
            $warnings[] = 'Thiếu dự án khiến CV khó chứng minh năng lực thực chiến.';
        }

        $education = collect($profile['education'] ?? [])
            ->filter(fn ($e) => filled($e['school'] ?? null) || filled($e['degree'] ?? null));
        $hasEducation = $education->isNotEmpty()
            || !empty($profile['has_education'])
            || (bool) preg_match('/học vấn|education|university|đại học|cao đẳng|ptit|học viện|cử nhân/iu', $text);
        $checks[] = [
            'key' => 'education',
            'ok' => $hasEducation,
            'label' => $hasEducation ? 'Có học vấn' : 'Thiếu học vấn',
        ];
        if (!$hasEducation) {
            $fixes[] = 'Ghi rõ trường, ngành và năm học.';
        }

        $okCount = collect($checks)->where('ok', true)->count();
        $completeness = (int) round(($okCount / max(count($checks), 1)) * 100);

        $salaryNote = null;
        if ($cv->expected_salary) {
            $salaryNote = $cv->expected_salary >= 20_000_000
                ? 'Mức lương mong muốn khá cao — hãy đảm bảo CV có dự án mạnh và kỹ năng khớp JD.'
                : 'Mức lương mong muốn hợp lý cho giai đoạn sinh viên/fresher nếu CV có dự án minh chứng.';
        }

        return [
            'score' => $completeness,
            'completeness_score' => $completeness,
            'checks' => $checks,
            'warnings' => $warnings,
            'fixes' => $fixes,
            'target_role' => $cv->target_role,
            'expected_salary' => $cv->expected_salary,
            'salary_note' => $salaryNote,
            'summary' => 'Đã kiểm tra cấu trúc CV. Nhập vị trí mục tiêu và bấm Đánh giá để nhận nhận xét từ góc nhìn nhà tuyển dụng.',
            'skills_found' => $skills->values()->all(),
            'reviewed' => false,
        ];
    }

    private function getEvaluationPayload(UserCV $cv, string $jobTitle): array
    {
        $result = $this->ai->postWithFallback('evaluate-cv', [
            'skills' => $cv->skills ?? [],
            'cv_text' => $cv->parsed_text,
            'target_job' => $jobTitle,
            'expected_salary' => $cv->expected_salary ? (int) $cv->expected_salary : null,
        ], auth()->id(), '/evaluate-cv', timeout: 90, maxTotalMs: 70000);

        if ($result['ok'] && is_array($result['data']) && filled($result['data']['overview'] ?? null)) {
            return array_merge($result['data'], ['explanation_unavailable' => false]);
        }

        // Fallback: expert analysis + recommend gaps
        $recommend = $this->getRecommendationPayload($cv, $jobTitle);
        $expert = $this->buildExpertAnalysis($cv, $jobTitle, $recommend);
        $score = (int) ($expert['match_score_hint'] ?? $recommend['match_score'] ?? 60);

        return [
            'match_score' => $score,
            'verdict' => $score < 55
                ? 'Chưa sẵn sàng gọi phỏng vấn — cần chỉnh CV/stack'
                : 'Cần chỉnh sửa hồ sơ trước khi ứng tuyển',
            'overview' => $expert['overview'] ?? ($recommend['summary'] ?? ''),
            'strengths' => $expert['strengths'] ?? [],
            'weaknesses' => $expert['weaknesses'] ?? [],
            'improvements' => $expert['cv_improvements'] ?? [],
            'missing_items' => $expert['cv_additions'] ?? [],
            'skill_gaps' => $expert['skill_gaps'] ?? ($recommend['skill_gaps'] ?? []),
            'interview_focus' => $expert['learning_priorities'] ?? [],
            'recommended_keyword_topics' => $recommend['recommended_keyword_topics'] ?? [],
            'salary_comment' => null,
            'explanation_unavailable' => true,
        ];
    }

    private function mergeRecruiterEvaluation(array $local, array $ai, string $role): array
    {
        $score = (int) ($ai['match_score'] ?? $local['completeness_score'] ?? 0);
        $score = max(0, min(100, $score));
        $overview = trim((string) ($ai['overview'] ?? ''));
        $verdict = trim((string) ($ai['verdict'] ?? ''));

        return array_merge($local, [
            'score' => $score,
            'fit_score' => $score,
            'verdict' => $verdict,
            'overview' => $overview,
            'summary' => $verdict !== ''
                ? $verdict
                : ("Đánh giá mức phù hợp với {$role}: {$score}/100"),
            'strengths' => array_values(array_filter($ai['strengths'] ?? [])),
            'weaknesses' => array_values(array_filter($ai['weaknesses'] ?? [])),
            'improvements' => array_values(array_filter($ai['improvements'] ?? [])),
            'missing_items' => array_values(array_filter($ai['missing_items'] ?? [])),
            'skill_gaps' => array_values(array_filter($ai['skill_gaps'] ?? [])),
            'interview_focus' => array_values(array_filter($ai['interview_focus'] ?? [])),
            'recommended_keyword_topics' => array_values(array_filter(
                $ai['recommended_keyword_topics'] ?? ($ai['skill_gaps'] ?? [])
            )),
            'salary_note' => $ai['salary_comment'] ?? ($local['salary_note'] ?? null),
            'target_role' => $role,
            'reviewed' => true,
            'review_style' => 'recruiter',
            'explanation_unavailable' => (bool) ($ai['explanation_unavailable'] ?? false),
        ]);
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

        if (!$this->cvIsUsable($cv)) {
            return response()->json([
                'message' => 'CV chưa đọc được nội dung. Hãy điền form CV trước khi nhận gợi ý.',
                'parse_failed' => true,
            ], 422);
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

        $role = $request->job_title;
        $cv = $cv->fresh();

        // 1 lần gọi AI duy nhất (/evaluate-cv) dùng chung cho cả gợi ý khóa học lẫn
        // bản tóm tắt lưu vào lịch sử — trước đây gọi thêm /recommend + buildExpertAnalysis()
        // riêng, tốn thêm 1 round-trip AI và tạo ra 2 bản nhận xét không khớp nhau cho
        // cùng 1 thao tác của người dùng.
        $local = $this->evaluateCvLocally($cv, $user);
        $aiReview = $this->getEvaluationPayload($cv, $role);
        $evaluation = $this->mergeRecruiterEvaluation($local, $aiReview, $role);
        $cv->update(['evaluation_json' => $evaluation]);

        $suggestedCourseIds = $this->resolveSuggestedCourses([
            'skill_gaps' => $evaluation['skill_gaps'] ?? [],
            'recommended_keyword_topics' => $evaluation['recommended_keyword_topics'] ?? ($evaluation['skill_gaps'] ?? []),
            'target_role' => $role,
        ])->values();

        $expertAnalysis = [
            'overview' => $evaluation['overview'] ?? '',
            'strengths' => $evaluation['strengths'] ?? [],
            'weaknesses' => $evaluation['weaknesses'] ?? [],
            'cv_additions' => $evaluation['missing_items'] ?? [],
            'cv_improvements' => $evaluation['improvements'] ?? [],
            'learning_priorities' => $evaluation['interview_focus'] ?? [],
        ];

        $recommendation = CareerRecommendation::create([
            'user_id' => $user->id,
            'match_score' => $evaluation['score'] ?? 0,
            'skill_gaps' => $evaluation['skill_gaps'] ?? [],
            'suggested_courses' => $suggestedCourseIds,
            'ai_summary' => json_encode($expertAnalysis, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ]);

        $courses = Course::query()
            ->whereIn('id', $suggestedCourseIds)
            ->where('status', 'published')
            ->get(['id', 'title', 'slug', 'price', 'thumbnail', 'level']);

        return response()->json([
            'recommendation' => $this->serializeRecommendation($recommendation->load('job')),
            'evaluation' => $evaluation,
            'suggested_courses' => $courses,
            'cv' => $cv->fresh(),
        ]);
    }

    private function getRecommendationPayload(UserCV $cv, string $jobTitle): array
    {
        $skills = $cv->skills ?? [];

        $result = $this->ai->postWithFallback('recommend', [
            'skills' => $skills,
            'cv_text' => $cv->parsed_text,
            'target_job' => $jobTitle,
        ], auth()->id(), '/recommend', timeout: 60, maxTotalMs: 55000);

        if ($result['ok'] && is_array($result['data'])) {
            return $result['data'];
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

        $jobLower = mb_strtolower($jobTitle);
        $wantsJava = str_contains($jobLower, 'java') || str_contains($jobLower, 'spring');
        $hasJava = $normalizedSkills->contains(fn ($s) => (bool) preg_match('/(?<![a-z])java(?![a-z])|spring/', $s));
        if ($wantsJava && !$hasJava) {
            $weaknesses->prepend(
                'Stack trên CV (ví dụ PHP/Laravel/Vue) lệch so với vị trí ' . $jobTitle . ' — nhà tuyển dụng Java thường loại ngay nếu không thấy Java/Spring và dự án liên quan.'
            );
            $cvImprovements->prepend(
                'Nếu quyết theo Java Fullstack: bổ sung dự án Java/Spring (hoặc đang học) và giảm nhấn mạnh stack không liên quan ở phần đầu CV.'
            );
            $skillGaps = collect(['Java', 'Spring Boot', 'JPA/Hibernate', 'REST API'])
                ->merge($skillGaps)
                ->unique()
                ->values();
            $overview = 'Với góc nhìn tuyển dụng cho ' . $jobTitle . ', hồ sơ hiện tại chưa thuyết phục vì lệch stack. '
                . 'CV đang mạnh ở hướng web PHP/JS hơn là Java. '
                . 'Cần chỉnh CV và lộ trình kỹ năng trước khi nộp — điểm khớp JD sẽ thấp cho đến khi có minh chứng Java thực tế.';
        }

        return [
            'overview' => $overview,
            'strengths' => $strengths->take(4)->values()->all(),
            'weaknesses' => $weaknesses->take(5)->values()->all(),
            'cv_additions' => $cvAdditions->take(4)->values()->all(),
            'cv_improvements' => $cvImprovements->take(6)->values()->all(),
            'learning_priorities' => $learningPriorities->take(5)->values()->all(),
            'skill_gaps' => $skillGaps->take(8)->values()->all(),
            'match_score_hint' => ($wantsJava && !$hasJava) ? 38 : null,
        ];
    }

    private function resolveSuggestedCourses(array $data): Collection
    {
        $rawTopics = collect($data['recommended_keyword_topics'] ?? [])
            ->merge($data['skill_gaps'] ?? [])
            ->map(fn ($t) => trim((string) $t))
            ->filter()
            ->unique(fn ($t) => mb_strtolower($t))
            ->values();

        $topics = $this->expandCourseSearchTopics($rawTopics);
        if ($topics->isEmpty()) {
            return collect();
        }

        $targetRole = mb_strtolower(trim((string) ($data['target_role'] ?? $data['target_job'] ?? '')));

        $courses = Course::query()
            ->with(['category:id,name', 'skills:id,name'])
            ->where('status', 'published')
            ->get(['id', 'title', 'description', 'category_id', 'published_at', 'created_at']);

        $ranked = $courses
            ->map(function (Course $course) use ($topics, $targetRole) {
                $score = $this->scoreCourseAgainstTopics($course, $topics, $targetRole);

                return [
                    'id' => $course->id,
                    'score' => $score,
                    'published_at' => $course->published_at,
                    'created_at' => $course->created_at,
                ];
            })
            // Cần khớp thật sự (title/skill/category), không lấy khóa chỉ vì mô tả chung chung
            ->filter(fn (array $row) => $row['score'] >= 4)
            ->sort(function (array $a, array $b) {
                if ($a['score'] !== $b['score']) {
                    return $b['score'] <=> $a['score'];
                }
                $aTime = (string) ($a['published_at'] ?? $a['created_at'] ?? '');
                $bTime = (string) ($b['published_at'] ?? $b['created_at'] ?? '');

                return strcmp($bTime, $aTime);
            })
            ->take(5)
            ->pluck('id')
            ->values();

        return $ranked;
    }

    /**
     * Mở rộng từ khóa tìm khóa + loại topic quá chung (tránh gợi ý lệch).
     *
     * @param  \Illuminate\Support\Collection<int, string>  $rawTopics
     * @return \Illuminate\Support\Collection<int, string>
     */
    private function expandCourseSearchTopics(Collection $rawTopics): Collection
    {
        $aliases = [
            'java' => ['java', 'spring', 'spring boot', 'jpa', 'hibernate'],
            'spring boot' => ['spring boot', 'spring', 'java'],
            'spring' => ['spring', 'spring boot', 'java'],
            'javascript' => ['javascript', 'frontend'],
            'typescript' => ['typescript', 'javascript'],
            'vue.js' => ['vue.js', 'vue', 'nuxt'],
            'vue' => ['vue', 'vue.js', 'nuxt'],
            'nuxt' => ['nuxt', 'vue', 'vue.js'],
            'react' => ['react', 'next.js', 'frontend'],
            'php' => ['php', 'laravel'],
            'laravel' => ['laravel', 'php'],
            'python' => ['python', 'django', 'fastapi'],
            'docker' => ['docker', 'devops'],
            'mysql' => ['mysql', 'sql', 'cơ sở dữ liệu', 'database'],
            'sql' => ['sql', 'mysql', 'database', 'cơ sở dữ liệu'],
            'rest api' => ['rest api', 'backend'],
            'node.js' => ['node.js', 'nodejs'],
            'machine learning' => ['machine learning', 'trí tuệ nhân tạo', 'python'],
            'fullstack' => ['fullstack', 'full stack', 'full-stack'],
            'full stack' => ['full stack', 'fullstack', 'full-stack'],
        ];

        // Topic quá chung → dễ match nhầm mọi khóa (đặc biệt skill gắn lung tung trên seed)
        $stop = [
            'api', 'git', 'testing', 'communication', 'teamwork', 'js', 'ts',
            'làm việc nhóm', 'giao tiếp', 'giao tiếp kỹ thuật',
            'soft skills', 'kỹ năng mềm', 'html', 'css', 'html/css',
        ];

        $expanded = collect();
        foreach ($rawTopics as $topic) {
            $key = mb_strtolower(trim($topic));
            if ($key === '' || in_array($key, $stop, true)) {
                continue;
            }
            if (isset($aliases[$key])) {
                foreach ($aliases[$key] as $alias) {
                    $expanded->push($alias);
                }
            } else {
                $expanded->push($key);
            }
        }

        return $expanded
            ->map(fn ($t) => mb_strtolower(trim((string) $t)))
            ->filter(fn ($t) => mb_strlen($t) >= 2 && !in_array($t, $stop, true))
            ->unique()
            ->values();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, string>  $topics
     */
    private function scoreCourseAgainstTopics(Course $course, Collection $topics, string $targetRole = ''): int
    {
        $title = (string) ($course->title ?? '');
        $description = (string) ($course->description ?? '');
        $category = (string) ($course->category?->name ?? '');
        $skillNames = $course->relationLoaded('skills')
            ? $course->skills->pluck('name')->filter()->implode(' ')
            : '';

        $titleHits = 0;
        $categoryHits = 0;
        $skillHits = 0;
        $descriptionHits = 0;
        $matchedTopics = [];

        foreach ($topics as $topic) {
            $hit = false;
            if ($this->textHasTopic($title, $topic)) {
                $titleHits++;
                $hit = true;
            }
            if ($this->textHasTopic($category, $topic)) {
                $categoryHits++;
                $hit = true;
            }
            if ($this->textHasTopic($skillNames, $topic)) {
                $skillHits++;
                $hit = true;
            }
            if ($this->textHasTopic($description, $topic)) {
                $descriptionHits++;
                $hit = true;
            }
            if ($hit) {
                $matchedTopics[mb_strtolower((string) $topic)] = true;
            }
        }

        $distinctTopics = count($matchedTopics);
        if ($distinctTopics === 0) {
            return 0;
        }

        $blob = mb_strtolower($title . ' ' . $category);
        $isGeneralEducation = (bool) preg_match(
            '/tư tưởng|pháp luật|xác suất|thống kê|toán rời rạc|giáo dục thể chất|triết học|chính trị|kỹ năng mềm|mác[- ]lênin|học kỳ quân sự/u',
            $blob
        );
        $isTelecomCourse = (bool) preg_match('/viễn thông|điện tử|vô tuyến|truyền thông quang|mạng truyền thông|anten|sóng/u', $blob);
        $isBusinessCourse = (bool) preg_match('/quản trị kinh doanh|marketing|thương mại|kinh tế vi mô|kinh tế vĩ mô/u', $blob);
        $isItCourse = (bool) preg_match(
            '/công nghệ thông tin|lập trình|phần mềm|cơ sở dữ liệu|trí tuệ nhân tạo|backend|frontend|java|php|laravel|python|docker|thuật toán|cấu trúc dữ liệu|web|mobile|devops|database/u',
            $blob
        );

        $isItRole = (bool) preg_match(
            '/java|php|laravel|frontend|backend|fullstack|full.?stack|devops|software|lập trình|cntt|công nghệ thông tin|developer|react|vue|python|ai|data|spring|mobile/u',
            $targetRole
        );

        // Với JD IT: loại môn đại cương / lệch ngành trừ khi title/category khớp topic rõ
        if ($isItRole && $titleHits === 0 && $categoryHits === 0) {
            if ($isGeneralEducation || $isTelecomCourse || $isBusinessCourse) {
                return 0;
            }
            // Skill gắn lung tung trên seed — cần ≥2 topic khác nhau mới nhận
            if ($skillHits > 0 && $distinctTopics < 2 && !$isItCourse) {
                return 0;
            }
            if (!$isItCourse && $skillHits > 0 && $titleHits === 0) {
                return 0;
            }
        }

        $score = ($titleHits * 5) + ($categoryHits * 4) + ($skillHits * 2) + min($descriptionHits, 2);

        if ($isItRole) {
            if ($isItCourse) {
                $score += 3;
            }
            if ($isTelecomCourse || $isBusinessCourse) {
                $score -= 6;
            }
            if ($isGeneralEducation) {
                $score -= 10;
            }
        }

        // Bắt buộc có tín hiệu title/category hoặc (IT course + skill)
        if ($titleHits === 0 && $categoryHits === 0 && !($isItCourse && $skillHits >= 1)) {
            return 0;
        }

        return max(0, $score);
    }

    private function textHasTopic(string $haystack, string $topic): bool
    {
        $hay = mb_strtolower($haystack);
        $topic = mb_strtolower(trim($topic));
        if ($hay === '' || $topic === '') {
            return false;
        }

        // Alias quá ngắn dễ nhiễu
        if (in_array($topic, ['js', 'ts', 'go', 'c'], true)) {
            return false;
        }

        // Cụm từ: match nguyên cụm
        if (str_contains($topic, ' ') || str_contains($topic, '/') || str_contains($topic, '.')) {
            $normalizedHay = str_replace(['-', '_'], ' ', $hay);
            $normalizedTopic = str_replace(['-', '_'], ' ', $topic);

            return str_contains($normalizedHay, $normalizedTopic);
        }

        // Token đơn: biên từ Unicode — tránh "java" khớp "javascript"
        $pattern = '/(?<![\p{L}\p{N}_])' . preg_quote($topic, '/') . '(?![\p{L}\p{N}_])/iu';

        return (bool) preg_match($pattern, $hay);
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

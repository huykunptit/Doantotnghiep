<?php

namespace App\Http\Controllers\AIManagement;

use App\Http\Controllers\Controller;
use App\Models\AiRequestLog;
use App\Models\AiSetting;
use App\Services\StudentAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class LearningAdvisorController extends Controller
{
    protected StudentAnalyticsService $analyticsService;

    public function __construct(StudentAnalyticsService $analyticsService)
    {
        $this->analyticsService = $analyticsService;
    }

    /**
     * Get student learning analysis data (Raw data).
     */
    public function getAnalysis(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = $this->analyticsService->buildStudentProfile($user);
        return response()->json($profile);
    }

    /**
     * Call AI service to get advisor recommendations.
     */
    public function getAdvice(Request $request): JsonResponse
    {
        $user = $request->user();
        $profile = $this->analyticsService->buildStudentProfile($user);

        $aiSettings = AiSetting::current();
        $aiServiceUrl = config('services.ai_service.url') . '/learning/advise';
        $startTime = microtime(true);

        try {
            $response = Http::timeout(60)->post($aiServiceUrl, array_merge($profile, [
                'provider' => $aiSettings->provider,
                'model' => $aiSettings->model,
                'api_key' => $aiSettings->api_key,
            ]));

            $elapsed = (int) ((microtime(true) - $startTime) * 1000);
            $responseData = $response->json();
            $tokensUsed = $response->successful()
                ? (int) ($responseData['tokens_used']['total'] ?? 0)
                : 0;

            // Log AI Request
            AiRequestLog::create([
                'user_id' => $user->id,
                'endpoint' => '/learning/advise',
                'provider' => $aiSettings->provider,
                'model' => $aiSettings->model,
                'tokens_used' => $tokensUsed,
                'response_time_ms' => $elapsed,
                'status' => $response->successful() ? 'success' : 'error',
                'error_message' => $response->successful() ? null : 'HTTP ' . $response->status(),
            ]);

            if ($tokensUsed > 0) {
                $aiSettings->increment('tokens_used', $tokensUsed);
            }

            if ($response->successful()) {
                return response()->json($responseData);
            }

            return response()->json([
                'error' => $response->json('detail') ?? 'Không thể kết nối dịch vụ tư vấn AI.'
            ], $response->status());

        } catch (\Exception $e) {
            $elapsed = (int) ((microtime(true) - $startTime) * 1000);
            Log::error('Learning advisor AI request failed: ' . $e->getMessage());

            AiRequestLog::create([
                'user_id' => $user->id,
                'endpoint' => '/learning/advise',
                'provider' => $aiSettings->provider,
                'model' => $aiSettings->model,
                'tokens_used' => 0,
                'response_time_ms' => $elapsed,
                'status' => 'error',
                'error_message' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Lỗi kết nối AI Service: ' . $e->getMessage()
            ], 500);
        }
    }
}

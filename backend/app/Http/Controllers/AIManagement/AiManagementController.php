<?php

namespace App\Http\Controllers\AIManagement;

use App\Http\Controllers\Controller;

use App\Models\AiRequestLog;
use App\Models\AiSetting;
use App\Models\UserCV;
use App\Models\CareerRecommendation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AiManagementController extends Controller
{
    private function ensureAdmin(Request $request): ?JsonResponse
    {
        if (!$request->user()->hasRole('admin')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }
        return null;
    }

    /**
     * Dashboard overview: settings + usage stats.
     */
    public function dashboard(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensureAdmin($request)) {
            return $forbidden;
        }

        $settings = AiSetting::current();

        // ── Aggregate Stats ──
        $totalRequests = AiRequestLog::count();
        $successRequests = AiRequestLog::where('status', 'success')->count();
        $errorRequests = AiRequestLog::where('status', 'error')->count();
        $uniqueUsers = AiRequestLog::distinct('user_id')->count('user_id');
        $totalTokens = AiRequestLog::sum('tokens_used');
        $avgResponseTime = (int) AiRequestLog::avg('response_time_ms');

        // ── Requests by endpoint ──
        $byEndpoint = AiRequestLog::query()
            ->selectRaw("endpoint, COUNT(*) as count, SUM(tokens_used) as tokens")
            ->groupBy('endpoint')
            ->get();

        // ── Requests by provider ──
        $byProvider = AiRequestLog::query()
            ->selectRaw("provider, COUNT(*) as count, SUM(tokens_used) as tokens")
            ->groupBy('provider')
            ->get();

        // ── Daily requests (last 14 days) ──
        $dailyRequests = AiRequestLog::query()
            ->selectRaw("DATE(created_at) as date, COUNT(*) as count, SUM(tokens_used) as tokens")
            ->where('created_at', '>=', now()->subDays(14)->startOfDay())
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        // ── Recent logs (last 20) ──
        $recentLogs = AiRequestLog::with('user:id,name,email,avatar')
            ->orderByDesc('created_at')
            ->limit(20)
            ->get();

        // ── Career Advisor Stats ──
        $totalCVs = UserCV::count();
        $totalRecommendations = CareerRecommendation::count();

        return response()->json([
            'settings' => [
                ...$settings->toArray(),
                'has_api_key' => $settings->has_api_key,
                'usage_percent' => $settings->usage_percent,
            ],
            'stats' => [
                'total_requests' => $totalRequests,
                'success_requests' => $successRequests,
                'error_requests' => $errorRequests,
                'unique_users' => $uniqueUsers,
                'total_tokens' => $totalTokens,
                'avg_response_time' => $avgResponseTime,
                'total_cvs' => $totalCVs,
                'total_recommendations' => $totalRecommendations,
            ],
            'by_endpoint' => $byEndpoint,
            'by_provider' => $byProvider,
            'daily_requests' => $dailyRequests,
            'recent_logs' => $recentLogs,
        ]);
    }

    /**
     * Update AI settings (provider, model, quota, etc.)
     */
    public function updateSettings(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensureAdmin($request)) {
            return $forbidden;
        }

        $validated = $request->validate([
            'provider' => ['sometimes', 'required', 'string', 'in:chatgpt,gemini,claude'],
            'model' => ['sometimes', 'required', 'string', 'max:100'],
            'api_key' => ['nullable', 'string', 'max:500'],
            'monthly_token_quota' => ['sometimes', 'integer', 'min:1000'],
            'max_requests_per_minute' => ['sometimes', 'integer', 'min:1', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        $settings = AiSetting::current();

        // Only update api_key if explicitly provided (not empty string)
        if (array_key_exists('api_key', $validated) && $validated['api_key'] === null) {
            unset($validated['api_key']);
        }

        $settings->update($validated);

        return response()->json([
            'message' => 'AI settings updated',
            'settings' => [
                ...$settings->fresh()->toArray(),
                'has_api_key' => $settings->fresh()->has_api_key,
                'usage_percent' => $settings->fresh()->usage_percent,
            ],
        ]);
    }

    /**
     * Reset token usage counter.
     */
    public function resetQuota(Request $request): JsonResponse
    {
        if ($forbidden = $this->ensureAdmin($request)) {
            return $forbidden;
        }

        $settings = AiSetting::current();
        $settings->update([
            'tokens_used' => 0,
            'quota_reset_at' => now(),
        ]);

        return response()->json([
            'message' => 'Token quota reset successfully',
            'settings' => [
                ...$settings->fresh()->toArray(),
                'has_api_key' => $settings->fresh()->has_api_key,
                'usage_percent' => 0,
            ],
        ]);
    }

    /**
     * Get available models for each provider.
     */
    public function providers(): JsonResponse
    {
        return response()->json([
            'providers' => [
                [
                    'id' => 'chatgpt',
                    'name' => 'ChatGPT (OpenAI)',
                    'icon' => 'smart_toy',
                    'color' => '#10a37f',
                    'models' => [
                        ['id' => 'o3-mini', 'name' => 'o3-mini (Newest Reasoning)', 'tier' => 'premium'],
                        ['id' => 'o1', 'name' => 'o1 (High Reasoning)', 'tier' => 'premium'],
                        ['id' => 'gpt-4o', 'name' => 'GPT-4o (Omni)', 'tier' => 'standard'],
                        ['id' => 'gpt-4o-mini', 'name' => 'GPT-4o Mini', 'tier' => 'economy'],
                        ['id' => 'o1-mini', 'name' => 'o1-mini', 'tier' => 'standard'],
                    ],
                ],
                [
                    'id' => 'gemini',
                    'name' => 'Gemini (Google)',
                    'icon' => 'auto_awesome',
                    'color' => '#4285f4',
                    'models' => [
                        ['id' => 'gemini-2.0-flash', 'name' => 'Gemini 2.0 Flash', 'tier' => 'standard'],
                        ['id' => 'gemini-2.0-pro-exp-02-05', 'name' => 'Gemini 2.0 Pro (Exp)', 'tier' => 'premium'],
                        ['id' => 'gemini-1.5-pro', 'name' => 'Gemini 1.5 Pro', 'tier' => 'premium'],
                        ['id' => 'gemini-1.5-flash', 'name' => 'Gemini 1.5 Flash', 'tier' => 'economy'],
                    ],
                ],
                [
                    'id' => 'claude',
                    'name' => 'Claude (Anthropic)',
                    'icon' => 'psychology',
                    'color' => '#d97706',
                    'models' => [
                        ['id' => 'claude-3-7-sonnet-20250219', 'name' => 'Claude 3.7 Sonnet', 'tier' => 'premium'],
                        ['id' => 'claude-3-5-sonnet-20241022', 'name' => 'Claude 3.5 Sonnet', 'tier' => 'standard'],
                        ['id' => 'claude-3-5-haiku-20241022', 'name' => 'Claude 3.5 Haiku', 'tier' => 'economy'],
                        ['id' => 'claude-3-opus-20240229', 'name' => 'Claude 3 Opus', 'tier' => 'premium'],
                    ],
                ],
            ],
        ]);
    }
}

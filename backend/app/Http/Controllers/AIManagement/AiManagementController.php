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
        if (!\App\Support\Authorize::isAdmin($request->user())) {
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
            'provider' => ['sometimes', 'required', 'string', 'in:chatgpt,gemini,claude,openrouter,ollama'],
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
                    'name' => 'ChatGPT (via proxy)',
                    'icon' => 'smart_toy',
                    'color' => '#10a37f',
                    'models' => [
                        ['id' => 'nghi/gpt-5.5', 'name' => 'GPT 5.5 (nghimmo, mặc định)', 'tier' => 'standard'],
                        ['id' => 'nghi/gpt-5.6', 'name' => 'GPT 5.6', 'tier' => 'standard'],
                        ['id' => 'nghi/gpt-5.6-luna', 'name' => 'GPT 5.6 Luna (nhanh)', 'tier' => 'economy'],
                        ['id' => 'nghi/gpt-5.6-terra', 'name' => 'GPT 5.6 Terra (cân bằng)', 'tier' => 'standard'],
                        ['id' => 'nghi/gpt-5.6-sol', 'name' => 'GPT 5.6 Sol (mạnh)', 'tier' => 'premium'],
                        ['id' => 'nghi/gpt-5.4-mini', 'name' => 'GPT 5.4 Mini', 'tier' => 'economy'],
                    ],
                ],
                [
                    'id' => 'gemini',
                    'name' => 'Gemini (Google)',
                    'icon' => 'auto_awesome',
                    'color' => '#4285f4',
                    'models' => [
                        ['id' => 'gemini-2.5-flash', 'name' => 'Gemini 2.5 Flash (ổn định)', 'tier' => 'standard'],
                        ['id' => 'gemini-flash-latest', 'name' => 'Gemini Flash Latest', 'tier' => 'standard'],
                        ['id' => 'gemini-flash-lite-latest', 'name' => 'Gemini Flash Lite Latest', 'tier' => 'economy'],
                        ['id' => 'gemini-3-flash-preview', 'name' => 'Gemini 3 Flash Preview', 'tier' => 'standard'],
                        ['id' => 'gemini-3.5-flash', 'name' => 'Gemini 3.5 Flash', 'tier' => 'standard'],
                        ['id' => 'gemini-3.5-flash-lite', 'name' => 'Gemini 3.5 Flash Lite', 'tier' => 'economy'],
                        ['id' => 'gemini-3.6-flash', 'name' => 'Gemini 3.6 Flash', 'tier' => 'standard'],
                        ['id' => 'gemini-3.1-flash-lite', 'name' => 'Gemini 3.1 Flash Lite', 'tier' => 'economy'],
                    ],
                ],
                [
                    'id' => 'claude',
                    'name' => 'Claude (via proxy)',
                    'icon' => 'psychology',
                    'color' => '#d97706',
                    'models' => [
                        ['id' => 'nghi/claude-haiku-4.5', 'name' => 'Claude Haiku 4.5 (nghimmo)', 'tier' => 'economy'],
                        ['id' => 'nghi/claude-sonnet-4.6', 'name' => 'Claude Sonnet 4.6 (nghimmo)', 'tier' => 'standard'],
                        ['id' => 'nghi/claude-sonnet-5', 'name' => 'Claude Sonnet 5 (nghimmo)', 'tier' => 'premium'],
                        ['id' => 'nghi/claude-opus-5', 'name' => 'Claude Opus 5 (nghimmo)', 'tier' => 'premium'],
                        ['id' => 'claude-3-5-haiku-20241022', 'name' => 'Claude 3.5 Haiku (official)', 'tier' => 'economy'],
                    ],
                ],
                [
                    'id' => 'openrouter',
                    'name' => 'OpenRouter',
                    'icon' => 'hub',
                    'color' => '#7c3aed',
                    'models' => [
                        ['id' => 'deepseek/deepseek-chat', 'name' => 'DeepSeek Chat', 'tier' => 'standard'],
                        ['id' => 'openai/gpt-4o-mini', 'name' => 'GPT-4o Mini', 'tier' => 'economy'],
                        ['id' => 'qwen/qwen-2.5-7b-instruct', 'name' => 'Qwen 2.5 7B Instruct', 'tier' => 'economy'],
                        ['id' => 'openrouter/auto', 'name' => 'OpenRouter Auto', 'tier' => 'standard'],
                    ],
                ],
                [
                    'id' => 'ollama',
                    'name' => 'Ollama (Local)',
                    'icon' => 'computer',
                    'color' => '#111827',
                    'models' => [
                        ['id' => 'qwen2.5:latest', 'name' => 'Qwen 2.5 (local)', 'tier' => 'local'],
                        ['id' => 'qwen2.5:7b', 'name' => 'Qwen 2.5 7B', 'tier' => 'local'],
                        ['id' => 'llama3.2', 'name' => 'Llama 3.2', 'tier' => 'local'],
                        ['id' => 'mistral', 'name' => 'Mistral', 'tier' => 'local'],
                    ],
                ],
            ],
        ]);
    }
}

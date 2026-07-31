<?php

namespace App\Services;

use App\Models\AiRequestLog;
use App\Models\AiSetting;
use Illuminate\Support\Facades\Http;

class AiServiceClient
{
    /**
     * POST tới ai-service với chuỗi fallback provider.
     * Primary → gemini → claude → openrouter → ollama.
     *
     * @param  array<string, mixed>  $basePayload  Payload không gồm provider/model/api_key
     * @return array{ok: bool, data: array, provider: string, model: ?string, fallback: bool, error: ?string}
     */
    public function postWithFallback(
        string $path,
        array $basePayload,
        ?int $userId = null,
        ?string $logEndpoint = null,
        int $connectTimeout = 5,
        int $timeout = 60,
        bool $requireJsonObject = true,
    ): array {
        $aiSettings = AiSetting::current();
        $url = rtrim((string) config('services.ai_service.url'), '/') . '/' . ltrim($path, '/');
        $primary = strtolower((string) ($aiSettings->provider ?: 'chatgpt'));
        $endpoint = $logEndpoint ?: '/' . ltrim($path, '/');
        $lastError = null;

        foreach ($aiSettings->providerFallbackChain() as $index => $provider) {
            $apiKey = $aiSettings->resolveApiKey($provider);
            if (!$apiKey) {
                continue;
            }

            $model = $aiSettings->resolveModel($provider);
            $providerTimeout = $provider === 'ollama' ? max($timeout, 180) : $timeout;
            $started = microtime(true);

            try {
                $response = Http::connectTimeout($connectTimeout)
                    ->timeout($providerTimeout)
                    ->post($url, array_merge($basePayload, [
                        'provider' => $provider,
                        'model' => $model,
                        'api_key' => $apiKey,
                    ]));

                $elapsed = (int) ((microtime(true) - $started) * 1000);
                $responseData = $response->json();
                $tokensUsed = $response->successful()
                    ? (int) (is_array($responseData) ? ($responseData['tokens_used']['total'] ?? 0) : 0)
                    : 0;

                AiRequestLog::create([
                    'user_id' => $userId,
                    'endpoint' => $endpoint,
                    'provider' => $provider,
                    'model' => $model,
                    'tokens_used' => $tokensUsed,
                    'response_time_ms' => $elapsed,
                    'status' => $response->successful() ? 'success' : 'error',
                    'error_message' => $response->successful()
                        ? null
                        : ('HTTP ' . $response->status() . ' ' . (is_array($responseData) ? ($responseData['detail'] ?? '') : $response->body())),
                ]);

                if ($tokensUsed > 0) {
                    $aiSettings->increment('tokens_used', $tokensUsed);
                }

                if ($response->successful() && is_array($responseData)) {
                    if ($requireJsonObject && $responseData === []) {
                        $lastError = 'Empty JSON response';
                        continue;
                    }

                    return [
                        'ok' => true,
                        'data' => $responseData,
                        'provider' => $provider,
                        'model' => $model,
                        'fallback' => $index > 0,
                        'error' => null,
                    ];
                }

                $lastError = is_array($responseData)
                    ? (string) ($responseData['detail'] ?? ('HTTP ' . $response->status()))
                    : ('HTTP ' . $response->status());
            } catch (\Throwable $e) {
                $elapsed = (int) ((microtime(true) - $started) * 1000);
                $lastError = $e->getMessage();

                AiRequestLog::create([
                    'user_id' => $userId,
                    'endpoint' => $endpoint,
                    'provider' => $provider,
                    'model' => $model,
                    'tokens_used' => 0,
                    'response_time_ms' => $elapsed,
                    'status' => 'error',
                    'error_message' => $lastError,
                ]);
            }
        }

        return [
            'ok' => false,
            'data' => [],
            'provider' => $primary,
            'model' => $aiSettings->resolveModel($primary),
            'fallback' => false,
            'error' => $lastError,
        ];
    }
}

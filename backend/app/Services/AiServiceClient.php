<?php

namespace App\Services;

use App\Models\AiRequestLog;
use App\Models\AiSetting;
use Illuminate\Support\Facades\Http;

class AiServiceClient
{
    /** Header xác thực nội bộ gửi kèm mọi request tới ai-service (rỗng nếu chưa cấu hình secret). */
    protected function internalHeaders(): array
    {
        $secret = (string) config('services.ai_service.shared_secret');

        return $secret !== '' ? ['X-Internal-Token' => $secret] : [];
    }

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
        ?int $maxTotalMs = null,
    ): array {
        $aiSettings = AiSetting::current();
        $url = rtrim((string) config('services.ai_service.url'), '/') . '/' . ltrim($path, '/');
        $primary = strtolower((string) ($aiSettings->provider ?: 'chatgpt'));
        $endpoint = $logEndpoint ?: '/' . ltrim($path, '/');
        $lastError = null;
        $deadline = $maxTotalMs !== null ? (microtime(true) + $maxTotalMs / 1000) : null;

        foreach ($aiSettings->providerFallbackChain() as $index => $provider) {
            if ($deadline !== null && microtime(true) >= $deadline) {
                $lastError = $lastError ?? 'Tổng thời gian chờ AI provider đã vượt giới hạn.';
                break;
            }

            $apiKey = $aiSettings->resolveApiKey($provider);
            if (!$apiKey) {
                continue;
            }

            $model = $aiSettings->resolveModel($provider);
            $providerTimeout = $provider === 'ollama' ? max($timeout, 180) : $timeout;
            if ($deadline !== null) {
                $remaining = $deadline - microtime(true);
                $providerTimeout = min($providerTimeout, max(1, (int) ceil($remaining)));
            }
            $started = microtime(true);

            try {
                $response = Http::connectTimeout($connectTimeout)
                    ->timeout($providerTimeout)
                    ->withHeaders($this->internalHeaders())
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

    /**
     * Proxy streaming (SSE) tới ai-service, chỉ dùng provider chính (không lặp cả chuỗi
     * fallback khi đang stream — không thể "undo" chữ đã gửi cho client giữa chừng).
     *
     * Nếu provider chính lỗi kết nối / trả HTTP lỗi NGAY TỪ ĐẦU (trước khi có byte nào
     * được forward), tự động rơi về postWithFallback() (chuỗi fallback đầy đủ, non-stream,
     * gọi $fallbackPath — endpoint JSON tương ứng, KHÔNG phải endpoint stream) và giả lập
     * lại thành 1 SSE event duy nhất — client luôn nhận đúng 1 kiểu response.
     *
     * @param  string  $streamPath  Endpoint SSE trên ai-service (vd. "chat/stream")
     * @param  string  $fallbackPath  Endpoint JSON non-stream tương ứng (vd. "chat")
     * @param  array<string, mixed>  $basePayload
     * @return \Generator<string> Từng dòng SSE thô ("data: ...\n\n"), sẵn sàng để echo thẳng.
     */
    public function streamChat(
        string $streamPath,
        string $fallbackPath,
        array $basePayload,
        ?int $userId = null,
        ?string $logEndpoint = null,
        int $connectTimeout = 5,
        int $timeout = 90,
    ): \Generator {
        $aiSettings = AiSetting::current();
        $url = rtrim((string) config('services.ai_service.url'), '/') . '/' . ltrim($streamPath, '/');
        $primary = strtolower((string) ($aiSettings->provider ?: 'chatgpt'));
        $endpoint = $logEndpoint ?: '/' . ltrim($streamPath, '/');
        $model = $aiSettings->resolveModel($primary);
        $apiKey = $aiSettings->resolveApiKey($primary);
        $started = microtime(true);

        if ($apiKey) {
            try {
                $response = Http::connectTimeout($connectTimeout)
                    ->timeout($timeout)
                    ->withOptions(['stream' => true])
                    ->withHeaders($this->internalHeaders())
                    ->post($url, array_merge($basePayload, [
                        'provider' => $primary,
                        'model' => $model,
                        'api_key' => $apiKey,
                    ]));
            } catch (\Throwable $e) {
                $response = null;
            }

            if (isset($response) && $response->successful()) {
                $buffer = '';
                $body = $response->toPsrResponse()->getBody();

                try {
                    while (!$body->eof()) {
                        $chunk = $body->read(4096);
                        if ($chunk === '') {
                            continue;
                        }
                        $buffer .= $chunk;
                        yield $chunk;
                    }
                } catch (\Throwable $e) {
                    yield 'data: ' . json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE) . "\n\n";
                }

                $elapsed = (int) ((microtime(true) - $started) * 1000);
                $tokensUsed = $this->extractTokensFromSseBuffer($buffer);
                AiRequestLog::create([
                    'user_id' => $userId,
                    'endpoint' => $endpoint,
                    'provider' => $primary,
                    'model' => $model,
                    'tokens_used' => $tokensUsed,
                    'response_time_ms' => $elapsed,
                    'status' => 'success',
                    'error_message' => null,
                ]);
                if ($tokensUsed > 0) {
                    $aiSettings->increment('tokens_used', $tokensUsed);
                }

                return;
            }
        }

        // Provider chính không stream được (thiếu key / lỗi kết nối / HTTP lỗi trước khi
        // có byte nào forward) → rơi về chuỗi fallback non-stream đầy đủ như /chat thường.
        $result = $this->postWithFallback($fallbackPath, $basePayload, $userId, $logEndpoint, maxTotalMs: 25000);

        if ($result['ok']) {
            $reply = (string) ($result['data']['reply'] ?? '');
            if ($reply !== '') {
                yield 'data: ' . json_encode(['delta' => $reply], JSON_UNESCAPED_UNICODE) . "\n\n";
            }
            yield 'data: ' . json_encode([
                'done' => true,
                'tokens_used' => $result['data']['tokens_used'] ?? ['prompt' => 0, 'completion' => 0, 'total' => 0],
                'rag_used' => $result['data']['rag_used'] ?? false,
                'sources' => $result['data']['sources'] ?? [],
            ], JSON_UNESCAPED_UNICODE) . "\n\n";

            return;
        }

        yield 'data: ' . json_encode([
            'error' => $result['error'] ?? 'AI service không khả dụng.',
        ], JSON_UNESCAPED_UNICODE) . "\n\n";
    }

    /** Đọc token usage từ event "done" cuối cùng trong buffer SSE đã forward, để ghi log. */
    protected function extractTokensFromSseBuffer(string $buffer): int
    {
        $lines = array_filter(explode("\n\n", $buffer));
        foreach (array_reverse($lines) as $line) {
            $line = trim($line);
            if (!str_starts_with($line, 'data:')) {
                continue;
            }
            $json = trim(substr($line, 5));
            $decoded = json_decode($json, true);
            if (is_array($decoded) && isset($decoded['done'])) {
                return (int) ($decoded['tokens_used']['total'] ?? 0);
            }
        }

        return 0;
    }
}

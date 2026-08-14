<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AiSetting extends Model
{
    protected $fillable = [
        'provider',
        'model',
        'api_key',
        'monthly_token_quota',
        'tokens_used',
        'max_requests_per_minute',
        'is_active',
        'quota_reset_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'quota_reset_at' => 'datetime',
        'api_key' => 'encrypted',
    ];

    protected $hidden = [
        'api_key',
    ];

    /**
     * Get the current active settings (singleton-style row).
     */
    public static function current(): self
    {
        return static::firstOrCreate([], [
            'provider' => 'gemini',
            'model' => 'gemini-2.5-flash',
            'monthly_token_quota' => 1000000,
            'tokens_used' => 0,
            'max_requests_per_minute' => 60,
            'is_active' => true,
        ]);
    }

    /**
     * Quick token-quota percentage.
     */
    public function getUsagePercentAttribute(): float
    {
        if ($this->monthly_token_quota <= 0) return 0;
        return round(($this->tokens_used / $this->monthly_token_quota) * 100, 1);
    }

    /**
     * Check if API key is configured (DB row or matching env fallback).
     */
    public function getHasApiKeyAttribute(): bool
    {
        // Ollama chạy local — không cần API key cloud
        if ($this->provider === 'ollama') {
            return (bool) config('services.ai_service.ollama_enabled');
        }

        if (!empty($this->api_key)) {
            return true;
        }

        return match ($this->provider) {
            'gemini' => filled(config('services.ai_service.gemini_api_key')),
            'openrouter' => filled(config('services.ai_service.openrouter_api_key')),
            'claude' => filled(config('services.ai_service.claude_api_key')),
            default => filled(config('services.ai_service.openai_api_key'))
                || filled(config('services.ai_service.claude_api_key')),
        };
    }

    /**
     * Resolve API key for a provider (settings key only applies to active provider).
     */
    public function resolveApiKey(string $provider): ?string
    {
        $provider = strtolower(trim($provider));

        if ($provider === 'ollama') {
            if (!config('services.ai_service.ollama_enabled')) {
                return null;
            }

            return 'local';
        }

        $fromEnv = match ($provider) {
            'gemini' => config('services.ai_service.gemini_api_key'),
            'openrouter' => config('services.ai_service.openrouter_api_key'),
            'claude' => config('services.ai_service.claude_api_key'),
            default => config('services.ai_service.openai_api_key')
                ?: config('services.ai_service.claude_api_key'),
        };

        if (filled($fromEnv)) {
            return (string) $fromEnv;
        }

        $primary = strtolower((string) ($this->provider ?: 'gemini'));
        if ($provider === $primary) {
            try {
                if (filled($this->api_key)) {
                    return (string) $this->api_key;
                }
            } catch (\Throwable) {
                // APP_KEY lệch / ciphertext cũ — bỏ qua key trong DB.
            }
        }

        return null;
    }

    public function resolveModel(string $provider): ?string
    {
        $provider = strtolower(trim($provider));
        $primary = strtolower((string) ($this->provider ?: 'gemini'));

        if ($provider === $primary && filled($this->model)) {
            return (string) $this->model;
        }

        return match ($provider) {
            'gemini' => (string) config('services.ai_service.gemini_model', 'gemini-2.5-flash'),
            'openrouter' => (string) config('services.ai_service.openrouter_model', 'deepseek/deepseek-chat'),
            'claude' => (string) config('services.ai_service.claude_model', 'nghi/claude-haiku-4.5'),
            'ollama' => (string) config('services.ai_service.ollama_model', 'qwen2.5:latest'),
            default => (string) config('services.ai_service.openai_model', 'nghi/gpt-5.5'),
        };
    }

    /**
     * Primary first, then live cloud backups. ChatGPT is in the chain.
     * Ollama is last and only when OLLAMA_ENABLED=true.
     *
     * @return list<string>
     */
    public function providerFallbackChain(): array
    {
        $primary = strtolower((string) ($this->provider ?: 'gemini'));
        $chain = [$primary];
        foreach (['gemini', 'openrouter', 'chatgpt', 'claude', 'ollama'] as $candidate) {
            if ($candidate === $primary) {
                continue;
            }
            if ($candidate === 'ollama' && !config('services.ai_service.ollama_enabled')) {
                continue;
            }
            $chain[] = $candidate;
        }

        return $chain;
    }
}

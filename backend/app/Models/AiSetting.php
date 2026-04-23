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
            'provider' => 'chatgpt',
            'model' => 'gpt-4o-mini',
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
     * Check if API key is configured.
     */
    public function getHasApiKeyAttribute(): bool
    {
        return !empty($this->api_key);
    }
}

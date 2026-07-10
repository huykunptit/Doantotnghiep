<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Voucher extends Model
{
    protected $fillable = [
        'name',
        'description',
        'type',
        'discount_value',
        'points_cost',
        'total_quantity',
        'redeemed_count',
        'image',
        'is_active',
        'expires_at',
        'course_id',
    ];

    protected function casts(): array
    {
        return [
            'is_active'    => 'boolean',
            'expires_at'   => 'datetime',
            'points_cost'  => 'integer',
            'discount_value' => 'integer',
        ];
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function userVouchers(): HasMany
    {
        return $this->hasMany(UserVoucher::class);
    }

    public function isAvailable(): bool
    {
        if (!$this->is_active) return false;
        if ($this->expires_at && $this->expires_at->isPast()) return false;
        if ($this->total_quantity !== null && $this->redeemed_count >= $this->total_quantity) return false;
        return true;
    }
}

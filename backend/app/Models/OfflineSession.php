<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class OfflineSession extends Model
{
    public const DEFAULT_CHECK_IN_RADIUS_METERS = 15;

    protected $fillable = [
        'lesson_id',
        'class_section_id',
        'title',
        'location',
        'start_at',
        'duration',
        'max_participants',
        'qr_token',
        'qr_expires_at',
        'latitude',
        'longitude',
        'check_in_radius_meters',
        'is_active',
    ];

    protected $casts = [
        'start_at'      => 'datetime',
        'qr_expires_at' => 'datetime',
        'latitude'      => 'float',
        'longitude'     => 'float',
        'check_in_radius_meters' => 'integer',
        'is_active'     => 'boolean',
    ];

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function classSection(): BelongsTo
    {
        return $this->belongsTo(ClassSection::class);
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(OfflineSessionAttendance::class);
    }

    public function checkInRadiusMeters(): int
    {
        $radius = (int) ($this->check_in_radius_meters ?: self::DEFAULT_CHECK_IN_RADIUS_METERS);

        return max(5, min(500, $radius));
    }

    public function generateQrToken(int $ttlMinutes = 5): void
    {
        $this->qr_token      = Str::random(48);
        $this->qr_expires_at = now()->addMinutes(max(1, $ttlMinutes));
        $this->save();
    }

    public function isQrValid(): bool
    {
        return $this->qr_token && $this->qr_expires_at && now()->lt($this->qr_expires_at);
    }

    public function qrPayload(): string
    {
        return json_encode([
            'type' => 'sylva_attendance',
            'session_id' => $this->id,
            'token' => $this->qr_token,
        ], JSON_UNESCAPED_UNICODE);
    }
}

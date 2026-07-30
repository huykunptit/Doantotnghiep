<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class OfflineSession extends Model
{
    public const DEFAULT_CHECK_IN_RADIUS_METERS = 15;

    public const QR_MODE_MANUAL = 'manual';

    public const QR_MODE_ROTATING = 'rotating';

    public const QR_MODE_STATIC = 'static';

    protected $fillable = [
        'lesson_id',
        'class_section_id',
        'title',
        'location',
        'room',
        'start_at',
        'duration',
        'max_participants',
        'qr_token',
        'qr_expires_at',
        'latitude',
        'longitude',
        'check_in_radius_meters',
        'is_active',
        'qr_enabled',
        'qr_mode',
        'qr_rotate_seconds',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'qr_expires_at' => 'datetime',
        'latitude' => 'float',
        'longitude' => 'float',
        'check_in_radius_meters' => 'integer',
        'is_active' => 'boolean',
        'qr_enabled' => 'boolean',
        'qr_rotate_seconds' => 'integer',
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

    public function normalizedQrMode(): string
    {
        $mode = (string) ($this->qr_mode ?: self::QR_MODE_MANUAL);

        return in_array($mode, [self::QR_MODE_MANUAL, self::QR_MODE_ROTATING, self::QR_MODE_STATIC], true)
            ? $mode
            : self::QR_MODE_MANUAL;
    }

    public function rotateIntervalSeconds(): int
    {
        return max(15, min(600, (int) ($this->qr_rotate_seconds ?: 60)));
    }

    /**
     * Resolve TTL in seconds for a newly minted QR token.
     */
    public function resolveQrTtlSeconds(?int $ttlMinutes = null): int
    {
        return match ($this->normalizedQrMode()) {
            self::QR_MODE_STATIC => max(60, (int) ($this->duration ?: 120) * 60),
            self::QR_MODE_ROTATING => $this->rotateIntervalSeconds() + 15,
            default => max(60, (int) ($ttlMinutes ?? 5) * 60),
        };
    }

    public function generateQrToken(?int $ttlMinutes = null): void
    {
        $ttlSeconds = $this->resolveQrTtlSeconds($ttlMinutes);
        $this->qr_token = Str::random(48);
        $this->qr_expires_at = now()->addSeconds($ttlSeconds);
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

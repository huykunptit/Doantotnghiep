<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class OfflineSession extends Model
{
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
        'is_active',
    ];

    protected $casts = [
        'start_at'      => 'datetime',
        'qr_expires_at' => 'datetime',
        'latitude'      => 'float',
        'longitude'     => 'float',
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

    public function generateQrToken(): void
    {
        $this->qr_token      = Str::random(48);
        $this->qr_expires_at = now()->addMinutes(5);
        $this->save();
    }

    public function isQrValid(): bool
    {
        return $this->qr_token && $this->qr_expires_at && now()->lt($this->qr_expires_at);
    }
}

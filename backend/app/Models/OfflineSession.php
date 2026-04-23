<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfflineSession extends Model
{
    protected $fillable = [
        'lesson_id',
        'location',
        'start_at',
        'duration',
        'max_participants',
    ];

    protected $casts = [
        'start_at' => 'datetime',
    ];

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}

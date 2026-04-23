<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonProgress extends Model
{
    protected $table = 'lesson_progress';

    protected $fillable = [
        'user_id',
        'lesson_id',
        'progress_percent',
        'last_position',
        'watched_seconds',
        'completed',
        'completed_at',
        'last_watched_at',
    ];

    protected $casts = [
        'progress_percent' => 'integer',
        'last_position' => 'integer',
        'watched_seconds' => 'integer',
        'completed' => 'boolean',
        'completed_at' => 'datetime',
        'last_watched_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}

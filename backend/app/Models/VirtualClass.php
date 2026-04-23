<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VirtualClass extends Model
{
    protected $fillable = [
        'lesson_id',
        'provider',
        'meeting_id',
        'meeting_password',
        'join_url',
        'start_url',
        'start_at',
        'duration',
    ];

    protected $casts = [
        'start_at' => 'datetime',
        'duration' => 'integer',
    ];

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}

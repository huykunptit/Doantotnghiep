<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamViolation extends Model
{
    protected $fillable = [
        'attempt_id',
        'user_id',
        'type',         // focus_lost | no_face | multiple_faces | suspicious | looking_away | phone_detected
        'severity',     // warning | critical
        'snapshot_url',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(QuizAttempt::class, 'attempt_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

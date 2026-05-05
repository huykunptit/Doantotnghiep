<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExamEnrollment extends Model
{
    protected $fillable = [
        'exam_id',
        'user_id',
        'enrolled_by',
        'enrolled_at',
    ];

    protected $casts = [
        'enrolled_at' => 'datetime',
    ];

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function enrolledByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'enrolled_by');
    }
}

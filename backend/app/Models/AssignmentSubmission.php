<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssignmentSubmission extends Model
{
    protected $fillable = [
        'lesson_assignment_id',
        'user_id',
        'file_url',
        'student_note',
        'grade',
        'feedback',
        'submitted_at',
    ];

    protected $casts = [
        'submitted_at' => 'datetime',
        'grade' => 'decimal:2',
    ];

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(LessonAssignment::class, 'lesson_assignment_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

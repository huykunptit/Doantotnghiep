<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LessonAssignment extends Model
{
    protected $fillable = [
        'lesson_id',
        'instructions',
        'max_file_size',
        'allowed_extensions',
        'due_at',
    ];

    protected $casts = [
        'due_at' => 'datetime',
    ];

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class);
    }
}

// ---------------------------------------------------------

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

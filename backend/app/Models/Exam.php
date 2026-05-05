<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Builder;

class Exam extends Model
{
    protected $fillable = [
        'course_id',
        'title',
        'description',
        'type',             // course_final | standalone
        'status',           // draft | scheduled | active | closed | archived
        'duration',
        'pass_score',
        'max_attempts',
        'shuffle_questions',
        'shuffle_answers',
        'review_options',
        'starts_at',
        'ends_at',
        'proctoring_enabled',
        'proctoring_settings',
        'created_by',
    ];

    protected $casts = [
        'starts_at'           => 'datetime',
        'ends_at'             => 'datetime',
        'duration'            => 'integer',
        'pass_score'          => 'integer',
        'max_attempts'        => 'integer',
        'shuffle_questions'   => 'boolean',
        'shuffle_answers'     => 'boolean',
        'review_options'      => 'array',
        'proctoring_enabled'  => 'boolean',
        'proctoring_settings' => 'array',
    ];

    /**
     * Default review options (what student sees after submitting).
     */
    public static function defaultReviewOptions(): array
    {
        return [
            'after_submit' => [
                'attempt'           => true,
                'correctness'       => true,
                'marks'             => true,
                'specific_feedback' => false,
                'general_feedback'  => false,
                'right_answer'      => false,
                'overall_feedback'  => true,
            ],
        ];
    }

    // ── Relationships ───────────────────────────────────────────────────

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function quiz(): HasOne
    {
        return $this->hasOne(Quiz::class);
    }

    public function examEnrollments(): HasMany
    {
        return $this->hasMany(ExamEnrollment::class);
    }

    public function enrolledUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'exam_enrollments')
            ->withPivot(['enrolled_by', 'enrolled_at'])
            ->withTimestamps();
    }

    // ── Scopes ──────────────────────────────────────────────────────────

    public function scopeStandalone(Builder $query): Builder
    {
        return $query->where('type', 'standalone');
    }

    public function scopeCourseExam(Builder $query): Builder
    {
        return $query->where('type', 'course_final');
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    public function isStandalone(): bool
    {
        return $this->type === 'standalone';
    }

    public function isCourseExam(): bool
    {
        return $this->type === 'course_final';
    }

    public function isOpen(): bool
    {
        $now = now();
        if ($this->starts_at && $now->lt($this->starts_at)) return false;
        if ($this->ends_at && $now->gt($this->ends_at)) return false;
        return in_array($this->status, ['active', 'scheduled']);
    }

    public function getReviewOption(string $key, bool $default = false): bool
    {
        $options = $this->review_options ?? self::defaultReviewOptions();
        return data_get($options, "after_submit.{$key}", $default);
    }
}

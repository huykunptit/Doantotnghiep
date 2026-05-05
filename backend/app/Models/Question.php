<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Question extends Model
{
    protected $fillable = [
        'code',
        'course_id',
        'question_bank_id',
        'question_group_id',
        'content',
        'type', // single_choice, multiple_choice, true_false, essay, matching, ordering, short_answer, numerical
        'difficulty', // 1-5
        'default_score',
        'explanation',
        'feedback',
        'general_feedback',
        'metadata', // JSON: e.g. { tolerance: 0.01 } for numerical
    ];

    protected $casts = [
        'default_score' => 'decimal:2',
        'difficulty'    => 'integer',
        'metadata'      => 'array',
    ];

    /**
     * Auto-generate question code if not provided.
     */
    protected static function booted(): void
    {
        static::creating(function (Question $question) {
            if (empty($question->code)) {
                $prefix = $question->course_id ? "C{$question->course_id}" : 'G';
                $question->code = "Q-{$prefix}-" . strtoupper(substr(uniqid(), -6));
            }
        });
    }

    // ── Relationships ───────────────────────────────────────────────────

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(QuestionBank::class, 'question_bank_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(QuestionGroup::class, 'question_group_id');
    }

    public function answers(): HasMany
    {
        return $this->hasMany(Answer::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(QuestionAttachment::class);
    }

    public function quizzes(): BelongsToMany
    {
        return $this->belongsToMany(Quiz::class, 'quiz_question')
            ->withPivot(['order', 'points'])
            ->withTimestamps();
    }

    // ── Helpers ──────────────────────────────────────────────────────────

    public function difficultyLabel(): string
    {
        return match ($this->difficulty) {
            1 => 'Nhận biết',
            2 => 'Thông hiểu',
            3 => 'Vận dụng',
            4 => 'Vận dụng cao',
            5 => 'Sáng tạo',
            default => 'Chưa xác định',
        };
    }
}

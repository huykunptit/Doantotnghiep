<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Question extends Model
{
    protected $fillable = [
        'course_id',
        'question_bank_id',
        'question_group_id',
        'content',
        'type', // single_choice, multiple_choice, essay, matching, ordering, short_answer
        'difficulty', // 1-5
        'explanation',
    ];

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

    public function quizzes(): BelongsToMany
    {
        return $this->belongsToMany(Quiz::class, 'quiz_question')
            ->withPivot(['order', 'points'])
            ->withTimestamps();
    }
}

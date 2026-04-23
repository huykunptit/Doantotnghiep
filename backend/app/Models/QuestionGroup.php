<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuestionGroup extends Model
{
    protected $fillable = [
        'course_id',
        'question_bank_id',
        'name',
        'description',
        'sort_order',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(QuestionBank::class, 'question_bank_id');
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class)->orderByDesc('created_at');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Quiz extends Model
{
    protected $fillable = [
        'lesson_id',
        'course_id',
        'exam_id',
        'scope',
        'title',
        'description',
        'time_limit',
        'pass_score',
        'settings',
    ];

    protected $casts = [
        'settings' => 'array',
    ];

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class);
    }

    public function questions(): BelongsToMany
    {
        return $this->belongsToMany(Question::class, 'quiz_question')
            ->withPivot(['order', 'points'])
            ->withTimestamps();
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    /**
     * Resolve the set of questions based on fixed questions and randomization rules.
     */
    public function resolveQuestions()
    {
        $fixedQuestions = $this->questions()->get();
        
        $randomQuestions = collect();
        $settings = $this->settings ?? [];
        
        if (!empty($settings['random_rules'])) {
            foreach ($settings['random_rules'] as $rule) {
                // Example Rule: {"bank_id": 1, "count": 5, "difficulty": 2}
                $query = Question::where('question_bank_id', $rule['bank_id']);

                if (!empty($rule['group_id'])) {
                    $query->where('question_group_id', $rule['group_id']);
                }
                
                if (!empty($rule['difficulty'])) {
                    $query->where('difficulty', $rule['difficulty']);
                }
                
                if (!empty($rule['type'])) {
                    $query->where('type', $rule['type']);
                }

                $picked = $query->inRandomOrder()
                    ->limit($rule['count'] ?? 0)
                    ->get();
                    
                $randomQuestions = $randomQuestions->merge($picked);
            }
        }
        
        // Merge and remove duplicates by ID
        return $fixedQuestions->merge($randomQuestions)->unique('id')->values();
    }
}

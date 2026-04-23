<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CareerRecommendation extends Model
{
    protected $fillable = [
        'user_id',
        'job_id',
        'match_score',
        'skill_gaps',
        'suggested_courses',
        'ai_summary',
    ];

    protected $casts = [
        'skill_gaps' => 'json',
        'suggested_courses' => 'json',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function job(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class, 'job_id');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class CourseLearningOutcome extends Model
{
    use HasFactory;

    protected $table = 'course_learning_outcomes';

    protected $fillable = [
        'course_id',
        'code',
        'description',
        'position',
    ];

    protected $casts = [
        'position' => 'integer',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function plos(): BelongsToMany
    {
        return $this->belongsToMany(ProgramLearningOutcome::class, 'clo_plo_map', 'clo_id', 'plo_id')
            ->withPivot('weight')
            ->withTimestamps();
    }
}

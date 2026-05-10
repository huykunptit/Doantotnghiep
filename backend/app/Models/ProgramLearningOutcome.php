<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ProgramLearningOutcome extends Model
{
    use HasFactory;

    protected $table = 'program_learning_outcomes';

    protected $fillable = [
        'program_id',
        'code',
        'description',
        'level',
        'position',
    ];

    protected $casts = [
        'position' => 'integer',
    ];

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function clos(): BelongsToMany
    {
        return $this->belongsToMany(CourseLearningOutcome::class, 'clo_plo_map', 'plo_id', 'clo_id')
            ->withPivot('weight')
            ->withTimestamps();
    }
}

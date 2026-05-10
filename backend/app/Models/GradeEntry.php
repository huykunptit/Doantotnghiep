<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GradeEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'enrollment_id',
        'grade_component_id',
        'score',
        'graded_by',
        'graded_at',
        'note',
    ];

    protected $casts = [
        'score' => 'decimal:2',
        'graded_at' => 'datetime',
    ];

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function component(): BelongsTo
    {
        return $this->belongsTo(GradeComponent::class, 'grade_component_id');
    }

    public function grader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }
}

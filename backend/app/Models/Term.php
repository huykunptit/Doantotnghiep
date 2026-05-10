<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Term extends Model
{
    use HasFactory;

    protected $fillable = [
        'academic_year_id',
        'name',
        'code',
        'start_date',
        'end_date',
        'enrollment_start_at',
        'enrollment_end_at',
        'exam_start_at',
        'exam_end_at',
        'is_current',
        'status',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'enrollment_start_at' => 'datetime',
        'enrollment_end_at' => 'datetime',
        'exam_start_at' => 'datetime',
        'exam_end_at' => 'datetime',
        'is_current' => 'boolean',
    ];

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class);
    }

    public function classSections(): HasMany
    {
        return $this->hasMany(ClassSection::class);
    }
}

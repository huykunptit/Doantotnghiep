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
        'start_date' => 'date:Y-m-d',
        'end_date' => 'date:Y-m-d',
        'enrollment_start_at' => 'datetime:Y-m-d',
        'enrollment_end_at' => 'datetime:Y-m-d',
        'exam_start_at' => 'datetime:Y-m-d',
        'exam_end_at' => 'datetime:Y-m-d',
        'is_current' => 'boolean',
    ];

    public function academicYear(): BelongsTo
    {
        return $this->belongsTo(AcademicYear::class);
    }

    /**
     * Cửa sổ khóa học = thời gian kỳ: hết hạn / đang học / sắp tới.
     * Không có ngày thì coi như đang mở (marketplace, khóa không gắn kỳ).
     */
    public function windowStatus(?\Carbon\CarbonInterface $today = null): string
    {
        $today = ($today ?? now())->copy()->startOfDay();
        $start = $this->start_date?->copy()->startOfDay();
        $end = $this->end_date?->copy()->startOfDay();

        if (! $start && ! $end) {
            return 'current';
        }
        if ($end && $end->lt($today)) {
            return 'expired';
        }
        if ($start && $start->gt($today)) {
            return 'upcoming';
        }

        return 'current';
    }

    /** Ví dụ: "Học kỳ 1 - Năm học 2025-2026" */
    public function displayName(): string
    {
        $name = trim((string) $this->name);
        $year = $this->academicYear?->name;

        if (preg_match('/^(Học kỳ\s+\d+)\s*\(([^)]+)\)$/ui', $name, $m)) {
            return "{$m[1]} - Năm học {$m[2]}";
        }

        if ($year && $year !== '' && !str_contains($name, $year)) {
            return "{$name} - Năm học {$year}";
        }

        return $name;
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

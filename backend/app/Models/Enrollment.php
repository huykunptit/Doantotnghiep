<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Enrollment extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'course_id',
        'term_id',
        'cohort_id',
        'class_section_id',
        'order_id',
        'enrollment_source',
        'enrolled_at',
    ];

    protected $casts = [
        'enrolled_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function cohort(): BelongsTo
    {
        return $this->belongsTo(Cohort::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function classSection(): BelongsTo
    {
        return $this->belongsTo(ClassSection::class);
    }

    public function gradeEntries(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(GradeEntry::class);
    }

    /**
     * Final aggregated score = Σ(entry.score * component.weight) / Σ(component.weight).
     * Returns null if there are no graded entries yet.
     */
    public function getFinalScoreAttribute(): ?float
    {
        $entries = $this->gradeEntries()->with('component')->get();
        $entries = $entries->filter(fn ($e) => $e->score !== null && $e->component);
        if ($entries->isEmpty()) return null;

        $weightSum = 0.0;
        $weighted = 0.0;
        foreach ($entries as $entry) {
            $weight = (float) $entry->component->weight;
            $max = (float) ($entry->component->max_score ?: 10);
            // Normalize per-component score to a 10-scale, then weight it.
            $normalized = $max > 0 ? ((float) $entry->score / $max) * 10 : 0;
            $weighted += $normalized * $weight;
            $weightSum += $weight;
        }
        return $weightSum > 0 ? round($weighted / $weightSum, 2) : null;
    }
}

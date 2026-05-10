<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Program extends Model
{
    use HasFactory;

    protected $fillable = [
        'institution_id',
        'unit_id',
        'program_type_id',
        'name',
        'code',
        'duration_months',
        'is_active',
    ];

    protected $casts = [
        'duration_months' => 'integer',
        'is_active' => 'boolean',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function programType(): BelongsTo
    {
        return $this->belongsTo(ProgramType::class);
    }

    public function majors(): HasMany
    {
        return $this->hasMany(Major::class);
    }

    public function curricula(): HasMany
    {
        return $this->hasMany(Curriculum::class);
    }

    public function learningOutcomes(): HasMany
    {
        return $this->hasMany(ProgramLearningOutcome::class)->orderBy('position');
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdministrativeClass extends Model
{
    use HasFactory;

    protected $fillable = [
        'institution_id',
        'unit_id',
        'program_id',
        'major_id',
        'cohort_id',
        'advisor_id',
        'code',
        'name',
        'expected_graduation_year',
        'capacity',
        'status',
        'description',
    ];

    protected $casts = [
        'expected_graduation_year' => 'integer',
        'capacity' => 'integer',
    ];

    public function institution(): BelongsTo
    {
        return $this->belongsTo(Institution::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function program(): BelongsTo
    {
        return $this->belongsTo(Program::class);
    }

    public function major(): BelongsTo
    {
        return $this->belongsTo(Major::class);
    }

    public function cohort(): BelongsTo
    {
        return $this->belongsTo(Cohort::class);
    }

    public function advisor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'advisor_id');
    }

    public function students(): HasMany
    {
        return $this->hasMany(User::class, 'administrative_class_id');
    }

    public function classSections(): BelongsToMany
    {
        return $this->belongsToMany(
            ClassSection::class,
            'administrative_class_class_section'
        )
            ->withPivot(['term_number', 'assigned_by', 'assigned_at'])
            ->withTimestamps();
    }
}

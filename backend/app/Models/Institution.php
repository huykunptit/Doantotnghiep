<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Institution extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'code',
        'institution_type',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function units(): HasMany
    {
        return $this->hasMany(Unit::class);
    }

    public function academicYears(): HasMany
    {
        return $this->hasMany(AcademicYear::class);
    }

    public function programs(): HasMany
    {
        return $this->hasMany(Program::class);
    }

    public function cohorts(): HasMany
    {
        return $this->hasMany(Cohort::class);
    }
}

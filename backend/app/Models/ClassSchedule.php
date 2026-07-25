<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ClassSchedule extends Model
{
    protected $fillable = [
        'administrative_class_id',
        'course_id',
        'term_id',
        'lecturer_id',
        'weekday',
        'start_time',
        'end_time',
        'room',
    ];

    protected $casts = [
        'weekday' => 'integer',
    ];

    public function administrativeClass(): BelongsTo
    {
        return $this->belongsTo(AdministrativeClass::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lecturer_id');
    }
}

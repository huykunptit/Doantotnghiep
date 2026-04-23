<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScormPackage extends Model
{
    protected $fillable = [
        'lesson_id',
        'uuid',
        'version',
        'entry_url',
        'identifier',
        'title',
    ];

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}

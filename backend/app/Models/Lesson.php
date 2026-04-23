<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lesson extends Model
{
    protected $fillable = [
        'course_id',
        'section_id',
        'title',
        'type',
        'description',
        'video_url',
        'order',
        'duration',
        'video_size',
        'video_status',
        'is_preview',
    ];

    protected $casts = [
        'duration' => 'integer',
        'is_preview' => 'boolean',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class);
    }

    public function progress()
    {
        return $this->hasMany(LessonProgress::class);
    }

    public function quiz()
    {
        return $this->hasOne(Quiz::class);
    }

    public function attachments()
    {
        return $this->hasMany(LessonAttachment::class);
    }

    public function virtualClass()
    {
        return $this->hasOne(VirtualClass::class);
    }

    public function scormPackage()
    {
        return $this->hasOne(ScormPackage::class);
    }

    public function offlineSession()
    {
        return $this->hasOne(OfflineSession::class);
    }

    public function assignment()
    {
        return $this->hasOne(LessonAssignment::class);
    }
}

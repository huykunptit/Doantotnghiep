<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CourseQaReply extends Model
{
    use HasFactory;

    protected $fillable = [
        'course_qa_id',
        'user_id',
        'content',
    ];

    public function qa(): BelongsTo
    {
        return $this->belongsTo(CourseQa::class, 'course_qa_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

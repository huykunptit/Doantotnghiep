<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionAttachment extends Model
{
    protected $fillable = [
        'question_id',
        'original_name',
        'file_path',
        'file_size',
        'mime_type',
        'type',
    ];

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}

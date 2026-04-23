<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserCV extends Model
{
    protected $table = 'user_cvs';

    protected $fillable = [
        'user_id',
        'file_path',
        'file_name',
        'parsed_text',
        'skills',
    ];

    protected $casts = [
        'skills' => 'json',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

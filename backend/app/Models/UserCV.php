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
        'source',
        'parsed_text',
        'skills',
        'profile_json',
        'target_role',
        'expected_salary',
        'evaluation_json',
    ];

    protected $casts = [
        'skills' => 'json',
        'profile_json' => 'array',
        'evaluation_json' => 'array',
        'expected_salary' => 'integer',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobPosting extends Model
{
    protected $fillable = [
        'title',
        'company',
        'description',
        'required_skills',
        'location',
    ];

    protected $casts = [
        'required_skills' => 'json',
    ];
}

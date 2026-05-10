<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Specialization extends Model
{
    use HasFactory;

    protected $fillable = [
        'major_id',
        'name',
        'code',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function major(): BelongsTo
    {
        return $this->belongsTo(Major::class);
    }
}

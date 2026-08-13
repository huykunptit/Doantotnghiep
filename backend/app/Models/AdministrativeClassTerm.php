<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Maps a curriculum's abstract term_number (1..N, "kỳ thứ mấy trong CTĐT")
 * to a real, dated Term ("Học kỳ 1 - Năm học 2025-2026") for one specific
 * administrative class. Two classes on the same curriculum can start in
 * different calendar terms, so this mapping is per-class, not per-curriculum.
 */
class AdministrativeClassTerm extends Model
{
    use HasFactory;

    protected $fillable = [
        'administrative_class_id',
        'term_number',
        'term_id',
    ];

    protected $casts = [
        'term_number' => 'integer',
    ];

    public function administrativeClass(): BelongsTo
    {
        return $this->belongsTo(AdministrativeClass::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }
}

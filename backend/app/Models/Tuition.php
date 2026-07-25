<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Tuition extends Model
{
    protected $fillable = [
        'user_id',
        'term_id',
        'amount',
        'status',
        'paid_at',
        'order_id',
        'note',
    ];

    protected $casts = [
        'amount'   => 'decimal:2',
        'paid_at'  => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function term(): BelongsTo
    {
        return $this->belongsTo(Term::class);
    }

    public function isPaid(): bool
    {
        return $this->status === 'paid';
    }
}

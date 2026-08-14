<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'course_id',
        'career_path_id',
        'amount',
        'original_amount',
        'discount_amount',
        'user_voucher_id',
        'cart_items',
        'status',
        'payment_method',
        'payment_ref',
        'gateway_response',
        'paid_at',
    ];

    protected $casts = [
        'amount'           => 'integer',
        'original_amount'  => 'integer',
        'discount_amount'  => 'integer',
        'cart_items'       => 'array',
        'gateway_response' => 'array',
        'paid_at'          => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    public function careerPath(): BelongsTo
    {
        return $this->belongsTo(CareerPath::class);
    }

    public function enrollment(): HasOne
    {
        return $this->hasOne(Enrollment::class);
    }

    public function userVoucher(): BelongsTo
    {
        return $this->belongsTo(UserVoucher::class);
    }

    public function isPathOrder(): bool
    {
        return $this->career_path_id !== null;
    }
}

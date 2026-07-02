<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OfflineSessionAttendance extends Model
{
    protected $table = 'offline_session_attendances';

    protected $fillable = [
        'user_id',
        'offline_session_id',
        'status',
        'checked_in_at',
        'device_info',
        'latitude',
        'longitude',
        'distance_meters',
    ];

    protected $casts = [
        'checked_in_at'   => 'datetime',
        'latitude'        => 'float',
        'longitude'       => 'float',
        'distance_meters' => 'float',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function offlineSession(): BelongsTo
    {
        return $this->belongsTo(OfflineSession::class);
    }
}

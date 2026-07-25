<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Mail\SystemNotificationMail;
use App\Services\MailConfigService;

class Notification extends Model
{
    protected $fillable = ['user_id', 'type', 'title', 'message', 'link', 'read_at'];

    protected $casts = [
        'read_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeUnread($query)
    {
        return $query->whereNull('read_at');
    }

    public static function send(
        int $userId,
        string $type,
        string $title,
        string $message,
        ?string $link = null,
        bool $email = false,
    ): self {
        $notification = static::create([
            'user_id' => $userId,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'link' => $link,
        ]);

        if ($email) {
            static::dispatchEmail($userId, $title, $message, $link);
        }

        return $notification;
    }

    public static function dispatchEmail(
        int $userId,
        string $title,
        string $message,
        ?string $link = null,
    ): void {
        try {
            $user = User::query()->find($userId);
            if (!$user?->email) {
                return;
            }

            MailConfigService::applyFromSiteSettings();

            $actionUrl = null;
            if ($link) {
                $frontend = rtrim((string) env('FRONTEND_URL', 'http://localhost:3000'), '/');
                $actionUrl = str_starts_with($link, 'http') ? $link : $frontend . $link;
            }

            Mail::to($user->email)->send(new SystemNotificationMail($title, $message, $actionUrl));
        } catch (\Throwable $e) {
            Log::warning('Notification email failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

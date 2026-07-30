<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class SystemNotificationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public string $notificationTitle,
        public string $notificationMessage,
        public ?string $actionUrl = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->notificationTitle,
        );
    }

    public function content(): Content
    {
        return new Content(
            htmlString: $this->buildHtml(),
        );
    }

    private function buildHtml(): string
    {
        $title = e($this->notificationTitle);
        $message = nl2br(e($this->notificationMessage));
        $brand = e(config('mail.from.name', 'Eript LMS'));
        $button = '';
        if ($this->actionUrl) {
            $url = e($this->actionUrl);
            $button = "<p style=\"margin:24px 0\"><a href=\"{$url}\" style=\"background:#0B5A54;color:#fff;padding:10px 18px;border-radius:8px;text-decoration:none;font-weight:700\">Xem chi tiết</a></p>";
        }

        return <<<HTML
<!DOCTYPE html>
<html><body style="font-family:Segoe UI,Arial,sans-serif;background:#f4f7f6;padding:24px;color:#1a222d">
  <div style="max-width:560px;margin:0 auto;background:#fff;border-radius:12px;padding:28px;border:1px solid #e5e7eb">
    <p style="margin:0 0 8px;color:#0B5A54;font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase">{$brand}</p>
    <h1 style="margin:0 0 12px;font-size:20px">{$title}</h1>
    <div style="font-size:15px;line-height:1.55;color:#334155">{$message}</div>
    {$button}
    <p style="margin:28px 0 0;font-size:12px;color:#94a3b8">Email tự động từ hệ thống LMS — vui lòng không trả lời.</p>
  </div>
</body></html>
HTML;
    }
}

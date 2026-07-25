<?php

namespace App\Http\Controllers;

use App\Models\Notification;
use App\Models\User;
use App\Services\MailConfigService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\SystemNotificationMail;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $notifications = Notification::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->paginate((int) $request->query('per_page', 20));

        return response()->json($notifications);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        $count = Notification::where('user_id', $request->user()->id)
            ->unread()
            ->count();

        return response()->json(['count' => $count]);
    }

    public function markAsRead(Request $request, Notification $notification): JsonResponse
    {
        if ($notification->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $notification->update(['read_at' => now()]);

        return response()->json(['message' => 'Marked as read']);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        Notification::where('user_id', $request->user()->id)
            ->unread()
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'All notifications marked as read']);
    }

    /**
     * Admin: gửi thông báo hàng loạt (in-app + tùy chọn email).
     */
    public function broadcast(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
            'link' => ['nullable', 'string', 'max:500'],
            'send_email' => ['sometimes', 'boolean'],
            'audience' => ['required', 'string', 'in:all_students,all_instructors,all_users,role,user_ids,admin_class'],
            'role' => ['nullable', 'string', 'max:50'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer'],
            'administrative_class_id' => ['nullable', 'integer', 'exists:administrative_classes,id'],
        ]);

        $query = User::query();
        switch ($validated['audience']) {
            case 'all_students':
                $query->role('student');
                break;
            case 'all_instructors':
                $query->role('instructor');
                break;
            case 'all_users':
                break;
            case 'role':
                if (empty($validated['role'])) {
                    return response()->json(['message' => 'role is required'], 422);
                }
                $query->role($validated['role']);
                break;
            case 'user_ids':
                $ids = $validated['user_ids'] ?? [];
                if ($ids === []) {
                    return response()->json(['message' => 'user_ids is required'], 422);
                }
                $query->whereIn('id', $ids);
                break;
            case 'admin_class':
                if (empty($validated['administrative_class_id'])) {
                    return response()->json(['message' => 'administrative_class_id is required'], 422);
                }
                $query->where('administrative_class_id', $validated['administrative_class_id']);
                break;
        }

        $sendEmail = (bool) ($validated['send_email'] ?? false);
        $userIds = $query->pluck('id');
        $sent = 0;
        foreach ($userIds as $userId) {
            Notification::send(
                (int) $userId,
                'announcement',
                $validated['title'],
                $validated['message'],
                $validated['link'] ?? null,
                $sendEmail,
            );
            $sent++;
        }

        return response()->json([
            'message' => 'Đã gửi thông báo.',
            'sent' => $sent,
            'email' => $sendEmail,
            'smtp_configured' => MailConfigService::isSmtpConfigured(),
        ]);
    }

    /**
     * Admin: kiểm tra cấu hình SMTP (gửi mail thử tới email admin).
     */
    public function testEmail(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'to' => ['nullable', 'email'],
        ]);

        $to = $validated['to'] ?? $request->user()->email;
        if (!$to) {
            return response()->json(['message' => 'Không có địa chỉ email nhận.'], 422);
        }

        if (!MailConfigService::applyFromSiteSettings() && config('mail.default') === 'log') {
            return response()->json([
                'message' => 'Chưa cấu hình SMTP trong Cài đặt hệ thống. Hiện MAIL_MAILER=log — email chỉ ghi vào log.',
                'smtp_configured' => false,
                'mailer' => config('mail.default'),
            ], 422);
        }

        try {
            Mail::to($to)->send(new SystemNotificationMail(
                'Kiểm tra email Sylva LMS',
                "Đây là email thử từ admin.\nNếu bạn nhận được thư này, SMTP đã hoạt động.",
                rtrim((string) env('FRONTEND_URL', 'http://localhost:3000'), '/') . '/student/notifications',
            ));
        } catch (\Throwable $e) {
            return response()->json([
                'message' => 'Gửi thất bại: ' . $e->getMessage(),
                'smtp_configured' => MailConfigService::isSmtpConfigured(),
            ], 500);
        }

        return response()->json([
            'message' => 'Đã gửi email thử tới ' . $to,
            'smtp_configured' => true,
            'mailer' => config('mail.default'),
        ]);
    }
}

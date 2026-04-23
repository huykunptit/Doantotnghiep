<?php

namespace App\Http\Controllers\PaymentManagement;

use App\Http\Controllers\Controller;
use App\Models\Enrollment;
use App\Models\Notification;
use App\Models\Order;
use App\Services\PayOSService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PayOS\Exceptions\APIException;
use PayOS\Exceptions\WebhookException;

class PayOSController extends Controller
{
    public function __construct(private readonly PayOSService $payOSService) {}

    public function payosReturn(Request $request): JsonResponse
    {
        $orderCode = (int) $request->integer('orderCode');
        $order = Order::with('course', 'user')->find($orderCode);

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        try {
            $payment = $this->payOSService->getPaymentLink($orderCode);
            $status = strtoupper((string) ($payment['status'] ?? 'PENDING'));
            $this->syncOrderFromStatus($order, $status, $payment);

            return response()->json([
                'message' => $status === 'PAID' ? 'Payment success' : 'Payment not completed',
                'status' => strtolower($status),
                'order' => $order->fresh(['course:id,title,thumbnail,price']),
            ]);
        } catch (APIException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function confirmWebhook(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if (!$user || !$user->hasRole('admin')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $validated = $request->validate([
            'webhook_url' => ['nullable', 'url'],
        ]);

        $webhookUrl = $validated['webhook_url'] ?? config('services.payos.webhook_url');

        if (!$webhookUrl) {
            return response()->json(['message' => 'Webhook URL is not configured'], 422);
        }

        try {
            $result = $this->payOSService->confirmWebhook($webhookUrl);

            return response()->json([
                'message' => 'Webhook confirmed successfully',
                'data' => $result,
            ]);
        } catch (APIException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function payosWebhook(Request $request): JsonResponse
    {
        try {
            $verified = $this->payOSService->verifyWebhook($request->all());
        } catch (WebhookException) {
            return response()->json(['message' => 'Invalid webhook signature'], 400);
        }

        $payload = isset($verified['data']) && is_array($verified['data']) ? $verified['data'] : $verified;
        $orderCode = (int) ($payload['orderCode'] ?? 0);

        $order = Order::with('course', 'user')->find($orderCode);
        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        $statusCode = (string) ($payload['code'] ?? $verified['code'] ?? '');
        $status = $statusCode === '00' ? 'PAID' : 'FAILED';
        $this->syncOrderFromStatus($order, $status, $payload);

        return response()->json(['success' => true]);
    }

    private function syncOrderFromStatus(Order $order, string $status, array $payload): void
    {
        if ($status === 'PAID' && $order->status !== 'paid') {
            DB::transaction(function () use ($order, $payload) {
                $order->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                    'payment_ref' => (string) ($payload['reference'] ?? $payload['id'] ?? $order->payment_ref),
                    'gateway_response' => $payload,
                ]);

                Enrollment::firstOrCreate([
                    'user_id' => $order->user_id,
                    'course_id' => $order->course_id,
                ], [
                    'enrolled_at' => now(),
                ]);
            });

            Notification::send($order->user_id, 'enrollment', 'Thanh toán thành công', "Bạn đã ghi danh vào khóa học \"{$order->course->title}\".", "/learn/{$order->course_id}");
            Notification::send($order->course->user_id, 'enrollment', 'Có học viên mới', "Học viên {$order->user->name} đã ghi danh vào khóa học \"{$order->course->title}\".", "/instructor/courses/{$order->course_id}/students");
            return;
        }

        if (in_array($status, ['CANCELLED', 'FAILED', 'EXPIRED'], true) && $order->status === 'pending') {
            $order->update([
                'status' => 'failed',
                'gateway_response' => $payload,
            ]);
        }
    }
}


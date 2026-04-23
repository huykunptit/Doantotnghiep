<?php

namespace App\Http\Controllers\PaymentManagement;

use App\Http\Controllers\Controller;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Notification;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Services\PayOSService;

class OrderController extends Controller
{
    public function __construct(private readonly PayOSService $payOSService) {}

    public function index(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $orders = Order::with('course:id,title,thumbnail,price')
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate(10);

        return response()->json($orders);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if ($order->user_id !== $user->id && !$user->hasRole('admin')) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $order->load('course:id,title,thumbnail,price');

        return response()->json($order);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $validated = $request->validate([
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'payment_method' => ['nullable', 'string', 'in:payos,momo,zalopay,bank_transfer'],
        ]);

        $course = Course::findOrFail($validated['course_id']);

        if ($course->status !== 'published') {
            return response()->json(['message' => 'Course is not available'], 422);
        }

        // Check already enrolled
        $alreadyEnrolled = Enrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)->exists();

        if ($alreadyEnrolled) {
            return response()->json(['message' => 'Already enrolled in this course'], 422);
        }

        $paymentMethod = $validated['payment_method'] ?? 'payos';

        // Paid order: already completed — block new order
        $paidOrder = Order::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->where('status', 'paid')
            ->first();

        if ($paidOrder) {
            return response()->json([
                'message' => 'Order already paid',
                'order'   => $paidOrder,
            ]);
        }

        // Pending order: regenerate payment link instead of creating a duplicate
        $pendingOrder = Order::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->where('status', 'pending')
            ->orderByDesc('id')
            ->first();

        if ($pendingOrder && $paymentMethod === 'payos' && $course->price > 0) {
            $pendingOrder->update([
                'amount' => $course->price,
                'payment_method' => 'payos',
            ]);
            $pendingOrder->load(['course:id,title,thumbnail,price', 'user:id,name,email']);

            try {
                $paymentLink = $this->payOSService->createPaymentLink($pendingOrder);
            } catch (\Throwable $e) {
                return response()->json(['message' => 'PayOS error: ' . $e->getMessage()], 502);
            }

            if (!$paymentLink) {
                return response()->json(['message' => 'PayOS is not configured'], 422);
            }

            $pendingOrder->update([
                'payment_ref' => $paymentLink['payment_link_id'] ?? null,
                'gateway_response' => $paymentLink['raw'] ?? null,
            ]);

            return response()->json([
                'message' => 'Reusing pending order',
                'order' => $pendingOrder->fresh(['course:id,title,thumbnail,price']),
                'payment_url' => $paymentLink['checkout_url'] ?? null,
            ]);
        }

        // Free course: auto-enroll
        if ($course->price <= 0) {
            DB::transaction(function () use ($user, $course) {
                Order::create([
                    'user_id'     => $user->id,
                    'course_id'   => $course->id,
                    'amount'      => 0,
                    'status'      => 'paid',
                    'payment_method' => 'free',
                    'payment_ref' => 'FREE_' . strtoupper(Str::random(8)),
                    'paid_at'     => now(),
                ]);

                Enrollment::firstOrCreate([
                    'user_id'   => $user->id,
                    'course_id' => $course->id,
                ], [
                    'enrolled_at' => now(),
                ]);
            });

            // Notify student
            Notification::send($user->id, 'enrollment', 'Ghi danh thành công', "Bạn đã ghi danh vào khóa học \"{$course->title}\".", "/learn/{$course->id}");
            // Notify instructor
            Notification::send($course->user_id, 'enrollment', 'Có học viên mới', "Học viên {$user->name} đã ghi danh vào khóa học \"{$course->title}\".", "/instructor/courses/{$course->id}/students");

            return response()->json(['message' => 'Enrolled in free course', 'enrolled' => true]);
        }

        // Create pending order
        $order = Order::create([
            'user_id'   => $user->id,
            'course_id' => $course->id,
            'amount'    => $course->price,
            'status'    => 'pending',
            'payment_method' => $paymentMethod,
        ]);

        $order->load(['course:id,title,thumbnail,price', 'user:id,name,email']);

        if ($paymentMethod === 'payos') {
            try {
                $paymentLink = $this->payOSService->createPaymentLink($order);
            } catch (\Throwable $e) {
                return response()->json(['message' => 'PayOS error: ' . $e->getMessage()], 502);
            }

            if (!$paymentLink) {
                return response()->json(['message' => 'PayOS is not configured'], 422);
            }

            $order->update([
                'payment_ref' => $paymentLink['payment_link_id'] ?? null,
                'gateway_response' => $paymentLink['raw'] ?? null,
            ]);

            return response()->json([
                'message' => 'Order created',
                'order' => $order->fresh(['course:id,title,thumbnail,price']),
                'payment_url' => $paymentLink['checkout_url'] ?? null,
            ], 201);
        }

        // Sandbox fallback for gateways that are not yet integrated.
        if (in_array($paymentMethod, ['momo', 'zalopay', 'bank_transfer'], true)) {
            DB::transaction(function () use ($order, $paymentMethod) {
                $order->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                    'payment_ref' => strtoupper($paymentMethod) . '_' . strtoupper(Str::random(8)),
                    'gateway_response' => [
                        'message' => 'Mock gateway auto-approved in sandbox mode.',
                        'gateway' => $paymentMethod,
                    ],
                ]);

                Enrollment::firstOrCreate([
                    'user_id' => $order->user_id,
                    'course_id' => $order->course_id,
                ], [
                    'enrolled_at' => now(),
                ]);
            });

            return response()->json([
                'message' => 'Payment success (sandbox)',
                'gateway' => $paymentMethod,
                'order' => $order->fresh(['course:id,title,thumbnail,price']),
                'enrolled' => true,
            ], 201);
        }

        return response()->json([
            'message' => 'Unsupported payment method',
        ], 422);
    }
}

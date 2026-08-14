<?php

namespace App\Http\Controllers\PaymentManagement;

use App\Http\Controllers\Controller;
use App\Models\CareerPath;
use App\Models\Notification;
use App\Models\Order;
use App\Models\UserCareerPath;
use App\Services\CareerPathFulfillmentService;
use App\Services\CheckoutService;
use App\Services\PayOSService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    public function __construct(
        private readonly PayOSService $payOSService,
        private readonly CareerPathFulfillmentService $pathFulfillment,
        private readonly CheckoutService $checkout,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $orders = Order::with([
            'course:id,title,thumbnail,price',
            'careerPath:id,title,slug,cover_url,price',
        ])
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->paginate(10);

        return response()->json($orders);
    }

    public function show(Request $request, Order $order): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        if ($order->user_id !== $user->id && !\App\Support\Authorize::isAdmin($user)) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $order->load([
            'course:id,title,thumbnail,price',
            'careerPath:id,title,slug,cover_url,price',
        ]);

        return response()->json($order);
    }

    public function quote(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $validated = $request->validate([
            'course_ids' => ['required', 'array', 'min:1'],
            'course_ids.*' => ['integer', 'exists:courses,id'],
            'user_voucher_id' => ['nullable', 'integer', 'exists:user_vouchers,id'],
        ]);

        try {
            $quote = $this->checkout->quote(
                $user,
                $validated['course_ids'],
                $validated['user_voucher_id'] ?? null,
            );
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        if ($quote['suggestions']) {
            $quote['suggestions'][0]['recommended'] = true;
        }

        return response()->json($quote);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $validated = $request->validate([
            'course_id' => ['nullable', 'integer', 'exists:courses,id', 'required_without_all:career_path_id,course_ids'],
            'course_ids' => ['nullable', 'array', 'min:1', 'required_without_all:course_id,career_path_id'],
            'course_ids.*' => ['integer', 'exists:courses,id'],
            'career_path_id' => ['nullable', 'integer', 'exists:career_paths,id', 'required_without_all:course_id,course_ids'],
            'payment_method' => ['nullable', 'string', 'in:payos,momo,zalopay,bank_transfer'],
            'user_voucher_id' => ['nullable', 'integer', 'exists:user_vouchers,id'],
        ]);

        if (!empty($validated['career_path_id']) && (!empty($validated['course_id']) || !empty($validated['course_ids']))) {
            return response()->json(['message' => 'Provide either course_id, course_ids or career_path_id'], 422);
        }

        if (!empty($validated['career_path_id'])) {
            return $this->storePathOrder($user, (int) $validated['career_path_id'], $validated['payment_method'] ?? 'payos');
        }

        $courseIds = !empty($validated['course_ids'])
            ? array_values(array_unique(array_map('intval', $validated['course_ids'])))
            : [(int) $validated['course_id']];

        return $this->storeCourseCart($user, $courseIds, $validated['payment_method'] ?? 'payos', $validated['user_voucher_id'] ?? null);
    }

    private function storeCourseCart($user, array $courseIds, string $paymentMethod, ?int $userVoucherId): JsonResponse
    {
        try {
            $quote = $this->checkout->quote($user, $courseIds, $userVoucherId);
        } catch (\RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $items = $quote['items'];
        $firstId = (int) ($items[0]['id'] ?? 0);

        $order = null;
        if ($quote['total'] <= 0) {
            DB::transaction(function () use ($user, $items, $quote, $firstId, $userVoucherId, &$order) {
                $order = Order::create([
                    'user_id' => $user->id,
                    'course_id' => $firstId,
                    'amount' => 0,
                    'original_amount' => $quote['subtotal'],
                    'discount_amount' => $quote['discount'],
                    'user_voucher_id' => $quote['applied']['id'] ?? $userVoucherId,
                    'cart_items' => $items,
                    'status' => 'paid',
                    'payment_method' => 'free',
                    'payment_ref' => 'FREE_' . strtoupper(Str::random(8)),
                    'paid_at' => now(),
                ]);
                $this->checkout->fulfill($order);
            });

            $titles = collect($items)->pluck('title')->implode(', ');
            Notification::send($user->id, 'enrollment', 'Ghi danh thành công', "Bạn đã ghi danh: {$titles}.", '/student/courses');

            return response()->json([
                'message' => 'Enrolled in free course',
                'enrolled' => true,
                'order' => $order?->fresh(['course:id,title,thumbnail,price']),
                'quote' => $quote,
            ]);
        }

        $order = Order::create([
            'user_id' => $user->id,
            'course_id' => $firstId,
            'amount' => $quote['total'],
            'original_amount' => $quote['subtotal'],
            'discount_amount' => $quote['discount'],
            'user_voucher_id' => $quote['applied']['id'] ?? null,
            'cart_items' => $items,
            'status' => 'pending',
            'payment_method' => $paymentMethod,
        ]);

        $response = $this->finalizeNewOrder($order, $paymentMethod, ['course:id,title,thumbnail,price']);
        $payload = $response->getData(true);
        $payload['quote'] = $quote;

        return response()->json($payload, $response->status());
    }

    private function storePathOrder($user, int $pathId, string $paymentMethod): JsonResponse
    {
        $path = CareerPath::with(['pathCourses' => fn ($q) => $q->where('is_required', true)])
            ->findOrFail($pathId);

        if ($path->status !== 'published') {
            return response()->json(['message' => 'Path is not available'], 422);
        }

        if ($path->pathCourses->isEmpty()) {
            return response()->json(['message' => 'Path has no required courses'], 422);
        }

        $purchased = UserCareerPath::where('user_id', $user->id)
            ->where('career_path_id', $path->id)
            ->whereIn('status', ['purchased', 'completed'])
            ->exists();

        if ($purchased) {
            return response()->json(['message' => 'Already purchased this path', 'enrolled' => true]);
        }

        $paidOrder = Order::where('user_id', $user->id)
            ->where('career_path_id', $path->id)
            ->where('status', 'paid')
            ->first();

        if ($paidOrder) {
            return response()->json([
                'message' => 'Order already paid',
                'order' => $paidOrder->load('careerPath:id,title,slug,cover_url,price'),
                'enrolled' => true,
            ]);
        }

        $pendingOrder = Order::where('user_id', $user->id)
            ->where('career_path_id', $path->id)
            ->where('status', 'pending')
            ->orderByDesc('id')
            ->first();

        if ($pendingOrder && $paymentMethod === 'payos' && $path->price > 0) {
            return $this->reusePayosLink($pendingOrder, $path->price, [
                'careerPath:id,title,slug,cover_url,price',
            ]);
        }

        if ($path->price <= 0) {
            $order = null;
            DB::transaction(function () use ($user, $path, &$order) {
                $order = Order::create([
                    'user_id' => $user->id,
                    'career_path_id' => $path->id,
                    'course_id' => null,
                    'amount' => 0,
                    'status' => 'paid',
                    'payment_method' => 'free',
                    'payment_ref' => 'FREE_PATH_' . strtoupper(Str::random(8)),
                    'paid_at' => now(),
                ]);
                $this->pathFulfillment->fulfillPaidOrder($order);
            });

            return response()->json([
                'message' => 'Enrolled in free path',
                'enrolled' => true,
                'order' => $order?->fresh(['careerPath:id,title,slug,cover_url,price']),
            ]);
        }

        $order = Order::create([
            'user_id' => $user->id,
            'career_path_id' => $path->id,
            'course_id' => null,
            'amount' => $path->price,
            'status' => 'pending',
            'payment_method' => $paymentMethod,
        ]);

        return $this->finalizeNewOrder($order, $paymentMethod, [
            'careerPath:id,title,slug,cover_url,price',
        ], true);
    }

    private function reusePayosLink(Order $pendingOrder, int $amount, array $with): JsonResponse
    {
        $pendingOrder->update([
            'amount' => $amount,
            'payment_method' => 'payos',
        ]);
        $pendingOrder->load(array_merge($with, ['user:id,name,email']));

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
            'order' => $pendingOrder->fresh($with),
            'payment_url' => $paymentLink['checkout_url'] ?? null,
            'payment_data' => $paymentLink['raw'] ?? null,
        ]);
    }

    private function finalizeNewOrder(Order $order, string $paymentMethod, array $with, bool $isPath = false): JsonResponse
    {
        $order->load(array_merge($with, ['user:id,name,email']));

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
                'order' => $order->fresh($with),
                'payment_url' => $paymentLink['checkout_url'] ?? null,
            ], 201);
        }

        if (in_array($paymentMethod, ['momo', 'zalopay', 'bank_transfer'], true)) {
            DB::transaction(function () use ($order, $paymentMethod, $isPath) {
                $order->update([
                    'status' => 'paid',
                    'paid_at' => now(),
                    'payment_ref' => strtoupper($paymentMethod) . '_' . strtoupper(Str::random(8)),
                    'gateway_response' => [
                        'message' => 'Mock gateway auto-approved in sandbox mode.',
                        'gateway' => $paymentMethod,
                    ],
                ]);

                if ($isPath) {
                    $this->pathFulfillment->fulfillPaidOrder($order->fresh());
                } else {
                    $this->checkout->fulfill($order->fresh());
                }
            });

            return response()->json([
                'message' => 'Payment success (sandbox)',
                'gateway' => $paymentMethod,
                'order' => $order->fresh($with),
                'enrolled' => true,
            ], 201);
        }

        return response()->json(['message' => 'Unsupported payment method'], 422);
    }
}

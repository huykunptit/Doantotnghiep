<?php

namespace App\Http\Controllers\PaymentManagement;

use App\Http\Controllers\Controller;
use App\Models\CareerPath;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Notification;
use App\Models\Order;
use App\Models\UserCareerPath;
use App\Services\CareerPathFulfillmentService;
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

    public function store(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = $request->user();

        $validated = $request->validate([
            'course_id' => ['nullable', 'integer', 'exists:courses,id', 'required_without:career_path_id'],
            'career_path_id' => ['nullable', 'integer', 'exists:career_paths,id', 'required_without:course_id'],
            'payment_method' => ['nullable', 'string', 'in:payos,momo,zalopay,bank_transfer'],
        ]);

        if (!empty($validated['career_path_id']) && !empty($validated['course_id'])) {
            return response()->json(['message' => 'Provide either course_id or career_path_id, not both'], 422);
        }

        if (!empty($validated['career_path_id'])) {
            return $this->storePathOrder($user, (int) $validated['career_path_id'], $validated['payment_method'] ?? 'payos');
        }

        return $this->storeCourseOrder($user, (int) $validated['course_id'], $validated['payment_method'] ?? 'payos');
    }

    private function storeCourseOrder($user, int $courseId, string $paymentMethod): JsonResponse
    {
        $course = Course::findOrFail($courseId);

        if ($course->status !== 'published') {
            return response()->json(['message' => 'Course is not available'], 422);
        }

        if ($course->course_mode === 'core') {
            return response()->json([
                'message' => 'Core curriculum courses cannot be purchased. Enrollment is handled by academic affairs.',
            ], 422);
        }

        $alreadyEnrolled = Enrollment::where('user_id', $user->id)
            ->where('course_id', $course->id)->exists();

        if ($alreadyEnrolled) {
            return response()->json(['message' => 'Already enrolled in this course'], 422);
        }

        $paidOrder = Order::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->whereNull('career_path_id')
            ->where('status', 'paid')
            ->first();

        if ($paidOrder) {
            return response()->json([
                'message' => 'Order already paid',
                'order' => $paidOrder,
            ]);
        }

        $pendingOrder = Order::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->whereNull('career_path_id')
            ->where('status', 'pending')
            ->orderByDesc('id')
            ->first();

        if ($pendingOrder && $paymentMethod === 'payos' && $course->price > 0) {
            return $this->reusePayosLink($pendingOrder, $course->price, [
                'course:id,title,thumbnail,price',
            ]);
        }

        if ($course->price <= 0) {
            DB::transaction(function () use ($user, $course) {
                $order = Order::create([
                    'user_id' => $user->id,
                    'course_id' => $course->id,
                    'amount' => 0,
                    'status' => 'paid',
                    'payment_method' => 'free',
                    'payment_ref' => 'FREE_' . strtoupper(Str::random(8)),
                    'paid_at' => now(),
                ]);

                Enrollment::firstOrCreate([
                    'user_id' => $user->id,
                    'course_id' => $course->id,
                ], [
                    'enrolled_at' => now(),
                    'order_id' => $order->id,
                    'enrollment_source' => 'marketplace',
                ]);
            });

            Notification::send($user->id, 'enrollment', 'Ghi danh thành công', "Bạn đã ghi danh vào khóa học \"{$course->title}\".", "/learn/{$course->id}");
            Notification::send($course->user_id, 'enrollment', 'Có học viên mới', "Học viên {$user->name} đã ghi danh vào khóa học \"{$course->title}\".", "/instructor/courses/{$course->id}/students");

            return response()->json(['message' => 'Enrolled in free course', 'enrolled' => true]);
        }

        $order = Order::create([
            'user_id' => $user->id,
            'course_id' => $course->id,
            'amount' => $course->price,
            'status' => 'pending',
            'payment_method' => $paymentMethod,
        ]);

        return $this->finalizeNewOrder($order, $paymentMethod, ['course:id,title,thumbnail,price']);
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
                    Enrollment::firstOrCreate([
                        'user_id' => $order->user_id,
                        'course_id' => $order->course_id,
                    ], [
                        'enrolled_at' => now(),
                        'order_id' => $order->id,
                        'enrollment_source' => 'marketplace',
                    ]);
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

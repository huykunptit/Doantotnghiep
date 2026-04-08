<?php

namespace App\Http\Controllers;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class OrderController extends Controller
{
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
            'payment_method' => ['nullable', 'string', 'in:vnpay,momo,zalopay,bank_transfer'],
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

        // Check pending/paid order
        $existingOrder = Order::where('user_id', $user->id)
            ->where('course_id', $course->id)
            ->whereIn('status', ['pending', 'paid'])
            ->first();

        if ($existingOrder) {
            return response()->json([
                'message' => 'Order already exists',
                'order'   => $existingOrder,
            ]);
        }

        // Free course: auto-enroll
        if ($course->price <= 0) {
            DB::transaction(function () use ($user, $course) {
                $order = Order::create([
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

            return response()->json(['message' => 'Enrolled in free course', 'enrolled' => true]);
        }

        // Create pending order
        $paymentMethod = $validated['payment_method'] ?? 'vnpay';
        $order = Order::create([
            'user_id'   => $user->id,
            'course_id' => $course->id,
            'amount'    => $course->price,
            'status'    => 'pending',
            'payment_method' => $paymentMethod,
        ]);

        $order->load('course:id,title,thumbnail,price');

        // Sandbox fallback for gateways that are not yet integrated.
        if (in_array($paymentMethod, ['momo', 'zalopay', 'bank_transfer'], true)) {
            DB::transaction(function () use ($order, $paymentMethod) {
                $order->update([
                    'status'      => 'paid',
                    'paid_at'     => now(),
                    'payment_ref' => strtoupper($paymentMethod) . '_' . strtoupper(Str::random(8)),
                    'gateway_response' => [
                        'message' => 'Mock gateway auto-approved in sandbox mode.',
                        'gateway' => $paymentMethod,
                    ],
                ]);

                Enrollment::firstOrCreate([
                    'user_id'   => $order->user_id,
                    'course_id' => $order->course_id,
                ], [
                    'enrolled_at' => now(),
                ]);
            });

            return response()->json([
                'message' => 'Payment success (sandbox)',
                'gateway' => $paymentMethod,
                'order'   => $order->fresh(['course:id,title,thumbnail,price']),
                'enrolled' => true,
            ], 201);
        }

        return response()->json([
            'message'      => 'Order created',
            'order'        => $order,
            'payment_url'  => $this->buildVnpayUrl($order, $request),
        ], 201);
    }

    public function vnpayReturn(Request $request): JsonResponse
    {
        $vnpData = $request->all();

        if (!$this->verifyVnpaySignature($vnpData)) {
            return response()->json(['message' => 'Invalid signature'], 400);
        }

        $responseCode = $vnpData['vnp_ResponseCode'] ?? '';
        $txnRef = $vnpData['vnp_TxnRef'] ?? '';

        $order = Order::where('id', $txnRef)->first();

        if (!$order) {
            return response()->json(['message' => 'Order not found'], 404);
        }

        if ($responseCode === '00' && $order->status === 'pending') {
            DB::transaction(function () use ($order, $vnpData) {
                $order->update([
                    'status'      => 'paid',
                    'payment_ref' => $vnpData['vnp_TransactionNo'] ?? null,
                ]);

                Enrollment::firstOrCreate([
                    'user_id'   => $order->user_id,
                    'course_id' => $order->course_id,
                ], [
                    'enrolled_at' => now(),
                ]);
            });

            return response()->json(['message' => 'Payment success', 'order' => $order->fresh()]);
        }

        $order->update(['status' => 'failed']);

        return response()->json(['message' => 'Payment failed', 'code' => $responseCode], 422);
    }

    public function vnpayIpn(Request $request): \Illuminate\Http\Response
    {
        $vnpData = $request->all();

        if (!$this->verifyVnpaySignature($vnpData)) {
            return response('{"RspCode":"97","Message":"Invalid Checksum"}', 200)
                ->header('Content-Type', 'application/json');
        }

        $txnRef = $vnpData['vnp_TxnRef'] ?? '';
        $order = Order::find($txnRef);

        if (!$order) {
            return response('{"RspCode":"01","Message":"Order not found"}', 200)
                ->header('Content-Type', 'application/json');
        }

        if ($order->status === 'paid') {
            return response('{"RspCode":"02","Message":"Order already confirmed"}', 200)
                ->header('Content-Type', 'application/json');
        }

        $responseCode = $vnpData['vnp_ResponseCode'] ?? '';

        if ($responseCode === '00') {
            DB::transaction(function () use ($order, $vnpData) {
                $order->update([
                    'status'      => 'paid',
                    'payment_ref' => $vnpData['vnp_TransactionNo'] ?? null,
                ]);

                Enrollment::firstOrCreate([
                    'user_id'   => $order->user_id,
                    'course_id' => $order->course_id,
                ], [
                    'enrolled_at' => now(),
                ]);
            });
        } else {
            $order->update(['status' => 'failed']);
        }

        return response('{"RspCode":"00","Message":"Confirm Success"}', 200)
            ->header('Content-Type', 'application/json');
    }

    private function buildVnpayUrl(Order $order, Request $request): ?string
    {
        $vnpUrl     = config('services.vnpay.url');
        $vnpTmnCode = config('services.vnpay.tmn_code');
        $vnpHashSecret = config('services.vnpay.hash_secret');
        $vnpReturnUrl  = config('services.vnpay.return_url');

        if (!$vnpTmnCode || !$vnpHashSecret) {
            return null;
        }

        $vnpParams = [
            'vnp_Version'    => '2.1.0',
            'vnp_Command'    => 'pay',
            'vnp_TmnCode'    => $vnpTmnCode,
            'vnp_Amount'     => (int) ($order->amount * 100),
            'vnp_CurrCode'   => 'VND',
            'vnp_TxnRef'     => (string) $order->id,
            'vnp_OrderInfo'  => 'Payment for course ' . $order->course_id,
            'vnp_OrderType'  => 'billpayment',
            'vnp_Locale'     => 'vn',
            'vnp_ReturnUrl'  => $vnpReturnUrl,
            'vnp_IpAddr'     => $request->ip(),
            'vnp_CreateDate' => now()->format('YmdHis'),
            'vnp_ExpireDate' => now()->addHours(1)->format('YmdHis'),
        ];

        ksort($vnpParams);
        $queryString = http_build_query($vnpParams);
        $hmac = hash_hmac('sha512', $queryString, $vnpHashSecret);

        return $vnpUrl . '?' . $queryString . '&vnp_SecureHash=' . $hmac;
    }

    private function verifyVnpaySignature(array $data): bool
    {
        $vnpHashSecret = config('services.vnpay.hash_secret');

        if (!$vnpHashSecret) {
            return false;
        }

        $secureHash = $data['vnp_SecureHash'] ?? '';
        unset($data['vnp_SecureHash'], $data['vnp_SecureHashType']);
        ksort($data);

        $hashData = http_build_query($data);
        $hash = hash_hmac('sha512', $hashData, $vnpHashSecret);

        return hash_equals($hash, $secureHash);
    }
}

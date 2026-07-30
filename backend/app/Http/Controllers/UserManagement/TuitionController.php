<?php

namespace App\Http\Controllers\UserManagement;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Tuition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Học phí sinh viên (mức cố định theo kỳ).
 *  GET  /me/tuition          — danh sách học phí các kỳ của SV
 *  POST /me/tuition/{tuition}/pay — thanh toán (demo: đánh dấu đã đóng)
 */
class TuitionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthorized'], 401);

        $rows = Tuition::query()
            ->where('user_id', $user->id)
            ->with(['term.academicYear'])
            ->orderBy('term_id')
            ->get()
            ->map(function (Tuition $t) {
                $term = $t->term;
                $label = $term?->displayName() ?? 'Học phí';

                return [
                    'id'      => $t->id,
                    'term'    => $term ? [
                        'id' => $term->id,
                        'name' => $term->name,
                        'code' => $term->code,
                        'label' => $label,
                        'academic_year' => $term->academicYear?->name,
                    ] : null,
                    'amount'  => (float) $t->amount,
                    'status'  => $t->status,
                    'paid_at' => $t->paid_at?->toIso8601String(),
                    'note'    => $t->note,
                ];
            });

        $totalDue = $rows->where('status', 'unpaid')->sum('amount');
        $totalPaid = $rows->where('status', 'paid')->sum('amount');

        $tuitionHistory = $rows
            ->where('status', 'paid')
            ->map(fn (array $row) => [
                'id' => 'tuition-' . $row['id'],
                'type' => 'tuition',
                'title' => $row['term']['label'] ?? $row['term']['name'] ?? 'Học phí',
                'description' => null,
                'amount' => $row['amount'],
                'status' => 'paid',
                'payment_method' => 'bank_transfer',
                'payment_ref' => null,
                'paid_at' => $row['paid_at'],
            ]);

        $marketplaceHistory = Order::query()
            ->where('user_id', $user->id)
            ->whereIn('status', ['paid', 'completed'])
            ->with([
                'course:id,title,course_mode',
                'careerPath:id,title',
            ])
            ->latest('paid_at')
            ->get()
            ->map(fn (Order $order) => [
                'id' => 'order-' . $order->id,
                'type' => $order->career_path_id ? 'career_path' : 'extension_course',
                'title' => $order->careerPath?->title ?? $order->course?->title ?? ('Đơn hàng #' . $order->id),
                'description' => $order->career_path_id
                    ? 'Lộ trình nghề nghiệp'
                    : 'Khóa học ngoài chương trình đào tạo',
                'amount' => (float) $order->amount,
                'status' => $order->status,
                'payment_method' => $order->payment_method,
                'payment_ref' => $order->payment_ref,
                'paid_at' => $order->paid_at?->toIso8601String(),
            ]);

        $paymentHistory = $tuitionHistory
            ->concat($marketplaceHistory)
            ->sortByDesc('paid_at')
            ->values();

        return response()->json([
            'items'      => $rows->values(),
            'total_due'  => $totalDue,
            'total_paid' => $totalPaid,
            'payment_history' => $paymentHistory,
        ]);
    }

    public function pay(Request $request, Tuition $tuition): JsonResponse
    {
        $user = $request->user();
        if (!$user) return response()->json(['message' => 'Unauthorized'], 401);

        if ($tuition->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        if ($tuition->isPaid()) {
            return response()->json(['message' => 'Học phí kỳ này đã được thanh toán.'], 422);
        }

        // Demo thanh toán: đánh dấu đã đóng. Có thể thay bằng luồng tạo order PayOS.
        $tuition->update([
            'status'  => 'paid',
            'paid_at' => now(),
        ]);

        return response()->json([
            'message' => 'Thanh toán học phí thành công.',
            'tuition' => [
                'id'      => $tuition->id,
                'status'  => $tuition->status,
                'paid_at' => $tuition->paid_at?->toIso8601String(),
            ],
        ]);
    }
}

<?php

namespace App\Http\Controllers\UserManagement;

use App\Http\Controllers\Controller;
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
            ->with('term:id,name,code')
            ->orderBy('term_id')
            ->get()
            ->map(fn (Tuition $t) => [
                'id'      => $t->id,
                'term'    => $t->term,
                'amount'  => (float) $t->amount,
                'status'  => $t->status,
                'paid_at' => $t->paid_at?->toIso8601String(),
                'note'    => $t->note,
            ]);

        $totalDue = $rows->where('status', 'unpaid')->sum('amount');
        $totalPaid = $rows->where('status', 'paid')->sum('amount');

        return response()->json([
            'items'      => $rows->values(),
            'total_due'  => $totalDue,
            'total_paid' => $totalPaid,
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

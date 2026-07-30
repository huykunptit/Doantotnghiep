<?php

namespace App\Http\Controllers\UserManagement;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Tuition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Học phí sinh viên (mức cố định theo kỳ).
 *  Student: GET /me/tuition, POST /me/tuition/{tuition}/pay
 *  Admin:   CRUD /admin/tuitions*
 */
class TuitionController extends Controller
{
    public function adminIndex(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'in:unpaid,paid'],
            'term_id' => ['nullable', 'integer', 'exists:terms,id'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = Tuition::query()
            ->with([
                'user:id,name,email,student_code',
                'term.academicYear',
            ])
            ->latest('id');

        if (!empty($validated['status'])) {
            $query->where('status', $validated['status']);
        }
        if (!empty($validated['term_id'])) {
            $query->where('term_id', $validated['term_id']);
        }
        if (!empty($validated['search'])) {
            $q = $validated['search'];
            $query->whereHas('user', function ($userQuery) use ($q) {
                $userQuery->where('name', 'like', "%{$q}%")
                    ->orWhere('email', 'like', "%{$q}%")
                    ->orWhere('student_code', 'like', "%{$q}%");
            });
        }

        $paginator = $query->paginate($validated['per_page'] ?? 20);

        $paginator->getCollection()->transform(function (Tuition $t) {
            $term = $t->term;
            return [
                'id' => $t->id,
                'user' => $t->user,
                'term' => $term ? [
                    'id' => $term->id,
                    'name' => $term->name,
                    'code' => $term->code,
                    'label' => $term->displayName(),
                    'academic_year' => $term->academicYear?->name,
                ] : null,
                'amount' => (float) $t->amount,
                'status' => $t->status,
                'paid_at' => $t->paid_at?->toIso8601String(),
                'note' => $t->note,
                'created_at' => $t->created_at?->toIso8601String(),
            ];
        });

        return response()->json($paginator);
    }

    public function adminStore(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'term_id' => ['required', 'integer', 'exists:terms,id'],
            'amount' => ['required', 'numeric', 'min:0'],
            'status' => ['nullable', 'in:unpaid,paid'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $status = $validated['status'] ?? 'unpaid';
        $tuition = Tuition::query()->updateOrCreate(
            [
                'user_id' => $validated['user_id'],
                'term_id' => $validated['term_id'],
            ],
            [
                'amount' => $validated['amount'],
                'status' => $status,
                'paid_at' => $status === 'paid' ? now() : null,
                'note' => $validated['note'] ?? null,
            ]
        );

        $tuition->load(['user:id,name,email,student_code', 'term.academicYear']);

        return response()->json([
            'message' => 'Đã tạo/cập nhật học phí.',
            'tuition' => $tuition,
        ], 201);
    }

    public function adminUpdate(Request $request, Tuition $tuition): JsonResponse
    {
        $validated = $request->validate([
            'amount' => ['sometimes', 'numeric', 'min:0'],
            'status' => ['sometimes', 'in:unpaid,paid'],
            'note' => ['nullable', 'string', 'max:255'],
            'term_id' => ['sometimes', 'integer', 'exists:terms,id'],
        ]);

        if (array_key_exists('status', $validated)) {
            if ($validated['status'] === 'paid' && !$tuition->isPaid()) {
                $validated['paid_at'] = now();
            }
            if ($validated['status'] === 'unpaid') {
                $validated['paid_at'] = null;
            }
        }

        $tuition->update($validated);
        $tuition->load(['user:id,name,email,student_code', 'term.academicYear']);

        return response()->json([
            'message' => 'Đã cập nhật học phí.',
            'tuition' => $tuition,
        ]);
    }

    public function adminDestroy(Tuition $tuition): JsonResponse
    {
        $tuition->delete();
        return response()->json(['message' => 'Đã xóa học phí.']);
    }

    public function adminMarkPaid(Tuition $tuition): JsonResponse
    {
        if ($tuition->isPaid()) {
            return response()->json(['message' => 'Học phí đã được thanh toán.'], 422);
        }

        $tuition->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        return response()->json([
            'message' => 'Đã xác nhận thanh toán học phí.',
            'tuition' => $tuition->fresh(['user:id,name,email,student_code', 'term.academicYear']),
        ]);
    }

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

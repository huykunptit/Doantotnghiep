<?php

namespace App\Services;

use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Order;
use App\Models\User;
use App\Models\UserVoucher;

class CheckoutService
{
    /**
     * @param  list<int>  $courseIds
     * @return array{
     *   items: list<array<string, mixed>>,
     *   subtotal: int,
     *   discount: int,
     *   total: int,
     *   applied: ?array<string, mixed>,
     *   suggestions: list<array<string, mixed>>
     * }
     */
    public function quote(User $user, array $courseIds, ?int $userVoucherId = null): array
    {
        $items = $this->resolveItems($user, $courseIds);
        $suggestions = $this->suggestVouchers($user, $items);

        $applied = null;
        $discounted = $items;
        if ($userVoucherId) {
            $uv = $this->usableVoucher($user, $userVoucherId);
            if (! $uv) {
                throw new \RuntimeException('Voucher không dùng được hoặc đang gắn đơn chưa thanh toán.');
            }
            $discounted = $this->applyVoucher($items, $uv);
            $applied = $this->serializeUserVoucher($uv, $this->totalDiscount($items, $discounted));
        }

        $subtotal = (int) collect($items)->sum('price');
        $total = (int) collect($discounted)->sum('payable');

        return [
            'items' => $discounted,
            'subtotal' => $subtotal,
            'discount' => max(0, $subtotal - $total),
            'total' => $total,
            'applied' => $applied,
            'suggestions' => $suggestions,
        ];
    }

    /**
     * @param  list<int>  $courseIds
     * @return list<array<string, mixed>>
     */
    public function resolveItems(User $user, array $courseIds): array
    {
        $ids = collect($courseIds)->map(fn ($id) => (int) $id)->unique()->values();
        $courses = Course::query()->whereIn('id', $ids)->get()->keyBy('id');

        $items = [];
        foreach ($ids as $id) {
            $course = $courses->get($id);
            if (! $course) {
                throw new \RuntimeException("Course {$id} not found");
            }
            if ($course->status !== 'published') {
                throw new \RuntimeException("Khóa \"{$course->title}\" chưa mở bán.");
            }
            if ($course->course_mode === 'core' && (int) $course->price > 0) {
                throw new \RuntimeException("Khóa \"{$course->title}\" thuộc CTĐT, không mua trên web.");
            }
            if (Enrollment::where('user_id', $user->id)->where('course_id', $course->id)->exists()) {
                throw new \RuntimeException("Bạn đã ghi danh khóa \"{$course->title}\".");
            }

            $price = max(0, (int) $course->price);
            $items[] = [
                'id' => $course->id,
                'title' => $course->title,
                'thumbnail' => $course->thumbnail,
                'price' => $price,
                'discount' => 0,
                'payable' => $price,
            ];
        }

        return $items;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    public function suggestVouchers(User $user, array $items): array
    {
        $locked = Order::query()
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->whereNotNull('user_voucher_id')
            ->pluck('user_voucher_id');

        $vouchers = UserVoucher::query()
            ->with('voucher')
            ->where('user_id', $user->id)
            ->where('status', 'unused')
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->when($locked->isNotEmpty(), fn ($q) => $q->whereNotIn('id', $locked))
            ->get();

        $ranked = [];
        foreach ($vouchers as $uv) {
            if (! $uv->voucher) {
                continue;
            }
            $discounted = $this->applyVoucher($items, $uv);
            $saving = $this->totalDiscount($items, $discounted);
            if ($saving <= 0) {
                continue;
            }
            $ranked[] = $this->serializeUserVoucher($uv, $saving);
        }

        usort($ranked, fn ($a, $b) => $b['savings'] <=> $a['savings']);

        return $ranked;
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    public function applyVoucher(array $items, UserVoucher $userVoucher): array
    {
        $voucher = $userVoucher->voucher;
        if (! $voucher) {
            return $items;
        }

        $type = $voucher->type;
        $value = (int) $voucher->discount_value;
        $targetCourseId = $voucher->course_id ? (int) $voucher->course_id : null;

        $out = [];
        $fixedLeft = $type === 'discount_fixed' ? $value : 0;

        $eligibleIndexes = [];
        foreach ($items as $i => $item) {
            $ok = (int) $item['price'] > 0
                && ($targetCourseId === null || (int) $item['id'] === $targetCourseId);
            if ($ok) {
                $eligibleIndexes[] = $i;
            }
        }

        if ($type === 'discount_fixed' && $eligibleIndexes) {
            usort($eligibleIndexes, fn ($a, $b) => (int) $items[$b]['price'] <=> (int) $items[$a]['price']);
        }

        $fixedApplied = false;
        foreach ($items as $i => $item) {
            $price = (int) $item['price'];
            $discount = 0;
            $eligible = in_array($i, $eligibleIndexes, true);

            if ($eligible && $type === 'discount_percent') {
                $discount = (int) floor($price * min(100, max(0, $value)) / 100);
            } elseif ($eligible && $type === 'free_course') {
                $discount = $price;
            } elseif ($eligible && $type === 'discount_fixed' && ! $fixedApplied) {
                $discount = min($price, $fixedLeft);
                $fixedApplied = true;
            }

            $discount = min($price, max(0, $discount));
            $out[] = array_merge($item, [
                'discount' => $discount,
                'payable' => $price - $discount,
            ]);
        }

        return $out;
    }

    public function usableVoucher(User $user, int $userVoucherId): ?UserVoucher
    {
        $uv = UserVoucher::query()
            ->with('voucher')
            ->where('user_id', $user->id)
            ->where('id', $userVoucherId)
            ->first();

        if (! $uv || $uv->status !== 'unused') {
            return null;
        }
        if ($uv->expires_at && $uv->expires_at->isPast()) {
            return null;
        }

        $locked = Order::query()
            ->where('user_id', $user->id)
            ->where('status', 'pending')
            ->where('user_voucher_id', $uv->id)
            ->exists();

        return $locked ? null : $uv;
    }

    /**
     * @param  list<array<string, mixed>>  $before
     * @param  list<array<string, mixed>>  $after
     */
    public function totalDiscount(array $before, array $after): int
    {
        return max(0, (int) collect($before)->sum('price') - (int) collect($after)->sum('payable'));
    }

    /**
     * @return array<string, mixed>
     */
    public function serializeUserVoucher(UserVoucher $uv, int $savings = 0): array
    {
        $voucher = $uv->voucher;

        return [
            'id' => $uv->id,
            'code' => $uv->code,
            'status' => $uv->status,
            'expires_at' => $uv->expires_at?->toIso8601String(),
            'savings' => $savings,
            'recommended' => false,
            'voucher' => $voucher ? [
                'id' => $voucher->id,
                'name' => $voucher->name,
                'description' => $voucher->description,
                'type' => $voucher->type,
                'discount_value' => $voucher->discount_value,
                'course_id' => $voucher->course_id,
            ] : null,
        ];
    }

    public function fulfill(Order $order): void
    {
        $courseIds = collect($order->cart_items ?? [])
            ->pluck('id')
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        if ($courseIds->isEmpty() && $order->course_id) {
            $courseIds = collect([(int) $order->course_id]);
        }

        foreach ($courseIds as $courseId) {
            Enrollment::firstOrCreate([
                'user_id' => $order->user_id,
                'course_id' => $courseId,
            ], [
                'enrolled_at' => now(),
                'order_id' => $order->id,
                'enrollment_source' => 'marketplace',
            ]);
        }

        if ($order->user_voucher_id) {
            UserVoucher::query()
                ->where('id', $order->user_voucher_id)
                ->where('status', 'unused')
                ->update([
                    'status' => 'used',
                    'used_at' => now(),
                    'order_id' => $order->id,
                ]);
        }
    }
}

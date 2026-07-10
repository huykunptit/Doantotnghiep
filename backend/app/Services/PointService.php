<?php

namespace App\Services;

use App\Models\PointTransaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PointService
{
    // Points config
    const POINTS = [
        'login_daily'      => 5,
        'streak_7'         => 50,
        'streak_30'        => 200,
        'lesson_complete'  => 10,
        'course_complete'  => 200,
        'purchase'         => 100,
        'exam_high_score'  => 50,  // score >= 90%
        'survey'           => 30,
        'review_course'    => 20,
    ];

    public function award(
        User $user,
        string $action,
        string $description,
        ?object $reference = null,
        ?int $override = null
    ): PointTransaction {
        $amount = $override ?? (self::POINTS[$action] ?? 0);
        if ($amount <= 0) {
            throw new \InvalidArgumentException("Unknown action or zero points: $action");
        }

        return DB::transaction(function () use ($user, $action, $amount, $description, $reference) {
            $tx = new PointTransaction([
                'user_id'     => $user->id,
                'type'        => 'earn',
                'action'      => $action,
                'amount'      => $amount,
                'description' => $description,
            ]);

            if ($reference) {
                $tx->referenceable_id   = $reference->id;
                $tx->referenceable_type = get_class($reference);
            }

            $tx->save();

            $user->increment('points_balance', $amount);

            return $tx;
        });
    }

    public function redeem(User $user, int $amount, string $description): PointTransaction
    {
        if ($user->points_balance < $amount) {
            throw new \RuntimeException('Không đủ điểm để đổi quà.');
        }

        return DB::transaction(function () use ($user, $amount, $description) {
            $tx = PointTransaction::create([
                'user_id'     => $user->id,
                'type'        => 'redeem',
                'action'      => 'redeem_voucher',
                'amount'      => -$amount,
                'description' => $description,
            ]);

            $user->decrement('points_balance', $amount);

            return $tx;
        });
    }

    public function handleDailyLogin(User $user): ?PointTransaction
    {
        $today = now()->toDateString();

        if ($user->last_login_date === $today) {
            return null; // Already claimed today
        }

        $yesterday = now()->subDay()->toDateString();
        $isStreak = $user->last_login_date === $yesterday;

        return DB::transaction(function () use ($user, $today, $isStreak) {
            $user->last_login_date = $today;

            if ($isStreak) {
                $user->streak_days++;
            } else {
                $user->streak_days = 1;
            }

            $user->streak_last_updated = $today;
            $user->save();

            $tx = $this->award($user, 'login_daily', 'Điểm thưởng đăng nhập hàng ngày');

            // Bonus for milestones
            if ($user->streak_days === 7) {
                $this->award($user, 'streak_7', "Chuỗi đăng nhập 7 ngày liên tiếp!");
            } elseif ($user->streak_days === 30) {
                $this->award($user, 'streak_30', "Chuỗi đăng nhập 30 ngày liên tiếp!");
            }

            return $tx;
        });
    }
}

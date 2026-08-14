<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\UserVoucher;
use App\Models\Voucher;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Marketplace discount vouchers redeemable with points.
 *
 * Idempotent: upserts catalog by name, then gifts a few unused vouchers
 * to demo students so checkout suggestions work immediately.
 */
class VoucherSeeder extends Seeder
{
    public function run(): void
    {
        $catalog = [
            ['name' => 'Giảm 5% học phí', 'percent' => 5, 'points' => 50, 'qty' => 800],
            ['name' => 'Giảm 10% học phí', 'percent' => 10, 'points' => 100, 'qty' => 600],
            ['name' => 'Giảm 15% học phí', 'percent' => 15, 'points' => 180, 'qty' => 500],
            ['name' => 'Giảm 20% học phí', 'percent' => 20, 'points' => 250, 'qty' => 400],
            ['name' => 'Giảm 25% học phí', 'percent' => 25, 'points' => 350, 'qty' => 300],
            ['name' => 'Giảm 30% học phí', 'percent' => 30, 'points' => 450, 'qty' => 250],
            ['name' => 'Giảm 40% học phí', 'percent' => 40, 'points' => 700, 'qty' => 180],
            ['name' => 'Giảm 50% học phí', 'percent' => 50, 'points' => 1000, 'qty' => 120],
            ['name' => 'Giảm 75% học phí', 'percent' => 75, 'points' => 1800, 'qty' => 60],
        ];

        $vouchers = [];
        foreach ($catalog as $row) {
            $vouchers[$row['percent']] = Voucher::query()->updateOrCreate(
                ['name' => $row['name']],
                [
                    'description' => "Đổi điểm thưởng để giảm {$row['percent']}% giá khóa học trên cửa hàng.",
                    'type' => 'discount_percent',
                    'discount_value' => $row['percent'],
                    'points_cost' => $row['points'],
                    'total_quantity' => $row['qty'],
                    'is_active' => true,
                    'expires_at' => now()->addYear(),
                    'course_id' => null,
                ]
            );
        }

        $gifts = [
            'student1@lms.com' => [20, 10],
            'student2@lms.com' => [15],
            'student3@lms.com' => [5, 25],
            'student5@lms.com' => [50],
            'student8@lms.com' => [30],
            'student13@lms.com' => [75],
        ];

        foreach ($gifts as $email => $percents) {
            $user = User::query()->where('email', $email)->first();
            if (! $user) {
                continue;
            }
            foreach ($percents as $percent) {
                $voucher = $vouchers[$percent] ?? null;
                if (! $voucher) {
                    continue;
                }
                $exists = UserVoucher::query()
                    ->where('user_id', $user->id)
                    ->where('voucher_id', $voucher->id)
                    ->where('status', 'unused')
                    ->exists();
                if ($exists) {
                    continue;
                }
                UserVoucher::query()->create([
                    'user_id' => $user->id,
                    'voucher_id' => $voucher->id,
                    'code' => strtoupper(Str::random(12)),
                    'status' => 'unused',
                    'points_spent' => 0,
                    'expires_at' => now()->addDays(90),
                ]);
            }
            if ((int) $user->points_balance < 2000) {
                $user->increment('points_balance', 2000 - (int) $user->points_balance);
            }
        }

        $this->command?->info('VoucherSeeder: '.count($catalog).' catalog vouchers, demo gifts for students.');
    }
}
